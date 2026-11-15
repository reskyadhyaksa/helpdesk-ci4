<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

/**
 * Parameter sistem yang bisa diubah admin tanpa deploy. Nilai bawaan ada di DEFAULTS (diisi seeder,
 * dan dipakai sebagai cadangan bila barisnya belum ada).
 */
class SystemParameterModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const ATTACHMENT_MAX_SIZE_MB      = 'ATTACHMENT_MAX_SIZE_MB';
    public const ATTACHMENT_MAX_FILES        = 'ATTACHMENT_MAX_FILES';
    public const ATTACHMENT_ALLOWED_EXTENSIONS = 'ATTACHMENT_ALLOWED_EXTENSIONS';

    public const DEFAULTS = [
        self::ATTACHMENT_MAX_SIZE_MB        => ['value' => '2', 'type' => 'INT'],
        // 3 file x 2 MB = 6 MB muat di post_max_size bawaan PHP (8M); lebih dari itu seluruh isian form hilang
        self::ATTACHMENT_MAX_FILES          => ['value' => '3', 'type' => 'INT'],
        self::ATTACHMENT_ALLOWED_EXTENSIONS => ['value' => 'jpg,jpeg,png,pdf,docx,xlsx,txt', 'type' => 'STRING'],
    ];

    protected $table            = 'system_parameters';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['param_key', 'param_value', 'value_type'];

    protected $validationRules = [
        'param_key'   => 'required|max_length[60]|is_unique[system_parameters.param_key,id,{id}]',
        'param_value' => 'required|max_length[255]',
    ];

    public function value(string $key): string
    {
        $row = $this->where('param_key', $key)->first();

        return $row['param_value'] ?? self::DEFAULTS[$key]['value'];
    }

    public function int(string $key): int
    {
        return (int) $this->value($key);
    }
}
