<?php
/**
 * Pengaturan sistem: satu kartu per kelompok parameter. Definisi (label, rentang) dari SystemParameterModel::DEFAULTS.
 *
 * @var array<string, list<array>> $groups
 * @var array{upload_mb: int, post_mb: int} $phpLimits
 */
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.settings.title'),
        'subtitle' => lang('Admin.pages.settings.subtitle'),
    ]) ?>

    <form action="<?= url_to('admin.settings.update') ?>" method="post" novalidate>
        <?= csrf_field() ?>

        <?php foreach ($groups as $group => $items): ?>
            <div class="box p-0 mb-5">
                <div class="form-card-body">
                    <p class="form-section-label"><?= esc(lang('Admin.settings.groups.' . $group)) ?></p>

                    <?php if ($group === 'attachment'): ?>
                        <p class="help mb-4">
                            Batas PHP di server ini: upload per file <strong><?= esc((string) $phpLimits['upload_mb']) ?> MB</strong>,
                            total per kiriman <strong><?= $phpLimits['post_mb'] > 0 ? esc((string) $phpLimits['post_mb']) . ' MB' : 'tanpa batas' ?></strong>.
                            Pengaturan di bawah tidak bisa melampauinya.
                        </p>
                    <?php endif ?>

                    <?php foreach ($items as $item): ?>
                        <?php $name = 'params[' . $item['key'] . ']'; ?>
                        <div class="field">
                            <?php if ($item['type'] === 'BOOL'): ?>
                                <!-- Nilai 0 terkirim saat checkbox tidak dicentang -->
                                <input type="hidden" name="<?= esc($name, 'attr') ?>" value="0">
                                <label class="option-card">
                                    <input type="checkbox" name="<?= esc($name, 'attr') ?>" value="1"
                                        <?= old('params.' . $item['key'], $item['value']) === '1' ? 'checked' : '' ?>>
                                    <span class="option-card-text">
                                        <span class="option-card-label"><?= esc($item['label']) ?></span>
                                        <span class="option-card-desc"><?= esc($item['help']) ?></span>
                                    </span>
                                </label>
                            <?php else: ?>
                                <label class="label" for="p-<?= esc($item['key'], 'attr') ?>"><?= esc($item['label']) ?></label>
                                <div class="control">
                                    <?php if ($item['type'] === 'INT'): ?>
                                        <input class="input" style="max-width: 12rem" type="number" inputmode="numeric" step="1"
                                            min="<?= (int) $item['min'] ?>" max="<?= (int) $item['max'] ?>"
                                            id="p-<?= esc($item['key'], 'attr') ?>" name="<?= esc($name, 'attr') ?>"
                                            value="<?= esc(old('params.' . $item['key'], $item['value']), 'attr') ?>">
                                    <?php else: ?>
                                        <input class="input" type="text" maxlength="255" id="p-<?= esc($item['key'], 'attr') ?>"
                                            name="<?= esc($name, 'attr') ?>" value="<?= esc(old('params.' . $item['key'], $item['value']), 'attr') ?>">
                                    <?php endif ?>
                                </div>
                                <p class="help"><?= esc($item['help']) ?></p>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endforeach ?>

        <div class="is-flex is-justify-content-flex-end mb-5">
            <button type="submit" class="button is-primary">
                <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
                <span>Simpan Pengaturan</span>
            </button>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
