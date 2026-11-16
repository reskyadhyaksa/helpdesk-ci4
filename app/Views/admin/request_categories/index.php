<?php

use App\Models\PermissionModel;

/**
 * @var string                      $keyword
 * @var array<string, list<string>> $filters        filter aktif (kunci => daftar nilai)
 * @var array<string, array>        $filterOptions  pilihan dropdown per filter
 * @var list<array>                 $categories
 * @var array<string, list<string>> $jobTitles  id kategori => job title yang boleh melihat
 * @var array<string, list<string>> $roles      id kategori => nama role yang boleh melihat
 */

// Tombol aksi hanya tampil sesuai permission (route tetap menjaga aksesnya)
$canCreate = auth_can(PermissionModel::ADMIN_REQUEST_CATEGORIES_CREATE);
$canUpdate = auth_can(PermissionModel::ADMIN_REQUEST_CATEGORIES_UPDATE);
$canDelete = auth_can(PermissionModel::ADMIN_REQUEST_CATEGORIES_DELETE);

$hasActions = $canUpdate || $canDelete;
// Teks dropdown filter: opsi kosong = tanpa filter. Kunci sesuai RequestCategoryModel::paginateForAdmin().
$filterLabels = [
    'parent_id' => 'Semua kategori utama',
    'job_title' => 'Semua job title',
    'role_id'   => 'Semua role',
];

$dropdownFilters = [];

foreach ($filterLabels as $name => $allLabel) {
    $dropdownFilters[$name] = [
        'placeholder' => $allLabel,
        'options'     => $filterOptions[$name],
        'selected'    => $filters[$name] ?? [],
    ];
}

$columns = [
    ['label' => 'Nama Kategori', 'width' => 18],
    ['label' => 'Deskripsi', 'width' => 22],
    ['label' => 'SLA', 'width' => 11],
    ['label' => 'Access Group', 'width' => 15],
    ['label' => 'Status', 'width' => 8],
];

if ($hasActions) {
    $columns[] = ['label' => 'Aksi', 'width' => 12];
}
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title' => lang('Admin.pages.requestCategories.title'),
        'subtitle' => lang('Admin.pages.requestCategories.subtitle'),
        'action' => $canCreate ? lang('Admin.pages.requestCategories.action') : null,
        'actionHref' => url_to('admin.request-categories.new'),
    ]) ?>

    <?= component('filter_bar', [
        'action' => url_to('admin.request-categories.index'),
        'keyword' => $keyword,
        'placeholder' => lang('Admin.requestCategory.searchPlaceholder'),
        'isFiltered' => $keyword !== '' || $filters !== [],
        'filters' => $dropdownFilters,
    ]) ?>

    <?php if (!$hasActions): ?>
        <?= component('readonly_notice', ['message' => 'Anda tidak punya izin mengubah atau menghapus kategori.']) ?>
    <?php endif ?>

    <?php component_start('data_table', [
        'columns' => $columns,
        'emptyIcon' => 'fa-folder-open',
        'emptyTitle' => lang('Admin.requestCategory.emptyTitle'),
        'emptyMessage' => lang('Admin.requestCategory.emptyMessage'),
        'pager' => $pager,
        'totalLabel' => 'kategori',
    ]) ?>
    <?php foreach ($categories as $category): ?>
        <?php
        $isSub = $category['parent_id'] !== null;
        $titles = $jobTitles[$category['id']] ?? [];
        $roleNames = $roles[$category['id']] ?? [];
        $name = $isSub ? $category['parent_name'] . ' › ' . $category['name'] : $category['name'];
        ?>
        <tr>
            <td><strong><?= esc($name) ?></strong></td>
            <td>
                <?php if ($category['description'] !== null): ?>
                    <?= esc($category['description']) ?>
                <?php else: ?>
                    <span class="has-text-grey">—</span>
                <?php endif ?>
            </td>
            <td>
                <?php if ($category['sla_name'] !== null): ?>
                    <?= esc($category['sla_name']) ?>
                <?php elseif ($category['parent_id'] !== null): ?>
                    <span class="has-text-grey">Ikut kategori utama</span>
                <?php else: ?>
                    <span class="has-text-grey">—</span>
                <?php endif ?>
            </td>
            <td>
                <?php if ($isSub): ?>
                    <span class="has-text-grey">Sesuai kategori utama</span>
                <?php elseif ($titles === [] && $roleNames === []): ?>
                    <span class="has-text-grey">—</span>
                <?php else: ?>
                    <?php
                    // Satu grup tampil, sisanya jadi "+N" yang menampilkan seluruh daftar saat disorot (js/components/popover.js),
                    // supaya kolom tidak memanjang ke bawah
                    $groups = [];

                    foreach ($titles as $title) {
                        $groups[] = ['class' => 'is-job_title', 'label' => $title];
                    }

                    foreach ($roleNames as $roleName) {
                        $groups[] = ['class' => 'is-role', 'label' => $roleName];
                    }

                    $cardId = 'groups-' . $category['id'];
                    ?>
                    <span class="access-group">
                        <span class="tag is-light is-rounded access-tag <?= $groups[0]['class'] ?>" title="<?= esc($groups[0]['label'], 'attr') ?>"><?= esc($groups[0]['label']) ?></span>
                        <?php if (count($groups) > 1): ?>
                            <button type="button" class="tag is-light is-rounded access-more" data-popover="<?= esc($cardId, 'attr') ?>"
                                aria-describedby="<?= esc($cardId, 'attr') ?>" aria-label="<?= count($groups) - 1 ?> grup akses lainnya">+<?= count($groups) - 1 ?></button>
                            <div class="hover-card" id="<?= esc($cardId, 'attr') ?>" role="tooltip" hidden>
                                <p class="hover-card-title">Access Group (<?= count($groups) ?>)</p>
                                <div class="hover-card-tags">
                                    <?php foreach ($groups as $group): ?>
                                        <span class="tag is-light is-rounded access-tag <?= $group['class'] ?>"><?= esc($group['label']) ?></span>
                                    <?php endforeach ?>
                                </div>
                            </div>
                        <?php endif ?>
                    </span>
                <?php endif ?>
            </td>
            <td>
                <?php if ($category['is_active']): ?>
                    <span class="tag is-success is-light is-rounded">Aktif</span>
                <?php else: ?>
                    <span class="tag is-light is-rounded">Nonaktif</span>
                <?php endif ?>
            </td>
            <?php if ($hasActions): ?>
                <td>
                    <div class="buttons is-right is-flex-wrap-nowrap">
                        <?php if ($canUpdate): ?>
                            <a class="button is-small is-light"
                                href="<?= url_to('admin.request-categories.edit', $category['id']) ?>">
                                <span class="icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></span>
                                <span>Ubah</span>
                            </a>
                        <?php endif ?>
                        <?php if ($canDelete): ?>
                            <form action="<?= url_to('admin.request-categories.delete', $category['id']) ?>" method="post"
                                data-confirm="<?= esc(lang('Admin.requestCategory.confirmDelete'), 'attr') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="button is-small is-danger is-light"
                                    aria-label="Hapus <?= esc($name, 'attr') ?>">
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