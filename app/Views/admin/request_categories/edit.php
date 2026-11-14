<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.requestCategory.editTitle'),
        'subtitle' => lang('Admin.requestCategory.formSubtitle'),
    ]) ?>

    <?= view('admin/request_categories/_form', [
        'category'        => $category,
        'parentOptions'   => $parentOptions,
        'slaOptions'      => $slaOptions,
        'jobTitleOptions' => $jobTitleOptions,
        'savedJobTitles'  => $savedJobTitles,
        'formAction'      => url_to('admin.request-categories.update', $category['id']),
        'submitLabel'     => 'Simpan Perubahan',
    ]) ?>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/request-category-form.js') ?>"></script>
<?= $this->endSection() ?>
