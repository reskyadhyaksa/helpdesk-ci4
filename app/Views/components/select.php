<?php
/**
 * Dropdown Bulma: label, pilihan, keadaan error, dan teks bantuan.
 * Tidak membungkus dengan .field; bungkus sendiri jika dipakai di dalam form bertumpuk.
 *
 * Contoh field form:
 *   <div class="field">
 *     <?= component('select', [
 *         'name' => 'priority', 'label' => 'Prioritas', 'options' => $map,
 *         'selected' => old('priority', $value), 'placeholder' => 'Pilih prioritas',
 *         'required' => true, 'fullwidth' => true, 'error' => $errors['priority'] ?? null,
 *     ]) ?>
 *   </div>
 *
 * Contoh inline ringkas (label hanya untuk screen reader):
 *   <?= component('select', ['name' => 'role_id', 'id' => 'role-1', 'label' => 'Role untuk Budi',
 *       'hideLabel' => true, 'options' => $map, 'selected' => $roleId, 'size' => 'is-small']) ?>
 *
 * @var string                $name
 * @var string                $label
 * @var array<string, string> $options      value => teks
 * @var string|null           $selected     value yang terpilih
 * @var string|null           $id           default sama dengan $name
 * @var string|null           $placeholder  opsi kosong di urutan pertama
 * @var string|null           $error        pesan error validasi
 * @var string|null           $help         teks bantuan di bawah dropdown
 * @var bool                  $hideLabel    label hanya terbaca screen reader
 * @var bool                  $required
 * @var bool                  $disabled
 * @var bool                  $fullwidth
 * @var string                $size         '' | 'is-small' | 'is-medium' | 'is-large'
 */
$id          ??= $name;
$selected    ??= null;
$placeholder ??= null;
$error       ??= null;
$help        ??= null;
$hideLabel   ??= false;
$required    ??= false;
$disabled    ??= false;
$fullwidth   ??= false;
$size        ??= '';

// Pesan error dan bantuan dihubungkan ke dropdown agar dibacakan screen reader
$describedBy = [];

if ($help !== null) {
    $describedBy[] = $id . 'Help';
}

if ($error !== null) {
    $describedBy[] = $id . 'Error';
}

$wrapperClasses = ['select', $size];

if ($fullwidth) {
    $wrapperClasses[] = 'is-fullwidth';
}

if ($error !== null) {
    $wrapperClasses[] = 'is-danger';
}
?>
<label class="<?= $hideLabel ? 'is-sr-only' : 'label' ?>" for="<?= esc($id, 'attr') ?>">
    <?= esc($label) ?>
    <?php if ($required && ! $hideLabel): ?>
        <span class="has-text-danger" aria-hidden="true">*</span>
    <?php endif ?>
</label>
<div class="control">
    <?php // data-select: diganti dropdown kustom oleh js/components/select.js; tanpa JS tetap dropdown bawaan ?>
    <div class="<?= esc(implode(' ', array_filter($wrapperClasses))) ?>" data-select>
        <select id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>"
            <?= $required ? 'required' : '' ?>
            <?= $disabled ? 'disabled' : '' ?>
            <?= $error !== null ? 'aria-invalid="true"' : '' ?>
            <?= $describedBy !== [] ? 'aria-describedby="' . esc(implode(' ', $describedBy)) . '"' : '' ?>>
            <?php if ($placeholder !== null): ?>
                <option value=""><?= esc($placeholder) ?></option>
            <?php endif ?>
            <?php foreach ($options as $value => $text): ?>
                <option value="<?= esc((string) $value, 'attr') ?>" <?= (string) $value === (string) $selected ? 'selected' : '' ?>><?= esc($text) ?></option>
            <?php endforeach ?>
        </select>
    </div>
</div>
<?php if ($help !== null): ?>
    <p class="help" id="<?= esc($id, 'attr') ?>Help"><?= esc($help) ?></p>
<?php endif ?>
<?php if ($error !== null): ?>
    <p class="help is-danger" id="<?= esc($id, 'attr') ?>Error"><?= esc($error) ?></p>
<?php endif ?>
