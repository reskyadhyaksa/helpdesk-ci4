<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

class AddAuditColumns extends Migration
{
    use AuditColumns;

    private const TABLES = ['roles', 'permissions', 'role_permissions', 'users'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $missing = array_filter(
                $this->auditFields(),
                fn (string $column) => ! $this->db->fieldExists($column, $table),
                ARRAY_FILTER_USE_KEY,
            );

            if ($missing !== []) {
                $this->forge->addColumn($table, $missing);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->forge->dropColumn($table, 'created_by,updated_by');
        }

        $this->forge->dropColumn('roles', 'created_at,updated_at');
        $this->forge->dropColumn('permissions', 'created_at,updated_at');
        $this->forge->dropColumn('role_permissions', 'created_at,updated_at');
    }
}
