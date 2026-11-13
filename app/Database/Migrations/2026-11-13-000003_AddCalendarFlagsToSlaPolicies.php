<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Aturan kalender perhitungan SLA (lihat Entity SlaPolicy):
 * is_holiday = 1 → tanggal merah & cuti bersama tidak dihitung,
 * is_off_day = 1 → hari off mingguan (default Sabtu & Minggu) tidak dihitung.
 */
class AddCalendarFlagsToSlaPolicies extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sla_policies', [
            'is_holiday' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'description'],
            'is_off_day' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'is_holiday'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('sla_policies', 'is_holiday,is_off_day');
    }
}
