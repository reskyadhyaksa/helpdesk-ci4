<!DOCTYPE html>
<html lang="id">
<head>
    <?= $this->include('partials/head') ?>
</head>
<body>
    <?= $this->include('partials/navbar') ?>
    <?= $this->include('partials/flash') ?>

    <main>
        <?= $this->renderSection('content') ?>
    </main>

    <script src="<?= base_url('js/navbar.js') ?>"></script>
</body>
</html>
