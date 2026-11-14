<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

class CreateTickets extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'UUID'],
            'ticket_number'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'requester_id'        => ['type' => 'UUID'],
            'request_category_id' => ['type' => 'UUID'],
            // Salinan saat tiket dibuat: SLA yang diubah kemudian tidak memengaruhi tiket lama, maka tanpa foreign key
            'sla_policy_id'       => ['type' => 'UUID', 'null' => true],
            'priority'            => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'subject'             => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'         => ['type' => 'TEXT'],
            'response_due_at'     => ['type' => 'DATETIME', 'null' => true],
            'resolution_due_at'   => ['type' => 'DATETIME', 'null' => true],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('ticket_number');
        $this->forge->addKey('requester_id');
        $this->forge->addKey('request_category_id');
        $this->forge->addForeignKey('requester_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('request_category_id', 'request_categories', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('tickets');
    }

    public function down(): void
    {
        $this->forge->dropTable('tickets');
    }
}
