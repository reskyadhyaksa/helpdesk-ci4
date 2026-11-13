<?php

namespace Config;

use App\Entities\SlaPolicy;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------

    /** @var array<string, string> */
    public array $login = [
        'username' => 'required|max_length[50]',
        'password' => 'required|max_length[255]',
    ];

    // Target dalam menit, maksimal 30 hari (43200 menit)
    public array $slaPolicy = [
        'name' => ['label' => 'Nama kebijakan', 'rules' => 'required|max_length[100]'],
        'priority' => [
            'label' => 'Prioritas',
            'rules' => 'required|in_list[' . SlaPolicy::PRIORITY_URGENT . ',' . SlaPolicy::PRIORITY_HIGH . ','
                . SlaPolicy::PRIORITY_MEDIUM . ',' . SlaPolicy::PRIORITY_LOW . ']',
        ],
        'response_minutes'   => ['label' => 'Target respons pertama', 'rules' => 'required|is_natural_no_zero|less_than_equal_to[43200]'],
        'resolution_minutes' => ['label' => 'Target penyelesaian', 'rules' => 'required|is_natural_no_zero|less_than_equal_to[43200]'],
        'description'        => ['label' => 'Deskripsi', 'rules' => 'permit_empty|max_length[255]'],
        'is_holiday'         => ['label' => 'Lewati hari libur', 'rules' => 'required|in_list[0,1]'],
        'is_off_day'         => ['label' => 'Lewati hari off', 'rules' => 'required|in_list[0,1]'],
        'is_active'          => ['label' => 'Status aktif', 'rules' => 'required|in_list[0,1]'],
    ];

    public array $userRole = [
        'role_id' => ['label' => 'Role', 'rules' => 'required|exact_length[36]'],
    ];

    // Slug tidak diisi admin; dibuat otomatis dari nama oleh RoleService.
    public array $role = [
        'name'          => ['label' => 'Nama role', 'rules' => 'required|max_length[100]'],
        'permissions.*' => ['label' => 'Permission', 'rules' => 'permit_empty|max_length[100]'],
    ];
}
