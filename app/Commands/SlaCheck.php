<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Peringatan SLA ke PIC (lonceng). Jadwalkan tiap menit: php spark sla:check
 */
class SlaCheck extends BaseCommand
{
    protected $group       = 'Notifications';
    protected $name        = 'sla:check';
    protected $description = 'Memberi notifikasi PIC untuk tiket yang SLA-nya hampir atau sudah terlewat.';

    public function run(array $params)
    {
        $result = service('slaAlerts')->run();

        CLI::write("{$result['warnings']} peringatan dan {$result['breaches']} pelanggaran SLA diberitahukan.");
    }
}
