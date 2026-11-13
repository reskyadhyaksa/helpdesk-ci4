<?php

namespace App\Database\Seeds;

use App\Models\RoleModel;
use CodeIgniter\Database\Seeder;

/**
 * Data awal RBAC: role, lalu permission lewat PermissionSeeder. Aman dijalankan ulang.
 */
class AuthSeeder extends Seeder
{
    private const ROLE_NAMES = [
        RoleModel::SLUG_USER  => 'User',
        RoleModel::SLUG_ADMIN => 'Admin',
    ];

    public function run(): void
    {
        $roles = model(RoleModel::class);

        foreach (self::ROLE_NAMES as $slug => $name) {
            if ($roles->where('slug', $slug)->first() === null) {
                $roles->insert(['slug' => $slug, 'name' => $name]);
            }
        }

        $this->call(PermissionSeeder::class);
    }
}
