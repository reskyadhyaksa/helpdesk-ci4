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

    // Kolom profil yang bisa dijadikan filter di halaman User. Nama kolom = nama parameter query string.
    public const FILTER_COLUMNS = ['job_title', 'position_level', 'organization'];

    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = User::class;
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = [
        'role_id', 'employee_no', 'name', 'email', 'position', 'position_level',
        'job_title', 'organization', 'is_active', 'last_login_time', 'last_synced_at',
    ];

    protected $validationRules = [
        'role_id'        => 'required',
        'employee_no'    => 'required|max_length[50]|is_unique[users.employee_no,id,{id}]',
        'name'           => 'required|max_length[100]',
        'email'          => 'permit_empty|max_length[255]',
        'position'       => 'permit_empty|max_length[100]',
        'position_level' => 'permit_empty|max_length[50]',
        'job_title'      => 'permit_empty|max_length[100]',
        'organization'   => 'permit_empty|max_length[150]',
    ];

    /**
     * Daftar pengguna untuk admin, dengan nama role. Antar filter digabung (AND); di dalam satu filter
     * boleh beberapa nilai (OR), mis. jabatan A atau B. Hasil dipaginasi; pager ada di $this->pager.
     *
     * @param array<string, list<string>> $filters kolom FILTER_COLUMNS => daftar nilai persis
     *
     * @return list<User>
     */
    public function paginateForAdmin(?string $keyword, array $filters = []): array
    {
        $this->select('users.*, roles.name AS role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->orderBy('users.name', 'ASC');

        foreach (array_intersect_key($filters, array_flip(self::FILTER_COLUMNS)) as $column => $values) {
            $this->whereIn('users.' . $column, $values);
        }

        if ($keyword !== null && $keyword !== '') {
            $this->groupStart()
                ->like('users.name', $keyword)
                ->orLike('users.employee_no', $keyword)
                ->orLike('users.email', $keyword)
                ->groupEnd();
        }

        return $this->paginate();
    }

    /**
     * Pilihan untuk dropdown filter: nilai unik tiap kolom FILTER_COLUMNS yang terisi, urut abjad.
     *
     * @return array<string, list<string>> kolom => nilai
     */
    public function filterOptions(): array
    {
        $options = [];

        foreach (self::FILTER_COLUMNS as $column) {
            $rows = $this->builder()
                ->select($column)
                ->distinct()
                ->where($column . ' IS NOT NULL', null, false)
                ->where($column . ' !=', '')
                ->orderBy($column, 'ASC')
                ->get()
                ->getResultArray();

            $options[$column] = array_column($rows, $column);
        }

        return $options;
    }

    /**
     * @return array<string, string> id => "Nama (NIK)", urut nama (untuk dropdown)
     */
    public function nameOptions(): array
    {
        $rows = $this->builder()->select('id, name, employee_no')->orderBy('name', 'ASC')->get()->getResultArray();

        return array_column(
            array_map(static fn (array $row): array => ['id' => $row['id'], 'label' => $row['name'] . ' (' . $row['employee_no'] . ')'], $rows),
            'label',
            'id',
        );
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
