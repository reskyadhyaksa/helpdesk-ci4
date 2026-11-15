<?php

namespace App\Services;

use App\Entities\TicketStatus;
use App\Models\PermissionModel;
use App\Models\UserModel;
use App\Models\UserNotificationModel;

/**
 * Notifikasi dalam aplikasi (lonceng): menentukan penerima dan menulis baris user_notifications.
 * Dipanggil di dalam transaksi perubahan tiket, jadi lonceng selalu konsisten dengan data tiket.
 * Setiap method mengembalikan baris yang dibuat (untuk di-push real-time setelah commit).
 */
class InAppNotificationService
{
    // Kejadian yang hanya ditujukan ke agen/PIC
    private const AGENT_EVENTS = ['ticket_assigned', 'sla_warning', 'sla_breached'];

    public const SLA_RESPONSE_WARNING    = 'response_warning';
    public const SLA_RESPONSE_BREACHED   = 'response_breached';
    public const SLA_RESOLUTION_WARNING  = 'resolution_warning';
    public const SLA_RESOLUTION_BREACHED = 'resolution_breached';

    public function __construct(
        private readonly UserNotificationModel $notifications,
        private readonly UserModel $users,
    ) {
    }

    /**
     * @return array{unread: int, items: list<array>} jumlah belum dibaca dan daftar terbaru (untuk lonceng)
     */
    public function listFor(string $userId, int $limit = 20): array
    {
        return ['unread' => $this->notifications->unreadCount($userId), 'items' => $this->notifications->latestFor($userId, $limit)];
    }

    public function unreadCount(string $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    /**
     * Hanya notifikasi milik $userId yang bisa ditandai.
     */
    public function markRead(string $notificationId, string $userId): bool
    {
        return $this->notifications->markRead($notificationId, $userId);
    }

    public function markAllRead(string $userId): void
    {
        $this->notifications->markAllRead($userId);
    }

    /**
     * @param 'assignee'|'resolver' $role
     *
     * @return list<array>
     */
    public function forAssignment(array $ticket, string $role, string $userId, ?string $actorId): array
    {
        return $this->create(
            $ticket,
            'ticket_assigned',
            lang('InApp.assigned.title'),
            lang('InApp.assigned.body', [$ticket['ticket_number'], $ticket['subject'], lang('InApp.roles.' . $role)]),
            [$userId],
            $actorId,
        );
    }

    /**
     * Pemohon (dan pengaju) serta kedua PIC, dikurangi pelaku.
     *
     * @return list<array>
     */
    public function forStatusChange(array $ticket, TicketStatus $from, TicketStatus $to, ?string $actorId): array
    {
        $title = $to === TicketStatus::Escalated ? lang('InApp.status.escalated') : lang('InApp.status.title');
        $body  = lang('InApp.status.body', [$ticket['ticket_number'], $from->label(), $to->label()]);

        return $this->create($ticket, 'status_changed', $title, $body, [...$this->requesterSide($ticket), ...$this->pics($ticket)], $actorId);
    }

    /**
     * Balasan publik agen → pemohon dan PIC lain; catatan internal → PIC saja (tidak pernah ke pihak pemohon);
     * balasan pemohon → PIC.
     *
     * @return list<array>
     */
    public function forComment(array $ticket, string $authorId, string $authorType, string $visibility): array
    {
        $internal   = $visibility === 'INTERNAL';
        $recipients = $authorType === 'AGENT' && ! $internal
            ? [...$this->requesterSide($ticket), ...$this->pics($ticket)]
            : $this->pics($ticket);

        return $this->create(
            $ticket,
            'comment_added',
            $internal ? lang('InApp.comment.internal') : lang('InApp.comment.title'),
            lang('InApp.comment.body', [$ticket['ticket_number'], $authorType === 'AGENT' ? lang('InApp.roles.assignee') : lang('InApp.someone')]),
            $recipients,
            $authorId,
        );
    }

    /**
     * @param self::SLA_* $kind
     *
     * @return list<array>
     */
    public function forSla(array $ticket, string $kind): array
    {
        $recipients = $this->pics($ticket);

        if ($recipients === []) {
            $recipients = array_column($this->users->withPermission(PermissionModel::ADMIN_TICKETS_ASSIGN), 'id');
        }

        return $this->create(
            $ticket,
            str_ends_with($kind, 'breached') ? 'sla_breached' : 'sla_warning',
            lang('InApp.sla.' . $kind),
            lang('InApp.sla.body', [$ticket['ticket_number'], $ticket['subject']]),
            $recipients,
            null,
        );
    }

    /**
     * @return list<string> akun pemohon dan akun pengaju (bila pengaju punya akun)
     */
    private function requesterSide(array $ticket): array
    {
        $ids = [$ticket['requester_id'] ?? null];

        if (! empty($ticket['created_by'])) {
            $ids[] = $this->users->findByEmployeeNo($ticket['created_by'])?->id;
        }

        return array_values(array_filter($ids));
    }

    /**
     * @return list<string>
     */
    private function pics(array $ticket): array
    {
        return array_values(array_filter([$ticket['assignee_id'] ?? null, $ticket['resolver_id'] ?? null]));
    }

    /**
     * @param list<string> $recipients
     *
     * @return list<array> baris yang dibuat: id, user_id, ticket_id, event_type, title, body, url, created_at
     */
    private function create(array $ticket, string $event, string $title, string $body, array $recipients, ?string $excludeUserId): array
    {
        $created = [];
        $pics    = $this->pics($ticket);
        $now     = date('Y-m-d H:i:s');

        foreach (array_unique($recipients) as $userId) {
            if ($userId === $excludeUserId) {
                continue;
            }

            $row = [
                'user_id'    => $userId,
                'ticket_id'  => $ticket['id'],
                'event_type' => $event,
                'title'      => $title,
                'body'       => mb_substr($body, 0, 500),
                // PIC dan agen membuka halaman admin; pemohon membuka halaman portal. Penugasan dan SLA selalu untuk
                // agen (baris tiket bisa masih versi sebelum PIC baru tersimpan, jadi tidak cukup mengandalkan $pics).
                'url'        => $this->path(in_array($userId, $pics, true) || in_array($event, self::AGENT_EVENTS, true) ? 'admin.tickets.show' : 'user.tickets.show', $ticket['id']),
            ];

            $created[] = ['id' => $this->notifications->insert($row, true), 'created_at' => $now] + $row;
        }

        return $created;
    }

    private function path(string $route, string $ticketId): string
    {
        return (string) parse_url(url_to($route, $ticketId), PHP_URL_PATH);
    }
}
