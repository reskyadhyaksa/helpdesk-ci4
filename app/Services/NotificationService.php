<?php

namespace App\Services;

use App\Entities\TicketStatus;
use App\Models\NotificationOutboxModel;
use App\Models\SystemParameterModel;
use App\Models\UserModel;
use Throwable;

/**
 * Menyusun notifikasi tiket ke outbox dan mengantrekannya ke RabbitMQ.
 * Pemanggil menulis outbox di dalam transaksi perubahan tiketnya, lalu memanggil publish() setelah commit.
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationOutboxModel $outbox,
        private readonly MessageQueue $queue,
        private readonly UserModel $users,
        private readonly SystemParameterModel $parameters,
    ) {
    }

    /**
     * Pemohon diberi tahu status barunya, kecuali dialah yang mengubahnya.
     *
     * @return list<string> id outbox yang dibuat
     */
    public function forStatusChange(array $ticket, TicketStatus $from, TicketStatus $to, ?string $actorId): array
    {
        if ($actorId !== null && $actorId === $ticket['requester_id']) {
            return [];
        }

        // Kontak pengunjung tanpa login yang belum terverifikasi ketikan sendiri: mengirim ke sana bisa dipakai
        // untuk meneror pihak lain atau membocorkan nama dari data HR. PIC yang menghubungi nomornya.
        if (! (int) $ticket['is_requester_verified']) {
            return [];
        }

        $recipients = [];
        $email      = $ticket['requester_email'] ?: $this->accountEmail($ticket['requester_id']);

        if ($email) {
            $recipients[NotificationOutboxModel::CHANNEL_EMAIL] = $email;
        }

        if (! empty($ticket['requester_phone'])) {
            $recipients[NotificationOutboxModel::CHANNEL_WHATSAPP] = $ticket['requester_phone'];
        }

        $subject = lang('Notification.statusChanged.subject', [$ticket['ticket_number']]);
        $body    = lang('Notification.statusChanged.body', [
            $ticket['requester_name'], $ticket['ticket_number'], $ticket['subject'], $from->label(), $to->label(),
        ]);

        // Pemohon tanpa akun tidak punya halaman tiket untuk ditautkan
        if ($ticket['requester_id'] !== null) {
            $body .= "\n" . lang('Notification.link', [url_to('user.tickets.show', $ticket['id'])]);
        }

        return $this->enqueue($ticket, NotificationOutboxModel::EVENT_STATUS_CHANGED, $recipients, $subject, $body);
    }

    /**
     * PIC yang baru ditugaskan diberi tahu lewat email, kecuali dia menugaskan dirinya sendiri.
     *
     * @param 'assignee'|'resolver' $role
     *
     * @return list<string> id outbox yang dibuat
     */
    public function forAssignment(array $ticket, string $role, string $userId, ?string $actorId): array
    {
        $user = $this->users->find($userId);

        if ($user === null || $userId === $actorId || empty($user->email)) {
            return [];
        }

        $subject = lang('Notification.assigned.subject', [$ticket['ticket_number']]);
        $body    = lang('Notification.assigned.body', [$user->name, $ticket['ticket_number'], $ticket['subject'], lang('Notification.roles.' . $role)])
            . "\n" . lang('Notification.link', [url_to('admin.tickets.show', $ticket['id'])]);

        return $this->enqueue($ticket, NotificationOutboxModel::EVENT_ASSIGNED, [NotificationOutboxModel::CHANNEL_EMAIL => $user->email], $subject, $body);
    }

    /**
     * Mengantrekan ke RabbitMQ setelah transaksi tiket ter-commit. Gagal publikasi tidak menggagalkan apa pun:
     * baris outbox tetap PENDING dan dikirim ulang oleh `php spark notifications:republish`.
     *
     * @param list<string> $outboxIds
     */
    public function publish(array $outboxIds): void
    {
        $queued = [];

        foreach ($outboxIds as $id) {
            try {
                $this->queue->publish(['outbox_id' => $id]);
                $queued[] = $id;
            } catch (Throwable $e) {
                log_message('warning', 'Notifikasi {id} belum terantrekan: {msg}', ['id' => $id, 'msg' => $e->getMessage()]);
            }
        }

        try {
            $this->outbox->markQueued($queued);
        } catch (Throwable $e) {
            log_message('warning', 'Penanda antrean notifikasi gagal ditulis: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, string> $recipients channel => alamat/nomor
     *
     * @return list<string>
     */
    private function enqueue(array $ticket, string $event, array $recipients, string $subject, string $body): array
    {
        // Email dan WhatsApp hanya berjalan bila dinyalakan di parameter sistem (bawaan: mati)
        $enabled = [
            NotificationOutboxModel::CHANNEL_EMAIL    => $this->parameters->flag(SystemParameterModel::NOTIFY_EMAIL_ENABLED),
            NotificationOutboxModel::CHANNEL_WHATSAPP => $this->parameters->flag(SystemParameterModel::NOTIFY_WHATSAPP_ENABLED),
        ];
        $recipients = array_filter($recipients, static fn (string $channel): bool => $enabled[$channel], ARRAY_FILTER_USE_KEY);

        if ($recipients === []) {
            log_message('info', 'Notifikasi {event} tiket {no} dilewati: penerima tanpa kontak.', ['event' => $event, 'no' => $ticket['ticket_number']]);

            return [];
        }

        $payload = json_encode(['subject' => $subject, 'body' => $body], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $ids     = [];

        foreach ($recipients as $channel => $recipient) {
            $ids[] = $this->outbox->insert([
                'ticket_id'  => $ticket['id'],
                'channel'    => $channel,
                'event_type' => $event,
                'recipient'  => $recipient,
                'payload'    => $payload,
                'status'     => NotificationOutboxModel::STATUS_PENDING,
            ], true);
        }

        return $ids;
    }

    private function accountEmail(?string $userId): ?string
    {
        return $userId === null ? null : $this->users->find($userId)?->email;
    }
}
