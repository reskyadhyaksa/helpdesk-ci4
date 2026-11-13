<?php

namespace Config;

use App\Models\HolidayModel;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\SlaPolicyModel;
use App\Models\UserModel;
use App\Models\WeeklyOffDayModel;
use App\Services\Auth\KeycloakAuthService;
use App\Services\AuthService;
use App\Services\HolidayService;
use App\Services\RoleService;
use App\Services\SlaPolicyService;
use App\Services\UserService;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function auth(bool $getShared = true): AuthService
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new AuthService(
            new KeycloakAuthService(),
            model(UserModel::class),
            model(RoleModel::class),
            model(PermissionModel::class),
        );
    }

    public static function slaPolicies(bool $getShared = true): SlaPolicyService
    {
        if ($getShared) {
            return static::getSharedInstance('slaPolicies');
        }

        return new SlaPolicyService(model(SlaPolicyModel::class));
    }

    public static function holidays(bool $getShared = true): HolidayService
    {
        if ($getShared) {
            return static::getSharedInstance('holidays');
        }

        return new HolidayService(model(HolidayModel::class), model(WeeklyOffDayModel::class), new KeycloakAuthService());
    }

    public static function users(bool $getShared = true): UserService
    {
        if ($getShared) {
            return static::getSharedInstance('users');
        }

        return new UserService(model(UserModel::class), model(RoleModel::class));
    }

    public static function roles(bool $getShared = true): RoleService
    {
        if ($getShared) {
            return static::getSharedInstance('roles');
        }

        return new RoleService(
            model(RoleModel::class),
            model(PermissionModel::class),
            model(RolePermissionModel::class),
            model(UserModel::class),
            db_connect(),
        );
    }
}
