<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container page-container page-zoom py-5">
    <h1 class="title">Dashboard</h1>
    <p class="subtitle">Selamat datang, <?= esc(auth_user()['name']) ?>.</p>
</div>
<?= $this->endSection() ?>
