<?php
$errors  = session('errors') ?? [];
$success = session('success');
$danger  = session('danger');
?>
<?php if ($success || $danger || $errors): ?>
    <div class="container mt-4 px-4">
        <?php if ($success): ?>
            <div class="notification is-success is-light"><?= esc($success) ?></div>
        <?php endif ?>

        <?php if ($danger): ?>
            <div class="notification is-danger is-light"><?= esc($danger) ?></div>
        <?php endif ?>

        <?php if ($errors): ?>
            <div class="notification is-danger is-light">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>
    </div>
<?php endif ?>
