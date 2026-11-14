<?php

use App\Models\PermissionModel;

/**
 * Form role + permission, dipakai halaman tambah dan ubah.
 * Slug role dibuat otomatis oleh RoleService dari nama, jadi tidak ditampilkan.
 *
 * @var array        $role        id, name
 * @var list<string> $granted     slug permission yang dimiliki role
 * @var list<string> $locked      slug permission yang tidak bisa dilepas
 * @var string       $formAction
 * @var string       $submitLabel
 */
$errors = session('errors') ?? [];

// Setelah validasi gagal pakai pilihan terakhir; checkbox kosong berarti tidak ada yang dicentang.
$checked = old('name') !== null ? (old('permissions') ?? []) : $granted;
?>
<form class="box p-0" action="<?= esc($formAction, 'attr') ?>" method="post" novalidate>
    <?= csrf_field() ?>

    <div class="form-card-body">
        <div class="field role-name-field">
            <label class="label" for="name">Nama Role <span class="has-text-danger" aria-hidden="true">*</span></label>
            <div class="control">
                <input class="input <?= isset($errors['name']) ? 'is-danger' : '' ?>" id="name" name="name" type="text" maxlength="100" required
                    value="<?= esc(old('name', $role['name']), 'attr') ?>" placeholder="Contoh: HCIS Officer"
                    <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="nameError"' : '' ?>>
            </div>
            <?php if (isset($errors['name'])): ?>
                <p class="help is-danger" id="nameError"><?= esc($errors['name']) ?></p>
            <?php endif ?>
        </div>

        <p class="label mb-1">Hak Akses</p>
        <p class="help mt-0 mb-3">
            <?= esc(lang('Admin.roles.reloginHint')) ?>
            <?php if ($locked !== []): ?>
                <?= esc(lang('Admin.roles.lockedHint')) ?>
            <?php endif ?>
        </p>

        <div class="perm-sections">
            <?php foreach (PermissionModel::GROUPS as $groupName => $group): ?>
                <?php $groupId = 'perm-' . url_title($groupName, '-', true) ?>
                <section class="perm-section" aria-labelledby="<?= esc($groupId, 'attr') ?>">
                    <header class="perm-section-head">
                        <span class="perm-section-icon"><i class="fa-solid <?= esc($group['icon'], 'attr') ?>" aria-hidden="true"></i></span>
                        <div>
                            <h3 class="perm-section-title" id="<?= esc($groupId, 'attr') ?>"><?= esc($groupName) ?></h3>
                            <p class="perm-section-desc"><?= esc($group['description']) ?></p>
                        </div>
                    </header>

                    <div class="perm-options">
                        <?php foreach ($group['permissions'] as $slug => $permission): ?>
                            <?php $isLocked = in_array($slug, $locked, true) ?>
                            <label class="option-card <?= $isLocked ? 'is-locked' : '' ?>" title="<?= esc($slug, 'attr') ?>">
                                <input type="checkbox" name="permissions[]" value="<?= esc($slug, 'attr') ?>"
                                    <?= $isLocked || in_array($slug, $checked, true) ? 'checked' : '' ?>
                                    <?= $isLocked ? 'disabled' : '' ?>>
                                <span class="option-card-text">
                                    <span class="option-card-label">
                                        <?= esc($permission['label']) ?>
                                        <?php if ($isLocked): ?>
                                            <span class="tag is-light is-rounded option-card-badge">
                                                <i class="fa-solid fa-lock mr-1" aria-hidden="true"></i>Selalu aktif
                                            </span>
                                        <?php endif ?>
                                    </span>
                                    <span class="option-card-desc"><?= esc($permission['description']) ?></span>
                                </span>
                            </label>
                        <?php endforeach ?>
                    </div>
                </section>
            <?php endforeach ?>
        </div>
    </div>

    <div class="form-card-footer">
        <a class="button is-light" href="<?= url_to('admin.roles.index') ?>">Batal</a>
        <button type="submit" class="button is-primary">
            <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
            <span><?= esc($submitLabel) ?></span>
        </button>
    </div>
</form>
