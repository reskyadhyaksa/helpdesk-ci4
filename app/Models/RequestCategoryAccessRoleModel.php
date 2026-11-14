<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class RequestCategoryAccessRoleModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    protected $table            = 'request_category_access_role';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['role_id', 'request_category_id'];

    protected $validationRules = [
        'role_id'             => 'required|string',
        'request_category_id' => 'required|string',
    ];

    /**
     * @return list<string> id kategori yang punya aturan untuk role ini
     */
    public function categoryIdsForRole(string $roleId): array
    {
        $rows = $this->select('request_category_id')->where('role_id', $roleId)->findAll();

        return array_column($rows, 'request_category_id');
    }

    /**
     * @return list<string> id role yang boleh melihat kategori ini
     */
    public function roleIdsFor(string $categoryId): array
    {
        $rows = $this->select('role_id')->where('request_category_id', $categoryId)->findAll();

        return array_column($rows, 'role_id');
    }

    /**
     * @param list<string> $categoryIds
     *
     * @return array<string, list<string>> [id kategori => nama role]; kategori tanpa aturan tidak ada
     */
    public function roleNamesByCategory(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rows = $this->select('request_category_access_role.request_category_id, roles.name')
            ->join('roles', 'roles.id = request_category_access_role.role_id')
            ->whereIn('request_category_access_role.request_category_id', $categoryIds)
            ->orderBy('roles.name', 'ASC')
            ->findAll();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['request_category_id']][] = $row['name'];
        }

        return $grouped;
    }

    /**
     * Jadikan daftar role kategori ini persis sama dengan $roleIds.
     *
     * @param list<string> $roleIds
     */
    public function replaceFor(string $categoryId, array $roleIds): void
    {
        $roleIds  = array_values(array_unique($roleIds));
        $existing = $this->roleIdsFor($categoryId);
        $removed  = array_diff($existing, $roleIds);

        if ($removed !== []) {
            $this->where('request_category_id', $categoryId)->whereIn('role_id', $removed)->delete();
        }

        // Satu per satu lewat insert() agar callback Auditable berjalan (insertBatch tidak menjalankannya)
        foreach (array_diff($roleIds, $existing) as $roleId) {
            $this->insert(['role_id' => $roleId, 'request_category_id' => $categoryId]);
        }
    }
}
