<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class RolePermissionModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    protected $table            = 'role_permissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['role_id', 'permission_id'];

    /**
     * Ganti seluruh permission satu role. Panggil di dalam transaksi.
     *
     * @param list<string> $permissionIds
     */
    public function replaceForRole(string $roleId, array $permissionIds): void
    {
        $this->where('role_id', $roleId)->delete();

        foreach ($permissionIds as $permissionId) {
            $this->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }
}
