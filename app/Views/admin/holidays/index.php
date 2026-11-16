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

    <!-- Jadwal kerja mingguan: satu baris per hari berisi status (Kerja/Libur) dan jam kerjanya. Nilai = nomor hari ISO
         (Senin = 1). Kotak centang off_days[] = hari libur; baris libur tidak mengirim jam. Terkunci sampai tombol Edit
         ditekan (js/pages/admin/holidays.js). -->
    <form class="box p-0 mb-5 week-schedule" action="<?= url_to('admin.holidays.off-days') ?>" method="post" data-off-days>
        <?= csrf_field() ?>

        <div class="form-card-body">
            <h2 class="form-section-label mb-1">Jadwal Kerja Mingguan</h2>
            <p class="is-size-7 has-text-grey-dark mb-4">
                Tentukan hari kantor beroperasi dan jam kerjanya. Perubahan berlaku untuk perhitungan SLA berikutnya.
            </p>

            <dl class="week-summary" aria-label="Ringkasan jadwal">
                <div>
                    <dt><i class="fa-solid fa-briefcase" aria-hidden="true"></i> Hari kerja</dt>
                    <dd data-summary-work>—</dd>
                </div>
                <div>
                    <dt><i class="fa-regular fa-clock" aria-hidden="true"></i> Jam kerja</dt>
                    <dd data-summary-hours>—</dd>
                </div>
                <div>
                    <dt><i class="fa-solid fa-mug-hot" aria-hidden="true"></i> Libur mingguan</dt>
                    <dd data-summary-off>—</dd>
                </div>
                <div>
                    <dt><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Total per minggu</dt>
                    <dd data-summary-total>—</dd>
                </div>
            </dl>

            <ul class="week-legend">
                <li>
                    <span class="week-legend-icon is-off"><i class="fa-solid fa-mug-hot" aria-hidden="true"></i></span>
                    <span><strong>Hari libur</strong> dilewati pada perhitungan SLA yang mengaktifkan opsi <strong>Lewati hari off</strong>.</span>
                </li>
                <li>
                    <span class="week-legend-icon is-work"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                    <span><strong>Jam kerja</strong> menentukan kapan SLA berjalan pada kebijakan yang mengaktifkan opsi <strong>Hanya jam kerja</strong>. Di luar jam ini waktu SLA berhenti.</span>
                </li>
            </ul>

            <?php // Status mode: terkunci / sedang mengedit / ada perubahan belum disimpan (diatur js/pages/admin/holidays.js) ?>
            <p class="week-mode" data-mode-note role="status">
                <i class="fa-solid <?= $canEditOffDay ? 'fa-lock' : 'fa-eye' ?>" aria-hidden="true" data-mode-icon></i>
                <span data-mode-text>
                    <?php if ($canEditOffDay): ?>
                        Jadwal terkunci. Tekan <strong>Edit</strong> di bawah untuk mengubahnya.
                    <?php else: ?>
                        Anda hanya bisa melihat jadwal ini.
                    <?php endif ?>
                </span>
            </p>

            <fieldset class="week-fieldset" disabled>
                <legend class="is-sr-only">Atur status dan jam kerja tiap hari</legend>

                <p class="week-key">
                    <span class="week-key-item"><span class="week-key-dot is-run"></span> Hijau: jam SLA berjalan</span>
                    <span class="week-key-item"><span class="week-key-dot is-stop"></span> Abu-abu: SLA berhenti (pada kebijakan <strong>Hanya jam kerja</strong>)</span>
                </p>

                <div class="week-head" aria-hidden="true">
                    <span>Hari</span>
                    <span>Status</span>
                    <span>Jam mulai – selesai</span>
                    <span>
                        Posisi dalam sehari (jam)
                        <span class="week-scale"><span>00</span><span>06</span><span>12</span><span>18</span><span>24</span></span>
                    </span>
                    <span>Durasi</span>
                </div>

                <ul class="week-list">
                    <?php foreach (WeeklyOffDayModel::DAYS as $number => $label): ?>
                        <?php
                        $isOff = in_array($number, $offDayNums, true);
                        $start = $schedule[$number]['start'] ?? WeeklyOffDayModel::DEFAULT_WORK_START;
                        $end   = $schedule[$number]['end'] ?? WeeklyOffDayModel::DEFAULT_WORK_END;
                        ?>
                        <li class="week-row <?= $isOff ? 'is-off' : '' ?>" data-day="<?= $number ?>" data-day-name="<?= esc($label, 'attr') ?>">
                            <span class="week-day"><?= esc($label) ?></span>

                            <label class="week-toggle">
                                <input type="checkbox" name="off_days[]" value="<?= $number ?>" <?= $isOff ? 'checked' : '' ?>
                                    aria-label="<?= esc($label, 'attr') ?> libur">
                                <span class="week-toggle-option is-work" aria-hidden="true"><i class="fa-solid fa-briefcase"></i> Kerja</span>
                                <span class="week-toggle-option is-off" aria-hidden="true"><i class="fa-solid fa-mug-hot"></i> Libur</span>
                            </label>

                            <div class="week-hours">
                                <label class="is-sr-only" for="work_start_<?= $number ?>">Jam mulai <?= esc($label) ?></label>
                                <input class="input" type="time" id="work_start_<?= $number ?>" name="work_start[<?= $number ?>]"
                                    value="<?= esc($start, 'attr') ?>" <?= $isOff ? 'disabled' : '' ?>>
                                <span aria-hidden="true">–</span>
                                <label class="is-sr-only" for="work_end_<?= $number ?>">Jam selesai <?= esc($label) ?></label>
                                <input class="input" type="time" id="work_end_<?= $number ?>" name="work_end[<?= $number ?>]"
                                    value="<?= esc($end, 'attr') ?>" <?= $isOff ? 'disabled' : '' ?>>
                            </div>
                            <p class="week-off-note">Libur, tidak dihitung dalam SLA</p>

                            <?php // Rentang jam kerja pada skala 00–24 (diisi js/pages/admin/holidays.js) ?>
                            <div class="week-track" aria-hidden="true"><span class="week-track-fill" data-fill><span data-fill-text></span></span></div>

                            <span class="week-duration" data-duration aria-live="polite"></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            </fieldset>
        </div>

        <?php if ($canEditOffDay): ?>
            <div class="form-card-footer">
                <button type="button" class="button is-light" data-copy-hours hidden
                    title="Salin jam kerja hari kerja pertama ke semua hari kerja lainnya">
                    <span class="icon"><i class="fa-regular fa-copy" aria-hidden="true"></i></span>
                    <span>Samakan jam semua hari kerja</span>
                </button>
                <button type="button" class="button is-light" data-off-days-toggle aria-pressed="false">
                    <span class="icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></span>
                    <span data-label>Edit</span>
                </button>
                <button type="submit" class="button is-primary" data-off-days-save disabled>
                    <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
                    <span>Simpan Jadwal</span>
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
