<?php

/**
 * Percakapan tiket: lampiran saat tiket dibuat, komentar dengan lampirannya, dan form balas.
 *
 * @var array{comments: list<array>, ticketAttachments: list<array>} $thread
 * @var string $attachmentRoute nama route unduh lampiran
 * @var string $commentRoute    nama route kirim komentar
 * @var string $ticketId
 * @var bool   $isAgent         agen boleh memilih catatan internal
 * @var bool   $canReply        form balas ditampilkan
 */
$errors = session('errors') ?? [];

$renderAttachments = static function (array $attachments) use ($attachmentRoute): string {
    if ($attachments === []) {
        return '';
    }

    $html = '<ul class="mt-2">';

    foreach ($attachments as $attachment) {
        // Lampiran yang sudah dihapus oleh kebijakan retensi: tampil sebagai teks redup, tanpa tautan unduh
        if (! empty($attachment['purged_at'])) {
            $html .= '<li class="has-text-grey"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> '
                . esc($attachment['original_name']) . ' <span class="is-size-7">(' . esc(lang('Attachment.purged')) . ')</span></li>';

            continue;
        }

        $html .= '<li><a href="' . esc(url_to($attachmentRoute, $attachment['id']), 'attr') . '">'
            . '<i class="fa-solid fa-paperclip" aria-hidden="true"></i> ' . esc($attachment['original_name']) . '</a>'
            . ' <span class="has-text-grey is-size-7">(' . esc(number_format($attachment['size_bytes'] / 1024, 0, ',', '.')) . ' KB)</span></li>';
    }

    return $html . '</ul>';
};
?>
<?php if ($thread['ticketAttachments'] !== []): ?>
    <div class="box">
        <p class="form-section-label">Lampiran Berkas (<?= count($thread['ticketAttachments']) ?>)</p>
        <?= $renderAttachments($thread['ticketAttachments']) ?>
    </div>
<?php endif ?>

<div class="box">
    <p class="form-section-label">Komentar &amp; Diskusi (<?= count($thread['comments']) ?>)</p>

    <?php if ($thread['comments'] === []): ?>
        <p class="has-text-grey">Belum ada pesan.</p>
    <?php endif ?>

    <?php foreach ($thread['comments'] as $comment): ?>
        <?php $internal = $comment['visibility'] === 'INTERNAL' ?>
        <article class="mb-4 p-3" style="border-radius: 0.75rem; background: <?= $internal ? '#fffbeb' : '#f8fafc' ?>">
            <p class="mb-1">
                <strong><?= esc($comment['author_name'] ?? 'Sistem') ?></strong>
                <?php if ($comment['author_type'] === 'AGENT'): ?>
                    <span class="tag is-light is-rounded is-info">Helpdesk</span>
                <?php endif ?>
                <?php if ($internal): ?>
                    <span class="tag is-light is-rounded is-warning">Catatan internal</span>
                <?php endif ?>
                <span class="has-text-grey is-size-7">· <?= esc(format_datetime($comment['created_at'])) ?></span>
            </p>
            <div style="white-space: pre-wrap"><?= esc($comment['body']) ?></div>
            <?= $renderAttachments($comment['attachments']) ?>
        </article>
    <?php endforeach ?>

    <?php if ($canReply): ?>
        <form action="<?= url_to($commentRoute, $ticketId) ?>" method="post" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>

            <?php if ($isAgent): ?>
                <div class="field">
                    <div class="control">
                        <label class="radio"><input type="radio" name="visibility" value="PUBLIC" checked> Balas ke pemohon</label>
                        <label class="radio ml-4"><input type="radio" name="visibility" value="INTERNAL"> Catatan internal (khusus tim)</label>
                    </div>
                </div>
            <?php endif ?>

            <div class="field">
                <label class="label" for="body">Pesan</label>
                <div class="control">
                    <textarea class="textarea <?= isset($errors['body']) ? 'is-danger' : '' ?>" id="body" name="body" rows="4"
                        maxlength="5000" required placeholder="Tulis pesan…"><?= esc(old('body')) ?></textarea>
                </div>
                <?php if (isset($errors['body'])): ?>
                    <p class="help is-danger"><?= esc($errors['body']) ?></p>
                <?php endif ?>
            </div>

            <div class="field">
                <label class="label" for="attachments">Lampiran <span class="has-text-weight-normal has-text-grey-dark">(Opsional)</span></label>
                <div class="control">
                    <input class="input" type="file" id="attachments" name="attachments[]" multiple>
                </div>
            </div>

            <button type="submit" class="button is-primary">
                <span class="icon"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></span>
                <span>Kirim</span>
            </button>
        </form>
    <?php else: ?>
        <p class="has-text-grey"><?= esc(lang('Ticket.commentClosed')) ?></p>
    <?php endif ?>
</div>
