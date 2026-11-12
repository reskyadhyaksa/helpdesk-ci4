<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Integrasi sistem luar. Nilai diisi dari .env, mis. integration.keycloakClientId.
 */
class Integration extends BaseConfig
{
    public string $keycloakTokenUrl     = '';
    public string $keycloakClientId     = '';
    public string $keycloakClientSecret = '';

    // Claim pada access_token Keycloak yang berisi nomor karyawan.
    public string $keycloakEmployeeClaim = 'preferred_username';

    public string $helpdeskApiBaseUrl = '';
    public string $helpdeskApiUser    = '';
    public string $helpdeskApiPass    = '';
}
