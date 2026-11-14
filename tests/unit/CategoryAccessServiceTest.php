<?php

use App\Entities\User;
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
        $this->assertSame('allow', $explain['C']['reason']);
        $this->assertSame(['access' => false, 'reason' => 'deny', 'detail' => null], $explain['B']);
        $this->assertSame(['access' => false, 'reason' => 'none', 'detail' => null], $explain['D']);
    }
}
