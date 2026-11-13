<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

class CreateSlaPolicies extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'UUID'],
            'name'               => ['type' => 'VARCHAR', 'constraint' => 100],
            'priority'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'response_minutes'   => ['type' => 'INT', 'unsigned' => true],
            'resolution_minutes' => ['type' => 'INT', 'unsigned' => true],
            'description'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('sla_policies');
    }

    public function down(): void
    {
        $this->forge->dropTable('sla_policies');
    }
}
