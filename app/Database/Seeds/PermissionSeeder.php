<?php

namespace App\Database\Seeds;

use App\Models\PermissionModel;
use App\Models\RoleModel;
use CodeIgniter\Database\Seeder;

/**
 * Menyamakan tabel permissions dengan PermissionModel::GROUPS dan memberi permission bawaan ke role.
 * Aman dijalankan ulang: yang baru ditambah, yang sudah tidak ada di kode dihapus
 * (pemetaannya ikut terhapus lewat cascade). Grant yang sudah ada tidak dicabut.
 *
 * Cara update permission:
 *  1. Ubah PermissionModel (konstanta + GROUPS), dan DEFAULT_ROLE_PERMISSIONS di bawah bila perlu.
 *  2. php spark db:seed PermissionSeeder
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions  = model(PermissionModel::class);
        $roles        = model(RoleModel::class);
        $definedSlugs = PermissionModel::definedSlugs();
        $now          = date('Y-m-d H:i:s');

        $permissions->whereNotIn('slug', $definedSlugs)->delete();

        foreach ($definedSlugs as $slug) {
            if ($permissions->where('slug', $slug)->first() === null) {
                $permissions->insert(['slug' => $slug]);
            }
        }

        // Permission bawaan tiap role. Admin mendapat semua (termasuk area user /u); user hanya portal.
        $defaults = [
            RoleModel::SLUG_USER  => [PermissionModel::USER_DASHBOARD_VIEW],
            RoleModel::SLUG_ADMIN => $definedSlugs,
        ];

        foreach ($defaults as $roleSlug => $slugs) {
            $role = $roles->where('slug', $roleSlug)->first();

            if ($role === null) {
                continue;
            }

            foreach ($slugs as $slug) {
                $permissionId = $permissions->where('slug', $slug)->first()['id'];
                $exists       = $this->db->table('role_permissions')
                    ->where(['role_id' => $role['id'], 'permission_id' => $permissionId])
                    ->countAllResults();

                if ($exists === 0) {
                    $this->db->table('role_permissions')->insert([
                        'id'            => uuid_v7(),
                        'role_id'       => $role['id'],
                        'permission_id' => $permissionId,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }
            }
        }
    }
}
