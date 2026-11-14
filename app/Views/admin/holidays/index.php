<?php

use App\Models\HolidayModel;
use App\Models\PermissionModel;
use App\Models\WeeklyOffDayModel;

/**
 * @var list<int>   $years           pilihan tahun filter
 * @var list<int>   $selectedYears   tahun terpilih (minimal satu; default tahun berjalan)
 * @var list<int>   $selectedMonths  bulan terpilih (kosong = semua bulan)
 * @var string      $keyword
 */

// Tombol aksi hanya tampil sesuai permission (route tetap menjaga aksesnya)
$canCreate     = auth_can(PermissionModel::ADMIN_HOLIDAYS_CREATE);
$canUpdate     = auth_can(PermissionModel::ADMIN_HOLIDAYS_UPDATE);
$canDelete     = auth_can(PermissionModel::ADMIN_HOLIDAYS_DELETE);
$canSync       = auth_can(PermissionModel::ADMIN_HOLIDAYS_SYNC);
$canEditOffDay = auth_can(PermissionModel::ADMIN_OFF_DAYS_UPDATE);

$hasActions = $canUpdate || $canDelete;
$columns    = ['Tanggal', 'Nama Hari Libur', 'Jenis', 'Diperbarui'];

if ($hasActions) {
    $columns[] = ['label' => 'Aksi', 'align' => 'right'];
}

