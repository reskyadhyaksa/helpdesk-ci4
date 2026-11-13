<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.roles.editTitle'),
        'subtitle' => lang('Admin.roles.formSubtitle'),
    ]) ?>

    <?= view('admin/roles/_form', [
        'role'        => $role,
        'granted'     => $granted,
        'locked'      => $locked,
        'formAction'  => url_to('admin.roles.update', $role['id']),
        'submitLabel' => 'Simpan Perubahan',
    ]) ?>
</section>
<?= $this->endSection() ?>
