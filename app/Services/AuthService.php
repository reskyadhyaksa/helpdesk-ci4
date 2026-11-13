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

        if ($employeeNo === '') {
            throw new BusinessException(lang('Auth.employeeNotFound'));
        }

        $user = $this->refreshedUser($employeeNo, $claims);

        if (! $user->is_active) {
            throw new BusinessException(lang('Auth.accountInactive'));
        }

        $this->startSession($user);
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
     * Profil diambil dari API Spring Boot paling banyak sekali sehari per karyawan: login pertama hari itu
     * (atau karyawan baru) memanggil API, login berikutnya hanya mencatat waktu login.
     *
     * @throws BusinessException API tidak mengenali karyawan
     */
    private function refreshedUser(string $employeeNo, array $claims): User
    {
        $user = $this->users->findByEmployeeNo($employeeNo);

        if ($user !== null && $user->isSyncedToday()) {
            $this->users->update($user->id, ['last_login_time' => date('Y-m-d H:i:s')]);

            return $user;
        }

        $employee = $this->keycloak->getHelpdeskUserData($employeeNo);

        if ($employee === null) {
            throw new BusinessException(lang('Auth.employeeNotFound'));
        }

        return $this->syncUser($user, $employeeNo, $employee, $claims);
    }

    /**
     * Karyawan baru dibuat otomatis dengan role user. Menjadi admin = mengganti role_id di tabel users.
     */
    private function syncUser(?User $user, string $employeeNo, array $employee, array $claims): User
    {
        $now     = date('Y-m-d H:i:s');
        $profile = [
            'name'            => $employee['name'] ?? $employee['fullName'] ?? $claims['name'] ?? $employeeNo,
            'email'           => $employee['officeMail'] ?? $claims['email'] ?? null,
            'position'        => $employee['position'] ?? null,
            'position_level'  => $employee['positionLevel'] ?? $employee['position_level'] ?? null,
            'job_title'       => $employee['jobTitle'] ?? $employee['job_title'] ?? null,
            'organization'    => $employee['organization'] ?? null,
            'last_login_time' => $now,
            'last_synced_at'  => $now,
        ];

        if ($user === null) {
            $this->users->insert($profile + [
                'role_id'     => $this->roles->idBySlug(RoleModel::SLUG_USER),
                'employee_no' => $employeeNo,
                'is_active'   => 1,
            ]);
        } else {
            $this->users->update($user->id, $profile);
        }

        return $this->users->findByEmployeeNo($employeeNo);
    }

    private function startSession(User $user): void
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
            'job_title'   => $user->job_title,
            'permissions' => $permissions,
            'is_admin'    => in_array(PermissionModel::ADMIN_ACCESS, $permissions, true),
        ]);
    }
}
