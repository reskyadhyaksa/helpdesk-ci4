<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.dashboard.title'),
        'subtitle' => lang('Admin.pages.dashboard.subtitle'),
    ]) ?>

    <div class="box">
        <?= component('empty_state', [
            'icon'    => 'fa-table-cells-large',
            'title'   => 'Selamat datang, ' . auth_user()['name'] . '.',
        ]) ?>
    </div>
</section>
<?= $this->endSection() ?>
