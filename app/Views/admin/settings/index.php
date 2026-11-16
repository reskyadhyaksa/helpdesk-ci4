<?php
/**
 * Pengaturan sistem: tab kelompok di header (satu kartu tampil per tab), satu kartu per kelompok di bawahnya (tiap parameter satu baris:
 * label + bantuan di kiri, isian di kanan), dan bilah simpan melekat di bawah yang menunjukkan ada tidaknya perubahan.
 * Definisi (label, satuan, rentang, subbagian) dari SystemParameterModel::DEFAULTS; perilaku di js/pages/admin/settings.js.
 *
 * @var array<string, list<array>> $groups
 * @var array{upload_mb: int, post_mb: int} $phpLimits
 */
$groupIcons = ['attachment' => 'fa-paperclip', 'notification' => 'fa-bell', 'sla' => 'fa-stopwatch'];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?php component_start('page_header', [
        'title'    => lang('Admin.pages.settings.title'),
        'subtitle' => lang('Admin.pages.settings.subtitle'),
    ]) ?>
        <?php component_slot('tabs') ?>
        <div class="admin-page-header-tab-list" role="tablist" aria-label="Kelompok pengaturan" data-header-tabs data-storage-key="settings.tab" data-default="<?= esc((string) array_key_first($groups), 'attr') ?>">
            <?php foreach ($groups as $group => $items): ?>
                <button type="button" class="admin-page-header-tab" role="tab" id="tab-<?= esc($group, 'attr') ?>" aria-controls="group-<?= esc($group, 'attr') ?>" data-tab="<?= esc($group, 'attr') ?>">
                    <i class="fa-solid <?= esc($groupIcons[$group] ?? 'fa-gear', 'attr') ?>" aria-hidden="true"></i>
                    <?= esc(lang('Admin.settings.groups.' . $group)) ?>
                    <span class="admin-page-header-tab-count"><?= count($items) ?></span>
                </button>
            <?php endforeach ?>
        </div>
    <?= component_end() ?>

    <form class="settings-layout" action="<?= url_to('admin.settings.update') ?>" method="post" novalidate data-settings-form>
        <?= csrf_field() ?>

        <div class="settings-main">
            <?php foreach ($groups as $group => $items): ?>
                <section class="box p-0 settings-card" id="group-<?= esc($group, 'attr') ?>" role="tabpanel" aria-labelledby="tab-<?= esc($group, 'attr') ?>" data-tab-panel="<?= esc($group, 'attr') ?>">
                    <header class="settings-card-head">
                        <span class="settings-card-icon"><i class="fa-solid <?= esc($groupIcons[$group] ?? 'fa-gear', 'attr') ?>" aria-hidden="true"></i></span>
                        <div>
                            <h2 class="settings-card-title"><?= esc(lang('Admin.settings.groups.' . $group)) ?></h2>
                            <p><?= esc(lang('Admin.settings.groupHints.' . $group)) ?></p>
                        </div>
                    </header>

                    <?php if ($group === 'attachment'): ?>
                        <p class="settings-note" role="note">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span>
                                Batas PHP di server ini: upload per file <strong><?= esc((string) $phpLimits['upload_mb']) ?> MB</strong>,
                                total per kiriman <strong><?= $phpLimits['post_mb'] > 0 ? esc((string) $phpLimits['post_mb']) . ' MB' : 'tanpa batas' ?></strong>.
                                Pengaturan di bawah tidak bisa melampauinya.
                            </span>
                        </p>
                    <?php endif ?>

                    <?php // Dua kolom parameter di layar lebar (admin-settings.css); judul subbagian dan isian teks panjang selebar kartu ?>
                    <div class="settings-grid">
                    <?php $lastSection = null ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $key     = $item['key'];
                        $name    = 'params[' . $key . ']';
                        $id      = 'p-' . $key;
                        $value   = old('params.' . $key, $item['value']);
                        $isBool  = $item['type'] === 'BOOL';
                        $isInt   = $item['type'] === 'INT';
                        $default = $isBool ? ($item['default'] === '1' ? 'Aktif' : 'Nonaktif') : $item['default'] . ($item['unit'] !== null ? ' ' . $item['unit'] : '');
                        ?>

                        <?php if ($item['section'] !== null && $item['section'] !== $lastSection): ?>
                            <h3 class="settings-section"><?= esc($item['section']) ?></h3>
                        <?php endif ?>
                        <?php $lastSection = $item['section'] ?>

                        <div class="settings-row">
                            <div class="settings-row-text">
                                <?php if ($isBool): ?>
                                    <p class="settings-row-label" id="lbl-<?= esc($key, 'attr') ?>"><?= esc($item['label']) ?></p>
                                <?php else: ?>
                                    <label class="settings-row-label" for="<?= esc($id, 'attr') ?>"><?= esc($item['label']) ?></label>
                                <?php endif ?>
                                <p class="settings-row-help" id="help-<?= esc($key, 'attr') ?>"><?= esc($item['help']) ?></p>
                            </div>

                            <div class="settings-row-control <?= $item['type'] === 'STRING' ? 'is-wide' : '' ?>">
                                <?php if ($isBool): ?>
                                    <!-- Nilai 0 terkirim saat saklar mati -->
                                    <input type="hidden" name="<?= esc($name, 'attr') ?>" value="0">
                                    <label class="settings-switch">
                                        <input type="checkbox" role="switch" name="<?= esc($name, 'attr') ?>" value="1"
                                            aria-labelledby="lbl-<?= esc($key, 'attr') ?>" aria-describedby="help-<?= esc($key, 'attr') ?>"
                                            data-saved="<?= esc($item['value'], 'attr') ?>" <?= $value === '1' ? 'checked' : '' ?>>
                                        <span class="settings-switch-track" aria-hidden="true"></span>
                                        <span class="settings-switch-text" aria-hidden="true">
                                            <span class="is-on">Aktif</span><span class="is-off">Nonaktif</span>
                                        </span>
                                    </label>
                                <?php elseif ($isInt): ?>
                                    <div class="settings-input-group">
                                        <input class="input" type="number" inputmode="numeric" step="1"
                                            min="<?= (int) $item['min'] ?>" max="<?= (int) $item['max'] ?>"
                                            id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>"
                                            value="<?= esc($value, 'attr') ?>" data-saved="<?= esc($item['value'], 'attr') ?>"
                                            aria-describedby="help-<?= esc($key, 'attr') ?> meta-<?= esc($key, 'attr') ?>">
                                        <?php if ($item['unit'] !== null): ?>
                                            <span class="settings-unit"><?= esc($item['unit']) ?></span>
                                        <?php endif ?>
                                    </div>
                                <?php else: ?>
                                    <input class="input" type="text" maxlength="255" id="<?= esc($id, 'attr') ?>"
                                        name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>" data-saved="<?= esc($item['value'], 'attr') ?>"
                                        aria-describedby="help-<?= esc($key, 'attr') ?> meta-<?= esc($key, 'attr') ?>">
                                <?php endif ?>

                                <p class="settings-meta" id="meta-<?= esc($key, 'attr') ?>">
                                    <?php if ($isInt): ?>Rentang <?= (int) $item['min'] ?>–<?= (int) $item['max'] ?> · <?php endif ?>Bawaan: <?= esc($default) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach ?>
                    </div>
                </section>
            <?php endforeach ?>

            <div class="settings-savebar" data-savebar>
                <p class="settings-savebar-text" role="status" data-savebar-text>
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>Belum ada perubahan.</span>
                </p>
                <button type="button" class="button is-light" data-settings-reset disabled>Batalkan</button>
                <button type="submit" class="button is-primary" data-settings-save>
                    <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
                    <span>Simpan Pengaturan</span>
                </button>
            </div>
        </div>
    </form>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/components/header-tabs.js') ?>"></script>
<script src="<?= base_url('js/pages/admin/settings.js') ?>"></script>
<?= $this->endSection() ?>
