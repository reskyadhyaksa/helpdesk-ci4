<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessException;
use App\Models\RequestCategoryAccessJobTitleModel;
use App\Models\RequestCategoryAccessUserModel;
use App\Models\RequestCategoryModel;

class CategoryAccessService
{
    public const REASON_JOB_TITLE      = 'job_title';
    public const REASON_ALLOW          = 'allow';
    public const REASON_INHERITED      = 'inherited';
    public const REASON_DENY           = 'deny';
    public const REASON_INHERITED_DENY = 'inherited_deny';
    public const REASON_NONE           = 'none';

    public function __construct(
        private readonly RequestCategoryModel $categories,
        private readonly RequestCategoryAccessJobTitleModel $jobTitleAccess,
        private readonly RequestCategoryAccessUserModel $userAccess,
    ) {
    }

    /**
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
     * Satu-satunya tempat aturan akses dihitung. deny selalu menang atas aturan job title dan allow.
     *
     * @return array<string, array{access: bool, reason: string, detail: ?string}> kunci = id kategori
     */
    public function explain(User $user): array
    {
        $jobTitle = trim((string) $user->job_title);
        $ruleIds  = $jobTitle === '' ? [] : $this->jobTitleAccess->categoryIdsForJobTitle($jobTitle);
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

        $explained = [];

        foreach ($this->categories->allOrdered() as $category) {
            $explained[$category['id']] = $this->explainCategory($category, $jobTitle, $ruleIds, $allowed, $denied);
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
     * Menambah pengecualian user + kategori; bila sudah ada, efeknya diganti.
     *
     * @throws BusinessException
     */
    public function addException(User $user, string $categoryId, string $effect, string $actorId): void
    {
        $this->assertNotOwnAccount($user, $actorId);

        if (! in_array($effect, [RequestCategoryAccessUserModel::EFFECT_ALLOW, RequestCategoryAccessUserModel::EFFECT_DENY], true)) {
            throw new BusinessException(lang('Admin.categoryAccess.invalidEffect'));
        }

        if ($this->categories->find($categoryId) === null) {
            throw new BusinessException(lang('Admin.categoryAccess.unknownCategory'));
        }

        $userId   = (string) $user->id;
        $existing = $this->userAccess->findOne($userId, $categoryId);

        if ($existing !== null) {
            $this->userAccess->update($existing['id'], ['effect' => $effect]);

            return;
        }

        $this->userAccess->insert([
            'user_id'             => $userId,
            'request_category_id' => $categoryId,
            'effect'              => $effect,
        ]);
    }

    /**
     * @throws BusinessException
     */
    public function removeException(User $user, string $categoryId, string $actorId): void
    {
        $this->assertNotOwnAccount($user, $actorId);

        $existing = $this->userAccess->findOne((string) $user->id, $categoryId);

        if ($existing !== null) {
            $this->userAccess->delete($existing['id']);
        }
    }

    /**
     * @param array{id: string, parent_id: ?string} $category
     * @param list<string>                          $ruleIds
     * @param array<string, true>                   $allowed
     * @param array<string, true>                   $denied
     *
     * @return array{access: bool, reason: string, detail: ?string}
     */
    private function explainCategory(array $category, string $jobTitle, array $ruleIds, array $allowed, array $denied): array
    {
        $id       = $category['id'];
        $parentId = $category['parent_id'];

        if (isset($denied[$id])) {
            return $this->result(false, self::REASON_DENY);
        }

        if ($parentId !== null && isset($denied[$parentId])) {
            return $this->result(false, self::REASON_INHERITED_DENY);
        }

        if ($parentId === null && in_array($id, $ruleIds, true)) {
            return $this->result(true, self::REASON_JOB_TITLE, $jobTitle);
        }

        if (isset($allowed[$id])) {
            return $this->result(true, self::REASON_ALLOW);
        }

        if ($parentId !== null && (in_array($parentId, $ruleIds, true) || isset($allowed[$parentId]))) {
            return $this->result(true, self::REASON_INHERITED);
        }

        return $this->result(false, self::REASON_NONE);
    }

    /**
     * Mencegah admin memberi atau mencabut pengecualian akses untuk akunnya sendiri.
     *
     * @throws BusinessException
     */
    private function assertNotOwnAccount(User $user, string $actorId): void
    {
        if ((string) $user->id === $actorId) {
            throw new BusinessException(lang('Admin.categoryAccess.cannotChangeOwn'));
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
