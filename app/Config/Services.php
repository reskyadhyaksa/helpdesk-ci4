<?php

namespace Config;

use App\Models\HolidayModel;
use App\Models\PermissionModel;
use App\Models\RequestCategoryAccessJobTitleModel;
use App\Models\RequestCategoryAccessRoleModel;
use App\Models\RequestCategoryAccessUserModel;
use App\Models\RequestCategoryModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\SlaPolicyModel;
use App\Models\TicketActivityModel;
use App\Models\TicketModel;
use App\Models\UserModel;
use App\Models\WeeklyOffDayModel;
use App\Services\Auth\KeycloakAuthService;
use App\Services\AuthService;
use App\Services\CategoryAccessService;
use App\Services\HolidayService;
use App\Services\RequestCategoryService;
use App\Services\RoleService;
use App\Services\SlaCalculator;
use App\Services\SlaPolicyService;
use App\Services\TicketService;
use App\Services\WhatsAppService;
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

    public static function requestCategories(bool $getShared = true): RequestCategoryService
    {
        if ($getShared) {
            return static::getSharedInstance('requestCategories');
        }

        return new RequestCategoryService(
            model(RequestCategoryModel::class),
            model(RequestCategoryAccessJobTitleModel::class),
            model(RequestCategoryAccessRoleModel::class),
            model(UserModel::class),
            model(RoleModel::class),
            static::categoryAccess(),
            db_connect(),
        );
    }

    public static function tickets(bool $getShared = true): TicketService
    {
        if ($getShared) {
            return static::getSharedInstance('tickets');
        }

        return new TicketService(
            model(TicketModel::class),
            model(TicketActivityModel::class),
            model(RequestCategoryModel::class),
            model(SlaPolicyModel::class),
            static::holidays(),
            new SlaCalculator(),
            static::categoryAccess(),
            model(UserModel::class),
            new KeycloakAuthService(),
            db_connect(),
        );
    }

    public static function whatsapp(bool $getShared = true): WhatsAppService
    {
        if ($getShared) {
            return static::getSharedInstance('whatsapp');
        }

        return new WhatsAppService(static::curlrequest(['timeout' => 10, 'http_errors' => false]), config(Integration::class));
    }

    public static function categoryAccess(bool $getShared = true): CategoryAccessService
    {
        if ($getShared) {
            return static::getSharedInstance('categoryAccess');
        }

        return new CategoryAccessService(
            model(RequestCategoryModel::class),
            model(RequestCategoryAccessJobTitleModel::class),
            model(RequestCategoryAccessRoleModel::class),
            model(RequestCategoryAccessUserModel::class),
        );
    }
}
