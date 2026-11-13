<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.sla.createTitle'),
        'subtitle' => lang('Admin.sla.formSubtitle'),
    ]) ?>

    <?= view('admin/sla_policies/_form', [
        'policy'      => $policy,
        'formAction'  => url_to('admin.sla-policies.create'),
        'submitLabel' => 'Simpan',
    ]) ?>
</section>
<?= $this->endSection() ?>
