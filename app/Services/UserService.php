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
    private const PER_PAGE = 15;

    public function __construct(
        private readonly UserModel $users,
        private readonly RoleModel $roles,
    ) {
    }

    /**
     * @return array{users: list<User>, pager: Pager}
     */
    public function paginate(?string $keyword): array
    {
        $users = $this->users->paginateForAdmin($keyword, self::PER_PAGE);

        return ['users' => $users, 'pager' => $this->users->pager];
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
