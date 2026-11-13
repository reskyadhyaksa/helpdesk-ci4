<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

/**
 * created_by/updated_by berisi NIK (users.employee_no), bukan id user. Mengubah tipe kolom dari UUID ke
 * VARCHAR(50) dan mengonversi nilai lama (id user) menjadi NIK-nya. Id yang tidak dikenal menjadi null.
 * Aman dijalankan pada database baru: kolom sudah bertipe benar dan tidak ada nilai yang perlu dikonversi.
 */
class ConvertAuditByToEmployeeNo extends Migration
{
    use AuditColumns;

    private const TABLES = [
        'roles', 'permissions', 'role_permissions', 'users', 'sla_policies', 'holidays', 'weekly_off_days',
    ];

    private const COLUMNS = ['created_by', 'updated_by'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->forge->modifyColumn($table, array_intersect_key($this->auditFields(), array_flip(self::COLUMNS)));

            foreach (self::COLUMNS as $column) {
                $this->db->query("UPDATE `{$table}` t LEFT JOIN `users` u ON u.id = t.`{$column}` SET t.`{$column}` = u.employee_no WHERE t.`{$column}` IS NOT NULL AND LENGTH(t.`{$column}`) = 36");
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                $this->db->query("UPDATE `{$table}` t LEFT JOIN `users` u ON u.employee_no = t.`{$column}` SET t.`{$column}` = u.id WHERE t.`{$column}` IS NOT NULL");
            }

            $this->forge->modifyColumn($table, [
                'created_by' => ['type' => 'UUID', 'null' => true],
                'updated_by' => ['type' => 'UUID', 'null' => true],
            ]);
        }
    }
}
