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

if (! function_exists('format_date')) {
    /**
     * Tanggal database → "Kamis, 1 Januari 2026" (nama hari dan bulan Indonesia). Kosong → "—".
     */
    function format_date(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return Time::parse($value)->toLocalizedString('EEEE, d MMMM yyyy');
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

if (! function_exists('format_comment')) {
    /**
     * Isi komentar → HTML aman. Semua teks di-escape lebih dulu; hanya tag buatan fungsi ini yang muncul:
     * **tebal**, *miring*, `kode`, daftar "- butir", dan daftar bernomor "1. butir". Baris biasa tetap apa adanya
     * (pembungkusnya memakai white-space: pre-wrap). "@Nama" untuk nama di $mentionNames dibungkus
     * <span class="mention"> agar bisa disorot.
     *
     * @param list<string> $mentionNames
     */
    function format_comment(string $text, array $mentionNames = []): string
    {
        // Nama terpanjang dicoba lebih dulu, supaya "@Siti Rahma" tidak terpotong oleh "@Siti"
        usort($mentionNames, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $mentionPattern = $mentionNames === [] ? null : '/(?<![\p{L}\p{N}])@(?:'
            . implode('|', array_map(static fn (string $name): string => preg_quote(esc($name), '/'), $mentionNames))
            . ')(?![\p{L}\p{N}])/u';

        $inline = static function (string $line) use ($mentionPattern): string {
            // Potongan `kode` dipisah dulu supaya tanda ** dan * di dalamnya tidak diubah
            $parts = preg_split('/(`[^`\n]+`)/', esc($line), -1, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($parts as $i => $part) {
                if ($i % 2 === 1) {
                    $parts[$i] = '<code>' . substr($part, 1, -1) . '</code>';

                    continue;
                }

                $part      = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/', '<strong>$1</strong>', $part);
                $part = preg_replace('/(?<![\w*])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![\w*])/', '<em>$1</em>', $part);

                $parts[$i] = $mentionPattern === null ? $part : preg_replace($mentionPattern, '<span class="mention">$0</span>', $part);
            }

            return implode('', $parts);
        };

        $blocks = [];   // tiap elemen: ['type' => 'text'|'ul'|'ol', 'lines' => list<string>]

        foreach (preg_split('/\R/', $text) as $line) {
            $type = 'text';

            if (preg_match('/^[-*] (.+)$/', $line, $match) === 1) {
                $type = 'ul';
                $line = $match[1];
            } elseif (preg_match('/^\d+\. (.+)$/', $line, $match) === 1) {
                $type = 'ol';
                $line = $match[1];
            }

            if ($blocks !== [] && $blocks[array_key_last($blocks)]['type'] === $type) {
                $blocks[array_key_last($blocks)]['lines'][] = $line;
            } else {
                $blocks[] = ['type' => $type, 'lines' => [$line]];
            }
        }

        $html = '';

        foreach ($blocks as $block) {
            $lines = array_map($inline, $block['lines']);

            if ($block['type'] === 'text') {
                // Pemisah baris antar-blok hanya di antara dua blok teks; daftar sudah berupa elemen blok
                $html .= implode("\n", $lines);

                continue;
            }

            $html .= '<' . $block['type'] . '><li>' . implode('</li><li>', $lines) . '</li></' . $block['type'] . '>';
        }

        return $html;
    }
}

if (! function_exists('render_comment')) {
    /**
     * Isi komentar siap tampil. Komentar HTML sudah disaring CommentSanitizer saat disimpan; komentar teks lama
     * di-escape dan diformat format_comment().
     *
     * @param array{body: string, body_format?: string} $comment
     * @param list<string>                              $mentionNames
     */
    function render_comment(array $comment, array $mentionNames = []): string
    {
        return ($comment['body_format'] ?? 'TEXT') === 'HTML' ? $comment['body'] : format_comment($comment['body'], $mentionNames);
    }
}

if (! function_exists('format_filesize')) {
    /**
     * Byte → "48 KB" atau "1,2 MB".
     */
    function format_filesize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }

        return number_format(max($bytes / 1024, 1), 0, ',', '.') . ' KB';
    }
}

if (! function_exists('format_ago')) {
    /**
     * Tanggal-waktu database → "Baru saja", "35 mnt lalu", "2 jam lalu", "3 hari lalu".
     */
    function format_ago(string $value): string
    {
        $seconds = max(0, time() - strtotime($value));

        if ($seconds < 60) {
            return 'Baru saja';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60) . ' mnt lalu';
        }

        if ($seconds < 86400) {
            return intdiv($seconds, 3600) . ' jam lalu';
        }

        return intdiv($seconds, 86400) . ' hari lalu';
    }
}

if (! function_exists('format_duration_compact')) {
    /**
     * Menit → bentuk padat untuk badge, mis. 105 → "1J45M", 480 → "8J", 45 → "45M".
     */
    function format_duration_compact(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest  = $minutes % 60;

        return ($hours > 0 ? $hours . 'J' : '') . ($rest > 0 || $hours === 0 ? $rest . 'M' : '');
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
