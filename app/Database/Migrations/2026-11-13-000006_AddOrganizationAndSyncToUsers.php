<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrganizationAndSyncToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'organization'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'job_title'],
            'last_synced_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'last_login_time'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'organization,last_synced_at');
    }
}
