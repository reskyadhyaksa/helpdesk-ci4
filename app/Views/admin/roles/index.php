<?php

use App\Models\PermissionModel;
use App\Models\RoleModel;

// Tombol aksi hanya tampil sesuai permission (route tetap menjaga aksesnya)
$canCreate = auth_can(PermissionModel::ADMIN_ROLES_CREATE);
$canUpdate = auth_can(PermissionModel::ADMIN_ROLES_UPDATE);
$canDelete = auth_can(PermissionModel::ADMIN_ROLES_DELETE);

// Tanpa izin aksi apa pun: kolom Aksi disembunyikan, diganti satu keterangan di atas tabel
$hasActions = $canUpdate || $canDelete;
$columns    = [
    ['label' => 'Role', 'width' => 16],
    ['label' => 'Pengguna', 'width' => 10],
    ['label' => 'Permission', 'width' => 11],
];

if ($hasActions) {
    $columns[] = ['label' => 'Aksi', 'width' => 12];
}
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'      => lang('Admin.pages.roles.title'),
        'subtitle'   => lang('Admin.pages.roles.subtitle'),
        'action'     => $canCreate ? lang('Admin.pages.roles.action') : null,
        'actionHref' => url_to('admin.roles.new'),
    ]) ?>

    <?php if (! $hasActions): ?>
        <?= component('readonly_notice', ['message' => 'Anda tidak punya izin mengubah atau menghapus role.']) ?>
    <?php endif ?>

    <?php component_start('data_table', [
        'columns'   => $columns,
        'emptyIcon' => 'fa-user-shield',
        'pager'     => $pager,
        'totalLabel' => 'role',
    ]) ?>
        <?php foreach ($roles as $role): ?>
            <?php
            $isSystem   = in_array($role['slug'], RoleModel::SYSTEM_SLUGS, true);
            $usersCnt   = (int) $role['users_count'];
            $deleteHint = 'Hapus role';

            if ($usersCnt > 0) {
                $deleteHint = lang('Admin.roles.hasUsers');
            }

            if ($isSystem) {
                $deleteHint = lang('Admin.roles.systemCannotDelete');
            }

            $isDeletable = ! $isSystem && $usersCnt === 0;
            ?>
            <tr>
                <td>
                    <span class="is-flex is-align-items-center" style="gap: 0.5rem">
                        <strong><?= esc($role['name']) ?></strong>
                        <?php if ($isSystem): ?>
                            <span class="tag is-link is-light is-rounded">Sistem</span>
                        <?php endif ?>
                    </span>
                </td>
                <td><?= $usersCnt ?> pengguna</td>
                <td><?= (int) $role['permissions_count'] ?> permission</td>
                <?php if ($hasActions): ?>
                    <td>
                        <div class="buttons is-right is-flex-wrap-nowrap">
                            <?php if ($canUpdate): ?>
                                <a class="button is-small is-light" href="<?= url_to('admin.roles.edit', $role['id']) ?>">
                                    <span class="icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></span>
                                    <span>Ubah</span>
                                </a>
                            <?php endif ?>
                            <?php if ($canDelete): ?>
                                <form action="<?= url_to('admin.roles.delete', $role['id']) ?>" method="post" data-confirm="<?= esc(lang('Admin.roles.confirmDelete'), 'attr') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="button is-small is-danger is-light"
                                        <?= $isDeletable ? '' : 'disabled' ?>
                                        title="<?= esc($deleteHint, 'attr') ?>"
                                        aria-label="Hapus <?= esc($role['name'], 'attr') ?>">
                                        <span class="icon"><i class="fa-solid fa-trash" aria-hidden="true"></i></span>
                                        <span>Hapus</span>
                                    </button>
                                </form>
                            <?php endif ?>
                        </div>
                    </td>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
    <?= component_end() ?>
</section>
<?= $this->endSection() ?>
