<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

class CreateWeeklyOffDays extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'UUID'],
            'day_of_week' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true],
            'is_off'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('day_of_week');
        $this->forge->createTable('weekly_off_days');
    }

    public function down(): void
    {
        $this->forge->dropTable('weekly_off_days');
    }
}
