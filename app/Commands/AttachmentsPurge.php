<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Menghapus lampiran tiket CLOSED yang sudah melewati masa retensi (pengaturan ATTACHMENT_RETENTION_DAYS di admin).
 * Jadwalkan harian: php spark attachments:purge
 */
class AttachmentsPurge extends BaseCommand
{
    protected $group       = 'Notifications';
    protected $name        = 'attachments:purge';
    protected $description = 'Menghapus lampiran tiket CLOSED yang melewati masa retensi.';

    public function run(array $params)
    {
        $count = service('attachmentRetention')->purge();

        CLI::write("{$count} lampiran dihapus.");
    }
}
