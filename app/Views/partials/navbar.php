<?php
$user = auth_user();

// Halaman yang belum dibuat memakai '#'. Ganti dengan url_to('nama.route') saat halamannya ada.
if ($user === null) {
    $menu = [
        ['label' => 'Beranda', 'icon' => 'home.svg', 'href' => url_to('home'), 'active' => url_is('/')],
        ['label' => 'FAQ & Help Center', 'icon' => 'shield-question.svg', 'href' => '#', 'active' => false],
        ['label' => 'Ticket Status', 'icon' => 'doc-search.svg', 'href' => url_to('home') . '#lacak-tiket', 'active' => false],
    ];
} elseif ($user['is_admin']) {
    $menu = [
        ['label' => 'Dashboard', 'icon' => 'home.svg', 'href' => url_to('admin.dashboard'), 'active' => url_is('admin')],
    ];
} else {
    $menu = [
        ['label' => 'Dashboard', 'icon' => 'home.svg', 'href' => url_to('user.dashboard'), 'active' => url_is('u')],
        ['label' => 'Riwayat Tiket & Pengajuan', 'icon' => 'doc-search.svg', 'href' => '#', 'active' => false],
        ['label' => 'FAQ & Help Center', 'icon' => 'shield-question.svg', 'href' => '#', 'active' => false],
    ];
}
?>
<nav class="navbar app-navbar" role="navigation" aria-label="Navigasi utama">
    <div class="container">
        <div class="navbar-brand">
            <a class="navbar-item" href="<?= url_to('home') ?>">
                <span class="brand-logo mr-3"><img src="<?= base_url('images/helpdesk.svg') ?>" alt="" width="20" height="20"></span>
                <span>
                    <strong class="is-block is-size-5">HC Helpdesk</strong>
                    <small class="is-block is-size-7 has-text-grey">Portal Bantuan Divisi Human Capital</small>
                </span>
            </a>

            <a role="button" class="navbar-burger" aria-label="Menu" aria-expanded="false" data-target="mainNavbar">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </a>
        </div>

        <div id="mainNavbar" class="navbar-menu">
            <div class="navbar-start is-flex-grow-1 is-justify-content-center">
                <?php foreach ($menu as $item): ?>
                    <a class="navbar-item is-tab <?= $item['active'] ? 'is-active' : '' ?>" href="<?= esc($item['href']) ?>">
                        <img class="mr-2" src="<?= base_url('images/' . $item['icon']) ?>" alt="" width="18" height="18">
                        <?= esc($item['label']) ?>
                    </a>
                <?php endforeach ?>
            </div>

            <div class="navbar-end">
                <?php if ($user === null): ?>
                    <div class="navbar-item">
                        <div class="buttons">
                            <a class="button is-primary is-rounded" href="#">+ Buat Tiket</a>
                            <a class="button is-primary is-outlined is-rounded" href="<?= url_to('login') ?>">Masuk</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link" tabindex="0">
                            <span class="avatar mr-3"><?= esc(strtoupper(mb_substr($user['name'], 0, 2))) ?></span>
                            <span>
                                <strong class="is-block is-size-7"><?= esc($user['name']) ?></strong>
                                <small class="is-block is-size-7 has-text-grey">NIK: <?= esc($user['employee_no']) ?></small>
                            </span>
                        </a>

                        <div class="navbar-dropdown is-right">
                            <?php if (! $user['is_admin']): ?>
                                <a class="navbar-item has-text-weight-bold" href="#">+ Buat Tiket Baru</a>
                                <hr class="navbar-divider">
                            <?php endif ?>
                            <form action="<?= url_to('logout') ?>" method="post">
                                <?= csrf_field() ?>
                                <button type="submit" class="navbar-item navbar-item-button has-text-danger has-text-weight-semibold">Keluar</button>
                            </form>
                        </div>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</nav>
