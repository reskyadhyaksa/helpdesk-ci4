<?php
$user     = auth_user();
$initials = $user === null ? '' : strtoupper(mb_substr($user['name'], 0, 2));

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
                    <small class="is-block is-size-7 has-text-grey-dark is-hidden-mobile is-hidden-desktop-only is-hidden-widescreen-only">Portal Bantuan Divisi Human Capital</small>
                </span>
            </a>

            <?php if ($user !== null): ?>
                <!-- Layar sentuh: lonceng tetap terlihat di bar atas, tidak tersembunyi di menu burger -->
                <div class="navbar-item brand-action is-hidden-desktop">
                    <a class="button is-light is-rounded icon-button" href="#" aria-label="Notifikasi">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    </a>
                </div>
            <?php endif ?>

            <button type="button" class="navbar-burger" aria-label="Menu" aria-expanded="false" aria-controls="mainNavbar" data-target="mainNavbar">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>
        </div>

        <div id="mainNavbar" class="navbar-menu">
            <div class="navbar-start is-flex-grow-1 is-justify-content-center">
                <?php foreach ($menu as $item): ?>
                    <a class="navbar-item is-tab <?= $item['active'] ? 'is-active' : '' ?>" href="<?= esc($item['href']) ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>>
                        <img class="mr-2 is-hidden-desktop-only is-hidden-widescreen-only" src="<?= base_url('images/' . $item['icon']) ?>" alt="" width="20" height="20">
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
                    <div class="navbar-item has-dropdown is-hoverable profile-menu">
                        <a class="navbar-link is-arrowless is-hidden-touch" role="button" tabindex="0" aria-haspopup="true">
                            <span class="profile-name has-text-right is-hidden-desktop-only">
                                <strong class="is-block" title="<?= esc($user['name'], 'attr') ?>"><?= esc($user['name']) ?></strong>
                                <small class="is-block is-size-7 has-text-grey-dark"><?= esc($user['employee_no']) ?></small>
                            </span>
                            <span class="avatar"><?= esc($initials) ?></span>
                        </a>

                        <div class="navbar-dropdown is-right profile-dropdown">
                            <div class="profile-header">
                                <span class="avatar"><?= esc($initials) ?></span>
                                <span class="profile-header-text">
                                    <strong class="is-block"><?= esc($user['name']) ?></strong>
                                    <small class="is-block is-size-7 has-text-grey-dark">NIK <?= esc($user['employee_no']) ?></small>
                                </span>
                                <span class="tag is-rounded profile-role is-hidden-touch"><?= esc($user['role_name']) ?></span>
                            </div>

                            <a class="navbar-item profile-cta" href="#">
                                <span class="menu-icon"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i></span>
                                Buat Tiket Baru
                            </a>
                            <!-- Di layar sentuh sudah ada di navigasi, jadi disembunyikan -->
                            <a class="navbar-item is-hidden-touch" href="#">
                                <span class="menu-icon"><i class="fa-solid fa-ticket" aria-hidden="true"></i></span>
                                Riwayat Tiket &amp; Pengajuan
                            </a>
                            <?php if ($user['is_admin']): ?>
                                <a class="navbar-item" href="<?= url_to('admin.dashboard') ?>">
                                    <span class="menu-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span>
                                    Menu Admin
                                </a>
                            <?php endif ?>

                            <hr class="navbar-divider">
                            <form action="<?= url_to('logout') ?>" method="post">
                                <?= csrf_field() ?>
                                <button type="submit" class="navbar-item navbar-item-button">
                                    <span class="menu-icon"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></span>
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>

                    <span class="vertical-divider is-hidden-touch" aria-hidden="true"></span>

                    <div class="navbar-item is-hidden-touch">
                        <a class="button is-light is-rounded icon-button" href="#" aria-label="Notifikasi">
                            <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        </a>
                    </div>

                    <?php if (! $user['is_admin']): ?>
                        <div class="navbar-item is-hidden-touch">
                            <a class="button is-primary is-rounded" href="#">
                                <span class="icon is-small"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                                <span>Buat Tiket</span>
                            </a>
                        </div>
                    <?php endif ?>
                <?php endif ?>
            </div>
        </div>
    </div>
</nav>
