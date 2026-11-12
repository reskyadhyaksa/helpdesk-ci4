<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessException;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Services\Auth\KeycloakAuthService;
use Config\Integration;

class AuthService
{
    private const SESSION_KEY = 'auth_user';

    public function __construct(
        private readonly KeycloakAuthService $keycloak,
        private readonly UserModel $users,
        private readonly RoleModel $roles,
        private readonly PermissionModel $permissions,
    ) {
    }

    /**
     * Identitas dari Keycloak, role dan permission dari database lokal.
     * Permission dimuat sekali saat login; perubahan role berlaku setelah login ulang.
     *
     * @throws BusinessException
     */
    public function login(string $username, string $password): void
    {
        $tokens = $this->keycloak->authenticate($username, $password);

        if ($tokens === null) {
            throw new BusinessException(lang('Auth.invalidCredentials'));
        }

        $claims     = $this->keycloak->parseTokenPayload($tokens['access_token'] ?? '') ?? [];
        $employeeNo = (string) ($claims[config(Integration::class)->keycloakEmployeeClaim] ?? '');
        $employee   = $employeeNo === '' ? null : $this->keycloak->getHelpdeskUserData($employeeNo);

        if ($employee === null) {
            throw new BusinessException(lang('Auth.employeeNotFound'));
        }

        $user = $this->findOrCreateUser($employeeNo, (string) ($claims['name'] ?? $employeeNo));

        if (! $user->is_active) {
            throw new BusinessException(lang('Auth.accountInactive'));
        }

        $this->startSession($user, $employee);
    }

    public function logout(): void
    {
        $session = session();
        $session->remove(self::SESSION_KEY);
        $session->regenerate(true);
    }

    public function user(): ?array
    {
        return session(self::SESSION_KEY);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->user()['permissions'] ?? [], true);
    }

    /**
     * Nama route halaman utama sesuai peran: tujuan setelah login.
     */
    public function homeRoute(): string
    {
        return ($this->user()['is_admin'] ?? false) ? 'admin.dashboard' : 'user.dashboard';
    }

    /**
     * Karyawan yang valid di Keycloak dibuat otomatis dengan role user.
     * Menjadi admin = mengganti role_id di tabel users.
     */
    private function findOrCreateUser(string $employeeNo, string $name): User
    {
        $user = $this->users->findByEmployeeNo($employeeNo);

        if ($user !== null) {
            return $user;
        }

        $this->users->insert([
            'role_id'     => $this->roles->idBySlug(RoleModel::SLUG_USER),
            'employee_no' => $employeeNo,
            'name'        => $name,
            'is_active'   => 1,
        ]);

        return $this->users->findByEmployeeNo($employeeNo);
    }

    private function startSession(User $user, array $employee): void
    {
        $permissions = $this->permissions->slugsForRole($user->role_id);

        $session = session();
        $session->regenerate(true);
        $session->set(self::SESSION_KEY, [
            'id'          => $user->id,
            'employee_no' => $user->employee_no,
            'name'        => $user->name,
            'role'        => $user->role_slug,
            'role_name'   => $user->role_name,
            'permissions' => $permissions,
            'is_admin'    => in_array(PermissionModel::ADMIN_ACCESS, $permissions, true),
            'employee'    => $employee,
        ]);
    }
}
