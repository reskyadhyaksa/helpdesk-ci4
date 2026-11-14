<?php

use App\Entities\User;
use App\Exceptions\BusinessException;
use App\Models\RequestCategoryAccessJobTitleModel;
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

    private function user(?string $jobTitle = 'Payroll Staff'): User
    {
        return new User(['id' => 'user-1', 'job_title' => $jobTitle]);
    }

    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }

    public function testJobTitleRuleGrantsCategoryAndItsSubs(): void
    {
        $service = new CategoryAccessService($this->categories(), $this->jobTitleModel(['A']), $this->userModel([]));

        $this->assertSame(['A', 'A1'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testAllowAddsCategoryBeyondRule(): void
    {
        $service = new CategoryAccessService(
            $this->categories(),
            $this->jobTitleModel(['A']),
            $this->userModel(['B' => 'allow']),
        );

        $this->assertSame(['A', 'A1', 'B'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testDenyRemovesRuleCategory(): void
    {
        $service = new CategoryAccessService(
            $this->categories(),
            $this->jobTitleModel(['A']),
            $this->userModel(['B' => 'allow', 'C' => 'allow', 'D' => 'allow', 'A' => 'deny']),
        );

        $this->assertSame(['B', 'C', 'D'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testDenyOnSubOnlyBlocksThatSub(): void
    {
        $service = new CategoryAccessService(
            $this->categories(),
            $this->jobTitleModel(['A']),
            $this->userModel(['A1' => 'deny']),
        );

        $this->assertSame(['A'], $this->sorted($service->effectiveCategoryIds($this->user())));
    }

    public function testAllowOnSubOnlyGrantsOnlyThatSub(): void
    {
        $service = new CategoryAccessService(
            $this->categories(),
            $this->jobTitleModel([]),
            $this->userModel(['A1' => 'allow']),
        );

        $this->assertSame(['A1'], $service->effectiveCategoryIds($this->user()));
    }

    public function testDenyOnParentBeatsAllowOnSub(): void
    {
        $service = new CategoryAccessService(
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

        $service = new CategoryAccessService($this->categories(), $jobTitles, $this->userModel(['B' => 'allow']));

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

        $service = new CategoryAccessService($this->categories(), $jobTitles, $this->userModel([]));

        $this->assertSame(['A', 'A1'], $this->sorted($service->effectiveCategoryIds($this->user('  Payroll Staff '))));

        // Pencocokan persis: huruf kecil tidak cocok dengan aturan
        $service = new CategoryAccessService($this->categories(), $this->jobTitleModel(['A']), $this->userModel([]));

        $this->assertSame([], $service->effectiveCategoryIds($this->user('payroll staff')));
    }

    public function testInactiveCategoriesStayVisible(): void
    {
        $service = new CategoryAccessService(
            $this->categories(['B' => ['is_active' => 0]]),
            $this->jobTitleModel(['B']),
            $this->userModel([]),
        );

        $this->assertContains('B', $service->effectiveCategoryIds($this->user()));
    }

    public function testExplainReportsReasons(): void
    {
        $service = new CategoryAccessService(
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
        $service = new CategoryAccessService(
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
        return new CategoryAccessService(
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

        $this->exceptionService($userAccess)->addException($this->user(), 'B', 'allow');
    }

    public function testAddingSameExceptionTwiceUpdatesEffect(): void
    {
        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->method('findOne')->willReturn(['id' => 'row-1', 'effect' => 'allow']);
        $userAccess->expects($this->once())->method('update')->with('row-1', ['effect' => 'deny']);
        $userAccess->expects($this->never())->method('insert');

        $this->exceptionService($userAccess)->addException($this->user(), 'B', 'deny');
    }

    public function testInvalidEffectIsRejected(): void
    {
        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->expects($this->never())->method($this->anything());

        $this->expectException(BusinessException::class);

        $this->exceptionService($userAccess)->addException($this->user(), 'B', 'maybe');
    }

    public function testUnknownCategoryIsRejected(): void
    {
        $categories = $this->createMock(RequestCategoryModel::class);
        $categories->method('find')->willReturn(null);

        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->expects($this->never())->method('insert');

        $this->expectException(BusinessException::class);

        $this->exceptionService($userAccess, $categories)->addException($this->user(), 'missing', 'allow');
    }

    public function testRemoveExceptionDeletesRow(): void
    {
        $userAccess = $this->createMock(RequestCategoryAccessUserModel::class);
        $userAccess->method('findOne')->with('user-1', 'B')->willReturn(['id' => 'row-1', 'effect' => 'allow']);
        $userAccess->expects($this->once())->method('delete')->with('row-1');

        $this->exceptionService($userAccess)->removeException($this->user(), 'B');
    }
}
