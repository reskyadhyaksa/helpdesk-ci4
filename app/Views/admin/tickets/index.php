<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.tickets.title'),
        'subtitle' => lang('Admin.pages.tickets.subtitle'),
    ]) ?>

    <?= component('data_table', [
        'emptyIcon' => 'fa-ticket',
        'columns' => ['No. Tiket', 'Judul', 'Pelapor', 'Kategori', 'Status', 'SLA', 'Dibuat'],
    ]) ?>
</section>
<?= $this->endSection() ?>
