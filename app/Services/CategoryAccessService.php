<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessException;
use App\Models\RequestCategoryAccessJobTitleModel;
use App\Models\RequestCategoryAccessRoleModel;
use App\Models\RequestCategoryAccessUserModel;
use App\Models\RequestCategoryModel;

class CategoryAccessService
{
    public const REASON_JOB_TITLE      = 'job_title';
    public const REASON_ROLE           = 'role';
    public const REASON_ALLOW          = 'allow';
    public const REASON_INHERITED      = 'inherited';
    public const REASON_DENY           = 'deny';
    public const REASON_INHERITED_DENY = 'inherited_deny';
    public const REASON_NONE           = 'none';

    public function __construct(
        private readonly RequestCategoryModel $categories,
        private readonly RequestCategoryAccessJobTitleModel $jobTitleAccess,
        private readonly RequestCategoryAccessRoleModel $roleAccess,
        private readonly RequestCategoryAccessUserModel $userAccess,
    ) {
    }

    /**
     * Kontrak untuk modul tiket (TicketModel::visibleTo()) di masa depan.
     *
     * 1. Tiket terlihat bila: pemohon = user INI, ATAU kategori tiket termasuk hasil method ini,
     *    ATAU user punya permission admin.tickets.view-all.
     * 2. Berikan entity User yang dimuat segar dari database, jangan yang dibangun dari auth_user()/session:
     *    salinan job_title dan role di session bisa basi, sedangkan method ini membaca $user->job_title dan $user->role_id.
     * 3. Jangan kirim daftar kosong ke whereIn([]). Daftar kosong berarti "tanpa kategori": hanya tiket sendiri.
     * 4. view-all berasal dari permission di session, jadi pencabutannya baru berlaku setelah login ulang;
     *    akses kategori dihitung ulang di setiap request.
     * 5. Tiket menunjuk kategori paling spesifik (sub bila ada). Kategori nonaktif tetap terlihat.
     *
     * @return list<string> id kategori utama dan sub yang boleh dilihat, termasuk yang nonaktif
     */
    public function effectiveCategoryIds(User $user): array
    {
        $ids = [];

        foreach ($this->explain($user) as $categoryId => $result) {
            if ($result['access']) {
                $ids[] = (string) $categoryId;
            }
        }

        return $ids;
    }

    /**
     * Satu-satunya tempat aturan akses dihitung. deny selalu menang atas aturan job title, aturan role, dan allow.
     * Urutan alasan bila beberapa berlaku: job_title > role > allow > inherited.
     *
     * @return array<string, array{access: bool, reason: string, detail: ?string}> kunci = id kategori
     */
    public function explain(User $user): array
    {
        $jobTitle = trim((string) $user->job_title);
        $roleId   = trim((string) $user->role_id);
        $allowed  = [];
        $denied   = [];

        foreach ($this->userAccess->forUser((string) $user->id) as $exception) {
            // Hanya allow yang persis boleh memberi akses; nilai lain (termasuk yang tidak dikenal) dianggap deny.
            if ($exception['effect'] === RequestCategoryAccessUserModel::EFFECT_ALLOW) {
                $allowed[$exception['request_category_id']] = true;
            } else {
                $denied[$exception['request_category_id']] = true;
            }
        }

        $context = [
            'jobTitle'    => $jobTitle,
            'roleId'      => $roleId,
            'jobRuleIds'  => $jobTitle === '' ? [] : $this->jobTitleAccess->categoryIdsForJobTitle($jobTitle),
            'roleRuleIds' => $roleId === '' ? [] : $this->roleAccess->categoryIdsForRole($roleId),
            'allowed'     => $allowed,
            'denied'      => $denied,
        ];

        $explained = [];

        foreach ($this->categories->allOrdered() as $category) {
            $explained[$category['id']] = $this->explainCategory($category, $context);
        }

        return $explained;
    }

    /**
     * @return list<array{request_category_id: string, effect: string}>
     */
    public function exceptionsFor(User $user): array
    {
        return $this->userAccess->forUser((string) $user->id);
    }

    /**
     * @return list<array{user_id: string, effect: string, name: string, employee_no: string}> pengecualian untuk satu kategori
     */
    public function userExceptionsForCategory(string $categoryId): array
    {
        return $this->userAccess->forCategory($categoryId);
    }

    /**
     * Menambah pengecualian user + kategori; bila sudah ada, efeknya diganti.
     *
     * @throws BusinessException
     */
    public function addException(User $user, string $categoryId, string $effect, string $actorId): void
    {
        $userId = (string) $user->id;

        $this->assertNotActor($userId, $actorId);
        $this->assertValidEffect($effect);
        $this->assertKnownCategory($categoryId);

        $this->saveException($userId, $categoryId, $effect, $actorId);
    }

    /**
     * @throws BusinessException
     */
    public function removeException(User $user, string $categoryId, string $actorId): void
    {
        $userId = (string) $user->id;

        $this->assertNotActor($userId, $actorId);
        $this->deleteException($userId, $categoryId, $actorId);
    }

