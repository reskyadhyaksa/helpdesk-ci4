<?php
/**
 * Baris filter di atas tabel: form GET (filter tersimpan di URL, jadi bisa di-bookmark dan ikut terbawa
 * di link pager), kolom pencarian, dan tombol Reset. Filter khusus halaman ditulis lewat slot, tombol
 * di kanan (mis. "Tambah") lewat slot "actions". Dropdown di slot otomatis mengirim form saat dipilih.
 * Tata letak: [dropdown filter + Reset] di kiri, [kolom cari] di kanan (justify between), [aksi] paling kanan.
 *
 *   <?php component_start('filter_bar', ['action' => url_to('admin.holidays.index'), 'keyword' => $keyword, 'isFiltered' => $isFiltered]) ?>
 *       <div class="admin-toolbar-select"><?= component('select', [... 'hideLabel' => true, 'fullwidth' => true]) ?></div>
 *       <?php component_slot('actions') ?>
 *       <a class="button is-primary" href="...">Tambah</a>
 *   <?= component_end() ?>
 *
 * Dropdown filter standar (multi-pilih dengan checkbox, ada kotak cari) cukup ditulis lewat $filters:
 *
 *   <?= component('filter_bar', ['action' => ..., 'keyword' => $keyword, 'isFiltered' => $isFiltered, 'filters' => [
 *       'job_title' => ['placeholder' => 'Semua jabatan', 'options' => $map, 'selected' => $selected['job_title'] ?? []],
 *   ]]) ?>
 *
 * Tanpa filter khusus cukup: <?= component('filter_bar', ['action' => ..., 'keyword' => $keyword]) ?>
 *
 * @var string      $action       URL halaman (tujuan form dan tombol Reset)
 * @var string      $keyword      kata kunci saat ini
 * @var string|null $placeholder  teks bantuan kolom cari
 * @var string|null $searchName   nama parameter cari, default "q"
 * @var bool|null   $isFiltered   ada filter aktif? default: hanya jika $keyword terisi
 * @var array<string, array{placeholder: string, options: array<string, string>, selected?: list<string>, searchable?: bool, emptyLabel?: string}> $filters
 *                                kunci = nama parameter; placeholder = teks saat tidak ada yang dipilih
 * @var string      $slot         field filter tambahan (dropdown dengan pengaturan lain)
 * @var string      $actions      tombol di kanan
 */
$keyword     ??= '';
$placeholder ??= 'Cari…';
$searchName  ??= 'q';
$isFiltered  ??= $keyword !== '';
$filters     ??= [];
$slot        ??= '';
$actions     ??= '';

$hasFilters = $filters !== [] || trim($slot) !== '';
$perPage    = config(\Config\Pager::class)->perPage;
$resetLink  = $isFiltered
    ? '<a class="button is-light" href="' . esc($action, 'attr') . '">Reset</a>'
    : '';
?>
<div class="admin-toolbar">
    <form class="admin-toolbar-filters" action="<?= esc($action, 'attr') ?>" method="get" role="search" data-autosubmit>
        <?php // Pilihan baris per halaman ikut terbawa saat filter diubah (hanya jika bukan default) ?>
        <?php if ($perPage !== \Config\Pager::DEFAULT_PER_PAGE): ?>
            <input type="hidden" name="per_page" value="<?= $perPage ?>">
        <?php endif ?>

        <?php // Kiri: dropdown filter + Reset. Tanpa dropdown, Reset ikut di samping kolom cari ?>
        <?php if ($hasFilters): ?>
            <div class="admin-toolbar-selects">
                <?php foreach ($filters as $name => $filter): ?>
                    <div class="admin-toolbar-select">
                        <?= component('select', [
                            'name'        => $name,
                            'label'       => $filter['placeholder'],
                            'hideLabel'   => true,
                            'options'     => $filter['options'],
                            'selected'    => $filter['selected'] ?? [],
                            'placeholder' => $filter['placeholder'],
                            'fullwidth'   => true,
                            'searchable'  => $filter['searchable'] ?? true,
                            'multiple'    => true,
                            'emptyLabel'  => $filter['emptyLabel'] ?? 'Tanpa filter',
                        ]) ?>
                    </div>
                <?php endforeach ?>
                <?= $slot ?>
                <?= $resetLink ?>
            </div>
        <?php endif ?>

        <div class="field has-addons mb-0 admin-toolbar-search">
            <div class="control has-icons-left is-expanded">
                <label class="is-sr-only" for="<?= esc($searchName, 'attr') ?>"><?= esc($placeholder) ?></label>
                <input class="input" id="<?= esc($searchName, 'attr') ?>" name="<?= esc($searchName, 'attr') ?>" type="search"
                    maxlength="100" value="<?= esc($keyword, 'attr') ?>" placeholder="<?= esc($placeholder, 'attr') ?>">
                <span class="icon is-left"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
            </div>
            <div class="control">
                <button type="submit" class="button is-primary">Cari</button>
            </div>
        </div>

        <?= $hasFilters ? '' : $resetLink ?>
    </form>

    <?php if (trim($actions) !== ''): ?>
        <div class="admin-toolbar-actions"><?= $actions ?></div>
    <?php endif ?>
</div>
