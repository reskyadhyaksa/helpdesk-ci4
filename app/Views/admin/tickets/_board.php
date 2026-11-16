<?php

use App\Entities\SlaPolicy;
use App\Entities\TicketStatus;
use App\Models\PermissionModel;

/**
 * Papan Kanban tiket admin: satu kolom per kelompok status, kartu tiket bertumpuk ke bawah di dalamnya; tiap kartu
 * dibuat melebar (tiga baris pendek) bukan menjulang. Kartu membuka modal detail (js/pages/admin/ticket-modal.js); Ctrl-klik membuka halaman penuh.
 * Pengguna yang boleh mengubah status bisa menyeret kartu ke jalur lain (js/pages/admin/ticket-board.js); aturan
 * transisi tetap diperiksa server (TicketController::move). Alternatif keyboard: ubah status di modal detail.
 *
 * @var list<array{key: string, label: string, tone: string, statuses: list<string>, total: int, tickets: list<array>}> $board
 * @var string $action URL halaman, dasar tautan "Lihat semua di tabel"
 */
$canMove = auth_can(PermissionModel::ADMIN_TICKETS_UPDATE);

$categoryName = static fn (array $ticket): string => $ticket['parent_name'] !== null
    ? $ticket['parent_name'] . ' › ' . $ticket['category_name']
    : $ticket['category_name'];
?>
<div class="board" aria-label="Papan tiket per status"
    <?= $canMove ? 'data-board-movable data-csrf-name="' . esc(csrf_token(), 'attr') . '" data-csrf-value="' . esc(csrf_hash(), 'attr') . '"' : '' ?>>
    <?php foreach ($board as $column): ?>
        <?php
        // status di jalur ini (nilai => label); dipakai js untuk memeriksa apakah kartu boleh dilepas di sini
        $laneStatuses = [];

        foreach ($column['statuses'] as $value) {
            $laneStatuses[$value] = TicketStatus::from($value)->label();
        }
        ?>
        <section class="board-lane" aria-labelledby="board-<?= esc($column['key'], 'attr') ?>"
            data-lane="<?= esc($column['key'], 'attr') ?>" data-lane-label="<?= esc($column['label'], 'attr') ?>"
            data-lane-statuses="<?= esc(json_encode($laneStatuses, JSON_UNESCAPED_UNICODE), 'attr') ?>">
            <header class="board-lane-head">
                <h2 id="board-<?= esc($column['key'], 'attr') ?>">
                    <span class="board-dot is-<?= esc($column['tone'], 'attr') ?>" aria-hidden="true"></span><?= esc($column['label']) ?>
                </h2>
                <span class="board-count" aria-label="<?= (int) $column['total'] ?> tiket"><?= (int) $column['total'] ?></span>

                <?php if ($column['total'] > count($column['tickets'])): ?>
                    <a class="board-more" href="<?= esc($action . '?' . http_build_query(['status' => $column['statuses']]), 'attr') ?>">
                        Lihat semua di tabel <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                <?php endif ?>
            </header>

            <div class="board-cards">
                <?php if ($column['tickets'] === []): ?>
                    <p class="board-empty">Tidak ada tiket<?= $canMove ? ' · lepas kartu di sini untuk memindahkan' : '' ?></p>
                <?php endif ?>

                <?php foreach ($column['tickets'] as $ticket): ?>
                    <?php
                    $priority = $ticket['priority'] ?? null;
                    $current  = TicketStatus::from($ticket['status']);
                    $allowed  = array_map(static fn (TicketStatus $next): string => $next->value, $current->allowedTransitions());
                    $movable  = $canMove && $allowed !== [];
                    ?>
                    <article class="board-card <?= $movable ? 'is-movable' : '' ?>"
                        <?= $movable
                            ? 'draggable="true" data-ticket-id="' . esc($ticket['id'], 'attr') . '" data-ticket-no="' . esc($ticket['ticket_number'], 'attr')
                                . '" data-status="' . esc($current->value, 'attr') . '" data-from-label="' . esc($current->label(), 'attr')
                                . '" data-allowed="' . esc(implode(',', $allowed), 'attr') . '" data-move-url="' . esc(url_to('admin.tickets.move', $ticket['id']), 'attr') . '"'
                            : '' ?>>
                        <a class="board-card-link" href="<?= url_to('admin.tickets.show', $ticket['id']) ?>" data-ticket-modal draggable="false">
                            <?php // Kartu melebar ke samping: 3 baris. Atas: nomor, prioritas, SLA. Tengah: judul. Bawah: kategori, pemohon, PIC ?>
                            <span class="board-top">
                                <span class="board-no"><?= esc($ticket['ticket_number']) ?></span>
                                <?php if ($priority !== null): ?>
                                    <span class="priority-pill is-<?= esc($priority, 'attr') ?>"><?= esc(SlaPolicy::PRIORITY_LABELS[$priority] ?? $priority) ?></span>
                                <?php endif ?>
                                <span class="board-sla"><?= view('tickets/_sla_badge', ['ticket' => $ticket]) ?></span>
                            </span>

                            <span class="board-title"><?= esc($ticket['subject']) ?></span>

                            <span class="board-foot">
                                <span class="table-chip" title="<?= esc($categoryName($ticket), 'attr') ?>"><?= esc($categoryName($ticket)) ?></span>
                                <?php if ($ticket['channel'] === 'PUBLIC_FORM'): ?>
                                    <span class="table-chip is-warning"><?= esc(lang('Ticket.channelPublic')) ?></span>
                                <?php endif ?>

                                <span class="board-person" title="Pemohon: <?= esc($ticket['requester_name'], 'attr') ?>">
                                    <span class="table-avatar is-primary is-sm" aria-hidden="true"><?= esc(initials($ticket['requester_name'])) ?></span>
                                    <span class="board-name"><?= esc($ticket['requester_name']) ?></span>
                                </span>

                                <?php if ($ticket['assignee_name'] !== null): ?>
                                    <span class="table-avatar is-sm" title="PIC: <?= esc($ticket['assignee_name'], 'attr') ?>">
                                        <span class="is-sr-only">PIC: <?= esc($ticket['assignee_name']) ?></span><span aria-hidden="true"><?= esc(initials($ticket['assignee_name'])) ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="table-avatar is-sm is-empty" title="Belum ada PIC">
                                        <span class="is-sr-only"><?= esc(lang('Ticket.unassigned')) ?></span><i class="fa-regular fa-user" aria-hidden="true"></i>
                                    </span>
                                <?php endif ?>
                            </span>
                        </a>
                    </article>
                <?php endforeach ?>
            </div>
        </section>
    <?php endforeach ?>
</div>
