<?php

namespace Config;

use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Services\Auth\KeycloakAuthService;
use App\Services\AuthService;
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
}
