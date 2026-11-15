<?php

namespace App\Services;

use Throwable;

/**
 * Meneruskan notifikasi baru ke server WebSocket lewat port internalnya (127.0.0.1), satu baris JSON per notifikasi.
 * Best-effort: server mati atau lambat tidak pernah mengganggu pemanggil, karena lonceng tetap benar dari database
 * dan browser mengambil ulang daftar saat tersambung kembali.
 */
class NotificationPusher
{
    // Hanya field ini yang dikirim ke browser
    private const BROWSER_FIELDS = ['id', 'ticket_id', 'event_type', 'title', 'body', 'url', 'created_at'];

    private readonly string $host;
    private readonly int $port;

    public function __construct(?string $host = null, ?int $port = null, private readonly float $timeoutSeconds = 0.2)
    {
        $this->host = $host ?? '127.0.0.1';
        $this->port = $port ?? (int) env('WS_INTERNAL_PORT', 2207);
    }

    /**
     * @param list<array> $notifications baris dari InAppNotificationService (memuat user_id sebagai tujuan)
     */
    public function push(array $notifications): void
    {
        foreach ($notifications as $notification) {
            try {
                $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $error, $this->timeoutSeconds);

                if ($socket === false) {
                    log_message('info', 'Server WebSocket tidak terjangkau; notifikasi tetap ada di lonceng: ' . $error);

                    return;
                }

                $payload = [
                    'user_id'      => $notification['user_id'],
                    'notification' => array_intersect_key($notification, array_flip(self::BROWSER_FIELDS)),
                ];

                fwrite($socket, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
                fclose($socket);
            } catch (Throwable $e) {
                log_message('warning', 'Push notifikasi gagal: ' . $e->getMessage());
            }
        }
    }
}
