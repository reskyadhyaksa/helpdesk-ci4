<?php
/**
 * Bagian lampiran, pernyataan, dan tombol kirim di dalam form tiket (lihat tickets/_form.php).
 * Perilaku drag & drop, daftar file, dan tombol kirim ada di public/js/pages/ticket-form.js.
 *
 * @var array $limits extensions, max_mb, max_files
 */
$extensions = array_map('strtoupper', $limits['extensions']);
?>
<div class="ticket-section-head">
    <h2 class="ticket-section-title"><?= esc(lang('Ticket.form.sectionAttach')) ?></h2>
    <span class="ticket-section-note"><?= esc(lang('Ticket.form.optionalAdvised')) ?></span>
</div>
<p class="mb-4"><?= esc(lang('Ticket.form.attachIntro')) ?></p>

<label class="ticket-dropzone" for="attachments" data-dropzone>
    <input class="ticket-dropzone-input" type="file" id="attachments" name="attachments[]" multiple
        accept="<?= esc('.' . implode(',.', $limits['extensions']), 'attr') ?>">
    <span class="ticket-dropzone-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
    <span class="ticket-dropzone-title">
        <?= esc(lang('Ticket.form.dropText')) ?>
        <span class="ticket-dropzone-link"><?= esc(lang('Ticket.form.dropChoose')) ?></span>
        <?= esc(lang('Ticket.form.dropSuffix')) ?>
    </span>
    <span class="ticket-dropzone-hint">
        <?= esc(lang('Ticket.form.dropLimits', [implode(', ', $extensions), $limits['max_mb'], $limits['max_files']])) ?>
    </span>
</label>
<ul class="ticket-files" data-file-list data-remove-label="<?= esc(lang('Ticket.form.removeFile'), 'attr') ?>"></ul>

<label class="checkbox ticket-agreement">
    <input type="checkbox" name="agreement" value="1" data-agreement>
    <span><?= esc(lang('Ticket.form.agreement')) ?></span>
</label>

<button type="submit" class="button is-primary is-fullwidth is-medium" data-submit>
    <?= esc(lang('Ticket.form.submit')) ?>
</button>
