<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.requestCategories.title'),
        'subtitle' => lang('Admin.pages.requestCategories.subtitle'),
        'action'   => lang('Admin.pages.requestCategories.action'),
    ]) ?>

    <?= component('data_table', [
        'emptyIcon' => 'fa-folder-open',
        'columns' => ['Nama Kategori', 'Deskripsi', 'SLA', 'Status', 'Aksi'],
    ]) ?>
</section>
<?= $this->endSection() ?>
