<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class HolidayModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    protected $table            = 'holidays';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['holiday_date', 'name', 'description', 'is_mass_leave'];

    protected $validationRules = [
        'holiday_date'  => 'required|valid_date[Y-m-d]|is_unique[holidays.holiday_date,id,{id}]',
        'name'          => 'required|max_length[150]',
        'description'   => 'permit_empty|max_length[255]',
        'is_mass_leave' => 'in_list[0,1]',
    ];

    protected $validationMessages = [
        'holiday_date' => ['is_unique' => 'Tanggal ini sudah terdaftar sebagai hari libur.'],
    ];

    public const MONTHS = [
        1  => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7  => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Hari libur pada tahun-tahun (dan bulan-bulan, jika diisi) terpilih yang namanya/deskripsinya memuat
     * kata kunci, tanggal terdekat lebih dulu. Dipaginasi; pager ada di $this->pager.
     *
     * @param list<int> $years  minimal satu tahun
     * @param list<int> $months 1–12; kosong = semua bulan
     *
     * @return list<array>
     */
    public function paginateFiltered(array $years, array $months, string $keyword): array
    {
        // Nilai sudah berupa angka (dari controller), jadi aman dipakai sebagai fungsi tanggal pada kolom
        $this->whereIn('YEAR(holiday_date)', array_map('intval', $years));

        if ($months !== []) {
            $this->whereIn('MONTH(holiday_date)', array_map('intval', $months));
        }

        if ($keyword !== '') {
            $this->groupStart()
                ->like('name', $keyword)
                ->orLike('description', $keyword)
                ->groupEnd();
        }

        return $this->orderBy('holiday_date', 'ASC')->paginate();
    }

    /**
     * @return list<int> tahun yang punya hari libur, terbaru lebih dulu
     */
    public function years(): array
    {
        $rows = $this->select('YEAR(holiday_date) AS year', false)->distinct()->orderBy('year', 'DESC')->findAll();

        return array_map('intval', array_column($rows, 'year'));
    }

    public function findByDate(string $date): ?array
    {
        return $this->where('holiday_date', $date)->first();
    }
}
