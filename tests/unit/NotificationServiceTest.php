<?php

use App\Entities\TicketStatus;
use App\Entities\User;
use App\Models\NotificationOutboxModel;
use App\Models\SystemParameterModel;
use App\Models\UserModel;
use App\Services\MessageQueue;
use App\Services\NotificationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationServiceTest extends CIUnitTestCase
{
    private NotificationOutboxModel $outbox;
    private MessageQueue $queue;
    private UserModel $users;
    private SystemParameterModel $parameters;

    /** @var list<array> */
    private array $rows = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->outbox = $this->createMock(NotificationOutboxModel::class);
        $this->queue  = $this->createMock(MessageQueue::class);
        $this->users  = $this->createMock(UserModel::class);
        $this->parameters = $this->createMock(SystemParameterModel::class);
        $this->parameters->method('flag')->willReturn(true);

        $this->outbox->method('insert')->willReturnCallback(function (array $row) {
            $this->rows[] = $row;

            return 'o' . count($this->rows);
        });
    }

    private function service(): NotificationService
    {
        return new NotificationService($this->outbox, $this->queue, $this->users, $this->parameters);
    }

    private function ticket(array $overrides = []): array
    {
        return $overrides + [
            'id' => '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b', 'ticket_number' => 'TKT-261115-AAAAAA', 'subject' => 'Akun terkunci',
            'requester_id' => 'u2', 'requester_name' => 'Sari', 'requester_email' => 'sari@x.id', 'requester_phone' => '081234567890', 'is_requester_verified' => 1,
        ];
    }

    public function testStatusChangeQueuesEmailAndWhatsAppForTheRequester(): void
    {
        $ids = $this->service()->forStatusChange($this->ticket(), TicketStatus::Open, TicketStatus::InProgress, 'agent1');

        $this->assertSame(['o1', 'o2'], $ids);
        $this->assertSame('EMAIL', $this->rows[0]['channel']);
        $this->assertSame('sari@x.id', $this->rows[0]['recipient']);
        $this->assertSame('WHATSAPP', $this->rows[1]['channel']);
        $this->assertSame('081234567890', $this->rows[1]['recipient']);

        $payload = json_decode($this->rows[0]['payload'], true);
        $this->assertStringContainsString('TKT-261115-AAAAAA', $payload['subject']);
        $this->assertStringContainsString('Sedang Diproses', $payload['body']);
        $this->assertSame('status_changed', $this->rows[0]['event_type']);
        $this->assertSame('PENDING', $this->rows[0]['status']);
    }

    public function testStatusChangeDoesNotNotifyTheRequesterAboutTheirOwnAction(): void
    {
        $this->outbox->expects($this->never())->method('insert');

        $this->assertSame([], $this->service()->forStatusChange($this->ticket(), TicketStatus::Resolved, TicketStatus::Reopened, 'u2'));
    }

    public function testStatusChangeFallsBackToTheAccountEmailWhenTheTicketHasNoContact(): void
    {
        $this->users->method('find')->with('u2')->willReturn(new User(['id' => 'u2', 'email' => 'akun@x.id']));

        $this->service()->forStatusChange(
            $this->ticket(['requester_email' => null, 'requester_phone' => null]),
            TicketStatus::Open,
            TicketStatus::InProgress,
            'agent1',
        );

        $this->assertCount(1, $this->rows);
        $this->assertSame('akun@x.id', $this->rows[0]['recipient']);
    }

    public function testStatusChangeWithoutAnyContactQueuesNothing(): void
    {
        $this->outbox->expects($this->never())->method('insert');

        $ids = $this->service()->forStatusChange(
            $this->ticket(['requester_id' => null, 'requester_email' => null, 'requester_phone' => null]),
            TicketStatus::Open,
            TicketStatus::InProgress,
            'agent1',
        );

        $this->assertSame([], $ids);
    }

    public function testAssignmentQueuesAnEmailForTheNewPicWithTheRoleLabel(): void
    {
        $this->users->method('find')->with('p1')->willReturn(new User(['id' => 'p1', 'name' => 'Siti', 'email' => 'siti@x.id']));

        $ids = $this->service()->forAssignment($this->ticket(), 'resolver', 'p1', 'agent1');

        $this->assertSame(['o1'], $ids);
        $this->assertSame('siti@x.id', $this->rows[0]['recipient']);
        $this->assertSame('ticket_assigned', $this->rows[0]['event_type']);
        $this->assertStringContainsString('PIC Resolver', json_decode($this->rows[0]['payload'], true)['body']);
    }

    public function testAssignmentDoesNotNotifyAPersonWhoAssignedThemselves(): void
    {
        $this->users->method('find')->willReturn(new User(['id' => 'p1', 'email' => 'siti@x.id']));
        $this->outbox->expects($this->never())->method('insert');

        $this->assertSame([], $this->service()->forAssignment($this->ticket(), 'assignee', 'p1', 'p1'));
    }

    public function testAssignmentToAPicWithoutEmailQueuesNothing(): void
    {
        $this->users->method('find')->willReturn(new User(['id' => 'p1', 'email' => null]));
        $this->outbox->expects($this->never())->method('insert');

        $this->assertSame([], $this->service()->forAssignment($this->ticket(), 'assignee', 'p1', 'agent1'));
    }

    public function testNotificationBodiesNeverContainInternalNotesOrOtherPeoplesContactData(): void
    {
        $this->service()->forStatusChange($this->ticket(), TicketStatus::Open, TicketStatus::InProgress, 'agent1');

        $body = json_decode($this->rows[0]['payload'], true)['body'];

        $this->assertStringNotContainsString('081234567890', $body);
        $this->assertStringNotContainsString('sari@x.id', $body);
    }

    public function testUnverifiedRequestersGetNoNotificationsBecauseTheirContactsAreSelfReported(): void
    {
        $this->outbox->expects($this->never())->method('insert');

        $ids = $this->service()->forStatusChange(
            $this->ticket(['is_requester_verified' => 0]),
            TicketStatus::Open,
            TicketStatus::InProgress,
            'agent1',
        );

        $this->assertSame([], $ids);
    }

    public function testPublishMarksOnlyTheSuccessfullyQueuedRowsAsQueued(): void
    {
        $this->queue->method('publish')->willReturnCallback(static function (array $payload): void {
            if ($payload['outbox_id'] === 'o2') {
                throw new RuntimeException('putus');
            }
        });
        $this->outbox->expects($this->once())->method('markQueued')->with(['o1']);

        $this->service()->publish(['o1', 'o2']);
    }

    public function testDisabledChannelsAreNotQueued(): void
    {
        $this->parameters = $this->createMock(SystemParameterModel::class);
        $this->parameters->method('flag')->willReturnMap([
            [SystemParameterModel::NOTIFY_EMAIL_ENABLED, false],
            [SystemParameterModel::NOTIFY_WHATSAPP_ENABLED, true],
        ]);

        $this->service()->forStatusChange($this->ticket(), TicketStatus::Open, TicketStatus::InProgress, 'agent1');

        $this->assertCount(1, $this->rows);
        $this->assertSame('WHATSAPP', $this->rows[0]['channel']);
    }

    public function testNothingIsQueuedWhenAllExternalChannelsAreOff(): void
    {
        $this->parameters = $this->createMock(SystemParameterModel::class);
        $this->parameters->method('flag')->willReturn(false);
        $this->outbox->expects($this->never())->method('insert');
        $this->users->method('find')->willReturn(new User(['id' => 'p1', 'name' => 'Siti', 'email' => 'siti@x.id']));

        $this->assertSame([], $this->service()->forStatusChange($this->ticket(), TicketStatus::Open, TicketStatus::InProgress, 'agent1'));
        $this->assertSame([], $this->service()->forAssignment($this->ticket(), 'assignee', 'p1', 'agent1'));
    }

    public function testPublishSendsOneMessagePerOutboxId(): void
    {
        $sent = [];
        $this->queue->method('publish')->willReturnCallback(static function (array $payload) use (&$sent): void {
            $sent[] = $payload;
        });

        $this->service()->publish(['o1', 'o2']);

        $this->assertSame([['outbox_id' => 'o1'], ['outbox_id' => 'o2']], $sent);
    }

    public function testPublishSwallowsQueueFailuresSoTheTicketChangeStillSucceeds(): void
    {
        $this->queue->method('publish')->willThrowException(new RuntimeException('rabbit mati'));

        $this->service()->publish(['o1']);

        $this->addToAssertionCount(1);
    }
}
