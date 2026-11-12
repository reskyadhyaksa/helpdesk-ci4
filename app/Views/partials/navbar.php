<?php
$user = auth_user();

// Halaman yang belum dibuat memakai '#'. Ganti dengan url_to('nama.route') saat halamannya ada.
if ($user === null) {
    $menu = [
        ['label' => 'Beranda', 'href' => url_to('home'), 'active' => url_is('/')],
        ['label' => 'FAQ & Help Center', 'href' => '#', 'active' => false],
        ['label' => 'Ticket Status', 'href' => '#', 'active' => false],
    ];
} elseif ($user['is_admin']) {
    $menu = [
        ['label' => 'Dashboard', 'href' => url_to('admin.dashboard'), 'active' => url_is('admin')],
    ];
} else {
    $menu = [
        ['label' => 'Dashboard', 'href' => url_to('user.dashboard'), 'active' => url_is('u')],
        ['label' => 'Riwayat Tiket', 'href' => '#', 'active' => false],
        ['label' => 'FAQ & Help Center', 'href' => '#', 'active' => false],
    ];
}
?>
<nav class="navbar is-light" role="navigation" aria-label="Navigasi utama">
    <div class="navbar-brand">
        <a class="navbar-item has-text-weight-bold" href="<?= url_to('home') ?>">HC Helpdesk</a>

        <a role="button" class="navbar-burger" aria-label="Menu" aria-expanded="false" data-target="mainNavbar">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>

    <div id="mainNavbar" class="navbar-menu">
        <div class="navbar-start">
            <?php foreach ($menu as $item): ?>
                <a class="navbar-item <?= $item['active'] ? 'is-active' : '' ?>" href="<?= esc($item['href']) ?>">
                    <?= esc($item['label']) ?>
                </a>
            <?php endforeach ?>
        </div>

        <div class="navbar-end">
            <div class="navbar-item">
                <?php if ($user === null): ?>
                    <a class="button is-primary" href="<?= url_to('login') ?>">Login</a>
                <?php else: ?>
                    <span class="mr-3"><?= esc($user['name']) ?> <span class="tag is-info is-light"><?= esc($user['role_name']) ?></span></span>
                    <form action="<?= url_to('logout') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="button is-light">Logout</button>
                    </form>
                <?php endif ?>
            </div>
        </div>
    </div>
</nav>
