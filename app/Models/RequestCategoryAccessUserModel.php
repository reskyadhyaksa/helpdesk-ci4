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

    /**
     * @return list<array{user_id: string, effect: string, name: string, employee_no: string}> pengecualian untuk satu kategori, urut nama
     */
    public function forCategory(string $categoryId): array
    {
        return $this->select('request_category_access_user.user_id, request_category_access_user.effect, users.name, users.employee_no')
            ->join('users', 'users.id = request_category_access_user.user_id')
            ->where('request_category_access_user.request_category_id', $categoryId)
            ->orderBy('users.name', 'ASC')
            ->findAll();
    }

    public function findOne(string $userId, string $categoryId): ?array
    {
        return $this->where('user_id', $userId)->where('request_category_id', $categoryId)->first();
    }
}
