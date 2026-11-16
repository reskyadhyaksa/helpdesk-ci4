<?php // Pratinjau lampiran: isinya diisi js/pages/admin/ticket-detail.js saat tombol mata ditekan ?>
<?php component_start('modal', ['id' => 'attachmentPreview', 'title' => 'Pratinjau Lampiran', 'size' => 'is-large preview-modal']) ?>
    <div class="preview-body" data-preview-body></div>
<?php component_slot('footer') ?>
    <a class="button is-light" href="#" data-preview-download download>
        <span class="icon"><i class="fa-solid fa-download" aria-hidden="true"></i></span>
        <span>Unduh</span>
    </a>
    <button type="button" class="button is-primary" data-modal-close>Tutup</button>
<?= component_end() ?>
