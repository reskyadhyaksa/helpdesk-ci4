<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class UserNotificationModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    protected $table            = 'user_notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['user_id', 'ticket_id', 'event_type', 'title', 'body', 'url', 'read_at'];

    protected $validationRules = [
        'user_id'    => 'required|exact_length[36]',
        'event_type' => 'required|max_length[30]',
        'title'      => 'required|max_length[150]',
        'body'       => 'required|max_length[500]',
        'url'        => 'required|max_length[255]',
    ];

    /**
     * @return list<array> terbaru dulu
     */
    public function latestFor(string $userId, int $limit): array
    {
        return $this->where('user_id', $userId)->orderBy('created_at', 'DESC')->findAll($limit);
    }

    public function unreadCount(string $userId): int
    {
        return $this->where('user_id', $userId)->where('read_at IS NULL', null, false)->countAllResults();
    }

    /**
     * Hanya milik $userId yang bisa ditandai; milik orang lain tidak berubah.
     */
    public function markRead(string $id, string $userId): bool
    {
        $this->builder()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('read_at IS NULL', null, false)
            ->update(['read_at' => date('Y-m-d H:i:s')]);

        return $this->db->affectedRows() === 1;
    }

    public function markAllRead(string $userId): void
    {
        $this->builder()->where('user_id', $userId)->where('read_at IS NULL', null, false)->update(['read_at' => date('Y-m-d H:i:s')]);
    }
}
