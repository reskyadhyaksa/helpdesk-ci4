<?php

use App\Models\SystemParameterModel;
use App\Models\UserNotificationModel;
use App\Services\NotificationRetentionService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationRetentionServiceTest extends CIUnitTestCase
{
    private UserNotificationModel $notifications;
    private SystemParameterModel $parameters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notifications = $this->createMock(UserNotificationModel::class);
        $this->parameters    = $this->createMock(SystemParameterModel::class);
    }

    private function service(int $days = 3): NotificationRetentionService
    {
        $this->parameters->method('int')->with(SystemParameterModel::NOTIFICATION_RETENTION_DAYS)->willReturn($days);

        return new NotificationRetentionService($this->notifications, $this->parameters);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-30 12:00:00');
    }

    public function testDefaultRetentionIsThreeDays(): void
    {
        $this->assertSame('3', SystemParameterModel::DEFAULTS[SystemParameterModel::NOTIFICATION_RETENTION_DAYS]['value']);
    }

    public function testOnlyNotificationsReadBeforeTheCutoffAreDeleted(): void
    {
        $this->notifications->expects($this->once())
            ->method('deleteReadBefore')
            ->with('2026-11-27 12:00:00')
            ->willReturn(7);

        $this->assertSame(7, $this->service(3)->purge($this->now()));
    }

    public function testTheCutoffFollowsTheConfiguredNumberOfDays(): void
    {
        $this->notifications->expects($this->once())->method('deleteReadBefore')->with('2026-11-23 12:00:00')->willReturn(0);

        $this->service(7)->purge($this->now());
    }

    public function testRetentionOfZeroDaysDisablesThePurgeAndNeverDeletes(): void
    {
        $this->notifications->expects($this->never())->method('deleteReadBefore');

        $this->assertSame(0, $this->service(0)->purge($this->now()));
    }

    public function testANegativeRetentionAlsoDisablesThePurge(): void
    {
        $this->notifications->expects($this->never())->method('deleteReadBefore');

        $this->assertSame(0, $this->service(-1)->purge($this->now()));
    }
}
