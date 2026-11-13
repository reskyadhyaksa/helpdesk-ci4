<?php

use App\Entities\User;
use App\Models\PermissionModel;
use CodeIgniter\Pager\Pager;

/**
 * @var list<User>   $users
 * @var Pager        $pager
 * @var list<array>  $roles
 * @var string       $keyword
 */
$canUpdate   = auth_can(PermissionModel::ADMIN_USERS_UPDATE);
$myId        = auth_user()['id'];
$roleOptions = array_column($roles, 'name', 'id');

$columns = ['Pengguna', 'NIK', 'Jabatan', 'Role', 'Login Terakhir', 'Status'];

if ($canUpdate) {
    $columns[] = ['label' => 'Aksi', 'align' => 'right'];
}
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.users.title'),
        'subtitle' => lang('Admin.pages.users.subtitle'),
    ]) ?>

    <form class="admin-toolbar" action="<?= url_to('admin.users.index') ?>" method="get" role="search">
        <div class="field has-addons mb-0">
            <div class="control has-icons-left is-expanded">
                <label class="is-sr-only" for="q"><?= esc(lang('Admin.users.searchPlaceholder')) ?></label>
                <input class="input" id="q" name="q" type="search" maxlength="100"
                    value="<?= esc($keyword, 'attr') ?>" placeholder="<?= esc(lang('Admin.users.searchPlaceholder'), 'attr') ?>">
                <span class="icon is-left"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
            </div>
            <div class="control">
                <button type="submit" class="button is-primary">Cari</button>
            </div>
        </div>
        <?php if ($keyword !== ''): ?>
            <a class="button is-light" href="<?= url_to('admin.users.index') ?>">Reset</a>
        <?php endif ?>
    </form>

    <?php if (! $canUpdate): ?>
        <?= component('readonly_notice', ['message' => 'Anda tidak punya izin mengubah role pengguna.']) ?>
    <?php endif ?>

    <?php component_start('data_table', [
        'columns'      => $columns,
        'emptyIcon'    => 'fa-users',
        'emptyTitle'   => lang('Admin.users.emptyTitle'),
        'emptyMessage' => lang('Admin.users.emptyMessage'),
        'pager'        => $pager,
        'totalLabel'   => 'pengguna',
    ]) ?>
        <?php foreach ($users as $user): ?>
            <?php $isMe = $user->id === $myId ?>
            <tr>
                <td>
                    <div class="is-flex is-align-items-center" style="gap: 0.75rem">
                        <span class="avatar is-flex-shrink-0"><?= esc(initials($user->name)) ?></span>
                        <div>
                            <strong><?= esc($user->name) ?></strong>
                            <?php if ($isMe): ?>
                                <span class="tag is-link is-light is-rounded ml-1">Anda</span>
                            <?php endif ?>
                            <small><?= esc($user->email ?? '—') ?></small>
                        </div>
                    </div>
                </td>
                <td><?= esc($user->employee_no) ?></td>
                <td><?= esc($user->job_title ?? '—') ?></td>
                <td>
                    <span class="tag is-link is-light is-rounded">
                        <i class="fa-solid fa-user-shield mr-1" aria-hidden="true"></i><?= esc($user->role_name) ?>
                    </span>
                </td>
                <td style="white-space: nowrap"><?= esc(format_datetime($user->last_login_time)) ?></td>
                <td>
                    <?php if ($user->is_active): ?>
                        <span class="tag is-success is-light is-rounded">Aktif</span>
                    <?php else: ?>
                        <span class="tag is-light is-rounded">Nonaktif</span>
                    <?php endif ?>
                </td>
                <?php if ($canUpdate): ?>
                    <td class="has-text-right">
                        <?php if ($isMe): ?>
                            <span class="is-size-7 has-text-grey" title="<?= esc(lang('Admin.users.cannotChangeOwnRole'), 'attr') ?>">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i> Akun Anda
                            </span>
                        <?php else: ?>
                            <button type="button" class="button is-small is-light"
                                data-modal-open="roleModal"
                                data-role-action="<?= url_to('admin.users.role', $user->id) ?>"
                                data-role-id="<?= esc($user->role_id, 'attr') ?>"
                                data-user-name="<?= esc($user->name, 'attr') ?>"
                                data-user-nik="<?= esc($user->employee_no, 'attr') ?>"
                                data-user-initials="<?= esc(initials($user->name), 'attr') ?>"
                                aria-label="Ubah role <?= esc($user->name, 'attr') ?>">
                                <span class="icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></span>
                                <span>Ubah Role</span>
                            </button>
                        <?php endif ?>
                    </td>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
    <?= component_end() ?>
</section>

<?php if ($canUpdate): ?>
    <?php // Satu modal untuk semua baris; isinya diisi js/pages/admin/users.js dari tombol "Ubah Role" ?>
    <?php component_start('modal', [
        'id'       => 'roleModal',
        'title'    => 'Ubah Role Pengguna',
        'subtitle' => 'Role baru berlaku setelah pengguna login ulang.',
        'form'     => ['action' => '', 'attributes' => ['data-role-form' => '']],
    ]) ?>
        <div class="role-modal-user">
            <span class="avatar" data-role-initials></span>
            <div>
                <strong class="is-block" data-role-name></strong>
                <small class="has-text-grey-dark">NIK <span data-role-nik></span></small>
            </div>
        </div>

        <div class="field">
            <?= component('select', [
                'name'      => 'role_id',
                'id'        => 'roleModalSelect',
                'label'     => 'Role',
                'options'   => $roleOptions,
                'fullwidth' => true,
            ]) ?>
        </div>
    <?php component_slot('footer') ?>
        <button type="button" class="button is-light" data-modal-close>Batal</button>
        <button type="submit" class="button is-primary">
            <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
            <span>Simpan</span>
        </button>
    <?= component_end() ?>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/users.js') ?>"></script>
<?= $this->endSection() ?>
