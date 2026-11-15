<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

/**
 * Notifikasi dalam aplikasi (lonceng) per pengguna, plus penanda peringatan SLA per tiket agar tidak ganda.
 */
class CreateUserNotifications extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'UUID'],
            'user_id'    => ['type' => 'UUID'],
            'ticket_id'  => ['type' => 'UUID', 'null' => true],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'body'       => ['type' => 'VARCHAR', 'constraint' => 500],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'read_at'    => ['type' => 'DATETIME', 'null' => true],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'read_at', 'created_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('ticket_id', 'tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_notifications');

        $this->forge->addColumn('tickets', [
            'response_warned_at'    => ['type' => 'DATETIME', 'null' => true],
            'response_breached_at'  => ['type' => 'DATETIME', 'null' => true],
            'resolution_warned_at'  => ['type' => 'DATETIME', 'null' => true],
            'resolution_breached_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('tickets', 'response_warned_at,response_breached_at,resolution_warned_at,resolution_breached_at');
        $this->forge->dropTable('user_notifications');
    }
}
