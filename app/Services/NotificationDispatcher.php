<?php

namespace App\Services;

use App\Models\NotificationOutboxModel;
use CodeIgniter\Email\Email;
use RuntimeException;
use Throwable;

/**
 * Mengirim notifikasi dari outbox (dipanggil worker untuk tiap pesan RabbitMQ) dengan retry berjeda.
 * Pesan antrean hanya membawa id; baris yang bukan PENDING atau belum waktunya dilewati, sehingga
 * pesan ganda tidak menghasilkan kiriman ganda.
 */
class NotificationDispatcher
{
    public const MAX_ATTEMPTS = 5;

    // Baris yang sudah diantrekan dianggap masih menunggu di antrean selama ini; lewat itu baru diantrekan ulang
    private const REQUEUE_AFTER_SECONDS = 600;

    // Jeda sebelum percobaan berikutnya (menit); percobaan ke-n memakai elemen ke-n, yang terakhir dipakai seterusnya
    private const BACKOFF_MINUTES = [1, 5, 15, 60];

    public function __construct(
        private readonly NotificationOutboxModel $outbox,
        private readonly MessageQueue $queue,
        private readonly Email $email,
        private readonly WhatsAppService $whatsapp,
    ) {
    }

    public function deliver(string $outboxId): void
    {
        $row = $this->outbox->find($outboxId);

        if ($row === null || $row['status'] !== NotificationOutboxModel::STATUS_PENDING) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        if ($row['next_attempt_at'] !== null && $row['next_attempt_at'] > $now) {
            return;
        }

        // Klaim atomik: pesan ganda atau pekerja ganda tidak akan mengirim baris yang sama dua kali
        if (! $this->outbox->claim($outboxId, (int) $row['attempt_count'])) {
            return;
        }

        $attempts = (int) $row['attempt_count'] + 1;

        try {
            $this->send($row);
        } catch (Throwable $e) {
            $this->recordFailure($outboxId, $attempts, $e->getMessage());

            return;
        }

        $this->outbox->update($outboxId, [
            'status'          => NotificationOutboxModel::STATUS_SENT,
            'attempt_count'   => $attempts,
            'next_attempt_at' => null,
            'last_error'      => null,
            'sent_at'         => $now,
        ]);
    }

    /**
     * Mengantrekan ulang baris PENDING yang sudah waktunya: yang gagal terpublikasi (RabbitMQ mati)
     * dan yang jeda retry-nya habis. Dijalankan berkala (cron) lewat `php spark notifications:republish`.
     *
     * @return int jumlah baris yang diantrekan
     */
    public function republishDue(int $limit = 100): int
    {
        $rows = $this->outbox->findDue(date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() - self::REQUEUE_AFTER_SECONDS), $limit);
        $ids  = [];

        foreach ($rows as $row) {
            $this->queue->publish(['outbox_id' => $row['id']]);
            $ids[] = $row['id'];
        }

        // Ditandai agar tidak diantrekan lagi tiap menit selama pesannya masih menunggu di antrean
        $this->outbox->markQueued($ids);

        return count($ids);
    }

    /**
     * @throws RuntimeException bila pengiriman gagal
     */
    private function send(array $row): void
    {
        $payload = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);

        $sent = $row['channel'] === NotificationOutboxModel::CHANNEL_WHATSAPP
            ? $this->whatsapp->sendMessage($this->internationalNumber($row['recipient']), $payload['body'])
            : $this->email->clear()->setTo($row['recipient'])->setSubject($payload['subject'])->setMessage($payload['body'])->send(false);

        if (! $sent) {
            $detail = $row['channel'] === NotificationOutboxModel::CHANNEL_WHATSAPP ? 'API WhatsApp menolak atau tidak terjangkau' : $this->email->printDebugger(['headers']);

            throw new RuntimeException($detail);
        }
    }

    private function recordFailure(string $outboxId, int $attempts, string $error): void
    {
        $giveUp = $attempts >= self::MAX_ATTEMPTS;
        $delay  = self::BACKOFF_MINUTES[min($attempts - 1, count(self::BACKOFF_MINUTES) - 1)];

        $this->outbox->update($outboxId, [
            'status'          => $giveUp ? NotificationOutboxModel::STATUS_FAILED : NotificationOutboxModel::STATUS_PENDING,
            'attempt_count'   => $attempts,
            'next_attempt_at' => $giveUp ? null : date('Y-m-d H:i:s', time() + $delay * 60),
            'last_error'      => mb_substr(trim(strip_tags($error)), 0, 255),
        ]);

        log_message('warning', 'Notifikasi {id} gagal (percobaan {n}): {err}', ['id' => $outboxId, 'n' => $attempts, 'err' => $error]);
    }

    /**
     * 0812-3456-7890 → 6281234567890
     */
    private function internationalNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        return str_starts_with($digits, '0') ? '62' . substr($digits, 1) : $digits;
    }
}
