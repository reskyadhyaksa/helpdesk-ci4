<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class RoleModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const SLUG_USER  = 'user';
    public const SLUG_ADMIN = 'admin';

    protected $table            = 'roles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['slug', 'name'];

    protected $validationRules = [
        'slug' => 'required|max_length[50]',
        'name' => 'required|max_length[100]',
    ];

    public function idBySlug(string $slug): ?string
    {
        return $this->where('slug', $slug)->first()['id'] ?? null;
    }
}
