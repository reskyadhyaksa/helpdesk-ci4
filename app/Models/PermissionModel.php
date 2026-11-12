<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class PermissionModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    // Format slug: <area>.<resource/aksi>. Tambah konstanta hanya saat fiturnya dibuat.
    public const ADMIN_ACCESS         = 'admin.access';
    public const ADMIN_DASHBOARD_VIEW = 'admin.dashboard.view';
    public const USER_DASHBOARD_VIEW  = 'user.dashboard.view';

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
