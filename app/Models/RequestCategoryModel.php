<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class RequestCategoryModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    protected $table            = 'request_categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['parent_id', 'name', 'description', 'sla_policy_id', 'is_active'];

    protected $validationRules = [
        'parent_id'     => 'permit_empty|string',
        'name'          => 'required|max_length[100]',
        'description'   => 'permit_empty|max_length[255]',
        'sla_policy_id' => 'permit_empty|string',
        'is_active'     => 'permit_empty|in_list[0,1]',
    ];

    /**
     * @return list<array> semua kategori: kategori utama diikuti subnya, urut nama
     */
    public function allOrdered(): array
    {
        $this->select('request_categories.id, request_categories.parent_id, request_categories.name, '
            . 'request_categories.description, request_categories.sla_policy_id, request_categories.is_active');

        return $this->orderedUnderParent()->findAll();
    }

    /**
     * Sama seperti allOrdered() plus parent_name dan sla_name, dipaginasi (pager di $this->pager).
     *
     * @param array{parent_id?: list<string>, job_title?: list<string>, role_id?: list<string>} $filters
     *
     * @return list<array>
     */
    public function paginateForAdmin(?string $keyword, array $filters = []): array
    {
        $this->select('request_categories.id, request_categories.parent_id, request_categories.name, '
            . 'request_categories.description, request_categories.sla_policy_id, request_categories.is_active, '
            . 'parent.name AS parent_name, sla_policies.name AS sla_name')
            ->join('sla_policies', 'sla_policies.id = request_categories.sla_policy_id', 'left');

        if ($keyword !== null && $keyword !== '') {
            $this->groupStart()
                ->like('request_categories.name', $keyword)
                ->orLike('request_categories.description', $keyword)
                ->groupEnd();
        }

        $this->applyFilters($filters);

        return $this->orderedUnderParent()->paginate();
    }

    /**
     * Tujuan tiket: kategori aktif yang tidak punya sub kategori (tiket menunjuk kategori paling spesifik).
     *
     * @return list<array> id, name, parent_name; kategori utama diikuti subnya, urut nama
     */
    public function selectableForTicket(): array
    {
        $this->select('request_categories.id, request_categories.name, parent.name AS parent_name')
            ->where('request_categories.is_active', 1)
            ->groupStart()->where('parent.id IS NULL', null, false)->orWhere('parent.is_active', 1)->groupEnd()
            ->where('NOT EXISTS (SELECT 1 FROM request_categories child WHERE child.parent_id = request_categories.id)', null, false);

        return $this->orderedUnderParent()->findAll();
    }

    public function hasTickets(string $id): bool
    {
        return $this->db->table('tickets')->where('request_category_id', $id)->countAllResults() > 0;
    }

    public function hasChildren(string $id): bool
    {
        return $this->where('parent_id', $id)->countAllResults() > 0;
    }

    public function nameExists(string $name, ?string $parentId, ?string $exceptId = null): bool
    {
        $this->where('name', $name);

        if ($parentId === null) {
            $this->where('parent_id IS NULL', null, false);
        } else {
            $this->where('parent_id', $parentId);
        }

        if ($exceptId !== null) {
            $this->where('id !=', $exceptId);
        }

        return $this->countAllResults() > 0;
    }

    /**
     * Filter parent: kategori itu beserta subnya. Filter akses: aturan job title/role disimpan di kategori
     * utama dan diikuti sub, jadi yang dicocokkan adalah kategori utama dari baris tersebut.
     */
    private function applyFilters(array $filters): void
    {
        if (isset($filters['parent_id'])) {
            $this->groupStart()
                ->whereIn('request_categories.id', $filters['parent_id'])
                ->orWhereIn('request_categories.parent_id', $filters['parent_id'])
                ->groupEnd();
        }

        $root = 'COALESCE(request_categories.parent_id, request_categories.id)';

        if (isset($filters['job_title'])) {
            $this->where("EXISTS (SELECT 1 FROM request_category_access_job_title a WHERE a.request_category_id = {$root}"
                . ' AND a.job_title IN (' . implode(',', $this->db->escape($filters['job_title'])) . '))', null, false);
        }

        if (isset($filters['role_id'])) {
            $this->where("EXISTS (SELECT 1 FROM request_category_access_role a WHERE a.request_category_id = {$root}"
                . ' AND a.role_id IN (' . implode(',', $this->db->escape($filters['role_id'])) . '))', null, false);
        }
    }

    private function orderedUnderParent(): static
    {
        return $this->join('request_categories parent', 'parent.id = request_categories.parent_id', 'left')
            ->orderBy('COALESCE(parent.name, request_categories.name)', 'ASC', false)
            ->orderBy('request_categories.parent_id IS NOT NULL', 'ASC', false)
            ->orderBy('request_categories.name', 'ASC');
    }
}
