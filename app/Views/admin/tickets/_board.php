<?php

use App\Entities\SlaPolicy;

/**
 * Papan Kanban tiket admin: satu kolom per kelompok status, kartu tiket di dalamnya. Kartu membuka modal detail
 * (js/pages/admin/ticket-modal.js) seperti baris tabel; Ctrl-klik membuka halaman penuh.
 *
 * @var list<array{key: string, label: string, tone: string, statuses: list<string>, total: int, tickets: list<array>}> $board
 * @var string $action URL halaman, dasar tautan "Lihat semua di tabel"
 */
$categoryName = static fn (array $ticket): string => $ticket['parent_name'] !== null
    ? $ticket['parent_name'] . ' › ' . $ticket['category_name']
    : $ticket['category_name'];
?>
<div class="board" aria-label="Papan tiket per status">
    <?php foreach ($board as $column): ?>
        <section class="board-column" aria-labelledby="board-<?= esc($column['key'], 'attr') ?>">
            <header class="board-column-head">
                <h2 id="board-<?= esc($column['key'], 'attr') ?>">
                    <span class="board-dot is-<?= esc($column['tone'], 'attr') ?>" aria-hidden="true"></span><?= esc($column['label']) ?>
                </h2>
                <span class="board-count" aria-label="<?= (int) $column['total'] ?> tiket"><?= (int) $column['total'] ?></span>
            </header>

            <div class="board-cards">
                <?php if ($column['tickets'] === []): ?>
                    <p class="board-empty">Tidak ada tiket</p>
                <?php endif ?>

                <?php foreach ($column['tickets'] as $ticket): ?>
                    <?php $priority = $ticket['priority'] ?? null ?>
                    <article class="board-card">
                        <a class="board-card-link" href="<?= url_to('admin.tickets.show', $ticket['id']) ?>" data-ticket-modal>
                            <span class="board-top">
                                <span class="board-no"><?= esc($ticket['ticket_number']) ?></span>
                                <?php if ($priority !== null): ?>
                                    <span class="priority-pill is-<?= esc($priority, 'attr') ?>"><?= esc(SlaPolicy::PRIORITY_LABELS[$priority] ?? $priority) ?></span>
                                <?php endif ?>
                            </span>

                            <span class="board-title"><?= esc($ticket['subject']) ?></span>

                            <span class="board-tags">
                                <span class="table-chip"><?= esc($categoryName($ticket)) ?></span>
                                <?php if ($ticket['channel'] === 'PUBLIC_FORM'): ?>
                                    <span class="table-chip is-warning"><?= esc(lang('Ticket.channelPublic')) ?></span>
                                <?php endif ?>
                            </span>

                            <span class="board-sla">
                                <?= view('tickets/_sla_badge', ['ticket' => $ticket]) ?>
                            </span>

                            <span class="board-foot">
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

            <?php if ($column['total'] > count($column['tickets'])): ?>
                <a class="board-more" href="<?= esc($action . '?' . http_build_query(['status' => $column['statuses']]), 'attr') ?>">
                    Lihat semua <?= (int) $column['total'] ?> tiket di tabel →
                </a>
            <?php endif ?>
        </section>
    <?php endforeach ?>
</div>
