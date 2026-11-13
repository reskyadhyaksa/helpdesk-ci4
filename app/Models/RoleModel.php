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

    // Role yang dirujuk kode (login otomatis, akses admin): tidak boleh dihapus atau diganti slug-nya.
    public const SYSTEM_SLUGS = [self::SLUG_USER, self::SLUG_ADMIN];

    protected $table            = 'roles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['slug', 'name'];

    protected $validationRules = [
        'slug' => 'required|max_length[50]|is_unique[roles.slug,id,{id}]',
        'name' => 'required|max_length[100]',
    ];

    protected $validationMessages = [
        'slug' => ['is_unique' => 'Slug role sudah dipakai.'],
    ];

    public function idBySlug(string $slug): ?string
    {
        return $this->where('slug', $slug)->first()['id'] ?? null;
    }

    public function slugExists(string $slug): bool
    {
        return $this->where('slug', $slug)->countAllResults() > 0;
    }

    /**
     * @return list<array> role beserta jumlah pengguna dan permission-nya, urut nama
     */
    public function allWithCounts(): array
    {
        return $this->select('roles.*')
            ->select('(SELECT COUNT(*) FROM users WHERE users.role_id = roles.id) AS users_count', false)
            ->select('(SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = roles.id) AS permissions_count', false)
            ->orderBy('roles.name', 'ASC')
            ->findAll();
    }
}
