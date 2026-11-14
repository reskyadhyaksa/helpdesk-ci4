<?php

namespace App\Services;

use App\Entities\SlaPolicy;
use App\Exceptions\BusinessException;
use App\Models\SlaPolicyModel;
use CodeIgniter\Pager\Pager;

class SlaPolicyService
{
    public function __construct(private readonly SlaPolicyModel $policies)
    {
    }

    /**
     * @return array{policies: list<SlaPolicy>, pager: Pager}
     */
    public function paginate(): array
    {
        $policies = $this->policies->paginateForAdmin();

        return ['policies' => $policies, 'pager' => $this->policies->pager];
    }

    /**
     * @return array<string, string> id => nama kebijakan aktif untuk dropdown
     */
    public function activeOptions(?string $alsoId = null): array
    {
        return $this->policies->activeNames($alsoId);
    }

    public function find(string $id): ?SlaPolicy
    {
        return $this->policies->find($id);
    }

    /**
     * @throws BusinessException
     */
    public function create(array $data): SlaPolicy
    {
        $this->ensureValidTargets($data);

        $id = $this->policies->insert($this->normalize($data), true);
        $this->ensureSaved($id !== false);

        return $this->policies->find($id);
    }

    /**
     * @throws BusinessException
     */
    public function update(SlaPolicy $policy, array $data): void
    {
        $this->ensureValidTargets($data);

        // id ikut divalidasi agar is_unique mengecualikan baris ini sendiri ({id} di aturan model)
        $saved = $this->policies->update($policy->id, ['id' => $policy->id] + $this->normalize($data));
        $this->ensureSaved($saved);
    }

    public function delete(SlaPolicy $policy): void
    {
        $this->policies->delete($policy->id);
    }

    /**
     * Tiket harus bisa direspons sebelum targetnya selesai.
     */
    private function ensureValidTargets(array $data): void
    {
        if ((int) $data['resolution_minutes'] < (int) $data['response_minutes']) {
            throw new BusinessException(lang('Admin.sla.resolutionBeforeResponse'));
        }
    }

    private function normalize(array $data): array
    {
        $data['is_active']   = (int) $data['is_active'];
        $data['is_holiday']  = (int) $data['is_holiday'];
        $data['is_off_day']  = (int) $data['is_off_day'];
        $data['is_work_hours'] = (int) $data['is_work_hours'];
        $data['description'] = trim($data['description'] ?? '') ?: null;

        return $data;
    }

    private function ensureSaved(bool $saved): void
    {
        if (! $saved) {
            throw new BusinessException(implode(' ', $this->policies->errors()));
        }
    }
}
