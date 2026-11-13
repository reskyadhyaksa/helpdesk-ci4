<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Pager\Pager;
use Throwable;

/**
 * Role dan permission-nya. Perubahan permission berlaku setelah pengguna login ulang
 * (permission dimuat ke session saat login, lihat AuthService).
 */
class RoleService
{
    // Tanpa permission ini role admin terkunci dari panel admin dan tidak bisa memulihkan aksesnya.
    private const LOCKED_ADMIN_PERMISSIONS = [
        PermissionModel::ADMIN_ACCESS,
        PermissionModel::ADMIN_ROLES_VIEW,
        PermissionModel::ADMIN_ROLES_UPDATE,
    ];

    public function __construct(
        private readonly RoleModel $roles,
        private readonly PermissionModel $permissions,
        private readonly RolePermissionModel $rolePermissions,
        private readonly UserModel $users,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @return list<array>
     */
    public function all(): array
    {
        return $this->roles->allWithCounts();
    }

    /**
     * @return array{roles: list<array>, pager: Pager}
     */
    public function paginate(): array
    {
        $roles = $this->roles->paginateWithCounts();

        return ['roles' => $roles, 'pager' => $this->roles->pager];
    }

    public function find(string $id): ?array
    {
        return $this->roles->find($id);
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(array $role): array
    {
        return $this->permissions->slugsForRole($role['id']);
    }

    private function isSystem(array $role): bool
    {
        return in_array($role['slug'], RoleModel::SYSTEM_SLUGS, true);
    }

    /**
     * @return list<string> permission yang selalu melekat dan tidak bisa dilepas dari role ini
     */
    public function lockedPermissions(array $role): array
    {
        return $role['slug'] === RoleModel::SLUG_ADMIN ? self::LOCKED_ADMIN_PERMISSIONS : [];
    }

    /**
     * @throws BusinessException
     */
    public function create(array $data): void
    {
        $permissionIds = $this->permissionIds($data['permissions'] ?? []);

        $this->transactional(function () use ($data, $permissionIds) {
            $roleId = $this->roles->insert(['slug' => $this->uniqueSlugFor($data['name']), 'name' => $data['name']], true);
            $this->ensureSaved($roleId !== false);

            $this->rolePermissions->replaceForRole($roleId, $permissionIds);
        });
    }

    /**
     * @throws BusinessException
     */
    /**
     * Slug tidak ikut berubah saat nama diganti, agar rujukan kode ke role tetap stabil.
     *
     * @throws BusinessException
     */
    public function update(array $role, array $data): void
    {
        $slugs         = array_merge($data['permissions'] ?? [], $this->lockedPermissions($role));
        $permissionIds = $this->permissionIds($slugs);

        $this->transactional(function () use ($role, $data, $permissionIds) {
            $saved = $this->roles->update($role['id'], ['name' => $data['name']]);
            $this->ensureSaved($saved);

            $this->rolePermissions->replaceForRole($role['id'], $permissionIds);
        });
    }

    /**
     * @throws BusinessException
     */
    public function delete(array $role): void
    {
        if ($this->isSystem($role)) {
            throw new BusinessException(lang('Admin.roles.systemCannotDelete'));
        }

        if ($this->users->countByRole($role['id']) > 0) {
            throw new BusinessException(lang('Admin.roles.hasUsers'));
        }

        // role_permissions ikut terhapus lewat foreign key ON DELETE CASCADE
        $this->roles->delete($role['id']);
    }

    /**
     * Hanya permission yang didefinisikan di kode yang boleh dipasang.
     *
     * @param list<string> $slugs
     *
     * @return list<string>
     */
    private function permissionIds(array $slugs): array
    {
        $slugs = array_values(array_unique($slugs));

        if (array_diff($slugs, PermissionModel::definedSlugs()) !== []) {
            throw new BusinessException(lang('Admin.roles.unknownPermission'));
        }

        return $this->permissions->idsBySlugs($slugs);
    }

    /**
     * Slug dari nama, mis. "HCIS Officer" → "hcis-officer". Jika sudah dipakai: "hcis-officer-2", dst.
     * Dipotong 45 karakter agar akhiran angka tetap muat di kolom 50 karakter.
     */
    private function uniqueSlugFor(string $name): string
    {
        helper('url');

        $base = rtrim(substr(url_title($name, '-', true), 0, 45), '-');

        if ($base === '') {
            $base = 'role';
        }

        $slug   = $base;
        $suffix = 2;

        while ($this->roles->slugExists($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function ensureSaved(bool $saved): void
    {
        if (! $saved) {
            throw new BusinessException(implode(' ', $this->roles->errors()));
        }
    }

    // Role dan permission-nya ditulis ke dua tabel: harus berhasil bersama atau batal bersama.
    private function transactional(callable $work): void
    {
        $this->db->transBegin();

        try {
            $work();
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }
}
