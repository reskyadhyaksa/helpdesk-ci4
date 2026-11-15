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
        'ticket_id', 'comment_id', 'original_name', 'storage_key', 'content_type', 'size_bytes', 'checksum_sha256',
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
