<?php

namespace App\Models\Traits;

/**
 * Setara AuditorAware di Spring: mengisi created_at/updated_at (bawaan CI4)
 * dan created_by/updated_by dari user yang sedang login. Null jika tidak ada user
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
        $userId = auth_user()['id'] ?? null;

        $data['data']['created_by'] = $userId;
        $data['data']['updated_by'] = $userId;

        return $data;
    }

    protected function auditOnUpdate(array $data): array
    {
        $data['data']['updated_by'] = auth_user()['id'] ?? null;

        return $data;
    }
}
