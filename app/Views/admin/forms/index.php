<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.forms.title'),
        'subtitle' => lang('Admin.pages.forms.subtitle'),
        'action'   => lang('Admin.pages.forms.action'),
    ]) ?>

    <?= component('data_table', [
        'emptyIcon' => 'fa-file-pen',
        'columns' => ['Nama Form', 'Kategori Permintaan', 'Jumlah Field', 'Status'],
    ]) ?>
</section>
<?= $this->endSection() ?>
