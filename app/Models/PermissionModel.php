<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class PermissionModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    // Format slug: <area>.<resource>.<aksi>; aksi CRUD = view, create, update, delete.
    // Tambah konstanta hanya saat fiturnya dibuat.
    public const ADMIN_ACCESS         = 'admin.access';
    public const ADMIN_DASHBOARD_VIEW = 'admin.dashboard.view';
    public const USER_DASHBOARD_VIEW  = 'user.dashboard.view';

    public const ADMIN_ROLES_VIEW   = 'admin.roles.view';
    public const ADMIN_ROLES_CREATE = 'admin.roles.create';
    public const ADMIN_ROLES_UPDATE = 'admin.roles.update';
    public const ADMIN_ROLES_DELETE = 'admin.roles.delete';

    // Pengguna dibuat otomatis saat login (Keycloak), jadi tidak ada create/delete
    public const ADMIN_USERS_VIEW   = 'admin.users.view';
    public const ADMIN_USERS_UPDATE = 'admin.users.update';

    public const ADMIN_SLA_VIEW   = 'admin.sla-policies.view';
    public const ADMIN_SLA_CREATE = 'admin.sla-policies.create';
    public const ADMIN_SLA_UPDATE = 'admin.sla-policies.update';
    public const ADMIN_SLA_DELETE = 'admin.sla-policies.delete';

    // Satu-satunya daftar permission. Dipakai form role (label + penjelasan) dan AuthSeeder (slug).
    // Modul: ikon Font Awesome + penjelasan singkat; permission: label + apa yang bisa dilakukan.
    public const GROUPS = [
        'Panel Admin' => [
            'icon'        => 'fa-gauge-high',
            'description' => 'Pintu masuk panel admin dan halaman ringkasannya.',
            'permissions' => [
                self::ADMIN_ACCESS         => ['label' => 'Akses panel admin', 'description' => 'Bisa masuk ke panel admin. Wajib untuk semua fitur admin.'],
                self::ADMIN_DASHBOARD_VIEW => ['label' => 'Lihat dashboard', 'description' => 'Melihat ringkasan aktivitas layanan.'],
            ],
        ],
        'Roles Category' => [
            'icon'        => 'fa-user-shield',
            'description' => 'Mengatur role dan hak akses setiap role.',
            'permissions' => [
                self::ADMIN_ROLES_VIEW   => ['label' => 'Lihat role', 'description' => 'Melihat daftar role dan hak aksesnya.'],
                self::ADMIN_ROLES_CREATE => ['label' => 'Tambah role', 'description' => 'Membuat role baru.'],
                self::ADMIN_ROLES_UPDATE => ['label' => 'Ubah role', 'description' => 'Mengubah nama dan hak akses role.'],
                self::ADMIN_ROLES_DELETE => ['label' => 'Hapus role', 'description' => 'Menghapus role yang tidak dipakai pengguna.'],
            ],
        ],
        'User Management' => [
            'icon'        => 'fa-users',
            'description' => 'Daftar pengguna portal beserta role-nya.',
            'permissions' => [
                self::ADMIN_USERS_VIEW   => ['label' => 'Lihat pengguna', 'description' => 'Melihat daftar pengguna dan role-nya.'],
                self::ADMIN_USERS_UPDATE => ['label' => 'Ubah role pengguna', 'description' => 'Memindahkan pengguna ke role lain.'],
            ],
        ],
        'SLA Policies' => [
            'icon'        => 'fa-clock',
            'description' => 'Batas waktu respons dan penyelesaian tiket.',
            'permissions' => [
                self::ADMIN_SLA_VIEW   => ['label' => 'Lihat SLA', 'description' => 'Melihat daftar kebijakan SLA.'],
                self::ADMIN_SLA_CREATE => ['label' => 'Tambah SLA', 'description' => 'Membuat kebijakan SLA baru.'],
                self::ADMIN_SLA_UPDATE => ['label' => 'Ubah SLA', 'description' => 'Mengubah target waktu dan status SLA.'],
                self::ADMIN_SLA_DELETE => ['label' => 'Hapus SLA', 'description' => 'Menghapus kebijakan SLA.'],
            ],
        ],
        'Portal User' => [
            'icon'        => 'fa-house-user',
            'description' => 'Halaman portal untuk karyawan.',
            'permissions' => [
                self::USER_DASHBOARD_VIEW => ['label' => 'Lihat dashboard user', 'description' => 'Membuka dashboard di portal karyawan.'],
            ],
        ],
    ];

    protected $table            = 'permissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['slug'];

    protected $validationRules = [
        'slug' => 'required|max_length[100]',
    ];

    /**
     * @return list<string> semua slug yang didefinisikan di GROUPS
     */
    public static function definedSlugs(): array
    {
        $slugs = [];

        foreach (self::GROUPS as $group) {
            foreach (array_keys($group['permissions']) as $slug) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * @param list<string> $slugs
     *
     * @return list<string> id permission untuk slug yang ada di database
     */
    public function idsBySlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return array_column($this->select('id')->whereIn('slug', $slugs)->findAll(), 'id');
    }

    /**
     * @return list<string> slug permission milik satu role
     */
    public function slugsForRole(string $roleId): array
    {
        $rows = $this->builder('role_permissions')
            ->select('permissions.slug')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->get()
            ->getResultArray();

        return array_column($rows, 'slug');
    }
}
