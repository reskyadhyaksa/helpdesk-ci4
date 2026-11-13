<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

class CreateHolidays extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'UUID'],
            'holiday_date'  => ['type' => 'DATE'],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_mass_leave' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('holiday_date');
        $this->forge->createTable('holidays');
    }

    public function down(): void
    {
        $this->forge->dropTable('holidays');
    }
}
