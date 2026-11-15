<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

/**
 * Outbox notifikasi (ERD notification_outbox): ditulis dalam transaksi yang sama dengan perubahan tiket,
 * lalu dikirim worker lewat RabbitMQ dengan retry.
 */
class CreateNotificationOutbox extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'UUID'],
            'ticket_id'       => ['type' => 'UUID'],
            'channel'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'event_type'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'recipient'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'payload'         => ['type' => 'TEXT'],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'PENDING'],
            'attempt_count'   => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'next_attempt_at' => ['type' => 'DATETIME', 'null' => true],
            'last_error'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sent_at'         => ['type' => 'DATETIME', 'null' => true],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['status', 'next_attempt_at']);
        $this->forge->addKey('ticket_id');
        $this->forge->addForeignKey('ticket_id', 'tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notification_outbox');
    }

    public function down(): void
    {
        $this->forge->dropTable('notification_outbox');
    }
}
