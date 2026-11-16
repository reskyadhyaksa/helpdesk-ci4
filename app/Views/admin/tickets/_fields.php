<?php

/**
 * Isian inti tiket (kategori, judul, deskripsi, lampiran) untuk form admin membuatkan tiket (new.php).
 *
 * @var list<array>        $categories id, name, parent_name
 * @var array<string, string> $errors
 */
$options = [];

foreach ($categories as $category) {
    $options[$category['id']] = $category['parent_name'] !== null
        ? $category['parent_name'] . ' › ' . $category['name']
        : $category['name'];
}
?>
<div class="field">
    <?= component('select', [
        'name'        => 'category_id',
        'label'       => 'Kategori',
        'options'     => $options,
        'selected'    => old('category_id'),
        'placeholder' => 'Pilih kategori',
        'searchable'  => true,
        'required'    => true,
        'fullwidth'   => true,
        'error'       => $errors['category_id'] ?? null,
    ]) ?>
</div>

<div class="field">
    <label class="label" for="subject">Judul <span class="has-text-danger" aria-hidden="true">*</span></label>
    <div class="control">
        <input class="input <?= isset($errors['subject']) ? 'is-danger' : '' ?>" id="subject" name="subject"
            type="text" maxlength="150" required value="<?= esc(old('subject'), 'attr') ?>"
            placeholder="Ringkasan singkat kebutuhan Anda"
            <?= isset($errors['subject']) ? 'aria-invalid="true" aria-describedby="subjectError"' : '' ?>>
    </div>
    <?php if (isset($errors['subject'])): ?>
        <p class="help is-danger" id="subjectError"><?= esc($errors['subject']) ?></p>
    <?php endif ?>
</div>

<div class="field">
    <label class="label" for="description">Deskripsi <span class="has-text-danger" aria-hidden="true">*</span></label>
    <div class="control">
        <textarea class="textarea <?= isset($errors['description']) ? 'is-danger' : '' ?>" id="description"
            name="description" rows="6" maxlength="2000" required
            placeholder="Jelaskan kendala atau permintaan Anda selengkap mungkin"
            <?= isset($errors['description']) ? 'aria-invalid="true" aria-describedby="descriptionError"' : '' ?>><?= esc(old('description')) ?></textarea>
    </div>
    <?php if (isset($errors['description'])): ?>
        <p class="help is-danger" id="descriptionError"><?= esc($errors['description']) ?></p>
    <?php endif ?>
</div>

<div class="field">
    <label class="label" for="attachments">Lampiran <span class="has-text-weight-normal has-text-grey-dark">(Opsional)</span></label>
    <div class="control">
        <input class="input" type="file" id="attachments" name="attachments[]" multiple>
    </div>
    <p class="help">Tangkapan layar atau dokumen pendukung. Jenis dan ukuran file dibatasi sesuai pengaturan sistem.</p>
</div>
