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

    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketCommentModel $comments,
        private readonly AttachmentService $attachments,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * Pemohon hanya bisa membalas ke publik. Balasan pemohon pada tiket RESOLVED membuka kembali tiketnya.
     *
     * @param array{id: string, employee_no?: string}   $viewer
     * @param array{body?: string, visibility?: string} $input
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

        $body = trim((string) ($input['body'] ?? ''));

        if ($body === '') {
            throw new BusinessException(lang('Ticket.commentEmpty'));
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new BusinessException(lang('Ticket.commentTooLong', [self::MAX_BODY_LENGTH]));
        }

        $files      = $this->attachments->validateAll($files);
        $visibility = $isAgent && ($input['visibility'] ?? null) === TicketCommentModel::VISIBILITY_INTERNAL
            ? TicketCommentModel::VISIBILITY_INTERNAL
            : TicketCommentModel::VISIBILITY_PUBLIC;

        // Penulis berlabel pemohon hanya bila memang pihak pemohon; PIC yang membalas lewat portal tetap berlabel Helpdesk
        $asAgent = $isAgent || ($isPic && ! $requesterSide);
        $comment = $this->save($ticketId, $viewer['id'], $asAgent, $visibility, $body, $files);

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
     * @param list<mixed> $files
     */
    private function save(string $ticketId, string $authorId, bool $isAgent, string $visibility, string $body, array $files): array
    {
        $stored = [];
        $id     = null;

        $this->db->transBegin();

        try {
            $id = $this->comments->insert([
                'ticket_id'   => $ticketId,
                'author_id'   => $authorId,
                'author_type' => $isAgent ? TicketCommentModel::AUTHOR_AGENT : TicketCommentModel::AUTHOR_REQUESTER,
                'visibility'  => $visibility,
                'body'        => $body,
            ], true);

            if ($id === false) {
                throw new BusinessException(implode(' ', $this->comments->errors()));
            }

            foreach ($files as $file) {
                $stored[] = $this->attachments->store($ticketId, $id, $file);
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->attachments->discard($stored);

            throw $e;
        }

        return $this->comments->find($id);
    }
}
