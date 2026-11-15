<?php

namespace App\Services;

use Throwable;

/**
 * Peta koneksi WebSocket per pengguna dan penerusan notifikasi. Murni logika (tanpa socket), supaya teruji;
 * server WebSocket (ws-server.php) hanya menyalurkan kejadian koneksi ke sini.
 */
class WsHub
{
    /** @var array<string, array<string, callable(string): void>> user id => [connection id => pengirim] */
    private array $connections = [];

    /** @var array<string, string> connection id => user id */
    private array $owner = [];

    public function __construct(private readonly int $maxConnectionsPerUser = 10)
    {
    }

    /**
     * @param callable(string): void $send
     *
     * @return list<string> id koneksi lama yang harus ditutup pemanggil karena melebihi batas per pengguna
     */
    public function register(string $connectionId, string $userId, callable $send): array
    {
        $this->connections[$userId][$connectionId] = $send;
        $this->owner[$connectionId]                = $userId;

        $evicted = [];

        while (count($this->connections[$userId]) > $this->maxConnectionsPerUser) {
            $oldest    = array_key_first($this->connections[$userId]);
            $evicted[] = $oldest;
            $this->unregister($oldest);
        }

        return $evicted;
    }

    public function unregister(string $connectionId): void
    {
        $userId = $this->owner[$connectionId] ?? null;

        if ($userId === null) {
            return;
        }

        unset($this->connections[$userId][$connectionId], $this->owner[$connectionId]);

        if ($this->connections[$userId] === []) {
            unset($this->connections[$userId]);
        }
    }

    /**
     * @param array<string, mixed> $notification
     *
     * @return int jumlah koneksi yang menerima
     */
    public function push(string $userId, array $notification): int
    {
        return $this->broadcast($this->connections[$userId] ?? [], json_encode(['notification' => $notification], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Menjaga koneksi tetap hidup melewati proxy yang menutup koneksi diam.
     */
    public function ping(): void
    {
        foreach ($this->connections as $senders) {
            $this->broadcast($senders, '{"type":"ping"}');
        }
    }

    /**
     * @return array{user_id: string, notification: array}|null perintah push dari aplikasi, atau null bila bentuknya salah
     */
    public static function parseInternalLine(string $line): ?array
    {
        $data = json_decode($line, true);

        if (! is_array($data) || ! is_string($data['user_id'] ?? null) || $data['user_id'] === '' || ! is_array($data['notification'] ?? null)) {
            return null;
        }

        return ['user_id' => $data['user_id'], 'notification' => $data['notification']];
    }

    /**
     * @param array<string, callable(string): void> $senders
     */
    private function broadcast(array $senders, string $data): int
    {
        $sent = 0;

        foreach ($senders as $send) {
            try {
                $send($data);
                $sent++;
            } catch (Throwable) {
                // Satu koneksi yang putus tidak boleh menghentikan pengiriman ke yang lain
            }
        }

        return $sent;
    }
}
