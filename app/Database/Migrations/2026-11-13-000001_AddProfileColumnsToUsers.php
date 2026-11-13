<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfileColumnsToUsers extends Migration
{
    private const COLUMNS = 'email,position,position_level,job_title,last_login_time';

    public function up(): void
    {
        $this->forge->addColumn('users', [
            'email'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'name'],
            'position'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'email'],
            'position_level'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'position'],
            'job_title'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'position_level'],
            'last_login_time' => ['type' => 'DATETIME', 'null' => true, 'after' => 'is_active'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', self::COLUMNS);
    }
}
