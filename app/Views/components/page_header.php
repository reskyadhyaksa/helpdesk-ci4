<?php
/**
 * Header halaman admin: judul, deskripsi, dan tombol aksi utama (opsional).
 * Tanpa $actionHref tombol tampil nonaktif ("Segera hadir") karena fiturnya belum dibuat.
 *
 * @var string      $title
 * @var string      $subtitle
 * @var string|null $action      Label tombol aksi
 * @var string|null $actionIcon  Ikon Font Awesome, default fa-plus
 * @var string|null $actionHref  Tujuan tombol aksi
 */
$action     ??= null;
$actionIcon ??= 'fa-plus';
$actionHref ??= null;
?>
<div class="admin-page-header">
    <div>
        <h1 class="title is-4 mb-1"><?= esc($title) ?></h1>
        <p class="has-text-grey-dark"><?= esc($subtitle) ?></p>
    </div>

    <?php if ($action !== null && $actionHref !== null): ?>
        <a class="button is-primary" href="<?= esc($actionHref, 'attr') ?>">
            <span class="icon"><i class="fa-solid <?= esc($actionIcon, 'attr') ?>" aria-hidden="true"></i></span>
            <span><?= esc($action) ?></span>
        </a>
    <?php elseif ($action !== null): ?>
        <button type="button" class="button is-primary" disabled title="<?= esc(lang('Admin.comingSoon'), 'attr') ?>">
            <span class="icon"><i class="fa-solid <?= esc($actionIcon, 'attr') ?>" aria-hidden="true"></i></span>
            <span><?= esc($action) ?></span>
        </button>
    <?php endif ?>
</div>
