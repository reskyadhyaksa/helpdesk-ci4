<?php
/**
 * Header halaman admin: judul, deskripsi, tombol aksi, dan (di area admin) lonceng notifikasi, semuanya satu baris
 * dengan garis pemisah di bawahnya. Satu desain untuk semua halaman, jadi ubah di sini saja.
 *
 *   <?= component('page_header', ['title' => 'Roles', 'subtitle' => '...', 'action' => 'Tambah Role', 'actionHref' => url_to('admin.roles.new')]) ?>
 *
 * Tanpa $actionHref tombol tampil nonaktif ("Segera hadir") karena fiturnya belum dibuat.
 * Tombol tambahan (mis. form POST "Sync") ditulis lewat slot dan tampil sebelum tombol utama:
 *
 *   <?php component_start('page_header', [...]) ?>
 *       <form ...><button class="button is-light">...</button></form>
 *   <?= component_end() ?>
 *
 * Halaman detail memakai $back untuk tautan kembali di atas judul:
 *
 *   <?= component('page_header', ['title' => '...', 'back' => ['href' => url_to('admin.tickets.index'), 'label' => 'Semua tiket']]) ?>
 *
 * Gaya di public/css/components/page-header.css.
 *
 * @var string      $title
 * @var string      $subtitle
 * @var string|null $action      Label tombol aksi
 * @var string|null $actionIcon  Ikon Font Awesome, default fa-plus
 * @var string|null $actionHref  Tujuan tombol aksi
 * @var array|null  $back        href dan label tautan kembali
 * @var bool|null   $withBell    lonceng notifikasi di kanan; default hanya di area /admin (portal user sudah punya di navbar)
 * @var string      $slot        Tombol tambahan
 */
$subtitle   ??= null;
$action     ??= null;
$actionIcon ??= 'fa-plus';
$actionHref ??= null;
$back       ??= null;
$withBell   ??= url_is('admin*');
$slot       ??= '';

$hasActions = $action !== null || trim($slot) !== '' || $withBell;
?>
<header class="admin-page-header">
    <div class="admin-page-header-main">
        <?php if ($back !== null): ?>
            <a class="admin-page-header-back" href="<?= esc($back['href'], 'attr') ?>">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span><?= esc($back['label']) ?></span>
            </a>
        <?php endif ?>

        <h1 class="admin-page-header-title"><?= esc($title) ?></h1>

        <?php if ($subtitle !== null): ?>
            <p class="admin-page-header-subtitle"><?= esc($subtitle) ?></p>
        <?php endif ?>
    </div>

    <?php if ($hasActions): ?>
        <div class="admin-page-header-actions">
            <?= $slot ?>

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

            <?php if ($withBell): ?>
                <?= view('partials/notification_bell') ?>
            <?php endif ?>
        </div>
    <?php endif ?>
</header>
