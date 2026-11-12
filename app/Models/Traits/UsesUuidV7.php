<?php

namespace App\Models\Traits;

trait UsesUuidV7
{
    protected function generateUuid(array $data): array
    {
        if (empty($data['data'][$this->primaryKey])) {
            $data['data'][$this->primaryKey] = uuid_v7();
        }

        return $data;
    }
}
