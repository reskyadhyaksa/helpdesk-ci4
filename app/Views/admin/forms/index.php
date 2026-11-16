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
        'columns' => [['label' => 'Nama Form', 'width' => 24], ['label' => 'Kategori Permintaan', 'width' => 20], ['label' => 'Jumlah Field', 'width' => 12], ['label' => 'Status', 'width' => 9]],
    ]) ?>
</section>
<?= $this->endSection() ?>
