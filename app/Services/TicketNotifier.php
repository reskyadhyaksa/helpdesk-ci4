<?php

namespace App\Services;

use App\Entities\TicketStatus;
use Throwable;

/**
 * Pintu tunggal notifikasi tiket. Alurnya dua tahap supaya konsisten dengan transaksi:
 *  1. forXxx() dipanggil DI DALAM transaksi perubahan tiket: menulis outbox (email/WhatsApp, bila dinyalakan)
 *     dan baris lonceng, lalu mengembalikan "pending".
 *  2. dispatch() dipanggil SETELAH commit: mengantrekan outbox ke RabbitMQ dan mem-push lonceng ke browser.
 *
 * @phpstan-type Pending array{outbox: list<string>, inApp: list<array>}
 */
class TicketNotifier
{
    public function __construct(
        private readonly NotificationService $outbox,
        private readonly InAppNotificationService $inApp,
        private readonly NotificationPusher $pusher,
    ) {
    }

    /**
     * @return Pending
     */
    public function forStatusChange(array $ticket, TicketStatus $from, TicketStatus $to, ?string $actorId): array
    {
        return [
            'outbox' => $this->outbox->forStatusChange($ticket, $from, $to, $actorId),
            'inApp'  => $this->inApp->forStatusChange($ticket, $from, $to, $actorId),
        ];
    }

    /**
     * @param 'assignee'|'resolver' $role
     *
     * @return Pending
     */
    public function forAssignment(array $ticket, string $role, string $userId, ?string $actorId): array
    {
        return [
            'outbox' => $this->outbox->forAssignment($ticket, $role, $userId, $actorId),
            'inApp'  => $this->inApp->forAssignment($ticket, $role, $userId, $actorId),
        ];
    }

    /**
     * Komentar hanya menghasilkan notifikasi lonceng. $mentionedIds = user yang disebut (@) di komentar.
     *
     * @param list<string> $mentionedIds
     *
     * @return Pending
     */
    public function forComment(array $ticket, string $authorId, string $authorType, string $visibility, array $mentionedIds = []): array
    {
        return ['outbox' => [], 'inApp' => $this->inApp->forComment($ticket, $authorId, $authorType, $visibility, $mentionedIds)];
    }

    /**
     * @param Pending $pending
     */
    public function dispatch(array $pending): void
    {
        try {
            $this->outbox->publish($pending['outbox']);
        } catch (Throwable $e) {
            log_message('warning', 'Antrean notifikasi gagal: ' . $e->getMessage());
        }

        try {
            $this->pusher->push($pending['inApp']);
        } catch (Throwable $e) {
            log_message('warning', 'Push notifikasi gagal: ' . $e->getMessage());
        }
    }
}
