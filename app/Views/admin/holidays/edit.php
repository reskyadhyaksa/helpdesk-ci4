<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.holiday.editTitle'),
        'subtitle' => lang('Admin.holiday.formSubtitle'),
    ]) ?>

    <?= view('admin/holidays/_form', [
        'holiday'     => $holiday,
        'formAction'  => url_to('admin.holidays.update', $holiday['id']),
        'submitLabel' => 'Simpan Perubahan',
    ]) ?>
</section>
<?= $this->endSection() ?>
