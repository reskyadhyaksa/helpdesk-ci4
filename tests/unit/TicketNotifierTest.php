<?php

use App\Entities\TicketStatus;
use App\Services\InAppNotificationService;
use App\Services\NotificationPusher;
use App\Services\NotificationService;
use App\Services\TicketNotifier;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TicketNotifierTest extends CIUnitTestCase
{
    private NotificationService $outbox;
    private InAppNotificationService $inApp;
    private NotificationPusher $pusher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outbox = $this->createMock(NotificationService::class);
        $this->inApp  = $this->createMock(InAppNotificationService::class);
        $this->pusher = $this->createMock(NotificationPusher::class);
    }

    private function notifier(): TicketNotifier
    {
        return new TicketNotifier($this->outbox, $this->inApp, $this->pusher);
    }

    public function testStatusChangeCollectsOutboxIdsAndBellRows(): void
    {
        $this->outbox->method('forStatusChange')->willReturn(['o1']);
        $this->inApp->method('forStatusChange')->willReturn([['id' => 'n1', 'user_id' => 'u1']]);

        $pending = $this->notifier()->forStatusChange(['id' => 't'], TicketStatus::Open, TicketStatus::InProgress, 'a');

        $this->assertSame(['outbox' => ['o1'], 'inApp' => [['id' => 'n1', 'user_id' => 'u1']]], $pending);
    }

    public function testAssignmentCollectsBothChannels(): void
    {
        $this->outbox->method('forAssignment')->willReturn(['o2']);
        $this->inApp->method('forAssignment')->willReturn([['id' => 'n2', 'user_id' => 'u2']]);

        $pending = $this->notifier()->forAssignment(['id' => 't'], 'assignee', 'u2', 'a');

        $this->assertSame(['o2'], $pending['outbox']);
        $this->assertSame('n2', $pending['inApp'][0]['id']);
    }

    public function testCommentsOnlyProduceBellNotifications(): void
    {
        $this->outbox->expects($this->never())->method($this->anything());
        $this->inApp->method('forComment')->willReturn([['id' => 'n3', 'user_id' => 'u3']]);

        $pending = $this->notifier()->forComment(['id' => 't'], 'u1', 'AGENT', 'PUBLIC');

        $this->assertSame([], $pending['outbox']);
        $this->assertCount(1, $pending['inApp']);
    }

    public function testDispatchPublishesTheOutboxAndPushesTheBellRows(): void
    {
        $this->outbox->expects($this->once())->method('publish')->with(['o1']);
        $this->pusher->expects($this->once())->method('push')->with([['id' => 'n1', 'user_id' => 'u1']]);

        $this->notifier()->dispatch(['outbox' => ['o1'], 'inApp' => [['id' => 'n1', 'user_id' => 'u1']]]);
    }

    public function testAPushFailureNeverBreaksTheCaller(): void
    {
        $this->pusher->method('push')->willThrowException(new RuntimeException('ws mati'));
        $this->outbox->expects($this->once())->method('publish');

        $this->notifier()->dispatch(['outbox' => ['o1'], 'inApp' => [['id' => 'n1', 'user_id' => 'u1']]]);

        $this->addToAssertionCount(1);
    }
}
