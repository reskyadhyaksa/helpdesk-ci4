<?php
/*
 * Flash message sebagai toast di pojok kanan atas (gaya: css/components/toast.css,
 * perilaku: js/components/toast.js). Isi dari session flashdata: success, danger, errors.
 * Sukses hilang sendiri setelah 5 detik; gagal/error 8 detik. Hover atau fokus menahan hitungan.
 */
$errors = session('errors') ?? [];

$toasts = [];

if (session('success')) {
    $toasts[] = ['type' => 'success', 'icon' => 'fa-circle-check', 'title' => 'Berhasil', 'messages' => [session('success')], 'timeout' => 5000];
}

if (session('danger')) {
    $toasts[] = ['type' => 'danger', 'icon' => 'fa-circle-exclamation', 'title' => 'Gagal', 'messages' => [session('danger')], 'timeout' => 8000];
}

if ($errors !== []) {
    $toasts[] = ['type' => 'danger', 'icon' => 'fa-triangle-exclamation', 'title' => 'Periksa kembali isian', 'messages' => array_values($errors), 'timeout' => 8000];
}
?>
<?php if ($toasts !== []): ?>
    <div class="toast-region" aria-label="Notifikasi">
        <?php foreach ($toasts as $toast): ?>
            <?php // Error dibacakan langsung oleh screen reader (alert); sukses dibacakan sopan (status) ?>
            <div class="toast is-<?= esc($toast['type']) ?>" role="<?= $toast['type'] === 'success' ? 'status' : 'alert' ?>"
                data-toast data-toast-timeout="<?= (int) $toast['timeout'] ?>">
                <span class="toast-icon"><i class="fa-solid <?= esc($toast['icon'], 'attr') ?>" aria-hidden="true"></i></span>

                <div class="toast-body">
                    <p class="toast-heading">
                        <span class="toast-title"><?= esc($toast['title']) ?></span>
                        <span class="toast-time">Baru saja</span>
                    </p>
                    <?php if (count($toast['messages']) === 1): ?>
                        <p class="toast-message"><?= esc($toast['messages'][0]) ?></p>
                    <?php else: ?>
                        <ul class="toast-message">
                            <?php foreach ($toast['messages'] as $message): ?>
                                <li><?= esc($message) ?></li>
                            <?php endforeach ?>
                        </ul>
                    <?php endif ?>
                </div>

                <button type="button" class="toast-close" aria-label="Tutup notifikasi" data-toast-close>
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>

                <span class="toast-progress" aria-hidden="true"></span>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
