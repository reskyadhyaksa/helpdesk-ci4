<?php
/**
 * Kontrol di atas tabel tiket admin (All Ticket dan My Ticket): tab cepat + baris filter.
 * Tab dan pilihan tampilan (Table / Kanban Grid) hanya mengisi input tersembunyi "tab" dan "view" pada form filter
 * lalu mengirimnya (js/app.js, [data-quick-tab] dan [data-view-toggle]), jadi semuanya digabung dalam satu URL.
 *
 * @var string                                         $action    URL halaman (tujuan form dan tombol Reset)
 * @var array<string, mixed>                           $filters   TicketService::normalizeFilters()
 * @var array<string, int>                             $tabCounts jumlah tiket per tab
 * @var array<string, array<string, string>>           $options   TicketService::filterOptions()
 * @var string                                         $title     judul panel
 * @var string                                         $subtitle  keterangan di bawah judul
 * @var string|null                                    $createUrl tombol "Buat Tiket" (null = tanpa tombol)
 */
$createUrl ??= null;

$tabs = [
    'all'      => 'All Tickets',
    'open'     => 'Only Open',
    'progress' => 'In Progress',
    'expiring' => 'Expiring SLA / Urgent',
];

$isFiltered = $filters['keyword'] !== '' || $filters['statuses'] !== [] || $filters['categories'] !== []
    || $filters['priorities'] !== [] || $filters['pics'] !== [] || $filters['unassigned'] || $filters['tab'] !== 'all';

$dropdowns = [
    'status'   => ['placeholder' => lang('Ticket.allStatuses'), 'selected' => $filters['statuses'], 'searchable' => false],
    'category' => ['placeholder' => 'Semua kategori', 'selected' => $filters['categories']],
    'priority' => ['placeholder' => 'Semua prioritas', 'selected' => $filters['priorities'], 'searchable' => false],
    'pic'      => ['placeholder' => 'Semua PIC', 'selected' => $filters['pics']],
];

foreach ($dropdowns as $name => $dropdown) {
    $dropdowns[$name]['options'] = $options[$name];
}
?>
<div class="list-panel-head">
    <div>
        <h1 class="title is-4 mb-1"><?= esc($title) ?></h1>
        <p class="has-text-grey-dark"><?= esc($subtitle) ?></p>
    </div>

<?php // [data-quick-filters] diganti utuh saat daftar diperbarui lewat fetch, supaya angka dan tab aktif ikut berubah ?>
    <div class="quick-tabs" data-quick-filters role="group" aria-label="Pintasan filter tiket">
        <?php foreach ($tabs as $tab => $label): ?>
            <button type="button"
                class="quick-tab <?= $tab === 'expiring' ? 'is-alert' : '' ?> <?= $filters['tab'] === $tab ? 'is-active' : '' ?>"
                data-quick-tab="<?= $tab ?>" aria-pressed="<?= $filters['tab'] === $tab ? 'true' : 'false' ?>">
                <?php if ($tab === 'expiring'): ?><span class="quick-dot" aria-hidden="true"></span><?php endif ?>
                <?= esc($label) ?> <span class="quick-count">(<?= (int) ($tabCounts[$tab] ?? 0) ?>)</span>
            </button>
        <?php endforeach ?>
    
        <div class="view-toggle" role="group" aria-label="Tampilan daftar">
            <button type="button" data-view-toggle="table" aria-pressed="<?= $filters['view'] === 'table' ? 'true' : 'false' ?>">
                <i class="fa-solid fa-table-list" aria-hidden="true"></i> Table
            </button>
            <button type="button" data-view-toggle="kanban" aria-pressed="<?= $filters['view'] === 'kanban' ? 'true' : 'false' ?>">
                <i class="fa-solid fa-table-columns" aria-hidden="true"></i> Kanban Grid
            </button>
        </div>
    </div>
</div>

<?php component_start('filter_bar', [
    'action'      => $action,
    'keyword'     => $filters['keyword'],
    'placeholder' => lang('Ticket.searchPlaceholder'),
    'isFiltered'  => $isFiltered,
    'filters'     => $dropdowns,
]) ?>
    <input type="hidden" name="tab" value="<?= $filters['tab'] === 'all' ? '' : esc($filters['tab'], 'attr') ?>">
    <input type="hidden" name="view" value="<?= $filters['view'] === 'kanban' ? 'kanban' : '' ?>">
    <label class="filter-toggle">
        <input type="checkbox" name="unassigned" value="1" <?= $filters['unassigned'] ? 'checked' : '' ?>>
        <span><i class="fa-solid fa-user-slash" aria-hidden="true"></i> Belum ditugaskan</span>
    </label>
    <?php if ($createUrl !== null): ?>
        <?php component_slot('actions') ?>
        <a class="button is-primary" href="<?= esc($createUrl, 'attr') ?>">
            <span class="icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
            <span><?= esc(lang('Ticket.adminNewAction')) ?></span>
        </a>
    <?php endif ?>
<?= component_end() ?>
