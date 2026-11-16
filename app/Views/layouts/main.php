<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <?= $this->include('partials/head') ?>
</head>
<body class="site-body">
    <?= $this->include('partials/navbar') ?>
    <?= $this->include('partials/flash') ?>

    <main>
        <?= $this->renderSection('content') ?>
    </main>

    <?= $this->include('partials/footer') ?>

    <script src="<?= base_url('js/app.js') ?>"></script>
    <script src="<?= base_url('js/components/select.js') ?>"></script>
    <script src="<?= base_url('js/components/toast.js') ?>"></script>
    <?php if (auth_user() !== null): ?>
        <script src="<?= base_url('js/components/notifications.js') ?>"></script>
    <?php endif ?>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
