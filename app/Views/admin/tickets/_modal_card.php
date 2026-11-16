<?php

use App\Entities\TicketStatus;
use App\Models\PermissionModel;

/**
 * Kartu modal detail tiket (header, isi, footer). Dikembalikan TicketController::show untuk permintaan AJAX
 * dan dimasukkan ke #ticketModal oleh js/pages/admin/ticket-modal.js.
 *
 * @var array       $ticket
 * @var list<array> $activities
 * @var list<array> $pics
 * @var array       $thread
 */
$pageUrl  = url_to('admin.tickets.show', $ticket['id']);
$category = $ticket['parent_name'] ?? $ticket['category_name'];
$isFinal  = TicketStatus::from($ticket['status'])->isFinal();
$canReply = auth_can(PermissionModel::ADMIN_TICKETS_COMMENT) && ! $isFinal;
$canAssign = auth_can(PermissionModel::ADMIN_TICKETS_ASSIGN);

// Pesan hasil aksi (ubah status, ganti PIC, balas) yang dikirim dari dalam modal
$notices = [];

if (session('success')) {
    $notices[] = ['class' => 'is-success', 'icon' => 'fa-circle-check', 'messages' => [session('success')]];
}

if (session('danger')) {
    $notices[] = ['class' => 'is-danger', 'icon' => 'fa-circle-exclamation', 'messages' => [session('danger')]];
}

if ((session('errors') ?? []) !== []) {
    $notices[] = ['class' => 'is-danger', 'icon' => 'fa-triangle-exclamation', 'messages' => array_values(session('errors'))];
}
?>
<div class="ticket-modal-inner" data-ticket-modal-card data-page-url="<?= esc($pageUrl, 'attr') ?>">
    <header class="ticket-modal-head">
        <span class="ticket-modal-logo" aria-hidden="true"><i class="fa-solid fa-headset"></i></span>

        <nav class="ticket-modal-crumb" aria-label="Posisi">
            <span>HC Helpdesk</span>
            <span class="ticket-modal-sep" aria-hidden="true">/</span>
            <strong class="ticket-modal-no" id="ticketModalTitle"><?= esc($ticket['ticket_number']) ?></strong>
        </nav>

        <button type="button" class="ticket-modal-icon" data-copy="<?= esc($ticket['ticket_number'], 'attr') ?>" aria-label="Salin nomor tiket" title="Salin nomor tiket">
            <i class="fa-regular fa-copy" aria-hidden="true"></i>
        </button>

        <span class="ticket-modal-divider" aria-hidden="true"></span>
        <span class="ticket-modal-chip"><span class="ticket-modal-dot" aria-hidden="true"></span><?= esc($category) ?></span>

        <div class="ticket-modal-actions">
            <button type="button" class="ticket-modal-text" data-copy="<?= esc($pageUrl, 'attr') ?>" data-copy-done="Tautan tersalin">
                <i class="fa-solid fa-share-nodes" aria-hidden="true"></i> <span>Bagikan</span>
            </button>
            <button type="button" class="ticket-modal-icon" data-print aria-label="Cetak tiket" title="Cetak">
                <i class="fa-solid fa-print" aria-hidden="true"></i>
            </button>

            <div class="dropdown is-right" data-menu>
                <div class="dropdown-trigger">
                    <button type="button" class="ticket-modal-icon" aria-haspopup="true" aria-expanded="false" aria-controls="ticketModalMenu" aria-label="Opsi lain" title="Opsi lain">
                        <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="dropdown-menu" id="ticketModalMenu" role="menu">
                    <div class="dropdown-content">
                        <a class="dropdown-item" role="menuitem" href="<?= esc($pageUrl, 'attr') ?>" target="_blank" rel="noopener">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-2" aria-hidden="true"></i>Buka di tab baru
                        </a>
                        <button type="button" class="dropdown-item" role="menuitem" data-copy="<?= esc($ticket['ticket_number'], 'attr') ?>" data-copy-done="Nomor tersalin">
                            <i class="fa-regular fa-copy mr-2" aria-hidden="true"></i>Salin nomor tiket
                        </button>
                    </div>
                </div>
            </div>

            <a class="ticket-modal-icon" href="<?= esc($pageUrl, 'attr') ?>" aria-label="Perluas ke halaman penuh" title="Perluas ke halaman penuh">
                <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
            </a>
            <button type="button" class="ticket-modal-icon" data-modal-close aria-label="Tutup" title="Tutup (Esc)">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <?php // .ticket-modal-zoom mengecilkan isi sesuai lebar/tinggi layar (admin-ticket-modal.css); kolom kiri dan kanan menggulir sendiri-sendiri ?>
    <div class="ticket-modal-scroll">
        <div class="ticket-modal-zoom">
            <?php foreach ($notices as $notice): ?>
                <div class="notification <?= $notice['class'] ?> is-light ticket-modal-notice" role="<?= $notice['class'] === 'is-success' ? 'status' : 'alert' ?>">
                    <button type="button" class="delete" data-dismiss aria-label="Tutup pesan"></button>
                    <i class="fa-solid <?= esc($notice['icon'], 'attr') ?> mr-2" aria-hidden="true"></i><?= esc(implode(' ', $notice['messages'])) ?>
                </div>
            <?php endforeach ?>

            <?= view('admin/tickets/_content', ['ticket' => $ticket, 'activities' => $activities, 'pics' => $pics, 'thread' => $thread]) ?>
        </div>
    </div>

    <footer class="ticket-modal-foot">
        <p class="ticket-modal-keys">
            <span>Navigasi Cepat:</span>
            <?php if ($canReply): ?>
                <span><kbd>m</kbd> Tanggapi</span>
            <?php endif ?>
            <?php if ($canAssign): ?>
                <span><kbd>a</kbd> Tetapkan PIC</span>
            <?php endif ?>
            <span><kbd>Esc</kbd> Tutup</span>
        </p>
        <p class="ticket-modal-brand">PT Mandiri Tunas Finance • HC Helpdesk</p>
    </footer>

    <?= view('admin/tickets/_preview_modal') ?>
</div>
