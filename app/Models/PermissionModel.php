<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class PermissionModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const ADMIN_ACCESS = 'admin.access';
    public const ADMIN_DASHBOARD_VIEW = 'admin.dashboard.view';
    public const USER_DASHBOARD_VIEW = 'user.dashboard.view';

    public const ADMIN_ROLES_VIEW = 'admin.roles.view';
    public const ADMIN_ROLES_CREATE = 'admin.roles.create';
    public const ADMIN_ROLES_UPDATE = 'admin.roles.update';
    public const ADMIN_ROLES_DELETE = 'admin.roles.delete';

    public const ADMIN_USERS_VIEW = 'admin.users.view';
    public const ADMIN_USERS_UPDATE = 'admin.users.update';
    public const ADMIN_USERS_CATEGORY_ACCESS = 'admin.users.category-access';

    public const ADMIN_REQUEST_CATEGORIES_VIEW = 'admin.request-categories.view';
    public const ADMIN_REQUEST_CATEGORIES_CREATE = 'admin.request-categories.create';
    public const ADMIN_REQUEST_CATEGORIES_UPDATE = 'admin.request-categories.update';
    public const ADMIN_REQUEST_CATEGORIES_DELETE = 'admin.request-categories.delete';

    public const ADMIN_TICKETS_VIEW_ALL = 'admin.tickets.view-all';

    public const ADMIN_SLA_VIEW = 'admin.sla-policies.view';
    public const ADMIN_SLA_CREATE = 'admin.sla-policies.create';
    public const ADMIN_SLA_UPDATE = 'admin.sla-policies.update';
    public const ADMIN_SLA_DELETE = 'admin.sla-policies.delete';

    public const ADMIN_HOLIDAYS_VIEW = 'admin.holidays.view';
    public const ADMIN_HOLIDAYS_CREATE = 'admin.holidays.create';
    public const ADMIN_HOLIDAYS_UPDATE = 'admin.holidays.update';
    public const ADMIN_HOLIDAYS_DELETE = 'admin.holidays.delete';
    public const ADMIN_HOLIDAYS_SYNC = 'admin.holidays.sync';

    // Hari off mingguan: satu pengaturan global, jadi hanya ada ubah
    public const ADMIN_OFF_DAYS_UPDATE = 'admin.off-days.update';

    public const GROUPS = [
        'Panel Admin' => [
            'icon' => 'fa-gauge-high',
            'description' => 'Pintu masuk panel admin dan halaman ringkasannya.',
            'permissions' => [
                self::ADMIN_ACCESS => ['label' => 'Akses panel admin', 'description' => 'Bisa masuk ke panel admin. Wajib untuk semua fitur admin.'],
                self::ADMIN_DASHBOARD_VIEW => ['label' => 'Lihat dashboard', 'description' => 'Melihat ringkasan aktivitas layanan.'],
            ],
        ],
        'Roles Category' => [
            'icon' => 'fa-user-shield',
            'description' => 'Mengatur role dan hak akses setiap role.',
            'permissions' => [
                self::ADMIN_ROLES_VIEW => ['label' => 'Lihat role', 'description' => 'Melihat daftar role dan hak aksesnya.'],
                self::ADMIN_ROLES_CREATE => ['label' => 'Tambah role', 'description' => 'Membuat role baru.'],
                self::ADMIN_ROLES_UPDATE => ['label' => 'Ubah role', 'description' => 'Mengubah nama dan hak akses role.'],
                self::ADMIN_ROLES_DELETE => ['label' => 'Hapus role', 'description' => 'Menghapus role yang tidak dipakai pengguna.'],
            ],
        ],
        'User Management' => [
            'icon' => 'fa-users',
            'description' => 'Daftar pengguna portal beserta role-nya.',
            'permissions' => [
                self::ADMIN_USERS_VIEW => ['label' => 'Lihat pengguna', 'description' => 'Melihat daftar pengguna dan role-nya.'],
                self::ADMIN_USERS_UPDATE => ['label' => 'Ubah role pengguna', 'description' => 'Memindahkan pengguna ke role lain.'],
                self::ADMIN_USERS_CATEGORY_ACCESS => ['label' => 'Kelola akses kategori user', 'description' => 'Mengelola pengecualian izin dan blokir akses kategori request per pengguna.'],
            ],
        ],
        'Request Category' => [
            'icon' => 'fa-folder-open',
            'description' => 'Kategori request yang dipilih saat membuat tiket.',
            'permissions' => [
                self::ADMIN_REQUEST_CATEGORIES_VIEW => ['label' => 'Lihat kategori request', 'description' => 'Melihat daftar kategori request.'],
                self::ADMIN_REQUEST_CATEGORIES_CREATE => ['label' => 'Tambah kategori request', 'description' => 'Membuat kategori request baru dan menentukan job title yang boleh melihat tiketnya (akses ke tiket rahasia).'],
                self::ADMIN_REQUEST_CATEGORIES_UPDATE => ['label' => 'Ubah kategori request', 'description' => 'Mengubah nama dan status kategori request serta job title yang boleh melihat tiketnya (akses ke tiket rahasia).'],
                self::ADMIN_REQUEST_CATEGORIES_DELETE => ['label' => 'Hapus kategori request', 'description' => 'Menghapus kategori request yang tidak dipakai.'],
            ],
        ],
        'Tiket' => [
            'icon' => 'fa-ticket',
            'description' => 'Akses ke tiket layanan.',
            'permissions' => [
                self::ADMIN_TICKETS_VIEW_ALL => ['label' => 'Lihat semua tiket', 'description' => 'Melihat tiket dari semua kategori tanpa dibatasi akses kategori.'],
            ],
        ],
        'SLA Policies' => [
            'icon' => 'fa-clock',
            'description' => 'Batas waktu respons dan penyelesaian tiket.',
            'permissions' => [
                self::ADMIN_SLA_VIEW => ['label' => 'Lihat SLA', 'description' => 'Melihat daftar kebijakan SLA.'],
                self::ADMIN_SLA_CREATE => ['label' => 'Tambah SLA', 'description' => 'Membuat kebijakan SLA baru.'],
                self::ADMIN_SLA_UPDATE => ['label' => 'Ubah SLA', 'description' => 'Mengubah target waktu dan status SLA.'],
                self::ADMIN_SLA_DELETE => ['label' => 'Hapus SLA', 'description' => 'Menghapus kebijakan SLA.'],
            ],
        ],
        'Holiday' => [
            'icon' => 'fa-calendar-days',
            'description' => 'Hari libur dan hari off mingguan yang tidak dihitung dalam SLA.',
            'permissions' => [
                self::ADMIN_HOLIDAYS_VIEW => ['label' => 'Lihat hari libur', 'description' => 'Membuka halaman Holiday: daftar hari libur dan hari off mingguan.'],
                self::ADMIN_HOLIDAYS_CREATE => ['label' => 'Tambah hari libur', 'description' => 'Menambahkan hari libur secara manual.'],
                self::ADMIN_HOLIDAYS_UPDATE => ['label' => 'Ubah hari libur', 'description' => 'Mengubah tanggal, nama, dan jenis hari libur.'],
                self::ADMIN_HOLIDAYS_DELETE => ['label' => 'Hapus hari libur', 'description' => 'Menghapus hari libur.'],
                self::ADMIN_HOLIDAYS_SYNC => ['label' => 'Sync hari libur', 'description' => 'Menarik daftar hari libur terbaru dari aplikasi eksternal.'],
                self::ADMIN_OFF_DAYS_UPDATE => ['label' => 'Ubah hari off mingguan', 'description' => 'Memilih hari off mingguan, mis. Sabtu dan Minggu.'],
            ],
        ],
        'Portal User' => [
            'icon' => 'fa-house-user',
            'description' => 'Halaman portal untuk karyawan.',
            'permissions' => [
                self::USER_DASHBOARD_VIEW => ['label' => 'Lihat dashboard user', 'description' => 'Membuka dashboard di portal karyawan.'],
            ],
        ],
    ];

    protected $table = 'permissions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $beforeInsert = ['generateUuid'];
    protected $allowedFields = ['slug'];

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
