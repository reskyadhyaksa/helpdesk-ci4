<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="section">
    <div class="container">
        <h1 class="title">Dashboard</h1>
        <p class="subtitle">Selamat datang, <?= esc(auth_user()['name']) ?>.</p>
    </div>
</section>
<?= $this->endSection() ?>
