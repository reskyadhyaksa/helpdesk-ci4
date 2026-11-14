<?php
/**
 * Form kategori permintaan, dipakai halaman tambah dan ubah.
 * Atas: dua kolom (kiri informasi kategori, kanan SLA + status). Bawah: tabel akses selebar penuh
 * (job title, role, dan pengecualian user) dengan baris tambah. Semua perubahan akses dikirim saat Simpan.
 * Job title dan role hanya untuk kategori utama; saat induk dipilih hanya pengecualian user yang tersisa
 * (js/pages/admin/request-category-form.js).
 *
 * @var array                 $category
 * @var array<string, string> $parentOptions        id => nama kategori utama
 * @var array<string, string> $slaOptions           id => nama SLA aktif
 * @var array<string, string> $jobTitleOptions      job title => job title
 * @var array<string, string> $roleOptions          id => nama role
 * @var array<string, string> $userOptions          id => "Nama (NIK)"
 * @var list<array>           $accessEntries        baris tabel akses (lihat RequestCategoryService::accessEntries)
 * @var bool                  $canManageUserAccess  boleh mengubah pengecualian user
 * @var string                $actorId              id user yang sedang login
 * @var string                $formAction
 * @var string                $submitLabel
 */
$errors = session('errors') ?? [];
$isActive = (string) old('is_active', (string) $category['is_active']) === '1';
$parentId = old('parent_id', $category['parent_id']);
$isSub = $parentId !== null && $parentId !== '';
?>
<form class="box p-0" action="<?= esc($formAction, 'attr') ?>" method="post" novalidate>
    <?= csrf_field() ?>

    <div class="form-card-body">
        <div class="columns is-variable is-5">
            <div class="column is-7-desktop">
                <p class="form-section-label">Informasi Kategori</p>

                <div class="field">
                    <label class="label" for="name">Nama Kategori <span class="has-text-danger"
                            aria-hidden="true">*</span></label>
                    <div class="control">
                        <input class="input <?= isset($errors['name']) ? 'is-danger' : '' ?>" id="name" name="name"
                            type="text" maxlength="100" required
                            value="<?= esc(old('name', $category['name']), 'attr') ?>" placeholder="Contoh: Payroll"
                            <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="nameError"' : '' ?>>
                    </div>
                    <?php if (isset($errors['name'])): ?>
                        <p class="help is-danger" id="nameError"><?= esc($errors['name']) ?></p>
                    <?php endif ?>
                </div>

                <div class="field">
                    <?= component('select', [
                        'name' => 'parent_id',
                        'label' => 'Kategori Utama',
                        'options' => $parentOptions,
                        'selected' => $parentId,
                        'placeholder' => 'Bukan sub kategori (kategori utama)',
                        'fullwidth' => true,
                        'searchable' => true,
                        'help' => 'Kosongkan jika ini kategori utama. Kategori yang sudah punya sub tidak bisa dijadikan sub.',
                        'error' => $errors['parent_id'] ?? null,
                    ]) ?>
                </div>

                <div class="field">
                    <label class="label" for="description">Deskripsi <span
                            class="has-text-weight-normal has-text-grey-dark">(Opsional)</span></label>
                    <div class="control">
                        <textarea class="textarea <?= isset($errors['description']) ? 'is-danger' : '' ?>"
                            id="description" name="description" rows="3" maxlength="255"
                            placeholder="Jenis permintaan yang termasuk kategori ini"><?= esc(old('description', $category['description'])) ?></textarea>
                    </div>
                    <?php if (isset($errors['description'])): ?>
                        <p class="help is-danger"><?= esc($errors['description']) ?></p>
                    <?php endif ?>
                </div>
            </div>

            <div class="column is-5-desktop">
                <div class="form-panel">
                    <p class="form-section-label">Layanan</p>

                    <div class="field">
                        <?= component('select', [
                            'name' => 'sla_policy_id',
                            'label' => 'Kebijakan SLA',
                            'options' => $slaOptions,
                            'selected' => old('sla_policy_id', $category['sla_policy_id']),
                            'placeholder' => 'Tanpa SLA / ikut kategori utama',
                            'fullwidth' => true,
                            'error' => $errors['sla_policy_id'] ?? null,
                        ]) ?>
                    </div>

                    <div class="form-panel-divider">
                        <p class="form-section-label">Status</p>

                        <!-- Nilai 0 terkirim saat checkbox tidak dicentang -->
                        <input type="hidden" name="is_active" value="0">
                        <label class="option-card">
                            <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                            <span class="option-card-text">
                                <span class="option-card-label">Aktif</span>
                                <span class="option-card-desc">Kategori bisa dipilih saat membuat tiket.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <section class="access-section <?= $isSub ? 'is-sub' : '' ?>" data-access aria-labelledby="accessTitle"
            data-actor-id="<?= esc($actorId, 'attr') ?>"
            data-msg-added="<?= esc(lang('Admin.requestCategory.added'), 'attr') ?>"
            data-msg-removed="<?= esc(lang('Admin.requestCategory.removed'), 'attr') ?>"
            data-msg-remove-label="<?= esc(lang('Admin.requestCategory.removeLabel'), 'attr') ?>"
            data-label-job-title="<?= esc(lang('Admin.requestCategory.typeJobTitle'), 'attr') ?>"
            data-label-role="<?= esc(lang('Admin.requestCategory.typeRole'), 'attr') ?>"
            data-label-user="<?= esc(lang('Admin.requestCategory.typeUser'), 'attr') ?>"
            data-label-allow="<?= esc(lang('Admin.requestCategory.rightAllow'), 'attr') ?>"
            data-label-deny="<?= esc(lang('Admin.requestCategory.rightDeny'), 'attr') ?>"
            data-msg-choose="<?= esc(lang('Admin.requestCategory.chooseFirst'), 'attr') ?>"
            data-msg-duplicate="<?= esc(lang('Admin.requestCategory.duplicate'), 'attr') ?>">
            <p class="form-section-label mb-1" id="accessTitle"><?= esc(lang('Admin.requestCategory.accessTitle')) ?>
            </p>
            <p class="help mt-0 mb-3 <?= $isSub ? 'is-hidden' : '' ?>" data-access-hint>
                <?= esc(lang('Admin.requestCategory.accessHint')) ?></p>
            <p class="help mt-0 mb-3 <?= $isSub ? '' : 'is-hidden' ?>" data-access-sub-hint>
                <?= esc(lang('Admin.requestCategory.accessSubHint')) ?></p>

            <?php $accessError = $errors['job_titles.*'] ?? $errors['role_ids.*'] ?? $errors['user_access.*'] ?? null ?>
            <?php if ($accessError !== null): ?>
                <p class="help is-danger"><?= esc($accessError) ?></p>
            <?php endif ?>

            <div class="table-container access-table-wrap">
                <table class="table is-fullwidth access-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= esc(lang('Admin.requestCategory.accessColumnType')) ?></th>
                            <th scope="col"><?= esc(lang('Admin.requestCategory.accessColumnName')) ?></th>
                            <th scope="col"><?= esc(lang('Admin.requestCategory.accessColumnRight')) ?></th>
                            <th scope="col" class="has-text-right"><span
                                    class="is-sr-only"><?= esc(lang('Admin.requestCategory.accessColumnAction')) ?></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody data-access-body>
                        <tr data-access-empty <?= $accessEntries === [] ? '' : 'hidden' ?>>
                            <td colspan="4" class="has-text-grey"><?= esc(lang('Admin.requestCategory.accessEmpty')) ?>
                            </td>
                        </tr>
                        <?php foreach ($accessEntries as $entry): ?>
                            <?= view('admin/request_categories/_access_row', $entry + [
                                'locked' => $entry['type'] === 'user' && $entry['value'] === $actorId,
                                'readonly' => $entry['type'] === 'user' && !$canManageUserAccess,
                            ]) ?>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <p class="is-sr-only" role="status" aria-live="polite" data-access-status></p>

            <?php if (!$canManageUserAccess): ?>
                <p class="help"><?= esc(lang('Admin.requestCategory.userNoPermission')) ?></p>
            <?php endif ?>

            <fieldset class="access-add" aria-label="<?= esc(lang('Admin.requestCategory.addTitle'), 'attr') ?>">
                <legend class="access-add-title"><?= esc(lang('Admin.requestCategory.addTitle')) ?></legend>

                <div class="access-types" role="radiogroup"
                    aria-label="<?= esc(lang('Admin.requestCategory.accessColumnType'), 'attr') ?>">
                    <?php foreach (['job_title' => 'typeJobTitle', 'role' => 'typeRole', 'user' => 'typeUser'] as $type => $langKey): ?>
                        <?php $typeDisabled = $type === 'user' && !$canManageUserAccess ?>
                        <label class="access-type <?= $typeDisabled ? 'is-disabled' : '' ?>">
                            <input type="radio" name="access_type" value="<?= $type ?>" data-access-type
                                <?= $type === 'job_title' ? 'checked' : '' ?>     <?= $typeDisabled ? 'disabled' : '' ?>>
                            <span><?= esc(lang('Admin.requestCategory.' . $langKey)) ?></span>
                        </label>
                    <?php endforeach ?>
                </div>

                <div class="access-picker" data-access-picker="job_title">
                    <?= component('select', [
                        'name' => 'pick_job_title',
                        'label' => lang('Admin.requestCategory.pickJobTitle'),
                        'hideLabel' => true,
                        'options' => $jobTitleOptions,
                        'placeholder' => lang('Admin.requestCategory.pickJobTitle'),
                        'searchPlaceholder' => lang('Admin.requestCategory.pickSearch'),
                        'fullwidth' => true,
                        'searchable' => true,
                    ]) ?>
                </div>
                <div class="access-picker is-hidden" data-access-picker="role">
                    <?= component('select', [
                        'name' => 'pick_role',
                        'label' => lang('Admin.requestCategory.pickRole'),
                        'hideLabel' => true,
                        'options' => $roleOptions,
                        'placeholder' => lang('Admin.requestCategory.pickRole'),
                        'searchPlaceholder' => lang('Admin.requestCategory.pickSearch'),
                        'fullwidth' => true,
                        'searchable' => true,
                    ]) ?>
                </div>
                <?php if ($canManageUserAccess): ?>
                    <div class="access-picker is-hidden" data-access-picker="user">
                        <?= component('select', [
                            'name' => 'pick_user',
                            'label' => lang('Admin.requestCategory.pickUser'),
                            'hideLabel' => true,
                            'options' => $userOptions,
                            'placeholder' => lang('Admin.requestCategory.pickUser'),
                            'searchPlaceholder' => lang('Admin.requestCategory.pickSearch'),
                            'fullwidth' => true,
                            'searchable' => true,
                        ]) ?>
                    </div>

                    <div class="access-types is-hidden" role="radiogroup" data-access-effects
                        aria-label="<?= esc(lang('Admin.requestCategory.accessColumnRight'), 'attr') ?>">
                        <label class="access-type is-allow">
                            <input type="radio" name="access_effect" value="allow" checked>
                            <span><?= esc(lang('Admin.requestCategory.rightAllow')) ?></span>
                        </label>
                        <label class="access-type is-deny">
                            <input type="radio" name="access_effect" value="deny">
                            <span><?= esc(lang('Admin.requestCategory.rightDeny')) ?></span>
                        </label>
                    </div>
                <?php endif ?>

                <button type="button" class="button is-primary access-add-button" data-access-add>
                    <span class="icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                    <span><?= esc(lang('Admin.requestCategory.addButton')) ?></span>
                </button>
            </fieldset>
            <p class="help is-danger is-hidden" role="alert" data-access-error></p>
        </section>
    </div>

    <div class="form-card-footer">
        <a class="button is-light" href="<?= url_to('admin.request-categories.index') ?>">Batal</a>
        <button type="submit" class="button is-primary">
            <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
            <span><?= esc($submitLabel) ?></span>
        </button>
    </div>
</form>