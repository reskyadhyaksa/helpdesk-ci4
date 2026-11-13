<?php

namespace App\Database\Traits;

/**
 * Definisi kolom audit untuk migration. Wajib ada di setiap tabel:
 * $this->forge->addField(['id' => [...], ...$this->auditFields()]);
 */
trait AuditColumns
{
    protected function auditFields(): array
    {
        return [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            // NIK (users.employee_no) pembuat/pengubah; tanpa foreign key, sama panjang dengan users.employee_no
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'updated_by' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        ];
    }
}
