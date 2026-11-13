<?php

/**
 * Form hari libur, dipakai halaman tambah dan ubah.
 *
 * @var array  $holiday     holiday_date, name, description, is_mass_leave
 * @var string $formAction
 * @var string $submitLabel
 */
$errors = session('errors') ?? [];

$fieldClass = static fn (string $field): string => isset($errors[$field]) ? 'is-danger' : '';
?>
<form class="box p-0" action="<?= esc($formAction, 'attr') ?>" method="post" novalidate>
    <?= csrf_field() ?>

    <div class="form-card-body">
        <div class="columns">
            <div class="column is-7-desktop">
                <p class="form-section-label">Informasi Hari Libur</p>

                <div class="field">
                    <label class="label" for="holiday_date">Tanggal <span class="has-text-danger" aria-hidden="true">*</span></label>
                    <div class="control">
                        <input class="input <?= $fieldClass('holiday_date') ?>" id="holiday_date" name="holiday_date"
                            type="date" required value="<?= esc(old('holiday_date', $holiday['holiday_date']), 'attr') ?>"
                            <?= isset($errors['holiday_date']) ? 'aria-invalid="true" aria-describedby="holiday_dateError"' : '' ?>>
                    </div>
                    <?php if (isset($errors['holiday_date'])): ?>
                        <p class="help is-danger" id="holiday_dateError"><?= esc($errors['holiday_date']) ?></p>
                    <?php endif ?>
                </div>

                <div class="field">
                    <label class="label" for="name">Nama Hari Libur <span class="has-text-danger" aria-hidden="true">*</span></label>
                    <div class="control">
                        <input class="input <?= $fieldClass('name') ?>" id="name" name="name" type="text" maxlength="150" required
                            value="<?= esc(old('name', $holiday['name']), 'attr') ?>" placeholder="Contoh: Hari Kemerdekaan RI"
                            <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="nameError"' : '' ?>>
                    </div>
                    <?php if (isset($errors['name'])): ?>
                        <p class="help is-danger" id="nameError"><?= esc($errors['name']) ?></p>
                    <?php endif ?>
                </div>

                <div class="field">
                    <label class="label" for="description">Deskripsi <span
                            class="has-text-weight-normal has-text-grey-dark">(Opsional)</span></label>
                    <div class="control">
                        <textarea class="textarea <?= $fieldClass('description') ?>" id="description" name="description"
                            rows="3" maxlength="255"><?= esc(old('description', $holiday['description'] ?? '')) ?></textarea>
                    </div>
                    <?php if (isset($errors['description'])): ?>
                        <p class="help is-danger"><?= esc($errors['description']) ?></p>
                    <?php endif ?>
                </div>

                <p class="form-section-label mt-5">Jenis</p>

                <!-- Nilai 0 terkirim saat checkbox tidak dicentang -->
                <input type="hidden" name="is_mass_leave" value="0">
                <label class="option-card">
                    <input type="checkbox" name="is_mass_leave" value="1"
                        <?= old('is_mass_leave', (string) $holiday['is_mass_leave']) === '1' ? 'checked' : '' ?>>
                    <span class="option-card-text">
                        <span class="option-card-label">Cuti bersama</span>
                        <span class="option-card-desc">Tidak dicentang berarti tanggal merah (hari libur nasional).</span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    <div class="form-card-footer">
        <a class="button is-light" href="<?= url_to('admin.holidays.index') ?>">Batal</a>
        <button type="submit" class="button is-primary">
            <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
            <span><?= esc($submitLabel) ?></span>
        </button>
    </div>
</form>