    /**
     * Menjadikan pengecualian user untuk SATU kategori persis sama dengan $desired (dipakai form kategori).
     * Hanya baris yang berubah ditulis. Pelaku tidak boleh menambah, mengubah, atau mencabut pengecualian
     * akunnya sendiri; barisnya yang tidak berubah dibiarkan. Semua pemeriksaan selesai sebelum ada yang ditulis.
     *
     * @param array<string, string> $desired id user => effect
     *
     * @throws BusinessException
     */
    public function syncUserExceptionsForCategory(string $categoryId, array $desired, string $actorId): void
    {
        $this->assertKnownCategory($categoryId);

        foreach ($desired as $effect) {
            $this->assertValidEffect((string) $effect);
        }

        $current = array_column($this->userAccess->forCategory($categoryId), 'effect', 'user_id');
        $changed = array_keys(array_filter(
            $desired,
            static fn (string $effect, $userId): bool => ($current[$userId] ?? null) !== $effect,
            ARRAY_FILTER_USE_BOTH,
        ));
        $removed = array_keys(array_diff_key($current, $desired));

        foreach ([...$changed, ...$removed] as $userId) {
            $this->assertNotActor((string) $userId, $actorId);
        }

        foreach ($changed as $userId) {
            $this->saveException((string) $userId, $categoryId, $desired[$userId], $actorId);
        }

        foreach ($removed as $userId) {
            $this->deleteException((string) $userId, $categoryId, $actorId);
        }
    }

    private function saveException(string $userId, string $categoryId, string $effect, string $actorId): void
    {
        $existing = $this->userAccess->findOne($userId, $categoryId);

        if ($existing !== null) {
            $this->userAccess->update($existing['id'], ['effect' => $effect]);
        } else {
            $this->userAccess->insert([
                'user_id'             => $userId,
                'request_category_id' => $categoryId,
                'effect'              => $effect,
            ]);
        }

        log_message('info', "Category access: exception {$effect} user={$userId} category={$categoryId} actor={$actorId}");
    }

    private function deleteException(string $userId, string $categoryId, string $actorId): void
    {
        $existing = $this->userAccess->findOne($userId, $categoryId);

        if ($existing === null) {
            return;
        }

        $this->userAccess->delete($existing['id']);
        log_message('info', "Category access: exception removed user={$userId} category={$categoryId} actor={$actorId}");
    }

    /**
     * @param array{id: string, parent_id: ?string} $category
     * @param array{jobTitle: string, roleId: string, jobRuleIds: list<string>, roleRuleIds: list<string>, allowed: array<string, true>, denied: array<string, true>} $context
     *
     * @return array{access: bool, reason: string, detail: ?string}
     */
    private function explainCategory(array $category, array $context): array
    {
        $id       = $category['id'];
        $parentId = $category['parent_id'];

        if (isset($context['denied'][$id])) {
            return $this->result(false, self::REASON_DENY);
        }

        if ($parentId !== null && isset($context['denied'][$parentId])) {
            return $this->result(false, self::REASON_INHERITED_DENY);
        }

        if ($parentId === null && in_array($id, $context['jobRuleIds'], true)) {
            return $this->result(true, self::REASON_JOB_TITLE, $context['jobTitle']);
        }

        if ($parentId === null && in_array($id, $context['roleRuleIds'], true)) {
            return $this->result(true, self::REASON_ROLE, $context['roleId']);
        }

        if (isset($context['allowed'][$id])) {
            return $this->result(true, self::REASON_ALLOW);
        }

        if ($parentId !== null && $this->parentGrantsAccess($parentId, $context)) {
            return $this->result(true, self::REASON_INHERITED);
        }

        return $this->result(false, self::REASON_NONE);
    }

    private function parentGrantsAccess(string $parentId, array $context): bool
    {
        return in_array($parentId, $context['jobRuleIds'], true)
            || in_array($parentId, $context['roleRuleIds'], true)
            || isset($context['allowed'][$parentId]);
    }

    /**
     * Mencegah admin memberi atau mencabut pengecualian akses untuk akunnya sendiri.
     *
     * @throws BusinessException
     */
    private function assertNotActor(string $userId, string $actorId): void
    {
        if ($userId === $actorId) {
            throw new BusinessException(lang('Admin.categoryAccess.cannotChangeOwn'));
        }
    }

    /**
     * @throws BusinessException
     */
    private function assertValidEffect(string $effect): void
    {
        if (! in_array($effect, [RequestCategoryAccessUserModel::EFFECT_ALLOW, RequestCategoryAccessUserModel::EFFECT_DENY], true)) {
            throw new BusinessException(lang('Admin.categoryAccess.invalidEffect'));
        }
    }

    /**
     * @throws BusinessException
     */
    private function assertKnownCategory(string $categoryId): void
    {
        if ($this->categories->find($categoryId) === null) {
            throw new BusinessException(lang('Admin.categoryAccess.unknownCategory'));
        }
    }

    /**
     * @return array{access: bool, reason: string, detail: ?string}
     */
    private function result(bool $access, string $reason, ?string $detail = null): array
    {
        return ['access' => $access, 'reason' => $reason, 'detail' => $detail];
    }
}
