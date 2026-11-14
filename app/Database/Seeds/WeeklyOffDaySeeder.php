<?php

namespace App\Database\Seeds;

use App\Models\WeeklyOffDayModel;
use CodeIgniter\Database\Seeder;

/**
 * Tujuh baris hari mingguan (Senin–Minggu). Aman dijalankan ulang: baris yang sudah ada tidak diubah,
 * jadi pengaturan hari off dari admin tidak tertimpa.
 */
class WeeklyOffDaySeeder extends Seeder
{
    public function run(): void
    {
        $days = model(WeeklyOffDayModel::class);

        foreach (array_keys(WeeklyOffDayModel::DAYS) as $day) {
            if ($days->findByDay($day) === null) {
                $isOff = in_array($day, WeeklyOffDayModel::DEFAULT_OFF_DAYS, true);

                $days->insert([
                    'day_of_week' => $day,
                    'is_off'      => $isOff ? 1 : 0,
                    'work_start'  => $isOff ? null : WeeklyOffDayModel::DEFAULT_WORK_START,
                    'work_end'    => $isOff ? null : WeeklyOffDayModel::DEFAULT_WORK_END,
                ]);
            }
        }
    }
}
