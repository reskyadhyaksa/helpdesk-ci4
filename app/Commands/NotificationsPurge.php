<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Menghapus notifikasi lonceng yang sudah dibaca melewati masa retensi (pengaturan NOTIFICATION_RETENTION_DAYS di admin).
 * Jadwalkan harian: php spark notifications:purge
 */
class NotificationsPurge extends BaseCommand
{
    protected $group       = 'Notifications';
    protected $name        = 'notifications:purge';
    protected $description = 'Menghapus notifikasi lonceng yang sudah dibaca dan melewati masa retensi.';

    public function run(array $params)
    {
        $count = service('notificationRetention')->purge();

        CLI::write("{$count} notifikasi dihapus.");
    }
}
