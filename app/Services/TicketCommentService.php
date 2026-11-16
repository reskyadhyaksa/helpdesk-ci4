<?php

namespace App\Services;

use App\Entities\TicketStatus;
use App\Exceptions\BusinessException;
use App\Models\TicketCommentModel;
use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Percakapan pada tiket: balasan publik, catatan internal (hanya agen), dan lampiran per komentar.
 */
class TicketCommentService
{
    private const MAX_BODY_LENGTH = 5000;

    private readonly CommentSanitizer $sanitizer;

    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketCommentModel $comments,
        private readonly AttachmentService $attachments,
        private readonly TicketNotifier $notifier,
        private readonly BaseConnection $db,
        ?CommentSanitizer $sanitizer = null,
    ) {
        $this->sanitizer = $sanitizer ?? new CommentSanitizer();
    }

    /**
     * Pemohon hanya bisa membalas ke publik. Balasan pemohon pada tiket RESOLVED membuka kembali tiketnya.
     *
     * @param array{id: string, employee_no?: string}   $viewer
     * @param array{body?: string, visibility?: string, format?: string} $input format "html" = body dari editor (disaring di sini);
     *                                                                         mention dibaca dari penanda di HTML-nya, hanya untuk agen
     * @param array<int, mixed>                         $files
     *
     * @return array baris komentar yang tersimpan
     *
     * @throws BusinessException
     */
    public function add(string $ticketId, array $viewer, bool $canViewAll, bool $isAgent, array $input, array $files = []): array
    {
        $ticket = $this->tickets->find($ticketId, $viewer, $canViewAll) ?? throw new BusinessException(lang('Ticket.notFound'));

        // Melihat tiket (mis. lewat akses kategori) belum berarti boleh membalas: hanya pihak pemohon, PIC, atau agen
        $requesterSide = $this->isRequesterSide($ticket, $viewer);
        $isPic         = in_array($viewer['id'], [$ticket['assignee_id'], $ticket['resolver_id']], true);

        if (! $isAgent && ! $requesterSide && ! $isPic) {
            throw new BusinessException(lang('Ticket.commentForbidden'));
        }

        if (TicketStatus::from($ticket['status'])->isFinal()) {
            throw new BusinessException(lang('Ticket.commentClosed'));
        }

        // Editor (JS) mengirim HTML yang disaring di sini; form tanpa JS mengirim teks biasa
        $isHtml = ($input['format'] ?? null) === 'html';
        $body   = trim((string) ($input['body'] ?? ''));
        $body   = $isHtml ? $this->sanitizer->clean($body) : $body;
        $text   = $isHtml ? $this->sanitizer->plainText($body) : $body;

        if ($text === '') {
            throw new BusinessException(lang('Ticket.commentEmpty'));
        }

        if (mb_strlen($text) > self::MAX_BODY_LENGTH) {
            throw new BusinessException(lang('Ticket.commentTooLong', [self::MAX_BODY_LENGTH]));
        }

        $files      = $this->attachments->validateAll($files);
        $visibility = $isAgent && ($input['visibility'] ?? null) === TicketCommentModel::VISIBILITY_INTERNAL
            ? TicketCommentModel::VISIBILITY_INTERNAL
            : TicketCommentModel::VISIBILITY_PUBLIC;

        // Penulis berlabel pemohon hanya bila memang pihak pemohon; PIC yang membalas lewat portal tetap berlabel Helpdesk
        $asAgent  = $isAgent || ($isPic && ! $requesterSide);
        $mentions = $isAgent && $isHtml ? $this->mentionableIds($ticket, $this->sanitizer->mentionIds($body)) : [];
        $comment  = $this->save($ticket, $viewer['id'], $asAgent, $visibility, $body, $isHtml, $files, $mentions);

        if ($requesterSide && ! $isAgent && $ticket['status'] === TicketStatus::Resolved->value) {
            $this->tickets->changeStatus($ticketId, TicketStatus::Reopened, $viewer['id']);
        }

        return $comment;
    }

    /**
     * @return array{comments: list<array>, ticketAttachments: list<array>} komentar (dengan lampirannya) dan lampiran saat tiket dibuat
     */
    public function thread(string $ticketId, bool $isAgent): array
    {
        $byComment         = [];
        $ticketAttachments = [];

        foreach ($this->attachments->forTicket($ticketId, $isAgent) as $attachment) {
            if ($attachment['comment_id'] === null) {
                $ticketAttachments[] = $attachment;

                continue;
            }

            $byComment[$attachment['comment_id']][] = $attachment;
        }

        $comments = array_map(
            static fn (array $comment): array => $comment + ['attachments' => $byComment[$comment['id']] ?? []],
            $this->comments->forTicket($ticketId, $isAgent),
        );

        return ['comments' => $comments, 'ticketAttachments' => $ticketAttachments];
    }

    /**
     * @param array{id: string, employee_no?: string} $viewer
     *
     * @return array{path: string, name: string, type: string}
     *
     * @throws BusinessException tidak ditemukan, tidak terlihat, lampiran catatan internal bagi non-agen, atau file hilang
     */
    public function attachmentForDownload(string $attachmentId, array $viewer, bool $canViewAll, bool $isAgent): array
    {
        $notFound   = new BusinessException(lang('Attachment.notFound'));
        $attachment = $this->attachments->findWithVisibility($attachmentId) ?? throw $notFound;

        if ($this->tickets->find($attachment['ticket_id'], $viewer, $canViewAll) === null) {
            throw $notFound;
        }

        if (! $isAgent && $attachment['comment_visibility'] === TicketCommentModel::VISIBILITY_INTERNAL) {
            throw $notFound;
        }

        // File sudah dihapus oleh kebijakan retensi
        if (! empty($attachment['purged_at'])) {
            throw $notFound;
        }

        $path = $this->attachments->absolutePath($attachment);

        if (! is_file($path)) {
            throw $notFound;
        }

        return ['path' => $path, 'name' => $attachment['original_name'], 'type' => $attachment['content_type']];
    }

    /**
     * Pemohon, atau pengaju yang mengajukan atas namanya. Kecocokan NIK hanya dihitung bila pemohon terverifikasi,
     * supaya pengunjung tanpa login yang mengaku NIK orang lain tidak menjadi "pemohon".
     *
     * @param array{id: string, employee_no?: string} $viewer
     */
    private function isRequesterSide(array $ticket, array $viewer): bool
    {
        $nip = (string) ($viewer['employee_no'] ?? '');

        return $ticket['requester_id'] === $viewer['id']
            || ($nip !== '' && $ticket['created_by'] === $nip)
            || ($nip !== '' && (int) $ticket['is_requester_verified'] === 1 && $ticket['requester_nip'] === $nip);
    }

    /**
     * Komentar dan lampirannya ditulis di dua tabel (plus file), jadi keduanya berhasil atau batal bersama.
     *
     * @param list<mixed>  $files
     * @param list<string> $mentions id user yang disebut dan boleh mengakses tiket
     */
    private function save(array $ticket, string $authorId, bool $isAgent, string $visibility, string $body, bool $isHtml, array $files, array $mentions): array
    {
        $stored  = [];
        $id      = null;
        $pending = ['outbox' => [], 'inApp' => []];
        $type    = $isAgent ? TicketCommentModel::AUTHOR_AGENT : TicketCommentModel::AUTHOR_REQUESTER;

        $this->db->transBegin();

        try {
            $id = $this->comments->insert([
                'ticket_id'   => $ticket['id'],
                'author_id'   => $authorId,
                'author_type' => $type,
                'visibility'  => $visibility,
                'body'        => $body,
                'body_format' => $isHtml ? TicketCommentModel::FORMAT_HTML : TicketCommentModel::FORMAT_TEXT,
            ], true);

            if ($id === false) {
                throw new BusinessException(implode(' ', $this->comments->errors()));
            }

            foreach ($files as $file) {
                $stored[] = $this->attachments->store($ticket['id'], $id, $file);
            }

            $pending = $this->queueNotifications($ticket, $authorId, $type, $visibility, $mentions);

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->attachments->discard($stored);

            throw $e;
        }

        $this->notifier->dispatch($pending);

        return $this->comments->find($id);
    }

    /**
     * Hanya orang yang boleh mengakses tiket (kandidat PIC) yang bisa disebut; id lain dibuang diam-diam.
     *
     * @param array<int, mixed> $requested
     *
     * @return list<string>
     */
    private function mentionableIds(array $ticket, array $requested): array
    {
        if ($requested === []) {
            return [];
        }

        $allowed = array_column($this->tickets->picCandidates($ticket), 'id');

        return array_values(array_intersect(array_unique(array_map('strval', $requested)), $allowed));
    }

    /**
     * Notifikasi lonceng ditulis di transaksi komentar. Gagal menyusunnya hanya dicatat: komentar tidak boleh batal karenanya.
     *
     * @return array{outbox: list<string>, inApp: list<array>}
     */
    private function queueNotifications(array $ticket, string $authorId, string $authorType, string $visibility, array $mentions): array
    {
        try {
            return $this->notifier->forComment($ticket, $authorId, $authorType, $visibility, $mentions);
        } catch (Throwable $e) {
            log_message('error', 'Notifikasi komentar gagal disusun: ' . $e->getMessage());

            return ['outbox' => [], 'inApp' => []];
        }
    }
}
