<?php

use App\Entities\User;
use App\Models\RequestCategoryAccessUserModel;

/**
 * Pengecualian akses kategori satu pengguna beserta akses efektifnya.
 *
 * @var User                                                              $user
 * @var list<array>                                                       $categories  semua kategori berurutan (utama lalu sub)
 * @var array<string, array{access: bool, reason: string, detail: ?string}> $explained   kunci = id kategori
 * @var list<array{request_category_id: string, effect: string}>           $exceptions
 * @var string                                                            $roleName    nama role user (alasan "sesuai role")
 */
$errors = session('errors') ?? [];

// id => nama; sub kategori ditulis "Induk › Sub"
$names = array_column($categories, 'name', 'id');
$label = static fn (array $category): string => $category['parent_id'] === null
    ? $category['name']
    : $names[$category['parent_id']] . ' › ' . $category['name'];

$categoryLabels = [];

foreach ($categories as $category) {
    $categoryLabels[$category['id']] = $label($category);
}

$effectOptions = [
    RequestCategoryAccessUserModel::EFFECT_ALLOW => 'Boleh',
    RequestCategoryAccessUserModel::EFFECT_DENY  => 'Blokir',
];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.categoryAccess.title'),
        'subtitle' => lang('Admin.categoryAccess.subtitle'),
    ]) ?>

    <div class="box">
        <div class="is-flex is-align-items-center" style="gap: 0.75rem">
            <span class="avatar is-flex-shrink-0"><?= esc(initials($user->name)) ?></span>
            <div>
                <strong><?= esc($user->name) ?></strong>
                <small>NIK <?= esc($user->employee_no) ?> · <?= esc($user->job_title ?? '—') ?></small>
            </div>
        </div>
        <a class="button is-light is-small mt-3" href="<?= url_to('admin.users.index') ?>">
            <span class="icon"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
            <span>Kembali ke daftar pengguna</span>
        </a>
    </div>

    <h2 class="title is-5"><?= esc(lang('Admin.categoryAccess.effectiveTitle')) ?></h2>
    <?php component_start('data_table', [
        'columns'      => ['Kategori', 'Akses', 'Alasan'],
        'emptyIcon'    => 'fa-folder-open',
        'emptyTitle'   => lang('Admin.requestCategory.emptyTitle'),
        'emptyMessage' => lang('Admin.requestCategory.emptyMessage'),
    ]) ?>
        <?php foreach ($categories as $category): ?>
            <?php $result = $explained[$category['id']] ?>
            <tr>
                <td><strong><?= esc($categoryLabels[$category['id']]) ?></strong></td>
                <td>
                    <?php if ($result['access']): ?>
                        <span class="tag is-success is-light is-rounded">Diizinkan</span>
                    <?php else: ?>
                        <span class="tag is-danger is-light is-rounded">Ditolak</span>
                    <?php endif ?>
                </td>
                <td>
                    <?= esc(lang('Admin.categoryAccess.reasons.' . $result['reason'])) ?>
                    <?php if ($result['reason'] === 'role'): ?>
                        <small><?= esc($roleName) ?></small>
                    <?php elseif ($result['detail'] !== null): ?>
                        <small><?= esc($result['detail']) ?></small>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
    <?= component_end() ?>

    <h2 class="title is-5 mt-5"><?= esc(lang('Admin.categoryAccess.exceptionsTitle')) ?></h2>
    <?php component_start('data_table', [
        'columns'      => ['Kategori', 'Efek', ['label' => 'Aksi', 'align' => 'right']],
        'emptyIcon'    => 'fa-user-lock',
        'emptyTitle'   => 'Belum ada pengecualian',
        'emptyMessage' => 'Pengguna ini hanya mengikuti aturan job title.',
    ]) ?>
        <?php foreach ($exceptions as $exception): ?>
            <?php $categoryName = $categoryLabels[$exception['request_category_id']] ?? '—' ?>
            <tr>
                <td><strong><?= esc($categoryName) ?></strong></td>
                <td>
                    <?php if ($exception['effect'] === RequestCategoryAccessUserModel::EFFECT_ALLOW): ?>
                        <span class="tag is-success is-light is-rounded">Boleh</span>
                    <?php else: ?>
                        <span class="tag is-danger is-light is-rounded">Blokir</span>
                    <?php endif ?>
                </td>
                <td>
                    <div class="buttons is-right is-flex-wrap-nowrap">
                        <form action="<?= url_to('admin.users.category-access.delete', $user->id, $exception['request_category_id']) ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="button is-small is-danger is-light" aria-label="Hapus pengecualian <?= esc($categoryName, 'attr') ?>">
                                <span class="icon"><i class="fa-solid fa-trash" aria-hidden="true"></i></span>
                                <span>Hapus</span>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach ?>
    <?= component_end() ?>

    <form class="box mt-5" action="<?= url_to('admin.users.category-access.store', $user->id) ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <div class="columns">
            <div class="column is-7">
                <?= component('select', [
                    'name'        => 'category_id',
                    'label'       => 'Kategori',
                    'options'     => $categoryLabels,
                    'selected'    => old('category_id'),
                    'placeholder' => 'Pilih kategori',
                    'fullwidth'   => true,
                    'searchable'  => true,
                    'error'       => $errors['category_id'] ?? null,
                ]) ?>
            </div>
            <div class="column is-3">
                <?= component('select', [
                    'name'      => 'effect',
                    'label'     => 'Efek',
                    'options'   => $effectOptions,
                    'selected'  => old('effect', RequestCategoryAccessUserModel::EFFECT_ALLOW),
                    'fullwidth' => true,
                    'error'     => $errors['effect'] ?? null,
                ]) ?>
            </div>
            <div class="column is-2 is-flex is-align-items-flex-end">
                <button type="submit" class="button is-primary is-fullwidth">
                    <span class="icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                    <span>Tambah</span>
                </button>
            </div>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
