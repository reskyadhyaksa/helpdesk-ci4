<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Worker notifikasi: membaca antrean RabbitMQ dan mengirim notifikasi dari outbox.
 * Jalankan sebagai proses panjang (supervisor/NSSM): php spark notifications:work
 */
class NotificationsWork extends BaseCommand
{
    protected $group       = 'Notifications';
    protected $name        = 'notifications:work';
    protected $description = 'Memproses antrean notifikasi tiket dari RabbitMQ.';
    protected $usage       = 'notifications:work [--idle detik]';
    protected $options     = ['--idle' => 'Berhenti bila tidak ada pesan selama sekian detik (bawaan: 0 = berjalan terus).'];

    public function run(array $params)
    {
        $idle       = (int) (CLI::getOption('idle') ?? 0);
        $dispatcher = service('notificationDispatcher');

        CLI::write('Menunggu pesan notifikasi' . ($idle > 0 ? " (berhenti setelah {$idle} detik tanpa pesan)" : '') . '…');

        service('messageQueue')->consume(static function (array $payload) use ($dispatcher): void {
            // Proses ini berjalan berhari-hari: sambungkan ulang bila MariaDB sudah memutus koneksi yang menganggur
            db_connect()->reconnect();

            $dispatcher->deliver((string) ($payload['outbox_id'] ?? ''));
            CLI::write('Diproses: ' . ($payload['outbox_id'] ?? '?'));
        }, $idle);
    }
}
