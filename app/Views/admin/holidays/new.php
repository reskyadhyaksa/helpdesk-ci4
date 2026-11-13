<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.holiday.createTitle'),
        'subtitle' => lang('Admin.holiday.formSubtitle'),
    ]) ?>

    <?= view('admin/holidays/_form', [
        'holiday'     => $holiday,
        'formAction'  => url_to('admin.holidays.create'),
        'submitLabel' => 'Simpan',
    ]) ?>
</section>
<?= $this->endSection() ?>
