<?php

namespace Config;

use App\Entities\SlaPolicy;
use App\Models\RequestCategoryAccessUserModel;
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
        'list' => 'CodeIgniter\Validation\Views\list',
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
        'response_minutes' => ['label' => 'Target respons pertama', 'rules' => 'required|is_natural_no_zero|less_than_equal_to[43200]'],
        'resolution_minutes' => ['label' => 'Target penyelesaian', 'rules' => 'required|is_natural_no_zero|less_than_equal_to[43200]'],
        'description' => ['label' => 'Deskripsi', 'rules' => 'permit_empty|max_length[255]'],
        'is_holiday' => ['label' => 'Lewati hari libur', 'rules' => 'required|in_list[0,1]'],
        'is_off_day' => ['label' => 'Lewati hari off', 'rules' => 'required|in_list[0,1]'],
        'is_work_hours' => ['label' => 'Hanya jam kerja', 'rules' => 'required|in_list[0,1]'],
        'is_active' => ['label' => 'Status aktif', 'rules' => 'required|in_list[0,1]'],
    ];

    public array $requestCategory = [
        'name' => ['label' => 'Nama kategori', 'rules' => 'required|max_length[100]'],
        'description' => ['label' => 'Deskripsi', 'rules' => 'permit_empty|max_length[255]'],
        'parent_id' => ['label' => 'Kategori Utama', 'rules' => 'permit_empty|exact_length[36]'],
        'sla_policy_id' => ['label' => 'Kebijakan SLA', 'rules' => 'permit_empty|exact_length[36]'],
        'is_active' => ['label' => 'Status aktif', 'rules' => 'required|in_list[0,1]'],
        'job_titles.*' => ['label' => 'Job title', 'rules' => 'permit_empty|max_length[100]'],
        'role_ids.*' => ['label' => 'Role', 'rules' => 'permit_empty|exact_length[36]'],
        'user_access.*' => ['label' => 'Pengecualian user', 'rules' => 'permit_empty|in_list[allow,deny]'],
    ];

    public array $publicTicket = [
        'nip'          => ['label' => 'NIK', 'rules' => 'required|max_length[50]'],
        'organization' => ['label' => 'Divisi / Regional', 'rules' => 'required|max_length[150]'],
        'phone'        => ['label' => 'Nomor handphone', 'rules' => 'required|regex_match[/^[0-9+\s\-()]{8,30}$/]'],
        'email'        => ['label' => 'E-mail', 'rules' => 'permit_empty|valid_email|max_length[255]'],
        'category_id'  => ['label' => 'Kategori', 'rules' => 'required|exact_length[36]'],
        'subject'      => ['label' => 'Judul', 'rules' => 'required|max_length[150]'],
        'description'  => ['label' => 'Deskripsi', 'rules' => 'required|max_length[2000]'],
    ];

    public array $ticket = [
        'for_nip'     => ['label' => 'NIK pemohon', 'rules' => 'permit_empty|max_length[50]'],
        'category_id' => ['label' => 'Kategori', 'rules' => 'required|exact_length[36]'],
        'subject' => ['label' => 'Judul', 'rules' => 'required|max_length[150]'],
        'description' => ['label' => 'Deskripsi', 'rules' => 'required|max_length[2000]'],
    ];

    public array $ticketStatus = [
        'status' => ['label' => 'Status', 'rules' => 'required|in_list[OPEN,IN_PROGRESS,NEED_RESPONSE,ON_HOLD,ESCALATED,RESOLVED,REOPENED,CLOSED,CANCELLED]'],
    ];

    public array $ticketAssign = [
        'role'    => ['label' => 'Peran PIC', 'rules' => 'required|in_list[assignee,resolver]'],
        'user_id' => ['label' => 'PIC', 'rules' => 'permit_empty|exact_length[36]'],
    ];

    public array $holiday = [
        'holiday_date' => ['label' => 'Tanggal', 'rules' => 'required|valid_date[Y-m-d]'],
        'name' => ['label' => 'Nama hari libur', 'rules' => 'required|max_length[150]'],
        'description' => ['label' => 'Deskripsi', 'rules' => 'permit_empty|max_length[255]'],
        'is_mass_leave' => ['label' => 'Cuti bersama', 'rules' => 'required|in_list[0,1]'],
    ];

    // Tidak ada hari dicentang = field tidak terkirim, jadi boleh kosong (service menolak jika semua hari off)
    public array $offDays = [
        'off_days.*' => ['label' => 'Hari off', 'rules' => 'permit_empty|in_list[1,2,3,4,5,6,7]'],
    ];

    public array $userRole = [
        'role_id' => ['label' => 'Role', 'rules' => 'required|exact_length[36]'],
    ];

    public array $categoryAccessException = [
        'category_id' => ['label' => 'Kategori', 'rules' => 'required|exact_length[36]'],
        'effect' => [
            'label' => 'Efek',
            'rules' => 'required|in_list[' . RequestCategoryAccessUserModel::EFFECT_ALLOW . ',' . RequestCategoryAccessUserModel::EFFECT_DENY . ']',
        ],
    ];

    // Slug tidak diisi admin; dibuat otomatis dari nama oleh RoleService.
    public array $role = [
        'name' => ['label' => 'Nama role', 'rules' => 'required|max_length[100]'],
        'permissions.*' => ['label' => 'Permission', 'rules' => 'permit_empty|max_length[100]'],
    ];
}
        'work_start.*' => ['label' => 'Jam mulai', 'rules' => 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]'],
        'work_end.*' => ['label' => 'Jam selesai', 'rules' => 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]'],
