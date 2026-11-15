<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

/**
 * Parameter sistem yang bisa diubah admin tanpa deploy. Nilai bawaan ada di DEFAULTS (diisi seeder,
 * dan dipakai sebagai cadangan bila barisnya belum ada).
 */
class SystemParameterModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const ATTACHMENT_MAX_SIZE_MB      = 'ATTACHMENT_MAX_SIZE_MB';
    public const ATTACHMENT_MAX_FILES        = 'ATTACHMENT_MAX_FILES';
    public const ATTACHMENT_RETENTION_DAYS   = 'ATTACHMENT_RETENTION_DAYS';
    public const ATTACHMENT_ALLOWED_EXTENSIONS = 'ATTACHMENT_ALLOWED_EXTENSIONS';

    // Kanal notifikasi di luar aplikasi (email/WhatsApp) dan jendela peringatan SLA
    public const NOTIFY_EMAIL_ENABLED    = 'NOTIFY_EMAIL_ENABLED';
    public const NOTIFY_WHATSAPP_ENABLED = 'NOTIFY_WHATSAPP_ENABLED';
    public const SLA_WARNING_MINUTES     = 'SLA_WARNING_MINUTES';
    public const SLA_ALERT_MAX_AGE_HOURS = 'SLA_ALERT_MAX_AGE_HOURS';

    /**
     * Satu-satunya definisi parameter: nilai bawaan, tipe, dan metadata halaman pengaturan admin
     * (grup, label, bantuan, rentang untuk INT). Urutan = urutan tampil.
     */
    public const DEFAULTS = [
        self::ATTACHMENT_MAX_SIZE_MB => [
            'value' => '2', 'type' => 'INT', 'group' => 'attachment', 'min' => 1, 'max' => 100,
            'label' => 'Ukuran maksimal per file (MB)', 'help' => 'Tidak boleh melebihi batas upload PHP server.',
        ],
        // 3 file x 2 MB = 6 MB muat di post_max_size bawaan PHP (8M); lebih dari itu seluruh isian form hilang
        self::ATTACHMENT_MAX_FILES => [
            'value' => '3', 'type' => 'INT', 'group' => 'attachment', 'min' => 1, 'max' => 20,
            'label' => 'Jumlah file maksimal per kiriman', 'help' => 'Ukuran file × jumlah file tidak boleh melebihi post_max_size PHP.',
        ],
        self::ATTACHMENT_ALLOWED_EXTENSIONS => [
            'value' => 'jpg,jpeg,png,pdf,docx,xlsx,txt', 'type' => 'STRING', 'group' => 'attachment',
            'label' => 'Ekstensi file yang diizinkan', 'help' => 'Huruf kecil dipisah koma, mis. jpg,png,pdf. File berbahaya (php, exe, html, js, dst.) selalu ditolak.',
        ],
        // Lampiran tiket CLOSED dihapus setelah sekian hari; 0 = tidak pernah dihapus
        self::ATTACHMENT_RETENTION_DAYS => [
            'value' => '14', 'type' => 'INT', 'group' => 'attachment', 'min' => 0, 'max' => 3650,
            'label' => 'Hapus lampiran setelah tiket ditutup (hari)', 'help' => 'Isi 0 untuk tidak pernah menghapus. Yang dihapus hanya file; catatan lampiran tetap ada.',
        ],
        self::NOTIFY_EMAIL_ENABLED => [
            'value' => '0', 'type' => 'BOOL', 'group' => 'notification',
            'label' => 'Kirim notifikasi lewat email', 'help' => 'Nyalakan setelah SMTP dan alamat pengirim dikonfigurasi di server.',
        ],
        self::NOTIFY_WHATSAPP_ENABLED => [
            'value' => '0', 'type' => 'BOOL', 'group' => 'notification',
            'label' => 'Kirim notifikasi lewat WhatsApp', 'help' => 'Nyalakan setelah URL dan token API WhatsApp diisi di server.',
        ],
        self::SLA_WARNING_MINUTES => [
            'value' => '30', 'type' => 'INT', 'group' => 'sla', 'min' => 1, 'max' => 1440,
            'label' => 'Peringatan SLA sebelum batas (menit)', 'help' => 'PIC diberi tahu sekian menit sebelum batas respons/penyelesaian.',
        ],
        // Pelanggaran yang terlewat lebih lama dari ini tidak diperingatkan (mencegah banjir notifikasi saat pertama dijalankan)
        self::SLA_ALERT_MAX_AGE_HOURS => [
            'value' => '24', 'type' => 'INT', 'group' => 'sla', 'min' => 1, 'max' => 720,
            'label' => 'Batas usia peringatan pelanggaran SLA (jam)', 'help' => 'Pelanggaran yang sudah lebih lama dari ini tidak lagi diperingatkan.',
        ],
    ];

    protected $table            = 'system_parameters';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['param_key', 'param_value', 'value_type'];

    protected $validationRules = [
        'param_key'   => 'required|max_length[60]|is_unique[system_parameters.param_key,id,{id}]',
        'param_value' => 'required|max_length[255]',
    ];

    public function value(string $key): string
    {
        $row = $this->where('param_key', $key)->first();

        return $row['param_value'] ?? self::DEFAULTS[$key]['value'];
    }

    /**
     * Menyimpan nilai parameter: memperbarui barisnya, atau membuatnya bila belum ada.
     */
    public function put(string $key, string $value): void
    {
        $row = $this->where('param_key', $key)->first();

        if ($row !== null) {
            $this->update($row['id'], ['id' => $row['id'], 'param_value' => $value]);

            return;
        }

        $this->insert(['param_key' => $key, 'param_value' => $value, 'value_type' => self::DEFAULTS[$key]['type']]);
    }

    public function flag(string $key): bool
    {
        return $this->value($key) === '1';
    }

    public function int(string $key): int
    {
        return (int) $this->value($key);
    }
}
