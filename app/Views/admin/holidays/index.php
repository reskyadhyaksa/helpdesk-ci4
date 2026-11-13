<?php
// Halaman kerangka: hari off dan daftar hari libur belum tersimpan ke database.
// Saat fiturnya dibuat, daftar hari pindah ke konstanta model dan nilai tercentang dari pengaturan.
$weekdays    = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$defaultOffs = ['Sabtu', 'Minggu'];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.holidays.title'),
        'subtitle' => lang('Admin.pages.holidays.subtitle'),
        'action'   => lang('Admin.pages.holidays.action'),
    ]) ?>

    <!-- Pengaturan hari off mingguan: dipakai SLA dengan opsi "Lewati hari off" -->
    <div class="box p-0 mb-5">
        <div class="form-card-body">
            <div class="is-flex is-align-items-center is-justify-content-space-between mb-1" style="gap: 0.75rem">
                <p class="form-section-label mb-0">Hari Off Mingguan</p>
                <span class="tag is-light is-rounded"><?= esc(lang('Admin.comingSoon')) ?></span>
            </div>
            <p class="is-size-7 has-text-grey-dark mb-4">
                Hari yang dicentang tidak dihitung pada kebijakan SLA yang mengaktifkan opsi <strong>Lewati hari off</strong>.
            </p>

            <fieldset disabled style="min-width: 0; border: 0">
                <legend class="is-sr-only">Pilih hari off mingguan</legend>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr)); gap: 0.75rem">
                    <?php foreach ($weekdays as $day): ?>
                        <label class="option-card is-locked">
                            <input type="checkbox" name="off_days[]" value="<?= esc($day, 'attr') ?>"
                                <?= in_array($day, $defaultOffs, true) ? 'checked' : '' ?>>
                            <span class="option-card-label"><?= esc($day) ?></span>
                        </label>
                    <?php endforeach ?>
                </div>
            </fieldset>
        </div>

        <div class="form-card-footer">
            <button type="button" class="button is-primary" disabled title="<?= esc(lang('Admin.comingSoon'), 'attr') ?>">
                <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
                <span>Simpan Hari Off</span>
            </button>
        </div>
    </div>

    <h2 class="title is-6 mb-3">Daftar Hari Libur</h2>
    <p class="is-size-7 has-text-grey-dark mb-4">
        Tanggal merah dan cuti bersama, tidak dihitung pada kebijakan SLA yang mengaktifkan opsi <strong>Lewati hari libur</strong>.
    </p>

    <?= component('data_table', [
        'emptyIcon' => 'fa-calendar-days',
        'columns'   => ['Tanggal', 'Nama Hari Libur', 'Jenis', 'Diperbarui'],
    ]) ?>
</section>
<?= $this->endSection() ?>
