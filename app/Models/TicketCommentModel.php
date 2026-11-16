<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class TicketCommentModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const AUTHOR_REQUESTER = 'REQUESTER';
    public const AUTHOR_AGENT     = 'AGENT';

    public const VISIBILITY_PUBLIC   = 'PUBLIC';
    public const VISIBILITY_INTERNAL = 'INTERNAL';

    // TEXT = teks biasa (komentar lama dan form tanpa JS); HTML = hasil editor yang sudah disaring CommentSanitizer
    public const FORMAT_TEXT = 'TEXT';
    public const FORMAT_HTML = 'HTML';

    protected $table            = 'ticket_comments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['ticket_id', 'author_id', 'author_type', 'visibility', 'body', 'body_format', 'source'];

    // Batas 20000 karena HTML memuat tag; panjang teks sebenarnya dibatasi TicketCommentService
    protected $validationRules = [
        'ticket_id'   => 'required|exact_length[36]',
        'author_type' => 'required|in_list[REQUESTER,AGENT]',
        'visibility'  => 'required|in_list[PUBLIC,INTERNAL]',
        'body'        => 'required|max_length[20000]',
        'body_format' => 'required|in_list[TEXT,HTML]',
    ];

    /**
     * Percakapan satu tiket, terlama dulu, dengan nama penulis. Non-agen tidak pernah menerima komentar INTERNAL.
     *
     * @return list<array>
     */
    public function forTicket(string $ticketId, bool $includeInternal): array
    {
        $this->select('ticket_comments.*, users.name AS author_name')
            ->join('users', 'users.id = ticket_comments.author_id', 'left')
            ->where('ticket_comments.ticket_id', $ticketId);

        if (! $includeInternal) {
            $this->where('ticket_comments.visibility', self::VISIBILITY_PUBLIC);
        }

        return $this->orderBy('ticket_comments.created_at', 'ASC')->findAll();
    }
}
