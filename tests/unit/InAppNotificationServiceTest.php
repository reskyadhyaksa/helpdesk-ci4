<?php

use App\Entities\TicketStatus;
use App\Entities\User;
use App\Models\UserModel;
use App\Models\UserNotificationModel;
use App\Services\InAppNotificationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class InAppNotificationServiceTest extends CIUnitTestCase
{
    private const TICKET_ID = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b';

    private UserNotificationModel $notifications;
    private UserModel $users;

    /** @var list<array> */
    private array $rows = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->notifications = $this->createMock(UserNotificationModel::class);
        $this->users         = $this->createMock(UserModel::class);

        $this->notifications->method('insert')->willReturnCallback(function (array $row) {
            $this->rows[] = $row;

            return 'n' . count($this->rows);
        });
    }

    private function service(): InAppNotificationService
    {
        return new InAppNotificationService($this->notifications, $this->users);
    }

    private function ticket(array $overrides = []): array
    {
        return $overrides + [
            'id' => self::TICKET_ID, 'ticket_number' => 'TKT-261115-AAAAAA', 'subject' => 'Akun terkunci',
            'requester_id' => 'req', 'created_by' => null, 'assignee_id' => 'pic1', 'resolver_id' => 'pic2',
            'requester_phone' => '081234567890', 'requester_email' => 'sari@x.id',
        ];
    }

    /** @return list<string> user_id yang menerima, terurut */
    private function recipients(): array
    {
        $ids = array_column($this->rows, 'user_id');
        sort($ids);

        return $ids;
    }

    public function testListForReturnsTheUnreadCountAndTheLatestItems(): void
    {
        $this->notifications->method('unreadCount')->with('u1')->willReturn(3);
        $this->notifications->method('latestFor')->with('u1', 20)->willReturn([['id' => 'n1']]);

        $this->assertSame(['unread' => 3, 'items' => [['id' => 'n1']]], $this->service()->listFor('u1'));
    }

    public function testUnreadCountDoesNotLoadTheNotificationList(): void
    {
        $this->notifications->method('unreadCount')->with('u1')->willReturn(4);
        $this->notifications->expects($this->never())->method('latestFor');

        $this->assertSame(4, $this->service()->unreadCount('u1'));
    }

    public function testMarkReadIsScopedToTheOwner(): void
    {
        $this->notifications->expects($this->once())->method('markRead')->with('n1', 'u1')->willReturn(true);

        $this->assertTrue($this->service()->markRead('n1', 'u1'));
    }

    public function testMarkAllReadIsScopedToTheOwner(): void
    {
        $this->notifications->expects($this->once())->method('markAllRead')->with('u1');

        $this->service()->markAllRead('u1');
    }

    public function testAssignmentNotifiesTheNewPicWithAnAdminLink(): void
    {
        $result = $this->service()->forAssignment($this->ticket(), 'resolver', 'pic2', 'agent1');

        $this->assertSame(['pic2'], $this->recipients());
        $this->assertSame('ticket_assigned', $this->rows[0]['event_type']);
        $this->assertStringContainsString('/admin/tickets/' . self::TICKET_ID, $this->rows[0]['url']);
        $this->assertStringContainsString('PIC Resolver', $this->rows[0]['body']);
        $this->assertSame('pic2', $result[0]['user_id']);
        $this->assertSame('n1', $result[0]['id']);
    }

    public function testAssignmentLinkIsAnAdminLinkEvenWhenTheTicketRowDoesNotListTheNewPicYet(): void
    {
        // Baris tiket dibaca sebelum PIC baru tersimpan, jadi assignee_id masih kosong
        $this->service()->forAssignment($this->ticket(['assignee_id' => null, 'resolver_id' => null]), 'assignee', 'newpic', 'agent1');

        $this->assertStringContainsString('/admin/tickets/', $this->rows[0]['url']);
    }

    public function testAssignmentDoesNotNotifyAPersonWhoAssignedThemselves(): void
    {
        $this->notifications->expects($this->never())->method('insert');

        $this->assertSame([], $this->service()->forAssignment($this->ticket(), 'assignee', 'agent1', 'agent1'));
    }

    public function testStatusChangeNotifiesRequesterAndBothPicsExceptTheActor(): void
    {
        $this->service()->forStatusChange($this->ticket(), TicketStatus::Open, TicketStatus::InProgress, 'pic1');

        $this->assertSame(['pic2', 'req'], $this->recipients());
    }

    public function testRequesterGetsAPortalLinkAndPicsGetAnAdminLink(): void
    {
        $this->service()->forStatusChange($this->ticket(), TicketStatus::Open, TicketStatus::InProgress, 'agent1');

        $byUser = array_column($this->rows, 'url', 'user_id');
        $this->assertStringContainsString('/u/tickets/', $byUser['req']);
        $this->assertStringContainsString('/admin/tickets/', $byUser['pic1']);
    }

    public function testStatusChangeNotifiesEachPersonOnceWhenRolesOverlap(): void
    {
        $this->service()->forStatusChange($this->ticket(['resolver_id' => 'pic1']), TicketStatus::Open, TicketStatus::InProgress, 'agent1');

        $this->assertSame(['pic1', 'req'], $this->recipients());
    }

    public function testEscalationHasItsOwnTitle(): void
    {
        $this->service()->forStatusChange($this->ticket(), TicketStatus::InProgress, TicketStatus::Escalated, 'agent1');

        $this->assertStringContainsStringIgnoringCase('eskalasi', $this->rows[0]['title']);
        $this->assertSame('status_changed', $this->rows[0]['event_type']);
    }

    public function testStatusChangeForARequesterWithoutAccountAndNoPicNotifiesNobody(): void
    {
        $this->notifications->expects($this->never())->method('insert');

        $result = $this->service()->forStatusChange(
            $this->ticket(['requester_id' => null, 'assignee_id' => null, 'resolver_id' => null]),
            TicketStatus::Open,
            TicketStatus::InProgress,
            'agent1',
        );

        $this->assertSame([], $result);
    }

    public function testTheAccountThatFiledOnBehalfIsNotifiedToo(): void
    {
        $this->users->method('findByEmployeeNo')->with('00001')->willReturn(new User(['id' => 'filer']));

        $this->service()->forStatusChange($this->ticket(['created_by' => '00001']), TicketStatus::Open, TicketStatus::InProgress, 'agent1');

        $this->assertContains('filer', $this->recipients());
    }

    public function testPublicAgentReplyGoesToTheRequesterSideAndTheOtherPicButNotTheAuthor(): void
    {
        $this->service()->forComment($this->ticket(), 'pic1', 'AGENT', 'PUBLIC');

        $this->assertSame(['pic2', 'req'], $this->recipients());
        $this->assertSame('comment_added', $this->rows[0]['event_type']);
    }

    public function testInternalNoteNeverReachesTheRequesterSide(): void
    {
        $this->users->method('findByEmployeeNo')->willReturn(new User(['id' => 'filer']));

        $this->service()->forComment($this->ticket(['created_by' => '00001']), 'pic1', 'AGENT', 'INTERNAL');

        $this->assertSame(['pic2'], $this->recipients());
    }

    public function testRequesterReplyGoesToThePicsOnly(): void
    {
        $this->service()->forComment($this->ticket(), 'req', 'REQUESTER', 'PUBLIC');

        $this->assertSame(['pic1', 'pic2'], $this->recipients());
    }

    public function testSlaAlertNotifiesThePics(): void
    {
        $this->service()->forSla($this->ticket(), InAppNotificationService::SLA_RESPONSE_WARNING);

        $this->assertSame(['pic1', 'pic2'], $this->recipients());
        $this->assertSame('sla_warning', $this->rows[0]['event_type']);
    }

    public function testSlaBreachHasADistinctEventTypeAndTitle(): void
    {
        $this->service()->forSla($this->ticket(), InAppNotificationService::SLA_RESOLUTION_BREACHED);

        $this->assertSame('sla_breached', $this->rows[0]['event_type']);
        $this->assertStringContainsStringIgnoringCase('penyelesaian', $this->rows[0]['title']);
    }

    public function testSlaAlertFallsBackToThePeopleWhoCanAssignWhenNoPicIsSet(): void
    {
        $this->users->method('withPermission')->willReturn([['id' => 'lead1', 'name' => 'L', 'employee_no' => '1']]);

        $this->service()->forSla($this->ticket(['assignee_id' => null, 'resolver_id' => null]), InAppNotificationService::SLA_RESPONSE_BREACHED);

        $this->assertSame(['lead1'], $this->recipients());
    }

    public function testNotificationTextNeverContainsContactDataOrInternalDetails(): void
    {
        $this->service()->forStatusChange($this->ticket(), TicketStatus::Open, TicketStatus::InProgress, 'agent1');

        foreach ($this->rows as $row) {
            $this->assertStringNotContainsString('081234567890', $row['body'] . $row['title']);
            $this->assertStringNotContainsString('sari@x.id', $row['body'] . $row['title']);
        }
    }
}
