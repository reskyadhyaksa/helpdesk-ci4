<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

/**
 * Tiket mengikuti ERD HC Helpdesk: channel, status, snapshot pemohon (tiket tanpa login / atas nama orang lain),
 * dua PIC, dan tabel riwayat aktivitas.
 */
class ExtendTicketsForWorkflow extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->forge->addColumn('tickets', [
            'channel'                => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PORTAL', 'after' => 'ticket_number'],
            'status'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'OPEN', 'after' => 'channel'],
            'requester_nip'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '', 'after' => 'requester_id'],
            'requester_name'         => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => '', 'after' => 'requester_nip'],
            'requester_phone'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'requester_name'],
            'requester_email'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'requester_phone'],
            'requester_organization' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'requester_email'],
            'is_requester_verified'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'requester_organization'],
            'assignee_id'            => ['type' => 'UUID', 'null' => true, 'after' => 'is_requester_verified'],
            'resolver_id'            => ['type' => 'UUID', 'null' => true, 'after' => 'assignee_id'],
        ]);

        // Tiket tanpa login belum punya akun
        $this->forge->modifyColumn('tickets', ['requester_id' => ['type' => 'UUID', 'null' => true]]);

        $this->db->query('UPDATE tickets t JOIN users u ON u.id = t.requester_id '
            . 'SET t.requester_nip = u.employee_no, t.requester_name = u.name, t.requester_email = u.email');

        $this->db->query('ALTER TABLE tickets ADD CONSTRAINT tickets_assignee_fk FOREIGN KEY (assignee_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->db->query('ALTER TABLE tickets ADD CONSTRAINT tickets_resolver_fk FOREIGN KEY (resolver_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->db->query('CREATE INDEX tickets_status_idx ON tickets (status)');
        $this->db->query('CREATE INDEX tickets_requester_nip_idx ON tickets (requester_nip)');

        $this->createActivities();
    }

    public function down(): void
    {
        $this->forge->dropTable('ticket_activities');

        $this->db->query('ALTER TABLE tickets DROP FOREIGN KEY tickets_assignee_fk');
        $this->db->query('ALTER TABLE tickets DROP FOREIGN KEY tickets_resolver_fk');
        $this->db->query('DROP INDEX tickets_status_idx ON tickets');
        $this->db->query('DROP INDEX tickets_requester_nip_idx ON tickets');
        $this->forge->dropColumn('tickets', 'channel,status,requester_nip,requester_name,requester_phone,requester_email,'
            . 'requester_organization,is_requester_verified,assignee_id,resolver_id');
    }

    private function createActivities(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'UUID'],
            'ticket_id'  => ['type' => 'UUID'],
            'actor_id'   => ['type' => 'UUID', 'null' => true],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 30],
            'from_value' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'to_value'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('ticket_id');
        $this->forge->addForeignKey('ticket_id', 'tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('ticket_activities');
    }
}
