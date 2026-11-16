<?php

use App\Entities\User;
use App\Models\PermissionModel;
use CodeIgniter\Pager\Pager;

/**
 * @var list<User>   $users
 * @var Pager        $pager
 * @var list<array>  $roles
 * @var string       $keyword
 * @var array<string, list<string>> $filters        filter profil aktif (kolom => daftar nilai)
 * @var array<string, list<string>> $filterOptions  pilihan dropdown per kolom
 */
$canUpdate   = auth_can(PermissionModel::ADMIN_USERS_UPDATE);
$canAccess   = auth_can(PermissionModel::ADMIN_USERS_CATEGORY_ACCESS);
$hasActions  = $canUpdate || $canAccess;
$myId       = auth_user()['id'];
$roleOptions = array_column($roles, 'name', 'id');

// Teks dropdown filter: opsi kosong = tanpa filter. Kolom sesuai UserModel::FILTER_COLUMNS.
$filterLabels = [
    'job_title'      => 'Semua jabatan',
    'position_level' => 'Semua level',
    'organization'   => 'Semua organisasi',
];

$dropdownFilters = [];

foreach ($filterLabels as $column => $allLabel) {
    $dropdownFilters[$column] = [
        'placeholder' => $allLabel,
        'options'     => array_combine($filterOptions[$column], $filterOptions[$column]),
        'selected'    => $filters[$column] ?? [],
    ];
}

$columns = [
    ['label' => 'Pengguna', 'width' => 20],
    ['label' => 'NIK', 'width' => 9],
    ['label' => 'Jabatan', 'width' => 17],
    ['label' => 'Role', 'width' => 11],
    ['label' => 'Login Terakhir', 'width' => 12],
    ['label' => 'Status', 'width' => 8],
];

if ($hasActions) {
    $columns[] = ['label' => 'Aksi', 'width' => 11];
}
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.users.title'),
        'subtitle' => lang('Admin.pages.users.subtitle'),
    ]) ?>

    <?= component('filter_bar', [
        'action'      => url_to('admin.users.index'),
        'keyword'     => $keyword,
        'placeholder' => lang('Admin.users.searchPlaceholder'),
        'isFiltered'  => $keyword !== '' || $filters !== [],
        'filters'     => $dropdownFilters,
    ]) ?>

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
                <td>
                    <?= esc($user->job_title ?? '—') ?>
                    <?php $jobMeta = implode(' · ', array_filter([$user->position_level, $user->organization])) ?>
                    <?php if ($jobMeta !== ''): ?>
                        <small><?= esc($jobMeta) ?></small>
                    <?php endif ?>
                </td>
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
                <?php if ($hasActions): ?>
                    <td class="has-text-right">
                        <?php if ($isMe): ?>
                            <span class="is-size-7 has-text-grey" title="<?= esc(lang('Admin.users.cannotChangeOwnRole'), 'attr') ?>">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i> Akun Anda
                            </span>
                        <?php else: ?>
                            <div class="buttons is-right is-flex-wrap-nowrap">
                            <?php if ($canUpdate): ?>
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
                            <?php if ($canAccess): ?>
                                <a class="button is-small is-light" href="<?= url_to('admin.users.category-access', $user->id) ?>"
                                    aria-label="Akses kategori <?= esc($user->name, 'attr') ?>">
                                    <span class="icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span>
                                    <span>Akses kategori</span>
                                </a>
                            <?php endif ?>
                            </div>
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
