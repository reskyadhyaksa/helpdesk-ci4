<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.myTickets.title'),
        'subtitle' => lang('Admin.pages.myTickets.subtitle'),
    ]) ?>

    <?= component('data_table', [
        'emptyIcon' => 'fa-user-check',
        'columns' => ['No. Tiket', 'Judul', 'Pelapor', 'Status', 'Batas SLA'],
    ]) ?>
</section>
<?= $this->endSection() ?>
