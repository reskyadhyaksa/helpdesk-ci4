<?php

namespace App\Services\Auth;

use CodeIgniter\HTTP\CURLRequest;
use Config\Integration;
use Config\Services;
use Throwable;

/**
 * Klien HTTP ke Keycloak dan API Spring Boot. Tidak menyentuh session maupun database.
 */
class KeycloakAuthService
{
    private readonly Integration $config;
    private readonly CURLRequest $client;

    public function __construct()
    {
        $this->config = config(Integration::class);
        $this->client = Services::curlrequest([
            'timeout'     => 10,
            'http_errors' => false,
        ]);
    }

    /**
     * Direct Access Grant (password). Mengembalikan respons token, atau null jika gagal.
     */
    public function authenticate(string $username, string $password): ?array
    {
        $payload = [
            'grant_type' => 'password',
            'client_id'  => $this->config->keycloakClientId,
            'username'   => $username,
            'password'   => $password,
        ];

        if ($this->config->keycloakClientSecret !== '') {
            $payload['client_secret'] = $this->config->keycloakClientSecret;
        }

        try {
            $response = $this->client->post($this->config->keycloakTokenUrl, ['form_params' => $payload]);
        } catch (Throwable $e) {
            log_message('error', 'Keycloak connection exception: ' . $e->getMessage());

            return null;
        }

        if ($response->getStatusCode() !== 200) {
            log_message('error', 'Keycloak auth failed [HTTP {code}]: {body}', [
                'code' => $response->getStatusCode(),
                'body' => $response->getBody(),
            ]);

            return null;
        }

        return json_decode($response->getBody(), true);
    }

    /**
     * Membaca payload JWT tanpa verifikasi tanda tangan.
     * Aman hanya karena token diterima langsung dari Keycloak lewat koneksi server-ke-server.
     */
    public function parseTokenPayload(string $jwtToken): ?array
    {
        $parts = explode('.', $jwtToken);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Data karyawan dari API Spring Boot (Basic Auth).
     */
    public function getHelpdeskUserData(string $employeeNo): ?array
    {
        return $this->getHelpdesk('/api/helpdesk/user', ['employeeNo' => $employeeNo]);
    }

    /**
     * Daftar hari libur satu tahun dari API Spring Boot (Basic Auth).
     *
     * @return list<array{name: string, description: ?string, date: int|string, massLeaveFlag: ?bool}>|null
     */
    public function getHelpdeskHolidays(int $year): ?array
    {
        return $this->getHelpdesk('/api/helpdesk/holiday', ['year' => $year]);
    }

    private function getHelpdesk(string $path, array $query): ?array
    {
        $url = rtrim($this->config->helpdeskApiBaseUrl, '/') . $path;

        try {
            $response = $this->client->get($url, [
                'auth'    => [$this->config->helpdeskApiUser, $this->config->helpdeskApiPass, 'basic'],
                'headers' => ['Accept' => 'application/json'],
                'query'   => $query,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Spring Boot connection exception: ' . $e->getMessage());

            return null;
        }

        if ($response->getStatusCode() !== 200) {
            log_message('error', 'Spring Boot error [HTTP {code}] URL: {url}', [
                'code' => $response->getStatusCode(),
                'url'  => $url,
            ]);

            return null;
        }

        return json_decode($response->getBody(), true);
    }
}
