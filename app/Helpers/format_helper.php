<?php

use CodeIgniter\I18n\Time;

if (! function_exists('initials')) {
    /**
     * Dua huruf pertama nama untuk avatar, mis. "Fuguh Budi Utomo" → "FU".
     */
    function initials(string $name): string
    {
        return mb_strtoupper(mb_substr(trim($name), 0, 2));
    }
}

if (! function_exists('format_datetime')) {
    /**
     * Tanggal-waktu database → "13 Nov 2026, 14:30" (nama bulan Indonesia). Kosong → "—".
     */
    function format_datetime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return Time::parse($value)->toLocalizedString('d MMM yyyy, HH:mm');
    }
}

if (! function_exists('format_duration')) {
    /**
     * Menit → teks singkat, mis. 45 → "45 menit", 90 → "1 jam 30 menit", 480 → "8 jam".
     */
    function format_duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest  = $minutes % 60;

        if ($hours === 0) {
            return $rest . ' menit';
        }

        if ($rest === 0) {
            return $hours . ' jam';
        }

        return $hours . ' jam ' . $rest . ' menit';
    }
}
