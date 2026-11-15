<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * queued_at = kapan baris terakhir diantrekan ke RabbitMQ. Dipakai agar republish tidak menumpuk salinan
 * pesan untuk baris yang baru saja diantrekan dan masih menunggu di antrean.
 */
class AddQueuedAtToNotificationOutbox extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('notification_outbox', [
            'queued_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'next_attempt_at'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('notification_outbox', 'queued_at');
    }
}
