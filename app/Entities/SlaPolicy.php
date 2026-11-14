<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class SlaPolicy extends Entity
{
    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_LOW    = 'low';

    // Urutan = urutan tampil di pilihan form
    public const PRIORITY_LABELS = [
        self::PRIORITY_URGENT => 'P1 Urgent',
        self::PRIORITY_HIGH   => 'P2 High',
        self::PRIORITY_MEDIUM => 'P3 Medium',
        self::PRIORITY_LOW    => 'P4 Low',
    ];

    private const PRIORITY_TAG_CLASSES = [
        self::PRIORITY_URGENT => 'is-danger',
        self::PRIORITY_HIGH   => 'is-warning',
        self::PRIORITY_MEDIUM => 'is-info',
        self::PRIORITY_LOW    => 'is-success',
    ];

    /**
     * is_holiday: tanggal merah & cuti bersama tidak dihitung dalam target SLA.
     * is_off_day: hari off mingguan (default Sabtu & Minggu) tidak dihitung dalam target SLA.
     * is_work_hours: target SLA hanya berjalan pada jam kerja tiap hari (diatur di weekly_off_days).
     */
    protected $casts = [
        'response_minutes'   => 'integer',
        'resolution_minutes' => 'integer',
        'is_holiday'         => 'boolean',
        'is_off_day'         => 'boolean',
        'is_work_hours'      => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function priorityLabel(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? $this->priority;
    }

    public function priorityTagClass(): string
    {
        return self::PRIORITY_TAG_CLASSES[$this->priority] ?? 'is-light';
    }
}
