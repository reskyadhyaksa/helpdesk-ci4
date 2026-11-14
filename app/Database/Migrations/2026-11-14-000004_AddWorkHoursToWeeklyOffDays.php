<?php

namespace App\Database\Migrations;

use App\Models\WeeklyOffDayModel;
use CodeIgniter\Database\Migration;

/**
 * Jam kerja per hari. Hari off tidak punya jam (null); hari kerja yang sudah ada diisi jam bawaan.
 */
class AddWorkHoursToWeeklyOffDays extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('weekly_off_days', [
            'work_start' => ['type' => 'TIME', 'null' => true, 'after' => 'is_off'],
            'work_end'   => ['type' => 'TIME', 'null' => true, 'after' => 'work_start'],
        ]);

        $this->db->table('weekly_off_days')
            ->where('is_off', 0)
            ->update([
                'work_start' => WeeklyOffDayModel::DEFAULT_WORK_START,
                'work_end'   => WeeklyOffDayModel::DEFAULT_WORK_END,
            ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('weekly_off_days', 'work_start,work_end');
    }
}
