<?php

use App\Entities\User;
use App\Exceptions\BusinessException;
use App\Models\RequestCategoryAccessJobTitleModel;
use App\Models\RequestCategoryAccessRoleModel;
use App\Models\RequestCategoryAccessUserModel;
use App\Models\RequestCategoryModel;
use App\Services\CategoryAccessService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CategoryAccessServiceTest extends CIUnitTestCase
{
    private function categories(array $overrides = []): RequestCategoryModel
    {
        $rows = [
            ['id' => 'A', 'parent_id' => null, 'name' => 'A', 'is_active' => 1],
            ['id' => 'A1', 'parent_id' => 'A', 'name' => 'A1', 'is_active' => 1],
            ['id' => 'B', 'parent_id' => null, 'name' => 'B', 'is_active' => 1],
            ['id' => 'C', 'parent_id' => null, 'name' => 'C', 'is_active' => 1],
            ['id' => 'D', 'parent_id' => null, 'name' => 'D', 'is_active' => 1],
        ];

        foreach ($rows as &$row) {
            $row = array_merge($row, $overrides[$row['id']] ?? []);
        }

        $model = $this->createMock(RequestCategoryModel::class);
        $model->method('allOrdered')->willReturn($rows);

        return $model;
    }

    /**
     * @param list<string> $ruleIds
     */
    private function jobTitleModel(array $ruleIds): RequestCategoryAccessJobTitleModel
    {
        $model = $this->createMock(RequestCategoryAccessJobTitleModel::class);
        $model->method('categoryIdsForJobTitle')->willReturnCallback(
            static fn (string $title): array => $title === 'Payroll Staff' ? $ruleIds : [],
        );

        return $model;
    }

    /**
     * @param array<string, string> $exceptions [id kategori => effect]
     */
    private function userModel(array $exceptions): RequestCategoryAccessUserModel
    {
        $rows = [];

        foreach ($exceptions as $categoryId => $effect) {
            $rows[] = ['request_category_id' => $categoryId, 'effect' => $effect];
        }

        $model = $this->createMock(RequestCategoryAccessUserModel::class);
        $model->method('forUser')->willReturn($rows);

        return $model;
    }

    /**
     * @param list<string> $ruleIds id kategori untuk role 'role-1'
     */
    private function roleModel(array $ruleIds): RequestCategoryAccessRoleModel
    {
        $model = $this->createMock(RequestCategoryAccessRoleModel::class);
        $model->method('categoryIdsForRole')->willReturnCallback(
            static fn (string $roleId): array => $roleId === 'role-1' ? $ruleIds : [],
        );

        return $model;
    }

    private function make(
        RequestCategoryModel $categories,
        RequestCategoryAccessJobTitleModel $jobTitleAccess,
        RequestCategoryAccessUserModel $userAccess,
        ?RequestCategoryAccessRoleModel $roleAccess = null,
    ): CategoryAccessService {
        return new CategoryAccessService($categories, $jobTitleAccess, $roleAccess ?? $this->roleModel([]), $userAccess);
    }

    private function user(?string $jobTitle = 'Payroll Staff', ?string $roleId = 'role-1'): User
    {
        return new User(['id' => 'user-1', 'job_title' => $jobTitle, 'role_id' => $roleId]);
    }

    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }

    public function testJobTitleRuleGrantsCategoryAndItsSubs(): void
    {
        $service = $this->make($this->categories(), $this->jobTitleModel(['A']), $this->userModel([]));

        $this->assertSame(['A', 'A1'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testAllowAddsCategoryBeyondRule(): void
    {
        $service = $this->make(
            $this->categories(),
            $this->jobTitleModel(['A']),
            $this->userModel(['B' => 'allow']),
        );

        $this->assertSame(['A', 'A1', 'B'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testDenyRemovesRuleCategory(): void
    {
        $service = $this->make(
            $this->categories(),
            $this->jobTitleModel(['A']),
            $this->userModel(['B' => 'allow', 'C' => 'allow', 'D' => 'allow', 'A' => 'deny']),
        );

        $this->assertSame(['B', 'C', 'D'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testDenyOnSubOnlyBlocksThatSub(): void
    {
        $service = $this->make(
            $this->categories(),
            $this->jobTitleModel(['A']),
            $this->userModel(['A1' => 'deny']),
        );

        $this->assertSame(['A'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testAllowOnSubOnlyGrantsOnlyThatSub(): void
    {
        $service = $this->make(
            $this->categories(),
            $this->jobTitleModel([]),
            $this->userModel(['A1' => 'allow']),
        );

        $this->assertSame(['A1'], $service->effectiveCategoryIds($this->user()));
    }

    public function testDenyOnParentBeatsAllowOnSub(): void
    {
        $service = $this->make(
            $this->categories(),
            $this->jobTitleModel([]),
            $this->userModel(['A' => 'deny', 'A1' => 'allow']),
        );

        $this->assertSame([], $service->effectiveCategoryIds($this->user()));
        $this->assertSame(
            ['access' => false, 'reason' => 'inherited_deny', 'detail' => null],
            $service->explain($this->user())['A1'],
        );
    }

    public function testUserWithoutJobTitleGetsNoRuleAccess(): void
    {
        $jobTitles = $this->createMock(RequestCategoryAccessJobTitleModel::class);
        $jobTitles->expects($this->never())->method('categoryIdsForJobTitle');

        $service = $this->make($this->categories(), $jobTitles, $this->userModel(['B' => 'allow']));

        $this->assertSame(['B'], $service->effectiveCategoryIds($this->user(null)));
        $this->assertSame(['B'], $service->effectiveCategoryIds($this->user('   ')));
    }

    public function testJobTitleIsTrimmedBeforeLookup(): void
    {
        $jobTitles = $this->createMock(RequestCategoryAccessJobTitleModel::class);
        $jobTitles->expects($this->once())
            ->method('categoryIdsForJobTitle')
            ->with('Payroll Staff')
            ->willReturn(['A']);

        $service = $this->make($this->categories(), $jobTitles, $this->userModel([]));

        $this->assertSame(['A', 'A1'], $this->sorted($service->effectiveCategoryIds($this->user('  Payroll Staff '))));

        // Pencocokan persis: huruf kecil tidak cocok dengan aturan
        $service = $this->make($this->categories(), $this->jobTitleModel(['A']), $this->userModel([]));

        $this->assertSame([], $service->effectiveCategoryIds($this->user('payroll staff')));
    }

    public function testInactiveCategoriesStayVisible(): void
    {
        $service = $this->make(
            $this->categories(['B' => ['is_active' => 0]]),
            $this->jobTitleModel(['B']),
            $this->userModel([]),
        );

        $this->assertContains('B', $service->effectiveCategoryIds($this->user()));
    }

    public function testAllowOnParentGrantsItsSubCategories(): void
    {
        $service = $this->make($this->categories(), $this->jobTitleModel([]), $this->userModel(['A' => 'allow']));

        $this->assertSame(['A', 'A1'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testJobTitleRuleReasonOutranksAllowReason(): void
    {
        $service = $this->make($this->categories(), $this->jobTitleModel(['A']), $this->userModel(['A' => 'allow']));

        $this->assertSame('job_title', $service->explain($this->user())['A']['reason']);
    }

    public function testExplainReportsReasons(): void
    {
        $service = $this->make(
            $this->categories(),
            $this->jobTitleModel(['A']),
            $this->userModel(['C' => 'allow', 'B' => 'deny']),
        );

        $explain = $service->explain($this->user());

        $this->assertSame(['access' => true, 'reason' => 'job_title', 'detail' => 'Payroll Staff'], $explain['A']);
        $this->assertSame('inherited', $explain['A1']['reason']);
        $this->assertTrue($explain['A1']['access']);
        $this->assertSame(['access' => true, 'reason' => 'allow', 'detail' => null], $explain['C']);
        $this->assertSame(['access' => false, 'reason' => 'deny', 'detail' => null], $explain['B']);
        $this->assertSame(['access' => false, 'reason' => 'none', 'detail' => null], $explain['D']);
    }

    public function testUnknownEffectValueNeverGrantsAccess(): void
    {
        $service = $this->make(
            $this->categories(),
            $this->jobTitleModel([]),
            $this->userModel(['B' => 'maybe', 'A1' => '']),
        );

        $this->assertSame([], $service->effectiveCategoryIds($this->user()));
    }

    private function exceptionService(
        ?RequestCategoryAccessUserModel $userAccess = null,
        ?RequestCategoryModel $categories = null,
    ): CategoryAccessService {
        return $this->make(
            $categories ?? $this->knownCategory(),
            $this->jobTitleModel([]),
            $userAccess ?? $this->createMock(RequestCategoryAccessUserModel::class),
        );
    }

    private function knownCategory(): RequestCategoryModel
    {
        $categories = $this->createMock(RequestCategoryModel::class);
        $categories->method('find')->willReturn(['id' => 'B', 'parent_id' => null]);

        return $categories;
    }

    public function testAddExceptionInsertsNewRow(): void
    {
        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->method('findOne')->with('user-1', 'B')->willReturn(null);
        $userAccess->expects($this->once())->method('insert')->with([
            'user_id'             => 'user-1',
            'request_category_id' => 'B',
            'effect'              => 'allow',
        ]);
        $userAccess->expects($this->never())->method('update');

        $this->exceptionService($userAccess)->addException($this->user(), 'B', 'allow', 'admin-1');
    }

    public function testAddingSameExceptionTwiceUpdatesEffect(): void
    {
        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->method('findOne')->willReturn(['id' => 'row-1', 'effect' => 'allow']);
        $userAccess->expects($this->once())->method('update')->with('row-1', ['effect' => 'deny']);
        $userAccess->expects($this->never())->method('insert');

        $this->exceptionService($userAccess)->addException($this->user(), 'B', 'deny', 'admin-1');
    }

    public function testInvalidEffectIsRejected(): void
    {
        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->expects($this->never())->method($this->anything());

        $this->expectException(BusinessException::class);

        $this->exceptionService($userAccess)->addException($this->user(), 'B', 'maybe', 'admin-1');
    }

    public function testUnknownCategoryIsRejected(): void
    {
        $categories = $this->createMock(RequestCategoryModel::class);
        $categories->method('find')->willReturn(null);

        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->expects($this->never())->method('insert');

        $this->expectException(BusinessException::class);

        $this->exceptionService($userAccess, $categories)->addException($this->user(), 'missing', 'allow', 'admin-1');
    }

    public function testRemoveExceptionDeletesRow(): void
    {
        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->method('findOne')->with('user-1', 'B')->willReturn(['id' => 'row-1', 'effect' => 'allow']);
        $userAccess->expects($this->once())->method('delete')->with('row-1');

        $this->exceptionService($userAccess)->removeException($this->user(), 'B', 'admin-1');
    }

    private function untouchedUserAccess(): RequestCategoryAccessUserModel
    {
        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->expects($this->never())->method('findOne');
        $userAccess->expects($this->never())->method('insert');
        $userAccess->expects($this->never())->method('update');
        $userAccess->expects($this->never())->method('delete');

        return $userAccess;
    }

    public function testAddExceptionOnOwnAccountIsRejected(): void
    {
        $this->expectException(BusinessException::class);

        $this->exceptionService($this->untouchedUserAccess())->addException($this->user(), 'B', 'allow', 'user-1');
    }

    public function testRemoveExceptionOnOwnAccountIsRejected(): void
    {
        $this->expectException(BusinessException::class);

        $this->exceptionService($this->untouchedUserAccess())->removeException($this->user(), 'B', 'user-1');
    }

    public function testRoleRuleGrantsCategoryAndItsSubs(): void
    {
        $service = $this->make($this->categories(), $this->jobTitleModel([]), $this->userModel([]), $this->roleModel(['A']));

        $this->assertSame(['A', 'A1'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testRoleAndJobTitleRulesAreCombined(): void
    {
        $service = $this->make($this->categories(), $this->jobTitleModel(['A']), $this->userModel([]), $this->roleModel(['B']));

        $this->assertSame(['A', 'A1', 'B'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testDenyBeatsRoleRule(): void
    {
        $service = $this->make($this->categories(), $this->jobTitleModel([]), $this->userModel(['A' => 'deny']), $this->roleModel(['A', 'B']));

        $this->assertSame(['B'], $service->effectiveCategoryIds($this->user()));
    }

    public function testJobTitleReasonOutranksRoleReasonAndRoleOutranksAllow(): void
    {
        $service = $this->make(
            $this->categories(),
            $this->jobTitleModel(['A']),
            $this->userModel(['B' => 'allow']),
            $this->roleModel(['A', 'B']),
        );

        $explain = $service->explain($this->user());

        $this->assertSame('job_title', $explain['A']['reason']);
        $this->assertSame(['access' => true, 'reason' => 'role', 'detail' => 'role-1'], $explain['B']);
        $this->assertSame('inherited', $explain['A1']['reason']);
    }

    public function testUserWithoutRoleGetsNoRoleAccess(): void
    {
        $roleAccess = $this->createMock(RequestCategoryAccessRoleModel::class);
        $roleAccess->expects($this->never())->method('categoryIdsForRole');

        $service = $this->make($this->categories(), $this->jobTitleModel([]), $this->userModel(['B' => 'allow']), $roleAccess);

        $this->assertSame(['B'], $service->effectiveCategoryIds($this->user('Payroll Staff', null)));
    }

    /**
     * @param array<string, string> $current [user id => effect] pengecualian yang tersimpan untuk kategori B
     */
    private function syncService(array $current, ?RequestCategoryAccessUserModel &$userAccess = null): CategoryAccessService
    {
        $rows = [];

        foreach ($current as $userId => $effect) {
            $rows[] = ['user_id' => $userId, 'effect' => $effect, 'name' => $userId, 'employee_no' => '1'];
        }

        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->method('forCategory')->with('B')->willReturn($rows);
        $userAccess->method('findOne')->willReturnCallback(
            static fn (string $userId, string $categoryId): ?array => isset($current[$userId])
                ? ['id' => 'row-' . $userId, 'effect' => $current[$userId]]
                : null,
        );

        return $this->exceptionService($userAccess);
    }

    public function testSyncAddsChangesAndRemovesExceptions(): void
    {
        $service = $this->syncService(['u1' => 'allow', 'u2' => 'deny', 'u3' => 'allow'], $userAccess);

        $userAccess->expects($this->once())->method('update')->with('row-u1', ['effect' => 'deny']);
        $userAccess->expects($this->once())->method('insert')->with([
            'user_id'             => 'u4',
            'request_category_id' => 'B',
            'effect'              => 'allow',
        ]);
        $userAccess->expects($this->once())->method('delete')->with('row-u3');

        $service->syncUserExceptionsForCategory('B', ['u1' => 'deny', 'u2' => 'deny', 'u4' => 'allow'], 'admin-1');
    }

    public function testSyncRejectsGrantingTheActorAnException(): void
    {
        $service = $this->syncService([], $userAccess);

        $userAccess->expects($this->never())->method('insert');
        $userAccess->expects($this->never())->method('update');
        $userAccess->expects($this->never())->method('delete');
        $this->expectException(BusinessException::class);

        $service->syncUserExceptionsForCategory('B', ['admin-1' => 'allow'], 'admin-1');
    }

    public function testSyncRejectsRemovingTheActorsOwnException(): void
    {
        $service = $this->syncService(['admin-1' => 'deny'], $userAccess);

        $userAccess->expects($this->never())->method('delete');
        $this->expectException(BusinessException::class);

        $service->syncUserExceptionsForCategory('B', [], 'admin-1');
    }

    public function testSyncLeavesAnUnchangedOwnExceptionAlone(): void
    {
        $service = $this->syncService(['admin-1' => 'allow'], $userAccess);

        $userAccess->expects($this->once())->method('insert')->with($this->callback(
            static fn (array $row): bool => $row['user_id'] === 'u2' && $row['effect'] === 'deny',
        ));
        $userAccess->expects($this->never())->method('update');
        $userAccess->expects($this->never())->method('delete');

        $service->syncUserExceptionsForCategory('B', ['admin-1' => 'allow', 'u2' => 'deny'], 'admin-1');
    }

    public function testSyncRejectsAnUnknownEffectBeforeWritingAnything(): void
    {
        $service = $this->syncService([], $userAccess);

        $userAccess->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $service->syncUserExceptionsForCategory('B', ['u2' => 'allow', 'u3' => 'maybe'], 'admin-1');
    }
}
