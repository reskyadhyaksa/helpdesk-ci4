<?php

namespace App\Models;

use App\Entities\SlaPolicy;
use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class SlaPolicyModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    protected $table            = 'sla_policies';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = SlaPolicy::class;
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = [
        'name', 'priority', 'response_minutes', 'resolution_minutes', 'description',
        'is_holiday', 'is_off_day', 'is_work_hours', 'is_active',
    ];

    protected $validationRules = [
        'name'               => 'required|max_length[100]|is_unique[sla_policies.name,id,{id}]',
        'priority'           => 'required|max_length[20]',
        'response_minutes'   => 'required|is_natural_no_zero',
        'resolution_minutes' => 'required|is_natural_no_zero',
        'description'        => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'name' => ['is_unique' => 'Nama kebijakan SLA sudah dipakai.'],
    ];

    /**
     * Kebijakan aktif lebih dulu, lalu dari target respons tercepat. Dipaginasi; pager ada di $this->pager.
     *
     * @return list<SlaPolicy>
     */
    public function paginateForAdmin(): array
    {
        return $this->orderBy('is_active', 'DESC')
            ->orderBy('response_minutes', 'ASC')
            ->paginate();
    }

    /**
     * @param string|null $alsoId kebijakan tersimpan yang tetap ditampilkan walau sudah nonaktif
     *
     * @return array<string, string> id => nama kebijakan aktif (plus $alsoId), urut nama
     */
    public function activeNames(?string $alsoId = null): array
    {
        $this->select('id, name, is_active')->groupStart()->where('is_active', 1);

        if ($alsoId !== null) {
            $this->orWhere('id', $alsoId);
        }

        $rows   = $this->groupEnd()->orderBy('name', 'ASC')->asArray()->findAll();
        $labels = [];

        foreach ($rows as $row) {
            $labels[$row['id']] = $row['is_active'] ? $row['name'] : $row['name'] . ' (nonaktif)';
        }

        return $labels;
    }
}
