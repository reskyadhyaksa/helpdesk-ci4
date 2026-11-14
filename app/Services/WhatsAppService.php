<?php

namespace App\Services;

use CodeIgniter\HTTP\CURLRequest;
use Config\Integration;
use Throwable;

/**
 * Kirim pesan lewat API WhatsApp HC Eazy. URL dan token dari .env (Integration.whatsappApiUrl / whatsappApiToken).
 * Format request diasumsikan JSON {number, message}; ubah di sendMessage bila API berbeda.
 */
class WhatsAppService
{
    public function __construct(
        private readonly CURLRequest $client,
        private readonly Integration $config,
    ) {
    }

    /**
     * @return bool true bila API menjawab 2xx; tidak pernah melempar, kegagalan dicatat di log
     */
    public function sendMessage(string $number, string $message): bool
    {
        if ($this->config->whatsappApiUrl === '') {
            log_message('warning', 'WhatsApp API belum dikonfigurasi (Integration.whatsappApiUrl).');

            return false;
        }

        $headers = ['Accept' => 'application/json'];

        if ($this->config->whatsappApiToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->config->whatsappApiToken;
        }

        try {
            $response = $this->client->post($this->config->whatsappApiUrl, [
                'json'        => ['number' => $number, 'message' => $message],
                'headers'     => $headers,
                'http_errors' => false,
                'timeout'     => 10,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'WhatsApp API connection exception: ' . $e->getMessage());

            return false;
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            log_message('error', 'WhatsApp API error [HTTP {code}]', ['code' => $status]);

            return false;
        }

        return true;
    }

    /**
     * Mengirim kode OTP 6 digit. Pemanggil yang menyimpan dan memeriksa kodenya (belum dipasang ke alur tiket).
     *
     * @return string|null kode yang dikirim, atau null bila pengiriman gagal
     */
    public function sendOtp(string $number): ?string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $sent = $this->sendMessage($number, "Kode verifikasi HC Helpdesk Anda: {$code}. Jangan bagikan kode ini kepada siapa pun.");

        return $sent ? $code : null;
    }
}
