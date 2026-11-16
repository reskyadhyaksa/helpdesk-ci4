<?php

namespace App\Services;

use App\Entities\SlaPolicy;
use App\Exceptions\BusinessException;
use DateTimeImmutable;

/**
 * Menghitung batas waktu SLA. Tanpa akses database: semua data kalender dikirim lewat argumen.
 */
class SlaCalculator
{
    // Batas pengaman agar kalender yang semua harinya terlewati tidak membuat loop tak berujung
    private const MAX_DAYS = 400;

    /**
     * Hari dilewati bila (is_holiday dan tanggalnya libur) atau (is_off_day dan hari itu hari off).
     * Pada hari yang dihitung, target hanya berjalan pada jam kerja bila is_work_hours dan hari itu punya jam;
     * selain itu seharian penuh.
     *
     * @param array<int, array{is_off: bool, start: ?string, end: ?string}> $schedule     kunci = nomor hari ISO; jam "HH:MM" atau "HH:MM:SS"
     * @param list<string>                                                  $holidayDates tanggal libur "Y-m-d"
     *
     * @throws BusinessException
     */
    public function dueAt(DateTimeImmutable $start, int $minutes, SlaPolicy $policy, array $schedule, array $holidayDates): DateTimeImmutable
    {
        $remaining = $minutes;
        $cursor    = $start;

        for ($i = 0; $i < self::MAX_DAYS; $i++) {
            $day    = $cursor->setTime(0, 0);
            $window = $this->windowFor($day, $policy, $schedule, $holidayDates);

            if ($window !== null) {
                $from      = max($window[0], $cursor);
                $available = intdiv($window[1]->getTimestamp() - $from->getTimestamp(), 60);

                if ($available > 0 && $remaining <= $available) {
                    return $from->modify("+{$remaining} minutes");
                }

                $remaining -= max($available, 0);
            }

            $cursor = $day->modify('+1 day');
        }

        throw new BusinessException(lang('Ticket.slaUnreachable'));
    }

    /**
     * Menit SLA yang berjalan dari $from ke $to, dengan aturan hari dan jam yang sama seperti dueAt().
     * Negatif bila $to lebih awal dari $from (mis. sisa waktu ke batas yang sudah lewat).
     *
     * @param array<int, array{is_off: bool, start: ?string, end: ?string}> $schedule
     * @param list<string>                                                  $holidayDates
     */
    public function workingMinutesBetween(DateTimeImmutable $from, DateTimeImmutable $to, SlaPolicy $policy, array $schedule, array $holidayDates): int
    {
        if ($to < $from) {
            return -$this->workingMinutesBetween($to, $from, $policy, $schedule, $holidayDates);
        }

        $total = 0;
        $day   = $from->setTime(0, 0);

        for ($i = 0; $day < $to && $i < self::MAX_DAYS; $i++) {
            $window = $this->windowFor($day, $policy, $schedule, $holidayDates);

            if ($window !== null) {
                $seconds = min($window[1], $to)->getTimestamp() - max($window[0], $from)->getTimestamp();
                $total  += max(intdiv($seconds, 60), 0);
            }

            $day = $day->modify('+1 day');
        }

        return $total;
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}|null jendela hitung hari itu; null = hari dilewati
     */
    private function windowFor(DateTimeImmutable $day, SlaPolicy $policy, array $schedule, array $holidayDates): ?array
    {
        $info = $schedule[(int) $day->format('N')] ?? null;

        if ($policy->is_holiday && in_array($day->format('Y-m-d'), $holidayDates, true)) {
            return null;
        }

        if ($policy->is_off_day && ($info['is_off'] ?? false)) {
            return null;
        }

        if ($policy->is_work_hours && ($info['start'] ?? null) !== null && ($info['end'] ?? null) !== null) {
            return [$this->atTime($day, $info['start']), $this->atTime($day, $info['end'])];
        }

        return [$day, $day->modify('+1 day')];
    }

    private function atTime(DateTimeImmutable $day, string $time): DateTimeImmutable
    {
        return $day->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));
    }
}
