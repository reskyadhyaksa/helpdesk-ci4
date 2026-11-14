<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\RequestCategoryAccessJobTitleModel;
use App\Models\RequestCategoryModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Pager\Pager;
use Throwable;

class RequestCategoryService
{
    public function __construct(
        private readonly RequestCategoryModel $categories,
        private readonly RequestCategoryAccessJobTitleModel $jobTitleAccess,
        private readonly UserModel $users,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @return array{categories: list<array>, pager: Pager, jobTitles: array<string, list<string>>}
     */
    public function paginate(?string $keyword): array
    {
        $categories = $this->categories->paginateForAdmin($keyword);
        $jobTitles  = $this->jobTitleAccess->jobTitlesByCategory(array_column($categories, 'id'));

        return ['categories' => $categories, 'pager' => $this->categories->pager, 'jobTitles' => $jobTitles];
    }

    public function find(string $id): ?array
    {
        return $this->categories->find($id);
    }

    /**
     * @return list<array> kategori utama untuk dropdown induk
     */
    public function topLevel(): array
    {
        return array_values(array_filter($this->all(), static fn (array $category) => $category['parent_id'] === null));
    }

    /**
     * @return list<array>
     */
    public function all(): array
    {
        return $this->categories->allOrdered();
    }

    /**
     * @return list<string>
     */
    public function jobTitlesFor(string $categoryId): array
    {
        return $this->jobTitleAccess->jobTitlesFor($categoryId);
    }

    /**
     * @param list<string> $alsoInclude job title tersimpan yang mungkin sudah tidak ada di users
     *
     * @return list<string>
     */
    public function jobTitleOptions(array $alsoInclude = []): array
    {
        // Data HR bisa berspasi di tepi; form selalu mengirim nilai yang sudah di-trim
        $options = array_map('trim', array_merge($this->users->filterOptions()['job_title'], $alsoInclude));
        $options = array_unique(array_filter($options, static fn (string $title) => $title !== ''));
        sort($options);

        return $options;
    }

    /**
     * @throws BusinessException
     */
    public function create(array $data): void
    {
        $data = $this->normalize($data);
        $this->ensureValid($data, null, []);

        $this->transactional(function () use ($data) {
            $id = $this->categories->insert($this->categoryRow($data), true);
            $this->ensureSaved($id !== false);

            $this->jobTitleAccess->replaceFor($id, $data['job_titles']);
            $this->logJobTitleRules($id, $data['job_titles']);
        });
    }

    /**
     * @throws BusinessException
     */
    public function update(array $category, array $data): void
    {
        $data = $this->normalize($data);
        $this->ensureValid($data, $category, $this->jobTitleAccess->jobTitlesFor($category['id']));

        $this->transactional(function () use ($category, $data) {
            $saved = $this->categories->update($category['id'], $this->categoryRow($data));
            $this->ensureSaved($saved);

            $this->jobTitleAccess->replaceFor($category['id'], $data['job_titles']);
            $this->logJobTitleRules($category['id'], $data['job_titles']);
        });
    }

    /**
     * @throws BusinessException
     */
    public function delete(array $category): void
    {
        if ($this->categories->hasChildren($category['id'])) {
            throw new BusinessException(lang('Admin.requestCategory.hasSubCategories'));
        }

        $this->categories->delete($category['id']);
    }

    /**
     * @param array|null   $category berisi kategori yang sedang diubah, null saat membuat
     * @param list<string> $savedJobTitles
     */
    private function ensureValid(array $data, ?array $category, array $savedJobTitles): void
    {
        $this->ensureValidParent($data['parent_id'], $category);

        if ($this->categories->nameExists($data['name'], $data['parent_id'], $category['id'] ?? null)) {
            throw new BusinessException(lang('Admin.requestCategory.nameTaken'));
        }

        if ($data['parent_id'] !== null && $data['job_titles'] !== []) {
            throw new BusinessException(lang('Admin.requestCategory.jobTitlesOnlyTopLevel'));
        }

        $unknown = array_diff($data['job_titles'], $this->jobTitleOptions($savedJobTitles));

        if ($unknown !== []) {
            throw new BusinessException(lang('Admin.requestCategory.unknownJobTitle'));
        }
    }

    private function ensureValidParent(?string $parentId, ?array $category): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = $this->categories->find($parentId);

        if ($parent === null || $parent['parent_id'] !== null || $parentId === ($category['id'] ?? null)) {
            throw new BusinessException(lang('Admin.requestCategory.parentInvalid'));
        }

        // Kategori utama yang sudah punya sub tidak boleh turun menjadi sub
        if ($category !== null && $this->categories->hasChildren($category['id'])) {
            throw new BusinessException(lang('Admin.requestCategory.hasSubCategories'));
        }
    }

    private function normalize(array $data): array
    {
        $jobTitles = array_map('trim', $data['job_titles'] ?? []);

        return [
            'parent_id'     => trim($data['parent_id'] ?? '') ?: null,
            'name'          => trim($data['name']),
            'description'   => trim($data['description'] ?? '') ?: null,
            'sla_policy_id' => trim($data['sla_policy_id'] ?? '') ?: null,
            'is_active'     => (int) $data['is_active'],
            'job_titles'    => array_values(array_unique(array_filter($jobTitles, static fn (string $title) => $title !== ''))),
        ];
    }

    /**
     * @param list<string> $jobTitles
     */
    private function logJobTitleRules(string $categoryId, array $jobTitles): void
    {
        log_message('info', 'Category access: job title rules category=' . $categoryId . ' job_titles=' . implode(',', $jobTitles));
    }

    private function categoryRow(array $data): array
    {
        unset($data['job_titles']);

        return $data;
    }

    private function ensureSaved(bool $saved): void
    {
        if (! $saved) {
            throw new BusinessException(implode(' ', $this->categories->errors()));
        }
    }

    // Kategori dan aturan job title ditulis ke dua tabel: harus berhasil bersama atau batal bersama.
    private function transactional(callable $work): void
    {
        $this->db->transBegin();

        try {
            $work();
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }
}
