<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.sla.editTitle'),
        'subtitle' => lang('Admin.sla.formSubtitle'),
    ]) ?>

    <?= view('admin/sla_policies/_form', [
        'policy'      => $policy,
        'formAction'  => url_to('admin.sla-policies.update', $policy->id),
        'submitLabel' => 'Simpan Perubahan',
    ]) ?>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/sla-policy-form.js') ?>"></script>
<?= $this->endSection() ?>
