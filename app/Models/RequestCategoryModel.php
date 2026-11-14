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
     * @return list<array>
     */
    public function paginateForAdmin(?string $keyword): array
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

        return $this->orderedUnderParent()->paginate();
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

    private function orderedUnderParent(): static
    {
        return $this->join('request_categories parent', 'parent.id = request_categories.parent_id', 'left')
            ->orderBy('COALESCE(parent.name, request_categories.name)', 'ASC', false)
            ->orderBy('request_categories.parent_id IS NOT NULL', 'ASC', false)
            ->orderBy('request_categories.name', 'ASC');
    }
}
