<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <?= $this->include('partials/head') ?>
    <script src="<?= base_url('js/admin.js') ?>"></script>
</head>
<body>
    <div class="admin-shell">
        <?= $this->include('partials/admin_sidebar') ?>

        <div class="admin-main">
            <?= $this->include('partials/flash') ?>

            <main>
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>

    <script src="<?= base_url('js/app.js') ?>"></script>
    <script src="<?= base_url('js/components/select.js') ?>"></script>
    <script src="<?= base_url('js/components/toast.js') ?>"></script>
    <script src="<?= base_url('js/components/notifications.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
