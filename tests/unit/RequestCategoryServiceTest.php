<?php

use App\Exceptions\BusinessException;
use App\Models\RequestCategoryAccessJobTitleModel;
use App\Models\RequestCategoryModel;
use App\Models\UserModel;
use App\Services\RequestCategoryService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class RequestCategoryServiceTest extends CIUnitTestCase
{
    private const CATEGORY_ID = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b';
    private const PARENT_ID   = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0c';

    private RequestCategoryModel $categories;
    private RequestCategoryAccessJobTitleModel $jobTitleAccess;
    private UserModel $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categories     = $this->createMock(RequestCategoryModel::class);
        $this->jobTitleAccess = $this->createMock(RequestCategoryAccessJobTitleModel::class);
        $this->users          = $this->createMock(UserModel::class);
        $this->users->method('filterOptions')->willReturn(['job_title' => ['Payroll Staff', 'HC Officer']]);
    }

    private function service(?BaseConnection $db = null): RequestCategoryService
    {
        return new RequestCategoryService(
            $this->categories,
            $this->jobTitleAccess,
            $this->users,
            $db ?? $this->createMock(BaseConnection::class),
        );
    }

    private function data(array $overrides = []): array
    {
        return $overrides + [
            'parent_id'     => null,
            'name'          => 'Payroll',
            'description'   => '',
            'sla_policy_id' => '',
            'is_active'     => '1',
            'job_titles'    => [],
        ];
    }

    public function testCreateTopLevelStoresJobTitleRules(): void
    {
        $this->categories->expects($this->once())->method('insert')->willReturn(self::CATEGORY_ID);
        $this->jobTitleAccess->expects($this->once())
            ->method('replaceFor')
            ->with(self::CATEGORY_ID, ['Payroll Staff']);

        $this->service()->create($this->data(['job_titles' => ['Payroll Staff']]));
    }

    public function testSubCategoryCannotCarryJobTitles(): void
    {
        $this->categories->method('find')->willReturn(['id' => self::PARENT_ID, 'parent_id' => null]);
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['parent_id' => self::PARENT_ID, 'job_titles' => ['Payroll Staff']]));
    }

    public function testSubOfSubIsRejected(): void
    {
        $this->categories->method('find')->willReturn(['id' => self::PARENT_ID, 'parent_id' => 'another-parent']);
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['parent_id' => self::PARENT_ID]));
    }

    public function testDuplicateNameUnderSameParentIsRejected(): void
    {
        $this->categories->method('nameExists')->willReturn(true);
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data());
    }

    public function testUnknownJobTitleIsRejected(): void
    {
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['job_titles' => ['Tukang Sulap']]));
    }

    public function testJobTitleWithEdgeWhitespaceInUserDataCanBeSelected(): void
    {
        $users = $this->createMock(UserModel::class);
        $users->method('filterOptions')->willReturn(['job_title' => ['Payroll Staff ', 'HC Officer']]);
        $service = new RequestCategoryService(
            $this->categories,
            $this->jobTitleAccess,
            $users,
            $this->createMock(BaseConnection::class),
        );
        $this->categories->expects($this->once())->method('insert')->willReturn(self::CATEGORY_ID);
        $this->jobTitleAccess->expects($this->once())->method('replaceFor')->with(self::CATEGORY_ID, ['Payroll Staff']);

        $service->create($this->data(['job_titles' => ['Payroll Staff']]));

        $this->assertContains('Payroll Staff', $service->jobTitleOptions());
        $this->assertNotContains('Payroll Staff ', $service->jobTitleOptions());
    }

    public function testUpdateExcludesOwnIdWhenCheckingNameUniqueness(): void
    {
        $this->categories->expects($this->once())
            ->method('nameExists')
            ->with('Payroll', null, self::CATEGORY_ID)
            ->willReturn(false);
        $this->categories->method('update')->willReturn(true);

        $this->service()->update(['id' => self::CATEGORY_ID, 'parent_id' => null], $this->data());
    }

    public function testCategoryCannotBeItsOwnParent(): void
    {
        $this->categories->method('find')->willReturn(['id' => self::CATEGORY_ID, 'parent_id' => null]);
        $this->categories->expects($this->never())->method('update');
        $this->expectException(BusinessException::class);

        $this->service()->update(
            ['id' => self::CATEGORY_ID, 'parent_id' => null],
            $this->data(['parent_id' => self::CATEGORY_ID]),
        );
    }

    public function testTopLevelCategoryWithSubCategoriesCannotBecomeASub(): void
    {
        $this->categories->method('find')->willReturn(['id' => self::PARENT_ID, 'parent_id' => null]);
        $this->categories->method('hasChildren')->willReturn(true);
        $this->categories->expects($this->never())->method('update');
        $this->jobTitleAccess->expects($this->never())->method('replaceFor');
        $this->expectException(BusinessException::class);

        $this->service()->update(
            ['id' => self::CATEGORY_ID, 'parent_id' => null],
            $this->data(['parent_id' => self::PARENT_ID]),
        );
    }

    public function testTransactionRollsBackWhenSavingFails(): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->once())->method('transBegin');
        $db->expects($this->once())->method('transRollback');
        $db->expects($this->never())->method('transCommit');
        $this->categories->method('update')->willReturn(true);
        $this->jobTitleAccess->method('replaceFor')->willThrowException(new RuntimeException('db down'));
        $this->expectException(RuntimeException::class);

        $this->service($db)->update(['id' => self::CATEGORY_ID, 'parent_id' => null], $this->data());
    }

    public function testTransactionRollsBackWhenCategoryCannotBeSaved(): void
    {
        $db = $this->createMock(BaseConnection::class);
        $db->expects($this->once())->method('transRollback');
        $db->expects($this->never())->method('transCommit');
        $this->categories->method('update')->willReturn(false);
        $this->categories->method('errors')->willReturn(['name' => 'gagal']);
        $this->jobTitleAccess->expects($this->never())->method('replaceFor');
        $this->expectException(BusinessException::class);

        $this->service($db)->update(['id' => self::CATEGORY_ID, 'parent_id' => null], $this->data());
    }

    public function testDeleteRefusedWhenCategoryHasSubCategories(): void
    {
        $this->categories->method('hasChildren')->willReturn(true);
        $this->categories->expects($this->never())->method('delete');
        $this->expectException(BusinessException::class);

        $this->service()->delete(['id' => self::CATEGORY_ID, 'parent_id' => null]);
    }

    public function testJobTitleOptionsMergeSavedTitles(): void
    {
        $options = $this->service()->jobTitleOptions(['Lama', 'HC Officer']);

        $this->assertSame(['HC Officer', 'Lama', 'Payroll Staff'], $options);
    }

    public function testPaginateReturnsJobTitlesForTheVisibleCategories(): void
    {
        $this->categories->method('paginateForAdmin')->willReturn([
            ['id' => self::CATEGORY_ID, 'name' => 'Payroll'],
            ['id' => self::PARENT_ID, 'name' => 'Benefit'],
        ]);
        $this->jobTitleAccess->expects($this->once())
            ->method('jobTitlesByCategory')
            ->with([self::CATEGORY_ID, self::PARENT_ID])
            ->willReturn([self::CATEGORY_ID => ['Payroll Staff']]);

        $result = $this->service()->paginate(null);

        $this->assertCount(2, $result['categories']);
        $this->assertSame([self::CATEGORY_ID => ['Payroll Staff']], $result['jobTitles']);
        $this->assertArrayHasKey('pager', $result);
    }
}
