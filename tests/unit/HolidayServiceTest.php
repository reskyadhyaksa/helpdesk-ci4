<?php

use App\Exceptions\BusinessException;
use App\Models\HolidayModel;
use App\Models\WeeklyOffDayModel;
use App\Services\Auth\KeycloakAuthService;
use App\Services\HolidayService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HolidayServiceTest extends CIUnitTestCase
{
    private function service(WeeklyOffDayModel $offDays): HolidayService
    {
        return new HolidayService(
            $this->createMock(HolidayModel::class),
            $offDays,
            $this->createMock(KeycloakAuthService::class),
        );
    }

    /** Jam kerja Senin–Jumat 08:30–17:30 */
    private function weekdayHours(): array
    {
        $starts = $ends = [];

        foreach ([1, 2, 3, 4, 5] as $day) {
            $starts[$day] = '08:30';
            $ends[$day]   = '17:30';
        }

        return [$starts, $ends];
    }

    public function testUpdateOffDaysRejectsEndNotAfterStart(): void
    {
        $model = $this->createMock(WeeklyOffDayModel::class);
        $model->expects($this->never())->method('insert');
        $model->expects($this->never())->method('update');

        [$starts, $ends] = $this->weekdayHours();
        $starts[1]       = '09:00';
        $ends[1]         = '09:00';

        $this->expectException(BusinessException::class);

        $this->service($model)->updateOffDays([6, 7], $starts, $ends);
    }

    public function testUpdateOffDaysRejectsWorkDayWithoutHours(): void
    {
        $model = $this->createMock(WeeklyOffDayModel::class);
        $model->expects($this->never())->method('insert');

        [$starts, $ends] = $this->weekdayHours();
        unset($starts[2], $ends[2]);

        $this->expectException(BusinessException::class);

        $this->service($model)->updateOffDays([6, 7], $starts, $ends);
    }

    public function testUpdateOffDaysStoresNullHoursForOffDay(): void
    {
        $rows  = [];
        $model = $this->createMock(WeeklyOffDayModel::class);
        $model->method('findByDay')->willReturn(null);
        $model->expects($this->exactly(7))
            ->method('insert')
            ->willReturnCallback(static function (array $row) use (&$rows) {
                $rows[$row['day_of_week']] = $row;

                return 'id';
            });

        [$starts, $ends] = $this->weekdayHours();

        $this->service($model)->updateOffDays([6, 7], $starts, $ends);

        $this->assertSame(1, $rows[6]['is_off']);
        $this->assertNull($rows[6]['work_start']);
        $this->assertNull($rows[7]['work_end']);
        $this->assertSame(0, $rows[1]['is_off']);
        $this->assertSame('08:30', $rows[1]['work_start']);
        $this->assertSame('17:30', $rows[1]['work_end']);
    }

    public function testUpdateOffDaysStillRejectsAllDaysOff(): void
    {
        $model = $this->createMock(WeeklyOffDayModel::class);
        $model->expects($this->never())->method('insert');

        $this->expectException(BusinessException::class);

        $this->service($model)->updateOffDays([1, 2, 3, 4, 5, 6, 7], [], []);
    }
}
