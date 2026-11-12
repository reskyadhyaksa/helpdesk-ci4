<?php

namespace App\Database\Seeds;

use App\Models\PermissionModel;
use App\Models\RoleModel;
use CodeIgniter\Database\Seeder;

/**
 * Data awal RBAC (role, permission, pemetaannya). Aman dijalankan ulang.
 */
class AuthSeeder extends Seeder
{
    private const ROLE_NAMES = [
        RoleModel::SLUG_USER  => 'User',
        RoleModel::SLUG_ADMIN => 'Admin',
    ];

    private const ROLE_PERMISSIONS = [
        RoleModel::SLUG_USER  => [
            PermissionModel::USER_DASHBOARD_VIEW,
        ],
        RoleModel::SLUG_ADMIN => [
            PermissionModel::ADMIN_ACCESS,
            PermissionModel::ADMIN_DASHBOARD_VIEW,
        ],
    ];

    public function run(): void
    {
        $roles       = model(RoleModel::class);
        $permissions = model(PermissionModel::class);
        $now         = date('Y-m-d H:i:s');

        foreach (self::ROLE_NAMES as $slug => $name) {
            if ($roles->where('slug', $slug)->first() === null) {
                $roles->insert(['slug' => $slug, 'name' => $name]);
            }
        }

        $permissionSlugs = array_unique(array_merge(...array_values(self::ROLE_PERMISSIONS)));
        foreach ($permissionSlugs as $slug) {
            if ($permissions->where('slug', $slug)->first() === null) {
                $permissions->insert(['slug' => $slug]);
            }
        }

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $slugs) {
            $roleId = $roles->where('slug', $roleSlug)->first()['id'];

            foreach ($slugs as $slug) {
                $permissionId = $permissions->where('slug', $slug)->first()['id'];
                $exists       = $this->db->table('role_permissions')
                    ->where(['role_id' => $roleId, 'permission_id' => $permissionId])
                    ->countAllResults();

                if ($exists === 0) {
                    $this->db->table('role_permissions')->insert([
                        'id'            => uuid_v7(),
                        'role_id'       => $roleId,
                        'permission_id' => $permissionId,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }
            }
        }
    }
}
