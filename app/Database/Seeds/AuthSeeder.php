<?php

namespace App\Database\Seeds;

use App\Models\PermissionModel;
use App\Models\RoleModel;
use CodeIgniter\Database\Seeder;

/**
 * Data awal RBAC (role, permission, pemetaannya). Aman dijalankan ulang.
 * Menyamakan tabel permissions dengan PermissionModel::GROUPS: yang baru ditambah,
 * yang sudah tidak ada di kode dihapus (pemetaannya ikut terhapus lewat cascade).
 */
class AuthSeeder extends Seeder
{
    private const ROLE_NAMES = [
        RoleModel::SLUG_USER  => 'User',
        RoleModel::SLUG_ADMIN => 'Admin',
    ];

    public function run(): void
    {
        $roles       = model(RoleModel::class);
        $permissions = model(PermissionModel::class);
        $definedSlugs = PermissionModel::definedSlugs();
        $now         = date('Y-m-d H:i:s');

        foreach (self::ROLE_NAMES as $slug => $name) {
            if ($roles->where('slug', $slug)->first() === null) {
                $roles->insert(['slug' => $slug, 'name' => $name]);
            }
        }

        $permissions->whereNotIn('slug', $definedSlugs)->delete();

        foreach ($definedSlugs as $slug) {
            if ($permissions->where('slug', $slug)->first() === null) {
                $permissions->insert(['slug' => $slug]);
            }
        }

        // Admin mendapat semua permission (termasuk area user /u); user hanya portal.
        $rolePermissions = [
            RoleModel::SLUG_USER  => [PermissionModel::USER_DASHBOARD_VIEW],
            RoleModel::SLUG_ADMIN => $definedSlugs,
        ];

        foreach ($rolePermissions as $roleSlug => $slugs) {
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
