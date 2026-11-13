<?php
/**
 * Keterangan "mode lihat saja" di atas tabel, untuk pengguna tanpa izin aksi apa pun.
 * Ditulis sekali di atas tabel, bukan "tidak ada akses" di setiap baris.
 *
 *   <?php if (! $canUpdate && ! $canDelete): ?>
 *       <?= component('readonly_notice', ['message' => 'Anda tidak punya izin mengubah atau menghapus role.']) ?>
 *   <?php endif ?>
 *
 * @var string $message
 */
?>
<p class="readonly-notice" role="note">
    <i class="fa-solid fa-eye" aria-hidden="true"></i>
    <span><strong>Mode lihat saja.</strong> <?= esc($message) ?></span>
</p>
