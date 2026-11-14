<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

/**
 * Aturan akses berdasarkan role: user dengan role ini boleh melihat kategori (hanya kategori utama; sub ikut induk).
 */
class CreateRequestCategoryAccessRole extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'UUID'],
            'role_id'             => ['type' => 'UUID'],
            'request_category_id' => ['type' => 'UUID'],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['role_id', 'request_category_id']);
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('request_category_id', 'request_categories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('request_category_access_role');
    }

    public function down(): void
    {
        $this->forge->dropTable('request_category_access_role');
    }
}
