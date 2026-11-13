<?php

use App\Entities\User;
use App\Exceptions\BusinessException;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Services\UserService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class UserServiceTest extends CIUnitTestCase
{
    private const USER_ID  = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b';
    private const ADMIN_ID = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0c';
    private const ROLE_ID  = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0d';

    public function testAdminCannotChangeOwnRole(): void
    {
        $users = $this->createMock(UserModel::class);
        $users->expects($this->never())->method('update');
        $this->expectException(BusinessException::class);

        (new UserService($users, $this->createMock(RoleModel::class)))
            ->assignRole(new User(['id' => self::ADMIN_ID]), self::ROLE_ID, self::ADMIN_ID);
    }

    public function testUnknownRoleIsRejected(): void
    {
        $users = $this->createMock(UserModel::class);
        $users->expects($this->never())->method('update');
        $roles = $this->createMock(RoleModel::class);
        $roles->method('find')->willReturn(null);
        $this->expectException(BusinessException::class);

        (new UserService($users, $roles))->assignRole(new User(['id' => self::USER_ID]), self::ROLE_ID, self::ADMIN_ID);
    }

    public function testAssignRoleUpdatesUser(): void
    {
        $users = $this->createMock(UserModel::class);
        $users->expects($this->once())->method('update')->with(self::USER_ID, ['role_id' => self::ROLE_ID]);
        $roles = $this->createMock(RoleModel::class);
        $roles->method('find')->willReturn(['id' => self::ROLE_ID]);

        (new UserService($users, $roles))->assignRole(new User(['id' => self::USER_ID]), self::ROLE_ID, self::ADMIN_ID);
    }

    public function testInitials(): void
    {
        helper('format');

        $this->assertSame('FU', initials('Fuguh Budi Utomo'));
        $this->assertSame('—', format_datetime(null));
    }
}
