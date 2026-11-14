<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class WeeklyOffDayModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    // Hari dalam seminggu memakai angka ISO-8601 (Senin = 1 ... Minggu = 7), sama dengan date('N').
    public const DAYS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    // Hari off bawaan saat pertama kali di-seed
    public const DEFAULT_OFF_DAYS = [6, 7];

    // Jam kerja bawaan hari kerja (data awal seeder/migration dan isian awal form; bukan aturan hitung)
    public const DEFAULT_WORK_START = '08:30';
    public const DEFAULT_WORK_END   = '17:30';

    protected $table            = 'weekly_off_days';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['day_of_week', 'is_off', 'work_start', 'work_end'];

    protected $validationRules = [
        'day_of_week' => 'required|in_list[1,2,3,4,5,6,7]',
        'is_off'      => 'required|in_list[0,1]',
        'work_start'  => 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/]',
        'work_end'    => 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/]',
    ];

    /**
     * @return array<int, array{is_off: bool, start: ?string, end: ?string}> kunci = nomor hari; jam "HH:MM"
     */
    public function schedule(): array
    {
        $schedule = [];

        foreach ($this->findAll() as $row) {
            $schedule[(int) $row['day_of_week']] = [
                'is_off' => (bool) $row['is_off'],
                'start'  => $row['work_start'] === null ? null : substr($row['work_start'], 0, 5),
                'end'    => $row['work_end'] === null ? null : substr($row['work_end'], 0, 5),
            ];
        }

        ksort($schedule);

        return $schedule;
    }

    /**
     * @return list<int> nomor hari yang libur mingguan, urut Senin–Minggu
     */
    public function offDayNumbers(): array
    {
        $rows = $this->select('day_of_week')->where('is_off', 1)->orderBy('day_of_week', 'ASC')->findAll();

        return array_map('intval', array_column($rows, 'day_of_week'));
    }

    public function findByDay(int $day): ?array
    {
        return $this->where('day_of_week', $day)->first();
    }
}
