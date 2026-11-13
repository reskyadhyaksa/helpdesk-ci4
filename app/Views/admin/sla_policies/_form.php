<?php

use App\Entities\SlaPolicy;

/**
 * Form kebijakan SLA, dipakai halaman tambah dan ubah.
 * Dua kolom: kiri informasi kebijakan, kanan panel target waktu + status.
 *
 * @var SlaPolicy $policy
 * @var string    $formAction
 * @var string    $submitLabel
 */
$errors = session('errors') ?? [];
$isActive = old('is_active', $policy->is_active ? '1' : '0') === '1';

// Target dalam menit. Entity mengubah nilai kosong jadi 0, jadi 0 ditampilkan kosong (nilai valid selalu > 0).
$targets = [
    'response_minutes' => ['label' => 'Respons pertama', 'placeholder' => '60', 'help' => 'Batas waktu tiket pertama kali ditanggapi.'],
    'resolution_minutes' => ['label' => 'Penyelesaian', 'placeholder' => '480', 'help' => 'Batas waktu tiket selesai ditangani.'],
];

// Hari yang dilewati saat menghitung target (lihat Entity SlaPolicy)
$calendarFlags = [
    'is_holiday' => ['label' => 'Lewati hari libur', 'desc' => 'Tanggal merah dan cuti bersama tidak dihitung.'],
    'is_off_day' => ['label' => 'Lewati hari off', 'desc' => 'Hari off mingguan (default Sabtu & Minggu) tidak dihitung.'],
];
?>
<form class="box p-0" action="<?= esc($formAction, 'attr') ?>" method="post" novalidate>
    <?= csrf_field() ?>

    <div class="form-card-body">
        <div class="columns is-variable is-5">
            <div class="column is-7-desktop">
                <p class="form-section-label">Informasi Kebijakan</p>

                <div class="field">
                    <label class="label" for="name">Nama Kebijakan <span class="has-text-danger"
                            aria-hidden="true">*</span></label>
                    <div class="control">
                        <input class="input <?= isset($errors['name']) ? 'is-danger' : '' ?>" id="name" name="name"
                            type="text" maxlength="100" required value="<?= esc(old('name', $policy->name), 'attr') ?>"
                            placeholder="Contoh: Urgent" <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="nameError"' : '' ?>>
                    </div>
                    <?php if (isset($errors['name'])): ?>
                        <p class="help is-danger" id="nameError"><?= esc($errors['name']) ?></p>
                    <?php endif ?>
                </div>

                <div class="field">
                    <?= component('select', [
                        'name' => 'priority',
                        'label' => 'Prioritas',
                        'options' => SlaPolicy::PRIORITY_LABELS,
                        'selected' => old('priority', $policy->priority),
                        'placeholder' => 'Pilih prioritas',
                        'required' => true,
                        'fullwidth' => true,
                        'error' => $errors['priority'] ?? null,
                    ]) ?>
                </div>

                <div class="field">
                    <label class="label" for="description">Deskripsi <span
                            class="has-text-weight-normal has-text-grey-dark">(Opsional)</span></label>
                    <div class="control">
                        <textarea class="textarea <?= isset($errors['description']) ? 'is-danger' : '' ?>"
                            id="description" name="description" rows="3" maxlength="255"
                            placeholder="Kapan kebijakan ini dipakai, mis. akun HC Eazy terkunci saat jam kerja"><?= esc(old('description', $policy->description)) ?></textarea>
                    </div>
                    <?php if (isset($errors['description'])): ?>
                        <p class="help is-danger"><?= esc($errors['description']) ?></p>
                    <?php endif ?>
                </div>

                <p class="form-section-label mt-5">Perhitungan Hari</p>

                <div class="columns is-variable is-3 mb-0">
                    <?php foreach ($calendarFlags as $field => $flag): ?>
                        <div class="column">
                            <!-- Nilai 0 terkirim saat checkbox tidak dicentang -->
                            <input type="hidden" name="<?= $field ?>" value="0">
                            <label class="option-card h-100">
                                <input type="checkbox" name="<?= $field ?>" value="1"
                                    <?= old($field, $policy->{$field} ? '1' : '0') === '1' ? 'checked' : '' ?>>
                                <span class="option-card-text">
                                    <span class="option-card-label"><?= esc($flag['label']) ?></span>
                                    <span class="option-card-desc"><?= esc($flag['desc']) ?></span>
                                </span>
                            </label>
                        </div>
                    <?php endforeach ?>
                </div>
                <p class="help mt-0">
                    Daftar tanggal libur dan hari off diatur di
                    <a href="<?= url_to('admin.holidays.index') ?>">Data Management › Holiday</a>.
                </p>
            </div>

            <div class="column is-5-desktop">
                <div class="form-panel">
                    <p class="form-section-label">Target Waktu</p>

                    <?php foreach ($targets as $field => $target): ?>
                        <div class="field">
                            <label class="label" for="<?= $field ?>"><?= esc($target['label']) ?> <span
                                    class="has-text-danger" aria-hidden="true">*</span></label>
                            <div class="field has-addons mb-0">
                                <div class="control is-expanded">
                                    <input class="input <?= isset($errors[$field]) ? 'is-danger' : '' ?>" id="<?= $field ?>"
                                        name="<?= $field ?>" type="number" min="1" max="43200" step="1" required
                                        value="<?= esc(old($field, $policy->{$field} ?: ''), 'attr') ?>"
                                        placeholder="<?= esc($target['placeholder'], 'attr') ?>"
                                        aria-describedby="<?= $field ?>Help<?= isset($errors[$field]) ? ' ' . $field . 'Error' : '' ?>"
                                        <?= isset($errors[$field]) ? 'aria-invalid="true"' : '' ?>>
                                </div>
                                <div class="control">
                                    <span class="button is-static">menit</span>
                                </div>
                            </div>
                            <p class="help" id="<?= $field ?>Help"><?= esc($target['help']) ?></p>
                            <?php if (isset($errors[$field])): ?>
                                <p class="help is-danger" id="<?= $field ?>Error"><?= esc($errors[$field]) ?></p>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>

                    <p class="help mt-0">Contoh: 60 menit = 1 jam, 480 menit = 8 jam.</p>

                    <div class="form-panel-divider">
                        <p class="form-section-label">Status</p>

                        <!-- Nilai 0 terkirim saat checkbox tidak dicentang -->
                        <input type="hidden" name="is_active" value="0">
                        <label class="option-card">
                            <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                            <span class="option-card-text">
                                <span class="option-card-label">Aktif</span>
                                <span class="option-card-desc">Kebijakan bisa dipilih untuk kategori permintaan.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="form-card-footer">
        <a class="button is-light" href="<?= url_to('admin.sla-policies.index') ?>">Batal</a>
        <button type="submit" class="button is-primary">
            <span class="icon"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></span>
            <span><?= esc($submitLabel) ?></span>
        </button>
    </div>
</form>