<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\HolidayModel;
use App\Models\WeeklyOffDayModel;
use App\Services\Auth\KeycloakAuthService;
use CodeIgniter\Pager\Pager;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class HolidayService
{
    public function __construct(
        private readonly HolidayModel $holidays,
        private readonly WeeklyOffDayModel $offDays,
        private readonly KeycloakAuthService $api,
    ) {
    }

    public function find(string $id): ?array
    {
        return $this->holidays->find($id);
    }

    /**
     * @throws BusinessException
     */
    public function create(array $data): void
    {
        $this->ensureSaved($this->holidays->insert($this->normalize($data)) !== false);
    }

    /**
     * @throws BusinessException
     */
    public function update(array $holiday, array $data): void
    {
        // id ikut divalidasi agar is_unique mengecualikan baris ini sendiri ({id} di aturan model)
        $this->ensureSaved($this->holidays->update($holiday['id'], ['id' => $holiday['id']] + $this->normalize($data)));
    }

    public function delete(array $holiday): void
    {
        $this->holidays->delete($holiday['id']);
    }

    /**
     * @return list<int> nomor hari (ISO, Senin = 1) yang libur mingguan
     */
    public function offDayNumbers(): array
    {
        return $this->offDays->offDayNumbers();
    }

    /**
     * @param list<int|string> $days nomor hari yang dijadikan hari off; sisanya hari kerja
     *
     * @throws BusinessException
     */
    public function updateOffDays(array $days): void
    {
        $offDays = array_map('intval', $days);

        if (count(array_intersect(array_keys(WeeklyOffDayModel::DAYS), $offDays)) === count(WeeklyOffDayModel::DAYS)) {
            throw new BusinessException(lang('Admin.holiday.allDaysOff'));
        }

        foreach (array_keys(WeeklyOffDayModel::DAYS) as $day) {
            $row = ['day_of_week' => $day, 'is_off' => in_array($day, $offDays, true) ? 1 : 0];
            $existing = $this->offDays->findByDay($day);
            $saved    = $existing === null
                ? $this->offDays->insert($row) !== false
                : $this->offDays->update($existing['id'], $row);

            if (! $saved) {
                throw new BusinessException(implode(' ', $this->offDays->errors()));
            }
        }
    }

    /**
     * @return array{holidays: list<array>, pager: Pager}
     */
    public function paginateFiltered(array $years, array $months, string $keyword): array
    {
        $holidays = $this->holidays->paginateFiltered($years, $months, $keyword);

        return ['holidays' => $holidays, 'pager' => $this->holidays->pager];
    }

    /**
     * @return list<int> pilihan tahun untuk filter: tahun yang punya data, ditambah tahun berjalan
     */
    public function filterYears(): array
    {
        $years = array_unique([...$this->holidays->years(), (int) date('Y')]);
        rsort($years);

        return $years;
    }

    /**
     * Menyalin hari libur satu tahun dari API Spring Boot: tanggal baru ditambah, tanggal yang sudah ada diperbarui.
     * Hari libur yang hilang dari API tidak dihapus.
     *
     * @return int jumlah hari libur yang diterima dari API
     *
     * @throws BusinessException
     */
    public function sync(int $year): int
    {
        $items = $this->api->getHelpdeskHolidays($year);

        if ($items === null) {
            throw new BusinessException(lang('Admin.holiday.syncFailed'));
        }

        $synced = 0;

        foreach ($items as $item) {
            $date = $this->toDate($item['date'] ?? null);

            if ($date === null || trim($item['name'] ?? '') === '') {
                continue;
            }

            $data = [
                'holiday_date'  => $date,
                'name'          => $item['name'],
                'description'   => $item['description'] ?? null,
                'is_mass_leave' => empty($item['massLeaveFlag']) ? 0 : 1,
            ];
            $existing = $this->holidays->findByDate($date);

            $existing === null ? $this->create($data) : $this->update($existing, $data);
            $synced++;
        }

        return $synced;
    }

    private function normalize(array $data): array
    {
        $data['name']          = trim($data['name']);
        $data['description']   = trim($data['description'] ?? '') ?: null;
        $data['is_mass_leave'] = (int) $data['is_mass_leave'];

        return $data;
    }

    private function ensureSaved(bool $saved): void
    {
        if (! $saved) {
            throw new BusinessException(implode(' ', $this->holidays->errors()));
        }
    }

    /**
     * Tanggal dari API (epoch milidetik atau teks ISO) → "Y-m-d" di zona waktu aplikasi.
     */
    private function toDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = is_numeric($value)
                ? new DateTimeImmutable('@' . intdiv((int) $value, 1000))
                : new DateTimeImmutable((string) $value);
        } catch (Throwable) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone(app_timezone()))->format('Y-m-d');
    }
}
