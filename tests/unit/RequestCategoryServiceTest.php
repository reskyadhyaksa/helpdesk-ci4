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

    private function service(): RequestCategoryService
    {
        return new RequestCategoryService(
            $this->categories,
            $this->jobTitleAccess,
            $this->users,
            $this->createMock(BaseConnection::class),
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
