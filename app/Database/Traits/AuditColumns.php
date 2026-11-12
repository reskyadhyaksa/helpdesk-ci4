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
            'created_by' => ['type' => 'UUID', 'null' => true],
            'updated_by' => ['type' => 'UUID', 'null' => true],
        ];
    }
}
