<?php

use App\Entities\User;
use App\Exceptions\BusinessException;
use App\Models\RequestCategoryAccessJobTitleModel;
use App\Models\RequestCategoryAccessRoleModel;
use App\Models\RequestCategoryModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Services\CategoryAccessService;
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

    private const ROLE_ID = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0d';
    private const USER_ID = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0e';

    private RequestCategoryModel $categories;
    private RequestCategoryAccessJobTitleModel $jobTitleAccess;
    private RequestCategoryAccessRoleModel $roleAccess;
    private UserModel $users;
    private RoleModel $roles;
    private CategoryAccessService $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categories     = $this->createMock(RequestCategoryModel::class);
        $this->jobTitleAccess = $this->createMock(RequestCategoryAccessJobTitleModel::class);
        $this->roleAccess     = $this->createMock(RequestCategoryAccessRoleModel::class);
        $this->users          = $this->createMock(UserModel::class);
        $this->users->method('filterOptions')->willReturn(['job_title' => ['Payroll Staff', 'HC Officer']]);
        $this->roles  = $this->createMock(RoleModel::class);
        $this->access = $this->createMock(CategoryAccessService::class);
    }

    private function service(?BaseConnection $db = null, ?UserModel $users = null): RequestCategoryService
    {
        return new RequestCategoryService(
            $this->categories,
            $this->jobTitleAccess,
            $this->roleAccess,
            $users ?? $this->users,
            $this->roles,
            $this->access,
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
            'role_ids'      => [],
        ];
    }

    public function testCreateTopLevelStoresJobTitleRules(): void
    {
        $this->categories->expects($this->once())->method('insert')->willReturn(self::CATEGORY_ID);
        $this->jobTitleAccess->expects($this->once())
            ->method('replaceFor')
            ->with(self::CATEGORY_ID, ['Payroll Staff']);

        $this->service()->create($this->data(['job_titles' => ['Payroll Staff']]), 'admin-1', false);
    }

    public function testSubCategoryCannotCarryJobTitles(): void
    {
        $this->categories->method('find')->willReturn(['id' => self::PARENT_ID, 'parent_id' => null]);
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['parent_id' => self::PARENT_ID, 'job_titles' => ['Payroll Staff']]), 'admin-1', false);
    }

    public function testSubOfSubIsRejected(): void
    {
        $this->categories->method('find')->willReturn(['id' => self::PARENT_ID, 'parent_id' => 'another-parent']);
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['parent_id' => self::PARENT_ID]), 'admin-1', false);
    }

    public function testDuplicateNameUnderSameParentIsRejected(): void
    {
        $this->categories->method('nameExists')->willReturn(true);
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(), 'admin-1', false);
    }

    public function testUnknownJobTitleIsRejected(): void
    {
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['job_titles' => ['Tukang Sulap']]), 'admin-1', false);
    }

    public function testJobTitleWithEdgeWhitespaceInUserDataCanBeSelected(): void
    {
        $users = $this->createMock(UserModel::class);
        $users->method('filterOptions')->willReturn(['job_title' => ['Payroll Staff ', 'HC Officer']]);
        $service = $this->service(null, $users);
        $this->categories->expects($this->once())->method('insert')->willReturn(self::CATEGORY_ID);
        $this->jobTitleAccess->expects($this->once())->method('replaceFor')->with(self::CATEGORY_ID, ['Payroll Staff']);

        $service->create($this->data(['job_titles' => ['Payroll Staff']]), 'admin-1', false);

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

        $this->service()->update(['id' => self::CATEGORY_ID, 'parent_id' => null], $this->data(), 'admin-1', false);
    }

    public function testCategoryCannotBeItsOwnParent(): void
    {
        $this->categories->method('find')->willReturn(['id' => self::CATEGORY_ID, 'parent_id' => null]);
        $this->categories->expects($this->never())->method('update');
        $this->expectException(BusinessException::class);

        $this->service()->update(
            ['id' => self::CATEGORY_ID, 'parent_id' => null],
            $this->data(['parent_id' => self::CATEGORY_ID]),
            'admin-1',
            false,
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
            'admin-1',
            false,
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

        $this->service($db)->update(['id' => self::CATEGORY_ID, 'parent_id' => null], $this->data(), 'admin-1', false);
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

        $this->service($db)->update(['id' => self::CATEGORY_ID, 'parent_id' => null], $this->data(), 'admin-1', false);
    }

    public function testDeleteRefusedWhenCategoryHasSubCategories(): void
    {
        $this->categories->method('hasChildren')->willReturn(true);
        $this->categories->expects($this->never())->method('delete');
        $this->expectException(BusinessException::class);

        $this->service()->delete(['id' => self::CATEGORY_ID, 'parent_id' => null]);
    }

    public function testDeleteRefusedWhenCategoryHasTickets(): void
    {
        $this->categories->method('hasChildren')->willReturn(false);
        $this->categories->method('hasTickets')->willReturn(true);
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

    public function testPaginateReturnsRoleNamesForTheVisibleCategories(): void
    {
        $this->categories->method('paginateForAdmin')->willReturn([['id' => self::CATEGORY_ID, 'name' => 'Payroll']]);
        $this->roleAccess->expects($this->once())
            ->method('roleNamesByCategory')
            ->with([self::CATEGORY_ID])
            ->willReturn([self::CATEGORY_ID => ['HCBP Staff']]);

        $result = $this->service()->paginate(null);

        $this->assertSame([self::CATEGORY_ID => ['HCBP Staff']], $result['roles']);
    }

    public function testCreateStoresRoleRules(): void
    {
        $this->roles->method('find')->willReturn([['id' => self::ROLE_ID, 'name' => 'HCBP Staff']]);
        $this->categories->method('insert')->willReturn(self::CATEGORY_ID);
        $this->roleAccess->expects($this->once())->method('replaceFor')->with(self::CATEGORY_ID, [self::ROLE_ID]);

        $this->service()->create($this->data(['role_ids' => [self::ROLE_ID]]), 'admin-1', false);
    }

    public function testSubCategoryCannotCarryRoles(): void
    {
        $this->categories->method('find')->willReturn(['id' => self::PARENT_ID, 'parent_id' => null]);
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['parent_id' => self::PARENT_ID, 'role_ids' => [self::ROLE_ID]]), 'admin-1', false);
    }

    public function testUnknownRoleIsRejected(): void
    {
        $this->roles->method('find')->willReturn([]);
        $this->categories->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['role_ids' => [self::ROLE_ID]]), 'admin-1', false);
    }

    public function testCreateSyncsUserExceptionsWhenTheActorMayManageThem(): void
    {
        $this->users->method('find')->willReturn([new User(['id' => self::USER_ID])]);
        $this->categories->method('insert')->willReturn(self::CATEGORY_ID);
        $this->access->expects($this->once())
            ->method('syncUserExceptionsForCategory')
            ->with(self::CATEGORY_ID, [self::USER_ID => 'deny'], 'admin-1');

        $this->service()->create($this->data(['user_access' => [self::USER_ID => 'deny']]), 'admin-1', true);
    }

    public function testEmptySubmittedUserAccessClearsExceptionsWhenManaged(): void
    {
        $this->categories->method('update')->willReturn(true);
        $this->access->expects($this->once())
            ->method('syncUserExceptionsForCategory')
            ->with(self::CATEGORY_ID, [], 'admin-1');

        $this->service()->update(['id' => self::CATEGORY_ID, 'parent_id' => null], $this->data(), 'admin-1', true);
    }

    public function testUserExceptionsAreUntouchedWhenTheActorMayNotManageThem(): void
    {
        $this->categories->method('update')->willReturn(true);
        $this->access->expects($this->never())->method('syncUserExceptionsForCategory');

        $this->service()->update(
            ['id' => self::CATEGORY_ID, 'parent_id' => null],
            $this->data(['user_access' => [self::USER_ID => 'allow']]),
            'admin-1',
            false,
        );
    }

    public function testUnknownUserInExceptionsIsRejected(): void
    {
        $this->users->method('find')->willReturn([]);
        $this->categories->expects($this->never())->method('insert');
        $this->access->expects($this->never())->method('syncUserExceptionsForCategory');
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['user_access' => [self::USER_ID => 'allow']]), 'admin-1', true);
    }

    public function testAccessEntriesFromTheDatabaseCombineAllThreeTypes(): void
    {
        $this->jobTitleAccess->method('jobTitlesFor')->with(self::CATEGORY_ID)->willReturn(['Payroll Staff']);
        $this->roleAccess->method('roleIdsFor')->with(self::CATEGORY_ID)->willReturn([self::ROLE_ID]);
        $this->roles->method('find')->willReturn([['id' => self::ROLE_ID, 'name' => 'HCBP Staff']]);
        $this->access->method('userExceptionsForCategory')->with(self::CATEGORY_ID)->willReturn([
            ['user_id' => self::USER_ID, 'effect' => 'deny', 'name' => 'Sari', 'employee_no' => '123'],
        ]);

        $this->assertSame([
            ['type' => 'job_title', 'value' => 'Payroll Staff', 'label' => 'Payroll Staff', 'detail' => null, 'effect' => null],
            ['type' => 'role', 'value' => self::ROLE_ID, 'label' => 'HCBP Staff', 'detail' => null, 'effect' => null],
            ['type' => 'user', 'value' => self::USER_ID, 'label' => 'Sari', 'detail' => '123', 'effect' => 'deny'],
        ], $this->service()->accessEntries(self::CATEGORY_ID));
    }

    public function testAccessEntriesFromSubmittedInputKeepTheSubmittedState(): void
    {
        $this->jobTitleAccess->expects($this->never())->method('jobTitlesFor');
        $this->roles->method('find')->willReturn([['id' => self::ROLE_ID, 'name' => 'HCBP Staff']]);
        $this->users->method('find')->willReturn([new User(['id' => self::USER_ID, 'name' => 'Budi', 'employee_no' => '9'])]);

        $entries = $this->service()->accessEntries(self::CATEGORY_ID, [
            'job_titles' => [],
            'role_ids'   => [self::ROLE_ID],
            'user_access' => [self::USER_ID => 'allow'],
        ]);

        $this->assertSame([
            ['type' => 'role', 'value' => self::ROLE_ID, 'label' => 'HCBP Staff', 'detail' => null, 'effect' => null],
            ['type' => 'user', 'value' => self::USER_ID, 'label' => 'Budi', 'detail' => '9', 'effect' => 'allow'],
        ], $entries);
    }
}
