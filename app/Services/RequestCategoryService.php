<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\RequestCategoryAccessJobTitleModel;
use App\Models\RequestCategoryAccessRoleModel;
use App\Models\RequestCategoryModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Pager\Pager;
use Throwable;

class RequestCategoryService
{
    public const ACCESS_JOB_TITLE = 'job_title';
    public const ACCESS_ROLE      = 'role';
    public const ACCESS_USER      = 'user';

    public function __construct(
        private readonly RequestCategoryModel $categories,
        private readonly RequestCategoryAccessJobTitleModel $jobTitleAccess,
        private readonly RequestCategoryAccessRoleModel $roleAccess,
        private readonly UserModel $users,
        private readonly RoleModel $roles,
        private readonly CategoryAccessService $access,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @param array{parent_id?: list<string>, job_title?: list<string>, role_id?: list<string>} $filters
     *
     * @return array{categories: list<array>, pager: Pager, jobTitles: array<string, list<string>>, roles: array<string, list<string>>}
     */
    public function paginate(?string $keyword, array $filters = []): array
    {
        $categories  = $this->categories->paginateForAdmin($keyword, $filters);
        $categoryIds = array_column($categories, 'id');

        return [
            'categories' => $categories,
            'pager'      => $this->categories->pager,
            'jobTitles'  => $this->jobTitleAccess->jobTitlesByCategory($categoryIds),
            'roles'      => $this->roleAccess->roleNamesByCategory($categoryIds),
        ];
    }

    /**
     * Pilihan dropdown filter di daftar kategori: kategori utama, job title yang dipakai aturan, dan role.
     *
     * @return array{parent_id: array<string, string>, job_title: array<string, string>, role_id: array<string, string>}
     */
    public function filterOptions(): array
    {
        $jobTitles = $this->jobTitleAccess->usedJobTitles();

        return [
            'parent_id' => array_column($this->topLevel(), 'name', 'id'),
            'job_title' => array_combine($jobTitles, $jobTitles),
            'role_id'   => $this->roleOptions(),
        ];
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
     * @return array<string, string> id => nama role
     */
    public function roleOptions(): array
    {
        return $this->roles->nameOptions();
    }

    /**
     * @return array<string, string> id => "Nama (NIK)"
     */
    public function userOptions(): array
    {
        return $this->users->nameOptions();
    }

    /**
     * Baris tabel akses di form kategori: aturan job title, aturan role, dan pengecualian user.
     * Tanpa $submitted: keadaan tersimpan di database. Dengan $submitted (form yang gagal disimpan):
     * keadaan yang dikirim, supaya yang dihapus admin tidak muncul lagi.
     *
     * @param array{job_titles?: array, role_ids?: array, user_access?: array}|null $submitted
     *
     * @return list<array{type: string, value: string, label: string, detail: ?string, effect: ?string}>
     */
    public function accessEntries(?string $categoryId, ?array $submitted = null): array
    {
        if ($submitted === null) {
            return $categoryId === null ? [] : $this->savedAccessEntries($categoryId);
        }

        $data    = $this->normalize($submitted + ['name' => ''], true);
        $entries = array_map(fn (string $title): array => $this->entry(self::ACCESS_JOB_TITLE, $title, $title), $data['job_titles']);

        return [...$entries, ...$this->roleEntries($data['role_ids']), ...$this->userEntriesFromInput($data['user_access'])];
    }

    /**
     * @throws BusinessException
     */
    public function create(array $data, string $actorId, bool $canManageUserAccess): void
    {
        $data = $this->normalize($data, $canManageUserAccess);
        $this->ensureValid($data, null, [], $canManageUserAccess);

        $this->transactional(function () use ($data, $actorId, $canManageUserAccess) {
            $id = $this->categories->insert($this->categoryRow($data), true);
            $this->ensureSaved($id !== false);

            $this->saveAccess($id, $data, $actorId, $canManageUserAccess);
        });
    }

    /**
     * @throws BusinessException
     */
    public function update(array $category, array $data, string $actorId, bool $canManageUserAccess): void
    {
        $data = $this->normalize($data, $canManageUserAccess);
        $this->ensureValid($data, $category, $this->jobTitleAccess->jobTitlesFor($category['id']), $canManageUserAccess);

        $this->transactional(function () use ($category, $data, $actorId, $canManageUserAccess) {
            $saved = $this->categories->update($category['id'], $this->categoryRow($data));
            $this->ensureSaved($saved);

            $this->saveAccess($category['id'], $data, $actorId, $canManageUserAccess);
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

        // Tiket menunjuk kategori ini (foreign key RESTRICT): tolak dengan pesan, jangan biarkan database yang gagal
        if ($this->categories->hasTickets($category['id'])) {
            throw new BusinessException(lang('Admin.requestCategory.hasTickets'));
        }

        $this->categories->delete($category['id']);
    }

    /**
     * Aturan job title dan role mengikuti form; pengecualian user hanya disentuh bila pelaku berhak mengelolanya.
     */
    private function saveAccess(string $categoryId, array $data, string $actorId, bool $canManageUserAccess): void
    {
        $this->jobTitleAccess->replaceFor($categoryId, $data['job_titles']);
        $this->roleAccess->replaceFor($categoryId, $data['role_ids']);
        log_message('info', 'Category access: rules category=' . $categoryId
            . ' job_titles=' . implode(',', $data['job_titles']) . ' roles=' . implode(',', $data['role_ids']));

        if ($canManageUserAccess) {
            $this->access->syncUserExceptionsForCategory($categoryId, $data['user_access'], $actorId);
        }
    }

    /**
     * @param array|null   $category berisi kategori yang sedang diubah, null saat membuat
     * @param list<string> $savedJobTitles
     */
    private function ensureValid(array $data, ?array $category, array $savedJobTitles, bool $validateUsers): void
    {
        $this->ensureValidParent($data['parent_id'], $category);
        $this->ensureHasSla($data);

        if ($this->categories->nameExists($data['name'], $data['parent_id'], $category['id'] ?? null)) {
            throw new BusinessException(lang('Admin.requestCategory.nameTaken'));
        }

        $this->ensureValidRules($data, $savedJobTitles);

        if ($validateUsers && $data['user_access'] !== [] && ! $this->allExist($this->users, array_keys($data['user_access']))) {
            throw new BusinessException(lang('Admin.requestCategory.unknownUser'));
        }
    }

    /**
     * Aturan job title dan role hanya untuk kategori utama, dan harus berasal dari daftar yang ada.
     *
     * @param list<string> $savedJobTitles
     */
    private function ensureValidRules(array $data, array $savedJobTitles): void
    {
        if ($data['parent_id'] !== null && $data['job_titles'] !== []) {
            throw new BusinessException(lang('Admin.requestCategory.jobTitlesOnlyTopLevel'));
        }

        if ($data['parent_id'] !== null && $data['role_ids'] !== []) {
            throw new BusinessException(lang('Admin.requestCategory.rolesOnlyTopLevel'));
        }

        if (array_diff($data['job_titles'], $this->jobTitleOptions($savedJobTitles)) !== []) {
            throw new BusinessException(lang('Admin.requestCategory.unknownJobTitle'));
        }

        if ($data['role_ids'] !== [] && ! $this->allExist($this->roles, $data['role_ids'])) {
            throw new BusinessException(lang('Admin.requestCategory.unknownRole'));
        }
    }

    /**
     * @param list<string> $ids
     */
    private function allExist(RoleModel|UserModel $model, array $ids): bool
    {
        return count($model->find($ids)) === count($ids);
    }

    /**
     * Setiap kategori harus punya SLA yang berlaku: kategori utama wajib memilihnya sendiri; sub kategori boleh
     * mengosongkan (otomatis memakai SLA kategori utamanya) selama kategori utamanya punya SLA.
     *
     * @throws BusinessException
     */
    private function ensureHasSla(array $data): void
    {
        if ($data['sla_policy_id'] !== null) {
            return;
        }

        $parent = $data['parent_id'] === null ? null : $this->categories->find($data['parent_id']);

        if ($parent === null || empty($parent['sla_policy_id'])) {
            throw new BusinessException(lang('Admin.requestCategory.slaRequired'));
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

    private function normalize(array $data, bool $canManageUserAccess): array
    {
        return [
            'parent_id'     => trim($data['parent_id'] ?? '') ?: null,
            'name'          => trim($data['name']),
            'description'   => trim($data['description'] ?? '') ?: null,
            'sla_policy_id' => trim($data['sla_policy_id'] ?? '') ?: null,
            'is_active'     => (int) ($data['is_active'] ?? 1),
            'job_titles'    => $this->uniqueTrimmed($data['job_titles'] ?? []),
            'role_ids'      => $this->uniqueTrimmed($data['role_ids'] ?? []),
            'user_access'   => $canManageUserAccess ? $this->userAccessMap($data['user_access'] ?? []) : [],
        ];
    }

    /**
     * @return list<string>
     */
    private function uniqueTrimmed(array $values): array
    {
        $values = array_map(static fn ($value): string => trim((string) $value), $values);

        return array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
    }

    /**
     * @return array<string, string> id user => effect
     */
    private function userAccessMap(array $raw): array
    {
        $map = [];

        foreach ($raw as $userId => $effect) {
            $userId = trim((string) $userId);

            if ($userId !== '') {
                $map[$userId] = trim((string) $effect);
            }
        }

        return $map;
    }

    /**
     * @return list<array{type: string, value: string, label: string, detail: ?string, effect: ?string}>
     */
    private function savedAccessEntries(string $categoryId): array
    {
        $jobTitles = array_map(fn (string $title): array => $this->entry(self::ACCESS_JOB_TITLE, $title, $title), $this->jobTitleAccess->jobTitlesFor($categoryId));
        $users     = array_map(
            fn (array $row): array => $this->entry(self::ACCESS_USER, $row['user_id'], $row['name'], $row['employee_no'], $row['effect']),
            $this->access->userExceptionsForCategory($categoryId),
        );

        return [...$jobTitles, ...$this->roleEntries($this->roleAccess->roleIdsFor($categoryId)), ...$users];
    }

    /**
     * @param list<string> $roleIds
     *
     * @return list<array{type: string, value: string, label: string, detail: ?string, effect: ?string}>
     */
    private function roleEntries(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $entries = array_map(
            fn (array $role): array => $this->entry(self::ACCESS_ROLE, $role['id'], $role['name']),
            $this->roles->find($roleIds),
        );
        usort($entries, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $entries;
    }

    /**
     * @param array<string, string> $userAccess id user => effect
     *
     * @return list<array{type: string, value: string, label: string, detail: ?string, effect: ?string}>
     */
    private function userEntriesFromInput(array $userAccess): array
    {
        if ($userAccess === []) {
            return [];
        }

        $entries = array_map(
            fn ($user): array => $this->entry(self::ACCESS_USER, $user->id, $user->name, $user->employee_no, $userAccess[$user->id] ?? null),
            $this->users->find(array_keys($userAccess)),
        );
        usort($entries, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $entries;
    }

    /**
     * @return array{type: string, value: string, label: string, detail: ?string, effect: ?string}
     */
    private function entry(string $type, string $value, string $label, ?string $detail = null, ?string $effect = null): array
    {
        return ['type' => $type, 'value' => $value, 'label' => $label, 'detail' => $detail, 'effect' => $effect];
    }

    private function categoryRow(array $data): array
    {
        unset($data['job_titles'], $data['role_ids'], $data['user_access']);

        return $data;
    }

    private function ensureSaved(bool $saved): void
    {
        if (! $saved) {
            throw new BusinessException(implode(' ', $this->categories->errors()));
        }
    }

    // Kategori dan aturan aksesnya ditulis ke beberapa tabel: harus berhasil bersama atau batal bersama.
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
