<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class RequestCategoryAccessUserModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const EFFECT_ALLOW = 'allow';
    public const EFFECT_DENY  = 'deny';

    protected $table            = 'request_category_access_user';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['user_id', 'request_category_id', 'effect'];

    protected $validationRules = [
        'user_id'             => 'required|string',
        'request_category_id' => 'required|string',
        'effect'              => 'required|in_list[allow,deny]',
    ];

    /**
     * @return list<array{request_category_id: string, effect: string}>
     */
    public function forUser(string $userId): array
    {
        return $this->select('request_category_id, effect')->where('user_id', $userId)->findAll();
    }

    public function findOne(string $userId, string $categoryId): ?array
    {
        return $this->where('user_id', $userId)->where('request_category_id', $categoryId)->first();
    }
}
