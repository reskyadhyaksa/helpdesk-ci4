<?php

use App\Entities\TicketStatus;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TicketStatusTest extends CIUnitTestCase
{
    /** Tabel alur status dari ERD */
    public static function transitions(): iterable
    {
        $map = [
            'OPEN'          => ['IN_PROGRESS', 'ON_HOLD', 'CANCELLED'],
            'IN_PROGRESS'   => ['NEED_RESPONSE', 'ON_HOLD', 'ESCALATED', 'RESOLVED'],
            'NEED_RESPONSE' => ['IN_PROGRESS', 'RESOLVED', 'CANCELLED'],
            'ON_HOLD'       => ['IN_PROGRESS', 'ESCALATED'],
            'ESCALATED'     => ['IN_PROGRESS', 'ON_HOLD', 'RESOLVED'],
            'RESOLVED'      => ['CLOSED', 'REOPENED'],
            'REOPENED'      => ['IN_PROGRESS', 'ESCALATED', 'RESOLVED'],
            'CLOSED'        => [],
            'CANCELLED'     => [],
        ];

        foreach ($map as $from => $allowed) {
            yield $from => [$from, $allowed];
        }
    }

    /**
     * @dataProvider transitions
     */
    public function testAllowedTransitionsMatchTheStatusTable(string $from, array $allowed): void
    {
        $actual = array_map(static fn (TicketStatus $s) => $s->value, TicketStatus::from($from)->allowedTransitions());

        $this->assertEqualsCanonicalizing($allowed, $actual);
    }

    public function testCanTransitionToRejectsSkippingSteps(): void
    {
        $this->assertFalse(TicketStatus::Open->canTransitionTo(TicketStatus::Resolved));
        $this->assertTrue(TicketStatus::Open->canTransitionTo(TicketStatus::InProgress));
    }

    public function testClosedAndCancelledAreFinal(): void
    {
        $this->assertTrue(TicketStatus::Closed->isFinal());
        $this->assertTrue(TicketStatus::Cancelled->isFinal());
        $this->assertFalse(TicketStatus::Resolved->isFinal());
    }
}
