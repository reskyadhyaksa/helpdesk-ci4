<?php

namespace App\Database\Seeds;

use App\Models\SystemParameterModel;
use CodeIgniter\Database\Seeder;

/**
 * Nilai awal parameter sistem. Aman dijalankan ulang: yang sudah ada (mungkin sudah diubah admin) tidak ditimpa.
 */
class SystemParameterSeeder extends Seeder
{
    public function run(): void
    {
        $parameters = model(SystemParameterModel::class);

        foreach (SystemParameterModel::DEFAULTS as $key => $default) {
            if ($parameters->where('param_key', $key)->first() === null) {
                $parameters->insert(['param_key' => $key, 'param_value' => $default['value'], 'value_type' => $default['type']]);
            }
        }
    }
}
