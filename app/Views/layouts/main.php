<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <?= $this->include('partials/head') ?>
</head>
<body>
    <?= $this->include('partials/navbar') ?>
    <?= $this->include('partials/flash') ?>

    <main>
        <?= $this->renderSection('content') ?>
    </main>

    <?= $this->include('partials/footer') ?>

    <script src="<?= base_url('js/app.js') ?>"></script>
</body>
</html>
