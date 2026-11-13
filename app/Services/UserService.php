<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessException;
use App\Models\RoleModel;
use App\Models\UserModel;
use CodeIgniter\Pager\Pager;

/**
 * Pengelolaan pengguna oleh admin. Pengguna dibuat otomatis saat login (lihat AuthService),
 * jadi di sini hanya daftar dan penggantian role.
 */
class UserService
{
    public function __construct(
        private readonly UserModel $users,
        private readonly RoleModel $roles,
    ) {
    }

    /**
     * @param array<string, list<string>> $filters kolom profil (UserModel::FILTER_COLUMNS) => daftar nilai
     *
     * @return array{users: list<User>, pager: Pager}
     */
    public function paginate(?string $keyword, array $filters = []): array
    {
        $users = $this->users->paginateForAdmin($keyword, $filters);

        return ['users' => $users, 'pager' => $this->users->pager];
    }

    /**
     * @return array<string, list<string>> pilihan dropdown filter per kolom profil
     */
    public function filterOptions(): array
    {
        return $this->users->filterOptions();
    }

    public function find(string $id): ?User
    {
        return $this->users->find($id);
    }

    /**
     * Perubahan role berlaku setelah pengguna login ulang (permission dimuat ke session saat login).
     *
     * @throws BusinessException
     */
    public function assignRole(User $user, string $roleId, string $actorId): void
    {
        // Mencegah admin melepas akses adminnya sendiri tanpa sengaja
        if ($user->id === $actorId) {
            throw new BusinessException(lang('Admin.users.cannotChangeOwnRole'));
        }

        if ($this->roles->find($roleId) === null) {
            throw new BusinessException(lang('Admin.users.roleNotFound'));
        }

        $this->users->update($user->id, ['role_id' => $roleId]);
    }
}
