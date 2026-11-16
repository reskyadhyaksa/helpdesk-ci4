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
use App\Models\NotificationOutboxModel;
use App\Models\SystemParameterModel;
use App\Models\UserNotificationModel;
use App\Models\TicketActivityModel;
use App\Models\TicketAttachmentModel;
use App\Models\TicketCommentModel;
use App\Models\TicketModel;
use App\Models\UserModel;
use App\Models\WeeklyOffDayModel;
use App\Services\AttachmentRetentionService;
use App\Services\AttachmentService;
use App\Services\Auth\KeycloakAuthService;
use App\Services\InAppNotificationService;
use App\Services\MessageQueue;
use App\Services\NotificationPusher;
use App\Services\NotificationRetentionService;
use App\Services\SlaAlertService;
use App\Services\TicketNotifier;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use App\Services\AuthService;
use App\Services\CategoryAccessService;
use App\Services\HolidayService;
use App\Services\RequestCategoryService;
use App\Services\RoleService;
use App\Services\SlaCalculator;
use App\Services\SlaPolicyService;
use App\Services\SystemParameterService;
use App\Services\TicketCommentService;
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
            static::attachments(),
            static::ticketNotifier(),
            db_connect(),
        );
    }

    public static function ticketNotifier(bool $getShared = true): TicketNotifier
    {
        if ($getShared) {
            return static::getSharedInstance('ticketNotifier');
        }

        return new TicketNotifier(static::notifications(), static::inAppNotifications(), static::notificationPusher());
    }

    public static function inAppNotifications(bool $getShared = true): InAppNotificationService
    {
        if ($getShared) {
            return static::getSharedInstance('inAppNotifications');
        }

        return new InAppNotificationService(model(UserNotificationModel::class), model(UserModel::class));
    }

    public static function systemParameters(bool $getShared = true): SystemParameterService
    {
        if ($getShared) {
            return static::getSharedInstance('systemParameters');
        }

        return new SystemParameterService(model(SystemParameterModel::class));
    }

    public static function attachmentRetention(bool $getShared = true): AttachmentRetentionService
    {
        if ($getShared) {
            return static::getSharedInstance('attachmentRetention');
        }

        return new AttachmentRetentionService(model(TicketAttachmentModel::class), static::attachments(), model(SystemParameterModel::class));
    }

    public static function notificationRetention(bool $getShared = true): NotificationRetentionService
    {
        if ($getShared) {
            return static::getSharedInstance('notificationRetention');
        }

        return new NotificationRetentionService(model(UserNotificationModel::class), model(SystemParameterModel::class));
    }

    public static function slaAlerts(bool $getShared = true): SlaAlertService
    {
        if ($getShared) {
            return static::getSharedInstance('slaAlerts');
        }

        return new SlaAlertService(model(TicketModel::class), static::inAppNotifications(), static::notificationPusher(), model(SystemParameterModel::class));
    }

    public static function notificationPusher(bool $getShared = true): NotificationPusher
    {
        if ($getShared) {
            return static::getSharedInstance('notificationPusher');
        }

        return new NotificationPusher();
    }

    public static function messageQueue(bool $getShared = true): MessageQueue
    {
        if ($getShared) {
            return static::getSharedInstance('messageQueue');
        }

        return new MessageQueue();
    }

    public static function notificationDispatcher(bool $getShared = true): NotificationDispatcher
    {
        if ($getShared) {
            return static::getSharedInstance('notificationDispatcher');
        }

        return new NotificationDispatcher(model(NotificationOutboxModel::class), static::messageQueue(), static::email(), static::whatsapp());
    }

    public static function notifications(bool $getShared = true): NotificationService
    {
        if ($getShared) {
            return static::getSharedInstance('notifications');
        }

        return new NotificationService(model(NotificationOutboxModel::class), static::messageQueue(), model(UserModel::class), model(SystemParameterModel::class));
    }

    public static function attachments(bool $getShared = true): AttachmentService
    {
        if ($getShared) {
            return static::getSharedInstance('attachments');
        }

        return new AttachmentService(model(TicketAttachmentModel::class), model(SystemParameterModel::class));
    }

    public static function ticketComments(bool $getShared = true): TicketCommentService
    {
        if ($getShared) {
            return static::getSharedInstance('ticketComments');
        }

        return new TicketCommentService(
            static::tickets(),
            model(TicketCommentModel::class),
            static::attachments(),
            static::ticketNotifier(),
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
