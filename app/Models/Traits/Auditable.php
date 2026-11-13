<?php

namespace App\Models\Traits;

/**
 * Setara AuditorAware di Spring: mengisi created_at/updated_at (bawaan CI4)
 * dan created_by/updated_by dengan NIK (employee_no) user yang sedang login. Null jika tidak ada user
 * (CLI, seeder, atau proses sebelum login).
 *
 * Catatan: callback tidak berjalan di insertBatch(); isi kolom audit manual di sana.
 */
trait Auditable
{
    protected function initialize(): void
    {
        $this->useTimestamps  = true;
        $this->allowedFields  = array_merge($this->allowedFields, ['created_by', 'updated_by']);
        $this->beforeInsert[] = 'auditOnInsert';
        $this->beforeUpdate[] = 'auditOnUpdate';
    }

    protected function auditOnInsert(array $data): array
    {
        $employeeNo = auth_user()['employee_no'] ?? null;

        $data['data']['created_by'] = $employeeNo;
        $data['data']['updated_by'] = $employeeNo;

        return $data;
    }

    protected function auditOnUpdate(array $data): array
    {
        $data['data']['updated_by'] = auth_user()['employee_no'] ?? null;

        return $data;
    }
}
