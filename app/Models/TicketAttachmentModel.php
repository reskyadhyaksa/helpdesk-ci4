<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class TicketAttachmentModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    protected $table            = 'ticket_attachments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = [
        'ticket_id', 'comment_id', 'original_name', 'storage_key', 'content_type', 'size_bytes', 'checksum_sha256', 'purged_at',
    ];

    protected $validationRules = [
        'ticket_id'     => 'required|exact_length[36]',
        'original_name' => 'required|max_length[255]',
        'storage_key'   => 'required|max_length[255]',
    ];

    /**
     * Lampiran satu tiket (saat dibuat dan dari semua komentar). Non-agen tidak menerima lampiran komentar INTERNAL.
     * Kolom comment_visibility null = lampiran saat tiket dibuat.
     *
     * @return list<array>
     */
    public function forTicket(string $ticketId, bool $includeInternal): array
    {
        $this->select('ticket_attachments.*, ticket_comments.visibility AS comment_visibility')
            ->join('ticket_comments', 'ticket_comments.id = ticket_attachments.comment_id', 'left')
            ->where('ticket_attachments.ticket_id', $ticketId);

        if (! $includeInternal) {
            $this->groupStart()
                ->where('ticket_attachments.comment_id IS NULL', null, false)
                ->orWhere('ticket_comments.visibility', TicketCommentModel::VISIBILITY_PUBLIC)
                ->groupEnd();
        }

        return $this->orderBy('ticket_attachments.created_at', 'ASC')->findAll();
    }

    /**
     * Lampiran yang filenya sudah waktunya dihapus: belum dihapus dan tiketnya CLOSED sejak $closedBefore atau lebih lama.
     * Tiket CLOSED tanpa closed_at tidak pernah ikut (waktu tutupnya tidak diketahui).
     *
     * @return list<array>
     */
    public function dueForPurge(string $closedBefore, int $limit): array
    {
        return $this->select('ticket_attachments.id, ticket_attachments.storage_key')
            ->join('tickets', 'tickets.id = ticket_attachments.ticket_id')
            ->where('tickets.status', 'CLOSED')
            ->where('tickets.closed_at IS NOT NULL', null, false)
            ->where('tickets.closed_at <=', $closedBefore)
            ->where('ticket_attachments.purged_at IS NULL', null, false)
            ->orderBy('tickets.closed_at', 'ASC')
            ->findAll($limit);
    }

    public function markPurged(string $id, string $now): void
    {
        $this->builder()->where('id', $id)->update(['purged_at' => $now]);
    }

    /**
     * Satu lampiran dengan visibilitas komentarnya (untuk pemeriksaan unduhan).
     */
    public function findWithVisibility(string $id): ?array
    {
        return $this->select('ticket_attachments.*, ticket_comments.visibility AS comment_visibility')
            ->join('ticket_comments', 'ticket_comments.id = ticket_attachments.comment_id', 'left')
            ->where('ticket_attachments.id', $id)
            ->first();
    }
}
