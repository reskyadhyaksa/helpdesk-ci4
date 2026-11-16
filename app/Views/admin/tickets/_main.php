<?php

use App\Entities\TicketStatus;

/**
 * Kolom kiri detail tiket admin.
 *
 * @var array                       $ticket
 * @var list<array>                 $activities
 * @var array                       $thread
 * @var list<array>                 $mentionables orang yang bisa disebut (@): boleh mengakses tiket ini, bukan penulis
 * @var list<string>                $mentionNames nama yang disorot bila muncul sebagai "@Nama" di komentar
 * @var TicketStatus                $status
 * @var array<string, string>       $statusOptions transisi status yang diizinkan: nilai => label
 * @var bool                        $canUpdate
 * @var bool                        $canReply
 */
$errors = session('errors') ?? [];

// Komentar dan aktivitas digabung satu lini masa (terlama dulu); tab di atasnya menyaring per jenis
$entries = [];

foreach ($thread['comments'] as $comment) {
    $entries[] = ['kind' => 'comment', 'at' => $comment['created_at'], 'row' => $comment];
}

foreach ($activities as $activity) {
    $entries[] = ['kind' => 'activity', 'at' => $activity['created_at'], 'row' => $activity];
}

usort($entries, static fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

$statusLabel = static fn (?string $value): string => TicketStatus::tryFrom((string) $value)?->label() ?? (string) $value;
?>
<?php // ---- Judul + status ---- ?>
<p class="is-size-7 has-text-grey mb-1">
    <?= esc($ticket['ticket_number']) ?>
    <?php if ($ticket['channel'] === 'PUBLIC_FORM'): ?>
        <span class="tag is-light is-rounded is-warning"><?= esc(lang('Ticket.channelPublic')) ?></span>
    <?php endif ?>
    <?php if (! (int) $ticket['is_requester_verified']): ?>
        <span class="tag is-light is-rounded is-danger"><?= esc(lang('Ticket.unverified')) ?></span>
    <?php endif ?>
</p>
<h1 class="title is-3 ticket-title"><?= esc($ticket['subject']) ?></h1>

<?php if ($canUpdate && $statusOptions !== []): ?>
    <div class="dropdown mb-5" data-status-dropdown>
        <div class="dropdown-trigger">
            <button type="button" class="button <?= $status->tagClass() ?> ticket-status-button" aria-haspopup="true"
                aria-expanded="false" aria-controls="statusMenu">
                <span class="ticket-status-dot" aria-hidden="true"></span>
                <span><?= esc($status->label()) ?></span>
                <span class="icon is-small"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span>
            </button>
        </div>
        <div class="dropdown-menu" id="statusMenu" role="menu">
            <form class="dropdown-content" action="<?= url_to('admin.tickets.status', $ticket['id']) ?>" method="post">
                <?= csrf_field() ?>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <button type="submit" class="dropdown-item" role="menuitem" name="status" value="<?= esc($value, 'attr') ?>"><?= esc($label) ?></button>
                <?php endforeach ?>
            </form>
        </div>
    </div>
<?php else: ?>
    <p class="mb-5">
        <span class="button <?= $status->tagClass() ?> ticket-status-button is-static">
            <span class="ticket-status-dot" aria-hidden="true"></span>
            <span><?= esc($status->label()) ?></span>
        </span>
        <?php if ($canUpdate && $status->isFinal()): ?>
            <span class="is-size-7 has-text-grey ml-2">Status final, tidak bisa diubah.</span>
        <?php endif ?>
    </p>
<?php endif ?>

<?php // ---- Deskripsi + lampiran ---- ?>
<section class="ticket-card is-soft mb-5" aria-labelledby="descTitle">
    <h2 class="ticket-card-title" id="descTitle"><i class="fa-solid fa-align-left" aria-hidden="true"></i> Deskripsi Kendala</h2>
    <div class="ticket-description"><?= esc($ticket['description']) ?></div>

    <?php if ($thread['ticketAttachments'] !== []): ?>
        <h3 class="ticket-subtitle"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> Lampiran Berkas (<?= count($thread['ticketAttachments']) ?>)</h3>
        <?= view('admin/tickets/_attachments', ['attachments' => $thread['ticketAttachments'], 'cards' => true]) ?>
    <?php endif ?>
</section>

<?php // ---- Tab + lini masa ---- ?>
<div class="ticket-tabs-bar mb-4">
    <div class="ticket-tabs" role="group" aria-label="Filter percakapan">
        <button type="button" class="ticket-tab is-active" data-filter="all" aria-pressed="true">Semua</button>
        <button type="button" class="ticket-tab" data-filter="comment" aria-pressed="false">Komentar &amp; Diskusi (<?= count($thread['comments']) ?>)</button>
        <button type="button" class="ticket-tab" data-filter="activity" aria-pressed="false">Riwayat Aktivitas (<?= count($activities) ?>)</button>
    </div>
    <button type="button" class="ticket-sort" data-sort aria-label="Urutkan: terlama dahulu">
        <i class="fa-solid fa-arrow-up-wide-short" aria-hidden="true"></i> <span data-sort-label>Terlama Dahulu</span>
    </button>
</div>

<div class="ticket-timeline" data-timeline>
    <?php foreach ($entries as $entry): ?>
        <?php $row = $entry['row'] ?>
        <?php if ($entry['kind'] === 'comment'): ?>
            <?php $internal = $row['visibility'] === 'INTERNAL' ?>
            <?php $isAgent = $row['author_type'] === 'AGENT' ?>
            <article class="ticket-comment" data-kind="comment">
                <span class="ticket-avatar is-lg <?= $isAgent ? 'is-agent' : 'is-requester' ?>"><?= esc(initials($row['author_name'] ?? 'Sistem')) ?></span>
                <div class="ticket-comment-body <?= $internal ? 'is-internal' : '' ?>">
                    <header class="ticket-comment-head">
                        <strong><?= esc($row['author_name'] ?? 'Sistem') ?></strong>
                        <span class="tag is-rounded <?= $isAgent ? 'is-info is-light' : 'is-light' ?>"><?= $isAgent ? 'HC Helpdesk' : 'Pemohon' ?></span>
                        <?php if ($internal): ?>
                            <span class="tag is-rounded is-warning is-light"><i class="fa-solid fa-lock mr-1" aria-hidden="true"></i>Catatan Internal</span>
                        <?php endif ?>
                        <time class="ticket-comment-time" datetime="<?= esc($row['created_at'], 'attr') ?>">
                            <?= esc(format_datetime($row['created_at'])) ?> WIB (<?= esc(format_ago($row['created_at'])) ?>)
                        </time>
                    </header>
                    <div class="ticket-comment-text <?= ($row['body_format'] ?? 'TEXT') === 'HTML' ? 'is-rich' : '' ?>"><?= render_comment($row, $mentionNames) ?></div>
                    <?= view('admin/tickets/_attachments', ['attachments' => $row['attachments'], 'cards' => false]) ?>
                </div>
            </article>
        <?php else: ?>
            <div class="ticket-activity" data-kind="activity">
                <span class="ticket-activity-icon" aria-hidden="true"><i class="fa-solid fa-right-left"></i></span>
                <p>
                    <strong><?= esc($row['actor_name'] ?? 'Tamu / sistem') ?></strong>
                    <?= esc(lang('Ticket.activity.' . $row['action'])) ?>
                    <?php if ($row['from_value'] !== null || $row['to_value'] !== null): ?>
                        <?php $isStatus = $row['action'] === 'status_changed' ?>
                        <span class="ticket-chip"><?= esc($isStatus ? $statusLabel($row['from_value']) : ($row['from_value'] ?? '—')) ?></span>
                        <i class="fa-solid fa-arrow-right-long has-text-grey" aria-hidden="true"></i>
                        <span class="ticket-chip is-strong"><?= esc($isStatus ? $statusLabel($row['to_value']) : ($row['to_value'] ?? '—')) ?></span>
                    <?php endif ?>
                    <span class="has-text-grey">• <?= esc(format_datetime($row['created_at'])) ?> WIB</span>
                </p>
            </div>
        <?php endif ?>
    <?php endforeach ?>

    <?php if ($entries === []): ?>
        <p class="has-text-grey">Belum ada percakapan atau aktivitas.</p>
    <?php endif ?>
</div>
<p class="has-text-grey" data-timeline-empty hidden>Tidak ada data pada tab ini.</p>

<?php // ---- Form balas ---- ?>
<?php if ($canReply): ?>
    <form class="ticket-reply mt-5" action="<?= url_to('admin.tickets.comment', $ticket['id']) ?>" method="post" enctype="multipart/form-data" novalidate data-reply-form
        data-mentionables="<?= esc(json_encode(array_map(static fn (array $pic): array => ['id' => $pic['id'], 'name' => $pic['name'], 'nip' => $pic['employee_no']], $mentionables), JSON_UNESCAPED_UNICODE), 'attr') ?>">
        <?= csrf_field() ?>

        <div class="ticket-reply-head">
            <div class="ticket-modes" role="radiogroup" aria-label="Jenis balasan">
                <label class="ticket-mode">
                    <input type="radio" name="visibility" value="PUBLIC" checked data-hint="Pemohon akan menerima notifikasi">
                    <span><i class="fa-solid fa-globe" aria-hidden="true"></i> Balas ke Pemohon</span>
                </label>
                <label class="ticket-mode is-internal">
                    <input type="radio" name="visibility" value="INTERNAL" data-hint="Hanya terlihat oleh tim Helpdesk">
                    <span><i class="fa-solid fa-lock" aria-hidden="true"></i> Catatan Internal (Khusus Tim HC)</span>
                </label>
            </div>
            <span class="ticket-reply-hint" data-reply-hint>Pemohon akan menerima notifikasi</span>
        </div>

        <?php // Editor visual (contenteditable) diaktifkan js/pages/admin/ticket-detail.js; tanpa JS yang tampil hanya textarea teks biasa ?>
        <div class="ticket-toolbar" role="toolbar" aria-label="Format pesan" aria-controls="replyEditor" data-toolbar hidden>
            <button type="button" data-cmd="bold" aria-label="Tebal" title="Tebal (Ctrl+B)" aria-pressed="false"><i class="fa-solid fa-bold" aria-hidden="true"></i></button>
            <button type="button" data-cmd="italic" aria-label="Miring" title="Miring (Ctrl+I)" aria-pressed="false"><i class="fa-solid fa-italic" aria-hidden="true"></i></button>
            <button type="button" data-cmd="ul" aria-label="Daftar berpoin" title="Daftar berpoin" aria-pressed="false"><i class="fa-solid fa-list-ul" aria-hidden="true"></i></button>
            <button type="button" data-cmd="ol" aria-label="Daftar bernomor" title="Daftar bernomor" aria-pressed="false"><i class="fa-solid fa-list-ol" aria-hidden="true"></i></button>
            <button type="button" data-cmd="code" aria-label="Kode" title="Kode" aria-pressed="false"><i class="fa-solid fa-code" aria-hidden="true"></i></button>
            <span class="ticket-toolbar-divider" aria-hidden="true"></span>
            <button type="button" data-cmd="image" aria-label="Lampirkan gambar" title="Lampirkan gambar"><i class="fa-regular fa-image" aria-hidden="true"></i></button>
            <?php if ($mentionables !== []): ?>
                <button type="button" data-cmd="mention" aria-label="Sebut rekan tim" title="Sebut rekan tim (@)"><i class="fa-solid fa-at" aria-hidden="true"></i></button>
            <?php endif ?>
        </div>

        <div class="field ticket-editor">
            <label class="is-sr-only" for="body">Pesan</label>
            <ul class="mention-list" id="mentionList" role="listbox" aria-label="Rekan tim" data-mention-list hidden></ul>
            <input type="hidden" name="format" value="" data-format>
            <?php $placeholder = $mentionables !== [] ? 'Tulis komentar atau gunakan @ untuk menyebut rekan tim…' : 'Tulis komentar kepada pemohon…' ?>
            <div class="textarea ticket-editor-area content" id="replyEditor" contenteditable="true" role="textbox" aria-multiline="true"
                aria-label="Pesan" data-editor data-placeholder="<?= esc($placeholder, 'attr') ?>" hidden></div>
            <textarea class="textarea <?= isset($errors['body']) ? 'is-danger' : '' ?>" id="body" name="body" rows="5"
                maxlength="5000" required placeholder="<?= esc($placeholder, 'attr') ?>" data-fallback><?= esc(old('body')) ?></textarea>
            <?php if (isset($errors['body'])): ?>
                <p class="help is-danger"><?= esc($errors['body']) ?></p>
            <?php endif ?>
        </div>

        <div class="ticket-reply-foot">
            <label class="button is-light ticket-attach" for="attachments">
                <span class="icon"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>
                <span data-attach-label>Lampiran</span>
                <input class="is-sr-only" type="file" id="attachments" name="attachments[]" multiple>
            </label>
            <span class="is-size-7 has-text-grey is-hidden-mobile">Tekan Ctrl + Enter untuk kirim</span>
            <div class="buttons ml-auto">
                <button type="reset" class="button is-white">Batal</button>
                <button type="submit" class="button is-link">
                    <span>Simpan Tanggapan</span>
                    <span class="icon"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i></span>
                </button>
            </div>
        </div>
    </form>
<?php else: ?>
    <p class="has-text-grey mt-5"><?= esc(lang('Ticket.commentClosed')) ?></p>
<?php endif ?>
