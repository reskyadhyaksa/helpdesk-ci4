<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\SystemParameterModel;
use Closure;

/**
 * Pengaturan sistem yang diubah admin (halaman admin/settings): daftar per grup, validasi, dan penyimpanan.
 * Definisi parameter (label, tipe, rentang) ada di SystemParameterModel::DEFAULTS.
 */
class SystemParameterService
{
    // Ekstensi yang tidak boleh diizinkan sebagai lampiran, apa pun isi pengaturan
    private const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'com', 'msi', 'sh', 'ps1', 'vbs',
        'js', 'html', 'htm', 'svg', 'asp', 'aspx', 'jsp', 'dll', 'jar',
    ];

    private readonly Closure $iniReader;

    /**
     * @param callable(string): string|null $iniReader pembaca nilai php.ini (bisa diganti di test); bawaan ini_get
     */
    public function __construct(private readonly SystemParameterModel $parameters, ?callable $iniReader = null)
    {
        $this->iniReader = Closure::fromCallable($iniReader ?? static fn (string $name): string => (string) ini_get($name));
    }

    /**
     * @return array<string, list<array{key: string, label: string, help: string, type: string, value: string, min: ?int, max: ?int}>>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (SystemParameterModel::DEFAULTS as $key => $definition) {
            $groups[$definition['group']][] = [
                'key'   => $key,
                'label' => $definition['label'],
                'help'  => $definition['help'],
                'type'  => $definition['type'],
                'value' => $this->parameters->value($key),
                'min'   => $definition['min'] ?? null,
                'max'   => $definition['max'] ?? null,
            ];
        }

        return $groups;
    }

    /**
     * @return array{upload_mb: int, post_mb: int} batas PHP server dalam MB (post_mb 0 = tanpa batas)
     */
    public function phpLimits(): array
    {
        return [
            'upload_mb' => intdiv($this->bytes(($this->iniReader)('upload_max_filesize')), 1048576),
            'post_mb'   => intdiv($this->bytes(($this->iniReader)('post_max_size')), 1048576),
        ];
    }

    /**
     * Menyimpan hanya kunci yang dikenal. Semua nilai divalidasi dulu; satu saja salah, tidak ada yang disimpan.
     *
     * @param array<string, mixed> $input kunci parameter => nilai dari form
     *
     * @throws BusinessException berisi semua kesalahan sekaligus
     */
    public function update(array $input): void
    {
        $clean  = [];
        $errors = [];

        foreach (SystemParameterModel::DEFAULTS as $key => $definition) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $error = $this->validate($definition, trim((string) $input[$key]), $clean[$key]);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        $errors = [...$errors, ...$this->phpLimitErrors($clean)];

        if ($errors !== []) {
            throw new BusinessException(implode(' ', $errors));
        }

        foreach ($clean as $key => $value) {
            $this->parameters->put($key, $value);
        }
    }

    /**
     * @return string|null pesan kesalahan; $clean diisi nilai yang sudah dinormalisasi bila sah
     */
    private function validate(array $definition, string $value, ?string &$clean): ?string
    {
        $label = $definition['label'];

        switch ($definition['type']) {
            case 'BOOL':
                if (! in_array($value, ['0', '1'], true)) {
                    return "{$label}: pilihan tidak valid.";
                }

                break;

            case 'INT':
                if (! ctype_digit($value) || (int) $value < $definition['min'] || (int) $value > $definition['max']) {
                    return "{$label}: isi bilangan bulat {$definition['min']} sampai {$definition['max']}.";
                }

                $value = (string) (int) $value;
                break;

            default:
                // Spasi di sekitar koma dirapikan; spasi di dalam nama ekstensi ("jp g", "jpg png") tetap ditolak
                $value = preg_replace('/\s*,\s*/', ',', strtolower($value));

                if (preg_match('/^[a-z0-9]{1,10}(,[a-z0-9]{1,10})*$/', $value) !== 1) {
                    return "{$label}: isi huruf kecil atau angka dipisah koma, mis. jpg,png,pdf.";
                }

                $forbidden = array_intersect(explode(',', $value), self::FORBIDDEN_EXTENSIONS);

                if ($forbidden !== []) {
                    return "{$label}: ekstensi " . implode(', ', $forbidden) . ' tidak boleh diizinkan.';
                }
        }

        $clean = $value;

        return null;
    }

    /**
     * Ukuran file tidak boleh melebihi upload_max_filesize, dan ukuran × jumlah tidak boleh melebihi post_max_size
     * (bila terlampaui PHP membuang seluruh isian form, termasuk token CSRF, dan pengguna hanya melihat error 403).
     *
     * @param array<string, string> $clean nilai baru yang sudah sah
     *
     * @return list<string>
     */
    private function phpLimitErrors(array $clean): array
    {
        if (! isset($clean[SystemParameterModel::ATTACHMENT_MAX_SIZE_MB]) && ! isset($clean[SystemParameterModel::ATTACHMENT_MAX_FILES])) {
            return [];
        }

        $size   = (int) ($clean[SystemParameterModel::ATTACHMENT_MAX_SIZE_MB] ?? $this->parameters->value(SystemParameterModel::ATTACHMENT_MAX_SIZE_MB));
        $files  = (int) ($clean[SystemParameterModel::ATTACHMENT_MAX_FILES] ?? $this->parameters->value(SystemParameterModel::ATTACHMENT_MAX_FILES));
        $limits = $this->phpLimits();
        $errors = [];

        if ($size > $limits['upload_mb']) {
            $errors[] = "Ukuran maksimal per file ({$size} MB) melebihi batas upload PHP server ({$limits['upload_mb']} MB). Naikkan upload_max_filesize di php.ini atau perkecil nilainya.";
        }

        if ($limits['post_mb'] > 0 && $size * $files > $limits['post_mb']) {
            $errors[] = 'Ukuran file × jumlah file (' . ($size * $files) . " MB) melebihi post_max_size PHP server ({$limits['post_mb']} MB). Kurangi jumlah atau ukuran file.";
        }

        return $errors;
    }

    /**
     * "2M" → 2097152, "512K" → 524288, "1G" → 1073741824, "0" → 0.
     */
    private function bytes(string $shorthand): int
    {
        $shorthand = trim($shorthand);
        $number    = (int) $shorthand;

        return match (strtoupper(substr($shorthand, -1))) {
            'G'     => $number * 1073741824,
            'M'     => $number * 1048576,
            'K'     => $number * 1024,
            default => $number,
        };
    }
}
