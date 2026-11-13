<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.roles.createTitle'),
        'subtitle' => lang('Admin.roles.formSubtitle'),
    ]) ?>

    <?= view('admin/roles/_form', [
        'role'        => $role,
        'granted'     => $granted,
        'locked'      => $locked,
        'formAction'  => url_to('admin.roles.create'),
        'submitLabel' => 'Simpan',
    ]) ?>
</section>
<?= $this->endSection() ?>
