<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class NotificationOutboxModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const CHANNEL_EMAIL    = 'EMAIL';
    public const CHANNEL_WHATSAPP = 'WHATSAPP';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SENT    = 'SENT';
    public const STATUS_FAILED  = 'FAILED';

    public const EVENT_STATUS_CHANGED = 'status_changed';
    public const EVENT_ASSIGNED       = 'ticket_assigned';

    protected $table            = 'notification_outbox';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = [
        'ticket_id', 'channel', 'event_type', 'recipient', 'payload', 'status',
        'attempt_count', 'next_attempt_at', 'queued_at', 'last_error', 'sent_at',
    ];

    protected $validationRules = [
        'ticket_id' => 'required|exact_length[36]',
        'channel'   => 'required|in_list[EMAIL,WHATSAPP]',
        'recipient' => 'required|max_length[255]',
        'payload'   => 'required',
    ];

    /**
     * Baris PENDING yang sudah waktunya dikirim (belum dijadwalkan ulang, atau jedanya habis) dan yang tidak sedang
     * menunggu di antrean (belum pernah diantrekan, atau terakhir diantrekan sebelum $queuedBefore), terlama dulu.
     *
     * @return list<array>
     */
    public function findDue(string $now, string $queuedBefore, int $limit): array
    {
        return $this->where('status', self::STATUS_PENDING)
            ->groupStart()
                ->where('next_attempt_at IS NULL', null, false)
                ->orWhere('next_attempt_at <=', $now)
            ->groupEnd()
            ->groupStart()
                ->where('queued_at IS NULL', null, false)
                ->orWhere('queued_at <=', $queuedBefore)
            ->groupEnd()
            ->orderBy('created_at', 'ASC')
            ->findAll($limit);
    }

    /**
     * Klaim atomik satu percobaan kirim: hanya satu pekerja yang berhasil menaikkan attempt_count dari nilai yang
     * dilihatnya, sehingga dua pekerja tidak pernah mengirim baris yang sama.
     */
    public function claim(string $id, int $seenAttempts): bool
    {
        $this->builder()
            ->where('id', $id)
            ->where('status', self::STATUS_PENDING)
            ->where('attempt_count', $seenAttempts)
            ->set('attempt_count', 'attempt_count + 1', false)
            ->update();

        return $this->db->affectedRows() === 1;
    }

    /**
     * @param list<string> $ids
     */
    public function markQueued(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->builder()->whereIn('id', $ids)->update(['queued_at' => date('Y-m-d H:i:s')]);
    }
}
