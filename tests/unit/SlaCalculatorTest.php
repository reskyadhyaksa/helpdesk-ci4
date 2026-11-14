<?php

use App\Entities\SlaPolicy;
use App\Exceptions\BusinessException;
use App\Services\SlaCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kalender acuan: Senin 2026-11-16 … Jumat 2026-11-20, Sabtu 21, Minggu 22, Senin 23, Selasa 24.
 *
 * @internal
 */
final class SlaCalculatorTest extends CIUnitTestCase
{
    /** Senin–Jumat 08:30–17:30, Sabtu & Minggu off */
    private function schedule(string $start = '08:30', string $end = '17:30'): array
    {
        $schedule = [];

        foreach ([1, 2, 3, 4, 5] as $day) {
            $schedule[$day] = ['is_off' => false, 'start' => $start, 'end' => $end];
        }

        foreach ([6, 7] as $day) {
            $schedule[$day] = ['is_off' => true, 'start' => null, 'end' => null];
        }

        return $schedule;
    }

    private function policy(bool $holiday = true, bool $offDay = true, bool $workHours = true): SlaPolicy
    {
        return new SlaPolicy(['is_holiday' => $holiday, 'is_off_day' => $offDay, 'is_work_hours' => $workHours]);
    }

    private function due(string $start, int $minutes, ?SlaPolicy $policy = null, ?array $schedule = null, array $holidays = []): string
    {
        $result = (new SlaCalculator())->dueAt(
            new DateTimeImmutable($start, new DateTimeZone('Asia/Jakarta')),
            $minutes,
            $policy ?? $this->policy(),
            $schedule ?? $this->schedule(),
            $holidays,
        );

        return $result->format('Y-m-d H:i');
    }

    public function testWithinOneDay(): void
    {
        $this->assertSame('2026-11-16 11:00', $this->due('2026-11-16 10:00', 60));
    }

    public function testBeforeOpeningStartsAtOpening(): void
    {
        $this->assertSame('2026-11-16 09:00', $this->due('2026-11-16 07:00', 30));
    }

    public function testSpillsToNextWorkDay(): void
    {
        $this->assertSame('2026-11-23 09:00', $this->due('2026-11-20 16:00', 120));
    }

    public function testStartExactlyAtClosingGoesNextDay(): void
    {
        $this->assertSame('2026-11-23 09:00', $this->due('2026-11-20 17:30', 30));
    }

    public function testStartOnOffDay(): void
    {
        $this->assertSame('2026-11-23 09:30', $this->due('2026-11-21 10:00', 60));
    }

    public function testHolidaySkipped(): void
    {
        $this->assertSame('2026-11-24 09:00', $this->due('2026-11-20 17:00', 60, null, null, ['2026-11-23']));
    }

    public function testHolidayIgnoredWhenFlagOff(): void
    {
        $this->assertSame('2026-11-23 09:00', $this->due('2026-11-20 17:00', 60, $this->policy(holiday: false), null, ['2026-11-23']));
    }

    public function testFullDayWhenWorkHoursOff(): void
    {
        $this->assertSame('2026-11-23 02:00', $this->due('2026-11-20 16:00', 600, $this->policy(workHours: false)));
    }

    public function testOffDayIgnoredWhenFlagOff(): void
    {
        $this->assertSame('2026-11-21 11:00', $this->due('2026-11-21 10:00', 60, $this->policy(offDay: false, workHours: false)));
    }

    public function testAcceptsSecondsInSchedule(): void
    {
        $this->assertSame('2026-11-23 09:00', $this->due('2026-11-20 16:00', 120, null, $this->schedule('08:30:00', '17:30:00')));
    }

    public function testThrowsWhenAllDaysSkipped(): void
    {
        $allOff = [];

        foreach (range(1, 7) as $day) {
            $allOff[$day] = ['is_off' => true, 'start' => null, 'end' => null];
        }

        $this->expectException(BusinessException::class);

        $this->due('2026-11-16 10:00', 60, null, $allOff);
    }
}
