<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Mengantrekan ulang notifikasi PENDING yang sudah waktunya (gagal terpublikasi, atau jeda retry-nya habis).
 * Jadwalkan tiap menit: php spark notifications:republish
 */
class NotificationsRepublish extends BaseCommand
{
    protected $group       = 'Notifications';
    protected $name        = 'notifications:republish';
    protected $description = 'Mengantrekan ulang notifikasi PENDING yang sudah waktunya dikirim.';

    public function run(array $params)
    {
        $count = service('notificationDispatcher')->republishDue();

        CLI::write("{$count} notifikasi diantrekan.");
    }
}
