<?php

use App\Exceptions\BusinessException;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\UserModel;
use App\Services\RoleService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class RoleServiceTest extends CIUnitTestCase
{
    private const ROLE_ID = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b';

    private RoleModel $roles;
    private PermissionModel $permissions;
    private RolePermissionModel $rolePermissions;
    private UserModel $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roles           = $this->createMock(RoleModel::class);
        $this->permissions     = $this->createMock(PermissionModel::class);
        $this->rolePermissions = $this->createMock(RolePermissionModel::class);
        $this->users           = $this->createMock(UserModel::class);
    }

    private function service(): RoleService
    {
        return new RoleService(
            $this->roles,
            $this->permissions,
            $this->rolePermissions,
            $this->users,
            $this->createMock(BaseConnection::class),
        );
    }

    private function role(string $slug): array
    {
        return ['id' => self::ROLE_ID, 'slug' => $slug, 'name' => ucfirst($slug)];
    }

    public function testSystemRoleCannotBeDeleted(): void
    {
        $this->roles->expects($this->never())->method('delete');
        $this->expectException(BusinessException::class);

        $this->service()->delete($this->role(RoleModel::SLUG_USER));
    }

    public function testRoleWithUsersCannotBeDeleted(): void
    {
        $this->users->method('countByRole')->willReturn(3);
        $this->roles->expects($this->never())->method('delete');
        $this->expectException(BusinessException::class);

        $this->service()->delete($this->role('hc-officer'));
    }

    public function testUnusedCustomRoleCanBeDeleted(): void
    {
        $this->users->method('countByRole')->willReturn(0);
        $this->roles->expects($this->once())->method('delete')->with(self::ROLE_ID);

        $this->service()->delete($this->role('hc-officer'));
    }

    public function testCreateGeneratesSlugFromName(): void
    {
        $this->roles->method('slugExists')->willReturn(false);
        $this->roles->expects($this->once())
            ->method('insert')
            ->with(['slug' => 'hcis-officer', 'name' => 'HCIS Officer'], true)
            ->willReturn(self::ROLE_ID);

        $this->service()->create(['name' => 'HCIS Officer', 'permissions' => []]);
    }

    public function testCreateAddsSuffixWhenSlugTaken(): void
    {
        // "admin" sudah ada (role sistem) → role baru bernama "Admin" mendapat "admin-2"
        $this->roles->method('slugExists')->willReturnMap([['admin', true], ['admin-2', false]]);
        $this->roles->expects($this->once())
            ->method('insert')
            ->with(['slug' => 'admin-2', 'name' => 'Admin'], true)
            ->willReturn(self::ROLE_ID);

        $this->service()->create(['name' => 'Admin', 'permissions' => []]);
    }

    public function testUpdateKeepsSlug(): void
    {
        $this->permissions->method('idsBySlugs')->willReturn([]);
        $this->roles->expects($this->once())
            ->method('update')
            ->with(self::ROLE_ID, ['name' => 'HCIS Senior'])
            ->willReturn(true);

        $this->service()->update($this->role('hcis-officer'), ['name' => 'HCIS Senior', 'permissions' => []]);
    }

    public function testUnknownPermissionIsRejected(): void
    {
        $this->roles->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->service()->create(['name' => 'HC Officer', 'permissions' => ['admin.everything']]);
    }

    public function testAdminRoleAlwaysKeepsLockedPermissions(): void
    {
        $this->permissions->expects($this->once())
            ->method('idsBySlugs')
            ->with($this->callback(static fn (array $slugs) => in_array(PermissionModel::ADMIN_ACCESS, $slugs, true)
                && in_array(PermissionModel::ADMIN_ROLES_VIEW, $slugs, true)
                && in_array(PermissionModel::ADMIN_ROLES_UPDATE, $slugs, true)))
            ->willReturn([]);
        $this->roles->method('update')->willReturn(true);

        // Admin mencoba melepas semua permission
        $this->service()->update($this->role(RoleModel::SLUG_ADMIN), ['name' => 'Admin', 'permissions' => []]);
    }
}
