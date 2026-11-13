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
    protected $allowedFields    = [
        'role_id', 'employee_no', 'name', 'email', 'position', 'position_level',
        'job_title', 'is_active', 'last_login_time',
    ];

    protected $validationRules = [
        'role_id'        => 'required',
        'employee_no'    => 'required|max_length[50]|is_unique[users.employee_no,id,{id}]',
        'name'           => 'required|max_length[100]',
        'email'          => 'permit_empty|max_length[255]',
        'position'       => 'permit_empty|max_length[100]',
        'position_level' => 'permit_empty|max_length[50]',
        'job_title'      => 'permit_empty|max_length[100]',
    ];

    /**
     * Daftar pengguna untuk admin, dengan nama role. Hasil dipaginasi; pager ada di $this->pager.
     *
     * @return list<User>
     */
    public function paginateForAdmin(?string $keyword, int $perPage): array
    {
        $this->select('users.*, roles.name AS role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->orderBy('users.name', 'ASC');

        if ($keyword !== null && $keyword !== '') {
            $this->groupStart()
                ->like('users.name', $keyword)
                ->orLike('users.employee_no', $keyword)
                ->orLike('users.email', $keyword)
                ->groupEnd();
        }

        return $this->paginate($perPage);
    }

    public function countByRole(string $roleId): int
    {
        return $this->where('role_id', $roleId)->countAllResults();
    }

    public function findByEmployeeNo(string $employeeNo): ?User
    {
        return $this->select('users.*, roles.slug AS role_slug, roles.name AS role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.employee_no', $employeeNo)
            ->first();
    }
}
