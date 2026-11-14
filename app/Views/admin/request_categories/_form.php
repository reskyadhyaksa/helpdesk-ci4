<?php
/**
 * Form kategori permintaan, dipakai halaman tambah dan ubah.
 * Dua kolom: kiri informasi kategori, kanan panel akses job title + status.
 * Job title hanya untuk kategori utama; field disembunyikan saat induk dipilih (js/pages/admin/request-category-form.js).
 *
 * @var array                 $category
 * @var array<string, string> $parentOptions    id => nama kategori utama
 * @var array<string, string> $slaOptions       id => nama SLA aktif
 * @var array<string, string> $jobTitleOptions  job title => job title
 * @var list<string>          $savedJobTitles
 * @var string                $formAction
 * @var string                $submitLabel
 */
$errors   = session('errors') ?? [];
$isActive = (string) old('is_active', (string) $category['is_active']) === '1';
$parentId = old('parent_id', $category['parent_id']);
$isSub    = $parentId !== null && $parentId !== '';
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
                            type="text" maxlength="100" required value="<?= esc(old('name', $category['name']), 'attr') ?>"
                            placeholder="Contoh: Payroll" <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="nameError"' : '' ?>>
                    </div>
                    <?php if (isset($errors['name'])): ?>
                        <p class="help is-danger" id="nameError"><?= esc($errors['name']) ?></p>
                    <?php endif ?>
                </div>

                <div class="field">
                    <?= component('select', [
                        'name'        => 'parent_id',
                        'label'       => 'Kategori Induk',
                        'options'     => $parentOptions,
                        'selected'    => $parentId,
                        'placeholder' => 'Tanpa induk (kategori utama)',
                        'fullwidth'   => true,
                        'searchable'  => true,
                        'help'        => 'Kosongkan untuk kategori utama. Kategori yang sudah punya sub kategori tidak bisa dijadikan sub kategori.',
                        'error'       => $errors['parent_id'] ?? null,
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

                <div class="field">
                    <?= component('select', [
                        'name'        => 'sla_policy_id',
                        'label'       => 'Kebijakan SLA',
                        'options'     => $slaOptions,
                        'selected'    => old('sla_policy_id', $category['sla_policy_id']),
                        'placeholder' => 'Tanpa SLA / ikut induk',
                        'fullwidth'   => true,
                        'error'       => $errors['sla_policy_id'] ?? null,
                    ]) ?>
                </div>
            </div>

            <div class="column is-5-desktop">
                <div class="form-panel">
                    <p class="form-section-label">Akses</p>

                    <div class="field <?= $isSub ? 'is-hidden' : '' ?>" data-job-title-field>
                        <?= component('select', [
                            'name'        => 'job_titles',
                            'label'       => 'Job title yang boleh melihat',
                            'options'     => $jobTitleOptions,
                            'selected'    => old('job_titles', $savedJobTitles),
                            'placeholder' => 'Semua job title',
                            'fullwidth'   => true,
                            'multiple'    => true,
                            'searchable'  => true,
                            'emptyLabel'  => 'Belum ada job title',
                            'disabled'    => $isSub,
                            'help'        => 'Hanya untuk kategori utama. Sub kategori mengikuti induknya.',
                            'error'       => $errors['job_titles.*'] ?? null,
                        ]) ?>
                    </div>
                    <p class="help mt-0 <?= $isSub ? '' : 'is-hidden' ?>" data-job-title-inherit>Ikut induk.</p>

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
    </div>

    <div class="form-card-footer">
        <a class="button is-light" href="<?= url_to('admin.request-categories.index') ?>">Batal</a>
        <button type="submit" class="button is-primary">
            <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
            <span><?= esc($submitLabel) ?></span>
        </button>
    </div>
</form>
