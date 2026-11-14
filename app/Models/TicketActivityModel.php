<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class TicketActivityModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const ACTION_CREATED          = 'created';
    public const ACTION_STATUS_CHANGED   = 'status_changed';
    public const ACTION_ASSIGNEE_CHANGED = 'assignee_changed';
    public const ACTION_RESOLVER_CHANGED = 'resolver_changed';

    protected $table            = 'ticket_activities';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['ticket_id', 'actor_id', 'action', 'from_value', 'to_value'];

    public function record(string $ticketId, ?string $actorId, string $action, ?string $from = null, ?string $to = null): void
    {
        $this->insert([
            'ticket_id'  => $ticketId,
            'actor_id'   => $actorId,
            'action'     => $action,
            'from_value' => $from,
            'to_value'   => $to,
        ]);
    }

    /**
     * @return list<array> terbaru dulu, dengan actor_name (null = sistem/tamu)
     */
    public function forTicket(string $ticketId): array
    {
        return $this->select('ticket_activities.*, users.name AS actor_name')
            ->join('users', 'users.id = ticket_activities.actor_id', 'left')
            ->where('ticket_activities.ticket_id', $ticketId)
            ->orderBy('ticket_activities.created_at', 'DESC')
            ->findAll();
    }
}
