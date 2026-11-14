<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * is_work_hours = 1 → target SLA hanya berjalan pada jam kerja tiap hari (jam diatur di weekly_off_days).
 */
class AddWorkHoursFlagToSlaPolicies extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sla_policies', [
            'is_work_hours' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'is_off_day'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('sla_policies', 'is_work_hours');
    }
}
