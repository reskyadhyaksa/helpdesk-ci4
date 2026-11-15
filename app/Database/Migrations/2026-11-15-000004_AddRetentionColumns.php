<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Retensi lampiran: tickets.closed_at (kapan tiket ditutup) dan ticket_attachments.purged_at (kapan file dihapus otomatis).
 */
class AddRetentionColumns extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('tickets', [
            'closed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'resolution_breached_at'],
        ]);
        $this->forge->addColumn('ticket_attachments', [
            'purged_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'checksum_sha256'],
        ]);

        // Tiket yang sudah CLOSED sebelum kolom ini ada: waktu tutupnya diambil dari riwayat aktivitas
        $this->db->query(
            "UPDATE tickets t SET t.closed_at = ("
            . "SELECT MAX(a.created_at) FROM ticket_activities a "
            . "WHERE a.ticket_id = t.id AND a.action = 'status_changed' AND a.to_value = 'CLOSED') "
            . "WHERE t.status = 'CLOSED' AND t.closed_at IS NULL",
        );

        $this->db->query('CREATE INDEX tickets_status_closed_idx ON tickets (status, closed_at)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX tickets_status_closed_idx ON tickets');
        $this->forge->dropColumn('ticket_attachments', 'purged_at');
        $this->forge->dropColumn('tickets', 'closed_at');
    }
}