$isFiltered = $keyword !== '' || $selectedMonths !== [] || $selectedYears !== [(int) date('Y')];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?php component_start('page_header', [
        'title'    => lang('Admin.pages.holidays.title'),
        'subtitle' => lang('Admin.pages.holidays.subtitle'),
    ]) ?>
        <?php if ($canSync): ?>
            <form action="<?= url_to('admin.holidays.sync') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="button is-primary">
                    <span class="icon"><i class="fa-solid fa-rotate" aria-hidden="true"></i></span>
                    <span><?= esc(lang('Admin.holiday.syncAction')) ?></span>
                </button>
            </form>
        <?php endif ?>
    <?= component_end() ?>

    <!-- Hari off mingguan: dipakai SLA dengan opsi "Lewati hari off". Nilai = nomor hari ISO (Senin = 1).
         Terkunci sampai tombol Edit ditekan (js/pages/admin/holidays.js). -->
    <form class="box p-0 mb-5" action="<?= url_to('admin.holidays.off-days') ?>" method="post" data-off-days>
        <?= csrf_field() ?>

        <div class="form-card-body">
            <p class="form-section-label mb-1">Hari Off dan Jam Kerja Mingguan</p>
            <p class="is-size-7 has-text-grey-dark mb-4">
                Hari yang dicentang tidak dihitung pada kebijakan SLA yang mengaktifkan opsi <strong>Lewati hari off</strong>.
                Jam kerja tiap hari dipakai kebijakan SLA yang mengaktifkan opsi <strong>Hanya jam kerja</strong>.
                Perubahan berlaku untuk perhitungan SLA berikutnya.
            </p>

            <fieldset disabled style="min-width: 0; border: 0">
                <legend class="is-sr-only">Pilih hari off dan jam kerja mingguan</legend>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.75rem">
                    <?php foreach (WeeklyOffDayModel::DAYS as $number => $label): ?>
                        <?php
                        $start = $schedule[$number]['start'] ?? WeeklyOffDayModel::DEFAULT_WORK_START;
                        $end   = $schedule[$number]['end'] ?? WeeklyOffDayModel::DEFAULT_WORK_END;
                        ?>
                        <div class="option-card is-locked" style="flex-direction: column; gap: 0.5rem">
                            <label class="option-card-label" style="cursor: inherit">
                                <input type="checkbox" name="off_days[]" value="<?= $number ?>"
                                    <?= in_array($number, $offDayNums, true) ? 'checked' : '' ?>>
                                <span><?= esc($label) ?> libur</span>
                            </label>
                            <div class="is-flex is-align-items-center" style="gap: 0.4rem">
                                <input class="input is-small" type="time" name="work_start[<?= $number ?>]"
                                    value="<?= esc($start, 'attr') ?>" aria-label="Jam mulai <?= esc($label, 'attr') ?>">
                                <span aria-hidden="true">–</span>
                                <input class="input is-small" type="time" name="work_end[<?= $number ?>]"
                                    value="<?= esc($end, 'attr') ?>" aria-label="Jam selesai <?= esc($label, 'attr') ?>">
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </fieldset>
        </div>

        <?php if ($canEditOffDay): ?>
            <div class="form-card-footer">
                <button type="button" class="button is-light" data-off-days-toggle aria-pressed="false">
                    <span class="icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></span>
                    <span data-label>Edit</span>
                </button>
                <button type="submit" class="button is-primary" data-off-days-save disabled>
                    <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
                    <span>Simpan Hari Off</span>
                </button>
            </div>
        <?php endif ?>
    </form>

    <p class="is-size-7 has-text-grey-dark mb-4">
        Tanggal merah dan cuti bersama, tidak dihitung pada kebijakan SLA yang mengaktifkan opsi <strong>Lewati hari libur</strong>.
    </p>

    <?php component_start('filter_bar', [
        'action'      => url_to('admin.holidays.index'),
        'keyword'     => $keyword,
        'placeholder' => lang('Admin.holiday.searchPlaceholder'),
        'isFiltered'  => $isFiltered,
    ]) ?>
        <div class="admin-toolbar-select">
            <?= component('select', [
                'name'        => 'year',
                'label'       => lang('Admin.holiday.filterYear'),
                'hideLabel'   => true,
                'options'     => array_combine($years, $years),
                'selected'    => $selectedYears,
                'placeholder' => lang('Admin.holiday.currentYear'),
                'fullwidth'   => true,
                'multiple'    => true,
                'emptyLabel'  => 'Tahun berjalan',
            ]) ?>
        </div>
        <div class="admin-toolbar-select">
            <?= component('select', [
                'name'        => 'month',
                'label'       => lang('Admin.holiday.filterMonth'),
                'hideLabel'   => true,
                'options'     => HolidayModel::MONTHS,
                'selected'    => $selectedMonths,
                'placeholder' => lang('Admin.holiday.allMonths'),
                'fullwidth'   => true,
                'searchable'  => true,
                'multiple'    => true,
                'emptyLabel'  => 'Tanpa filter',
            ]) ?>
        </div>
        <?php component_slot('actions') ?>
        <?php if ($canCreate): ?>
            <a class="button is-primary" href="<?= url_to('admin.holidays.new') ?>">
                <span class="icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                <span><?= esc(lang('Admin.holiday.addAction')) ?></span>
            </a>
        <?php endif ?>
    <?= component_end() ?>

    <?php if (! $hasActions): ?>
        <?= component('readonly_notice', ['message' => 'Anda tidak punya izin mengubah atau menghapus hari libur.']) ?>
    <?php endif ?>

    <?php component_start('data_table', [
        'emptyIcon'    => 'fa-calendar-days',
        'emptyTitle'   => lang('Admin.holiday.emptyTitle'),
        'emptyMessage' => lang('Admin.holiday.emptyMessage'),
        'columns'      => $columns,
        'pager'        => $pager,
        'totalLabel'   => 'hari libur',
    ]) ?>
        <?php foreach ($holidays as $holiday): ?>
            <tr>
                <td><?= esc(format_date($holiday['holiday_date'])) ?></td>
                <td>
                    <strong class="is-block"><?= esc($holiday['name']) ?></strong>
                    <?php if ($holiday['description'] !== null): ?>
                        <small><?= esc($holiday['description']) ?></small>
                    <?php endif ?>
                </td>
                <td>
                    <?php if ($holiday['is_mass_leave']): ?>
                        <span class="tag is-warning is-light is-rounded"><?= esc(lang('Admin.holiday.massLeave')) ?></span>
                    <?php else: ?>
                        <span class="tag is-danger is-light is-rounded"><?= esc(lang('Admin.holiday.nationalDay')) ?></span>
                    <?php endif ?>
                </td>
                <td><?= esc(format_datetime($holiday['updated_at'])) ?></td>
                <?php if ($hasActions): ?>
                    <td>
                        <div class="buttons is-right is-flex-wrap-nowrap">
                            <?php if ($canUpdate): ?>
                                <a class="button is-small is-light" href="<?= url_to('admin.holidays.edit', $holiday['id']) ?>">
                                    <span class="icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></span>
                                    <span>Ubah</span>
                                </a>
                            <?php endif ?>
                            <?php if ($canDelete): ?>
                                <form action="<?= url_to('admin.holidays.delete', $holiday['id']) ?>" method="post" data-confirm="<?= esc(lang('Admin.holiday.confirmDelete'), 'attr') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="button is-small is-danger is-light" aria-label="Hapus <?= esc($holiday['name'], 'attr') ?>">
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

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/holidays.js') ?>"></script>
<?= $this->endSection() ?>
