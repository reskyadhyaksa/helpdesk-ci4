<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.requestCategory.createTitle'),
        'subtitle' => lang('Admin.requestCategory.formSubtitle'),
    ]) ?>

    <?= view('admin/request_categories/_form', [
        'category'            => $category,
        'parentOptions'       => $parentOptions,
        'slaOptions'          => $slaOptions,
        'jobTitleOptions'     => $jobTitleOptions,
        'roleOptions'         => $roleOptions,
        'userOptions'         => $userOptions,
        'accessEntries'       => $accessEntries,
        'canManageUserAccess' => $canManageUserAccess,
        'actorId'             => $actorId,
        'formAction'          => url_to('admin.request-categories.create'),
        'submitLabel'         => 'Simpan',
    ]) ?>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/request-category-form.js') ?>"></script>
<?= $this->endSection() ?>
