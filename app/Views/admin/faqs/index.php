<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.faqs.title'),
        'subtitle' => lang('Admin.pages.faqs.subtitle'),
        'action'   => lang('Admin.pages.faqs.action'),
    ]) ?>

    <?= component('data_table', [
        'emptyIcon' => 'fa-circle-question',
        'columns' => ['Pertanyaan', 'Kategori', 'Status', 'Diperbarui'],
    ]) ?>
</section>
<?= $this->endSection() ?>
