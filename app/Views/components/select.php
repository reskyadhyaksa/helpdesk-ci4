<?php
/**
 * Dropdown Bulma: label, pilihan, keadaan error, dan teks bantuan. Opsional: kotak cari di dalam menu
 * ('searchable') dan multi-pilih dengan checkbox ('multiple'). Tampilan kustomnya dibuat js/components/select.js.
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
 * Dengan pencarian (cocok untuk daftar panjang):
 *   <?= component('select', ['name' => 'job_title', 'label' => 'Jabatan', 'options' => $map, 'searchable' => true]) ?>
 *
 * Multi-pilih dengan checkbox: dikirim sebagai array (name[]), $selected berupa array nilai.
 * $placeholder dipakai sebagai teks saat belum ada yang dipilih. Di dalam form[data-autosubmit] form baru
 * dikirim saat menu ditutup, bukan setiap kali mencentang.
 *   <?= component('select', ['name' => 'roles', 'label' => 'Role', 'options' => $map, 'multiple' => true,
 *       'selected' => $selectedValues, 'placeholder' => 'Semua role', 'searchable' => true]) ?>
 *
 * @var string                $name
 * @var string                $label
 * @var array<string, string> $options      value => teks
 * @var string|array|null     $selected     value terpilih (array jika $multiple)
 * @var string|null           $id           default sama dengan $name
 * @var string|null           $placeholder  opsi kosong di urutan pertama (multi-pilih: teks saat kosong)
 * @var string|null           $error        pesan error validasi
 * @var string|null           $help         teks bantuan di bawah dropdown
 * @var bool                  $searchable   tampilkan kotak cari di dalam menu
 * @var string|null           $searchPlaceholder  teks kotak cari, default "Cari…"
 * @var bool                  $multiple     multi-pilih dengan checkbox + baris "Pilih semua" (kosong/sebagian/penuh)
 * @var string|null           $emptyLabel   multi-pilih: keterangan footer saat tidak ada pilihan, mis. "Tanpa filter"
 * @var bool                  $hideLabel    label hanya terbaca screen reader
 * @var bool                  $required
 * @var bool                  $disabled
 * @var bool                  $fullwidth
 * @var string                $size         '' | 'is-small' | 'is-medium' | 'is-large'
 */
$id                ??= $name;
$selected          ??= null;
$placeholder       ??= null;
$error             ??= null;
$help              ??= null;
$searchable        ??= false;
$searchPlaceholder ??= 'Cari…';
$multiple          ??= false;
$emptyLabel        ??= null;
$hideLabel         ??= false;
$required          ??= false;
$disabled          ??= false;
$fullwidth         ??= false;
$size              ??= '';

$selectedValues = array_map('strval', (array) $selected);

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
    <div class="<?= esc(implode(' ', array_filter($wrapperClasses))) ?>" data-select
        <?= $searchable ? 'data-searchable data-search-placeholder="' . esc($searchPlaceholder, 'attr') . '"' : '' ?>
        <?= $multiple && $placeholder !== null ? 'data-placeholder="' . esc($placeholder, 'attr') . '"' : '' ?>
        <?= $multiple && $emptyLabel !== null ? 'data-empty-label="' . esc($emptyLabel, 'attr') . '"' : '' ?>>
        <select id="<?= esc($id, 'attr') ?>" name="<?= esc($multiple ? $name . '[]' : $name, 'attr') ?>"
            <?= $multiple ? 'multiple' : '' ?>
            <?= $required ? 'required' : '' ?>
            <?= $disabled ? 'disabled' : '' ?>
            <?= $error !== null ? 'aria-invalid="true"' : '' ?>
            <?= $describedBy !== [] ? 'aria-describedby="' . esc(implode(' ', $describedBy)) . '"' : '' ?>>
            <?php if ($placeholder !== null && ! $multiple): ?>
                <option value=""><?= esc($placeholder) ?></option>
            <?php endif ?>
            <?php foreach ($options as $value => $text): ?>
                <option value="<?= esc((string) $value, 'attr') ?>" <?= in_array((string) $value, $selectedValues, true) ? 'selected' : '' ?>><?= esc($text) ?></option>
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
