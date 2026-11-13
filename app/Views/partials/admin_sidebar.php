<?php

use App\Models\PermissionModel;

$user     = auth_user();
$initials = initials($user['name']);

// Label dari Language/id/Admin.php, sama dengan judul di header tiap halaman.
// 'permission' (opsional): menu hanya tampil jika user memilikinya.
$menuGroups = [
    [
        'label' => null,
        'items' => [
            ['page' => 'dashboard', 'icon' => 'fa-table-cells-large', 'route' => 'admin.dashboard', 'path' => 'admin'],
        ],
    ],
    [
        'label' => lang('Admin.groups.tickets'),
        'items' => [
            ['page' => 'tickets', 'icon' => 'fa-ticket', 'route' => 'admin.tickets.index', 'path' => 'admin/tickets'],
            ['page' => 'myTickets', 'icon' => 'fa-user-check', 'route' => 'admin.tickets.mine', 'path' => 'admin/tickets/mine'],
        ],
    ],
    [
        'label' => lang('Admin.groups.settings'),
        'items' => [
            ['page' => 'roles', 'icon' => 'fa-user-shield', 'route' => 'admin.roles.index', 'path' => 'admin/roles*', 'permission' => PermissionModel::ADMIN_ROLES_VIEW],
            ['page' => 'slaPolicies', 'icon' => 'fa-clock', 'route' => 'admin.sla-policies.index', 'path' => 'admin/sla-policies*', 'permission' => PermissionModel::ADMIN_SLA_VIEW],
            ['page' => 'requestCategories', 'icon' => 'fa-folder-open', 'route' => 'admin.request-categories.index', 'path' => 'admin/request-categories*'],
            ['page' => 'faqs', 'icon' => 'fa-circle-question', 'route' => 'admin.faqs.index', 'path' => 'admin/faqs*'],
            ['page' => 'forms', 'icon' => 'fa-file-pen', 'route' => 'admin.forms.index', 'path' => 'admin/forms*'],
        ],
    ],
    [
        'label' => lang('Admin.groups.data'),
        'items' => [
            ['page' => 'users', 'icon' => 'fa-users', 'route' => 'admin.users.index', 'path' => 'admin/users*', 'permission' => PermissionModel::ADMIN_USERS_VIEW],
            ['page' => 'holidays', 'icon' => 'fa-calendar-days', 'route' => 'admin.holidays.index', 'path' => 'admin/holidays*'],
        ],
    ],
    [
        'label' => lang('Admin.groups.reports'),
        'items' => [
            ['page' => 'reports', 'icon' => 'fa-chart-column', 'route' => 'admin.reports.index', 'path' => 'admin/reports*'],
        ],
    ],
];
?>
<!-- Layar sentuh: bar atas berisi brand dan tombol pembuka sidebar -->
<header class="admin-topbar is-hidden-desktop">
    <a class="admin-brand" href="<?= url_to('admin.dashboard') ?>">
        <span class="brand-logo"><img src="<?= base_url('images/helpdesk.svg') ?>" alt="" width="20" height="20"></span>
        <strong class="is-size-5">HC Helpdesk</strong>
        <span class="tag is-link is-light is-rounded has-text-weight-bold">Admin</span>
    </a>

    <button type="button" class="navbar-burger" aria-label="Menu" aria-expanded="false" aria-controls="adminSidebar" data-target="adminSidebar">
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
    </button>
</header>

<aside class="admin-sidebar" id="adminSidebar" aria-label="Navigasi admin">
    <div class="admin-sidebar-head is-hidden-touch">
        <!-- Saat sidebar diperkecil, klik logo membuka kembali sidebar (lihat js/admin.js) -->
        <a class="admin-brand" href="<?= url_to('admin.dashboard') ?>" title="HC Helpdesk" data-sidebar-brand>
            <span class="brand-logo"><img src="<?= base_url('images/helpdesk.svg') ?>" alt="" width="22" height="22"></span>
            <span class="admin-brand-text">
                <span class="is-flex is-align-items-center" style="gap: 0.5rem">
                    <strong class="is-size-5">HC Helpdesk</strong>
                    <span class="tag is-link is-light is-rounded has-text-weight-bold">Admin</span>
                </span>
                <small class="is-block is-size-7 has-text-grey-dark">PT Mandiri Tunas Finance</small>
            </span>
        </a>

        <!-- Desktop: perkecil sidebar. Tombol ini tersembunyi saat sidebar sudah diperkecil. -->
        <button type="button" class="admin-sidebar-toggle" aria-controls="adminSidebar"
            aria-label="<?= esc(lang('Admin.collapseSidebar'), 'attr') ?>" title="<?= esc(lang('Admin.collapseSidebar'), 'attr') ?>"
            data-sidebar-toggle
            data-label-expand="<?= esc(lang('Admin.expandSidebar'), 'attr') ?>">
            <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="menu admin-menu">
        <?php foreach ($menuGroups as $group): ?>
            <?php if ($group['label'] !== null): ?>
                <p class="menu-label"><?= esc($group['label']) ?></p>
            <?php endif ?>

            <ul class="menu-list">
                <?php foreach ($group['items'] as $item): ?>
                    <?php
                    if (isset($item['permission']) && ! auth_can($item['permission'])) {
                        continue;
                    }

                    $isActive = url_is($item['path']);
                    $label    = lang('Admin.pages.' . $item['page'] . '.title');
                    ?>
                    <li>
                        <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= url_to($item['route']) ?>" title="<?= esc($label, 'attr') ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <span class="admin-menu-icon"><i class="fa-solid <?= esc($item['icon'], 'attr') ?>" aria-hidden="true"></i></span>
                            <span class="admin-collapsible-text"><?= esc($label) ?></span>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endforeach ?>
    </nav>

    <div class="admin-sidebar-footer">
        <div class="admin-user">
            <span class="avatar" title="<?= esc($user['name'], 'attr') ?>"><?= esc($initials) ?></span>
            <span class="admin-user-text">
                <strong class="is-block" title="<?= esc($user['name'], 'attr') ?>"><?= esc($user['name']) ?></strong>
                <small class="is-block is-size-7 has-text-grey-dark"><?= esc($user['job_title'] ?? $user['role_name']) ?></small>
            </span>
            <a class="admin-user-action" href="<?= url_to('home') ?>" title="Lihat Portal" aria-label="Lihat Portal">
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
            </a>
        </div>

        <form action="<?= url_to('logout') ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="admin-logout" title="Logout Session">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                <span class="admin-collapsible-text">Logout Session</span>
            </button>
        </form>
    </div>
</aside>
