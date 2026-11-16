<?php
/**
 * Lampiran tiket atau komentar (admin). Lampiran yang sudah dihapus kebijakan retensi tampil redup tanpa tautan unduh.
 *
 * @var list<array> $attachments
 * @var bool        $cards       true = kartu besar (deskripsi), false = daftar ringkas (di bawah komentar)
 * @var string      $routePrefix awalan route unduh/pratinjau; default 'admin.tickets' (portal user: 'user.tickets')
 * @var bool        $previewable tampilkan tombol pratinjau (hanya ada rutenya di area admin)
 */
use App\Services\AttachmentService;

$routePrefix ??= 'admin.tickets';
$previewable ??= true;

if ($attachments === []) {
    return;
}
?>
<ul class="ticket-files <?= $cards ? 'is-cards' : '' ?>">
    <?php foreach ($attachments as $file): ?>
        <?php
        $isImage = str_starts_with((string) $file['content_type'], 'image/');
        $purged  = ! empty($file['purged_at']);
        ?>
        <li class="ticket-file <?= $purged ? 'is-purged' : '' ?>">
            <span class="ticket-file-icon" aria-hidden="true"><i class="fa-regular <?= $isImage ? 'fa-image' : 'fa-file-lines' ?>"></i></span>
            <div class="ticket-file-info">
                <p class="ticket-file-name"><?= esc($file['original_name']) ?></p>
                <p class="ticket-file-meta">
                    <?= $purged ? esc(lang('Attachment.purged')) : esc(format_filesize((int) $file['size_bytes'])) ?>
                    • <?= esc(format_datetime($file['created_at'])) ?>
                </p>
            </div>
            <?php if ($previewable && ! $purged && AttachmentService::canPreview((string) $file['content_type'])): ?>
                <button type="button" class="ticket-file-action" data-modal-open="attachmentPreview"
                    data-preview-url="<?= esc(url_to($routePrefix . '.attachment.preview', $file['id']), 'attr') ?>"
                    data-preview-download="<?= esc(url_to($routePrefix . '.attachment', $file['id']), 'attr') ?>"
                    data-preview-type="<?= esc($file['content_type'] === AttachmentService::TYPE_PDF ? 'pdf' : (str_starts_with($file['content_type'], 'image/') ? 'image' : 'text'), 'attr') ?>"
                    data-preview-name="<?= esc($file['original_name'], 'attr') ?>"
                    aria-label="Lihat <?= esc($file['original_name'], 'attr') ?>" title="Lihat">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
            <?php endif ?>
            <?php if (! $purged): ?>
                <a class="ticket-file-action" href="<?= esc(url_to($routePrefix . '.attachment', $file['id']), 'attr') ?>"
                    aria-label="Unduh <?= esc($file['original_name'], 'attr') ?>" title="Unduh">
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                </a>
            <?php endif ?>
        </li>
    <?php endforeach ?>
</ul>
