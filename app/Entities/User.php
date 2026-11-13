<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class User extends Entity
{
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Profil sudah diambil dari API eksternal hari ini? Dipakai agar API dipanggil paling banyak sekali sehari.
     */
    public function isSyncedToday(): bool
    {
        return $this->last_synced_at !== null && substr($this->last_synced_at, 0, 10) === date('Y-m-d');
    }
}
