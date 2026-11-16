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
        'columns' => [['label' => 'Pertanyaan', 'width' => 30], ['label' => 'Kategori', 'width' => 13], ['label' => 'Status', 'width' => 9], ['label' => 'Diperbarui', 'width' => 13]],
    ]) ?>
</section>
<?= $this->endSection() ?>
