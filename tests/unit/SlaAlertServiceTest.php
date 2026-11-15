<?php

use App\Models\SystemParameterModel;
use App\Models\TicketModel;
use App\Services\InAppNotificationService;
use App\Services\NotificationPusher;
use App\Services\SlaAlertService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SlaAlertServiceTest extends CIUnitTestCase
{
    private TicketModel $tickets;
    private InAppNotificationService $inApp;
    private NotificationPusher $pusher;
    private SystemParameterModel $parameters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tickets    = $this->createMock(TicketModel::class);
        $this->inApp      = $this->createMock(InAppNotificationService::class);
        $this->pusher     = $this->createMock(NotificationPusher::class);
        $this->parameters = $this->createMock(SystemParameterModel::class);
        $this->parameters->method('int')->willReturnMap([
            [SystemParameterModel::SLA_WARNING_MINUTES, 30],
            [SystemParameterModel::SLA_ALERT_MAX_AGE_HOURS, 24],
        ]);
    }

    private function service(): SlaAlertService
    {
        return new SlaAlertService($this->tickets, $this->inApp, $this->pusher, $this->parameters);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-16 10:00:00');
    }

    public function testEachAlertKindIsQueriedWithTheCurrentTimeAndTheWarningWindow(): void
    {
        $asked = [];
        $this->tickets->method('dueForSla')->willReturnCallback(static function (string $kind, string $now, string $windowEnd) use (&$asked): array {
            $asked[$kind] = [$now, $windowEnd];

            return [];
        });

        $this->service()->run($this->now());

        $this->assertEqualsCanonicalizing([
            InAppNotificationService::SLA_RESPONSE_WARNING, InAppNotificationService::SLA_RESPONSE_BREACHED,
            InAppNotificationService::SLA_RESOLUTION_WARNING, InAppNotificationService::SLA_RESOLUTION_BREACHED,
        ], array_keys($asked));
        $this->assertSame(['2026-11-16 10:00:00', '2026-11-16 10:30:00'], $asked[InAppNotificationService::SLA_RESPONSE_WARNING]);
    }

    public function testBreachesOlderThanTheLookbackAreNotAlertedOnToAvoidAFirstRunFlood(): void
    {
        $this->parameters = $this->createMock(SystemParameterModel::class);
        $this->parameters->method('int')->willReturnMap([
            [SystemParameterModel::SLA_WARNING_MINUTES, 30],
            [SystemParameterModel::SLA_ALERT_MAX_AGE_HOURS, 24],
        ]);
        $oldest = null;
        $this->tickets->method('dueForSla')->willReturnCallback(static function (string $kind, string $now, string $windowEnd, string $oldestDue) use (&$oldest): array {
            $oldest = $oldestDue;

            return [];
        });

        $this->service()->run($this->now());

        $this->assertSame('2026-11-15 10:00:00', $oldest);
    }

    public function testAClaimedTicketGetsABellNotificationThatIsPushed(): void
    {
        $ticket = ['id' => 't1', 'ticket_number' => 'TKT-1'];
        $this->tickets->method('dueForSla')->willReturnCallback(
            static fn (string $kind): array => $kind === InAppNotificationService::SLA_RESPONSE_BREACHED ? [$ticket] : [],
        );
        $this->tickets->expects($this->once())->method('claimSla')->with('t1', InAppNotificationService::SLA_RESPONSE_BREACHED, '2026-11-16 10:00:00')->willReturn(true);
        $this->inApp->expects($this->once())->method('forSla')->with($ticket, InAppNotificationService::SLA_RESPONSE_BREACHED)
            ->willReturn([['id' => 'n1', 'user_id' => 'u1']]);
        $this->pusher->expects($this->once())->method('push')->with([['id' => 'n1', 'user_id' => 'u1']]);

        $result = $this->service()->run($this->now());

        $this->assertSame(['warnings' => 0, 'breaches' => 1], $result);
    }

    public function testATicketAnotherRunAlreadyClaimedIsNotNotifiedTwice(): void
    {
        $this->tickets->method('dueForSla')->willReturnCallback(
            static fn (string $kind): array => $kind === InAppNotificationService::SLA_RESOLUTION_WARNING ? [['id' => 't1']] : [],
        );
        $this->tickets->method('claimSla')->willReturn(false);
        $this->inApp->expects($this->never())->method('forSla');
        $this->pusher->expects($this->never())->method('push');

        $this->assertSame(['warnings' => 0, 'breaches' => 0], $this->service()->run($this->now()));
    }

    public function testOneFailingTicketDoesNotStopTheRest(): void
    {
        $this->tickets->method('dueForSla')->willReturnCallback(
            static fn (string $kind): array => $kind === InAppNotificationService::SLA_RESPONSE_WARNING ? [['id' => 'bad'], ['id' => 'good']] : [],
        );
        $this->tickets->method('claimSla')->willReturn(true);
        $this->inApp->method('forSla')->willReturnCallback(static function (array $ticket): array {
            if ($ticket['id'] === 'bad') {
                throw new RuntimeException('rusak');
            }

            return [['id' => 'n1', 'user_id' => 'u1']];
        });

        $result = $this->service()->run($this->now());

        $this->assertSame(['warnings' => 1, 'breaches' => 0], $result);
    }
}
