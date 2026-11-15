<?php

namespace App\Services;

use RuntimeException;

/**
 * Token singkat untuk menyambung ke server WebSocket: "<user_id base64url>.<kedaluwarsa>.<HMAC-SHA256>".
 * Diterbitkan aplikasi untuk pengguna yang sedang login (endpoint /notifications/token) dan diverifikasi server
 * WebSocket dengan rahasia yang sama (WS_SECRET), jadi browser tidak perlu cookie sesi ke server itu.
 */
class WsToken
{
    private readonly string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? (string) env('WS_SECRET', '');
    }

    /**
     * @throws RuntimeException bila WS_SECRET belum diisi
     */
    public function issue(string $userId, int $ttlSeconds = 120, ?int $now = null): string
    {
        if ($this->secret === '') {
            throw new RuntimeException('WS_SECRET belum diisi di .env.');
        }

        $payload = $this->encode($userId) . '.' . (($now ?? time()) + $ttlSeconds);

        return $payload . '.' . $this->sign($payload);
    }

    /**
     * @return string|null user id bila token sah dan belum kedaluwarsa
     */
    public function verify(string $token, ?int $now = null): ?string
    {
        $parts = explode('.', $token);

        if ($this->secret === '' || count($parts) !== 3 || ! ctype_digit($parts[1])) {
            return null;
        }

        [$encodedUser, $expires, $signature] = $parts;

        if (! hash_equals($this->sign($encodedUser . '.' . $expires), $signature)) {
            return null;
        }

        if ((int) $expires < ($now ?? time())) {
            return null;
        }

        $userId = base64_decode(strtr($encodedUser, '-_', '+/'), true);

        return $userId === false || $userId === '' ? null : $userId;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
