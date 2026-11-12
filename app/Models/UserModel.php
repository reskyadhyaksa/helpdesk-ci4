<?php

namespace App\Models;

use App\Entities\User;
use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class UserModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = User::class;
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['role_id', 'employee_no', 'name', 'is_active'];

    protected $validationRules = [
        'role_id'     => 'required',
        'employee_no' => 'required|max_length[50]|is_unique[users.employee_no,id,{id}]',
        'name'        => 'required|max_length[100]',
    ];

    public function findByEmployeeNo(string $employeeNo): ?User
    {
        return $this->select('users.*, roles.slug AS role_slug, roles.name AS role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.employee_no', $employeeNo)
            ->first();
    }
}
