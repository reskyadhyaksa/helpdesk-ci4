<?php

use App\Entities\SlaPolicy;
use App\Entities\TicketStatus;
use App\Models\PermissionModel;

/**
 * Baris tabel tiket untuk admin (All Ticket dan My Ticket): identitas tiket, pemohon, prioritas, status,
 * waktu, kondisi SLA, PIC, dan tombol aksi. Kolomnya ditulis di halaman pemanggil.
 * sla_remaining_minutes dihitung TicketService::paginateVisible (jam kerja, hari libur, dan hari off ikut).
 *
 * @var list<array> $tickets
 */
$categoryName = static fn (array $ticket): string => $ticket['parent_name'] !== null
    ? $ticket['parent_name'] . ' › ' . $ticket['category_name']
    : $ticket['category_name'];

$canAssign = auth_can(PermissionModel::ADMIN_TICKETS_ASSIGN);
?>
<?php foreach ($tickets as $ticket): ?>
    <?php
    $status    = TicketStatus::from($ticket['status']);
    $detail    = url_to('admin.tickets.show', $ticket['id']);
    $priority  = $ticket['priority'] ?? null;
    $isDone    = $status === TicketStatus::Resolved || $status->isFinal();
    ?>
    <tr>
        <td>
            <div class="ticket-head">
                <a href="<?= $detail ?>" data-ticket-modal><strong><?= esc($ticket['ticket_number']) ?></strong></a>
                <span class="table-chip"><?= esc($categoryName($ticket)) ?></span>
                <?php if ($ticket['channel'] === 'PUBLIC_FORM'): ?>
                    <span class="table-chip is-warning"><?= esc(lang('Ticket.channelPublic')) ?></span>
                <?php endif ?>
            </div>
            <p class="has-text-weight-semibold has-text-grey-dark table-clamp"><?= esc($ticket['subject']) ?></p>
            <small class="table-clamp"><?= esc($ticket['description']) ?></small>
        </td>
        <td>
            <div class="table-person">
                <span class="table-avatar is-primary"><?= esc(initials($ticket['requester_name'])) ?></span>
                <div>
                    <p class="has-text-weight-semibold has-text-grey-dark table-clamp"><?= esc($ticket['requester_name']) ?></p>
                    <small class="is-mono">NIK: <?= esc($ticket['requester_nip']) ?></small>
                    <?php if (! empty($ticket['requester_organization'])): ?>
                        <small class="table-clamp"><?= esc($ticket['requester_organization']) ?></small>
                    <?php endif ?>
                </div>
            </div>
        </td>
        <td class="has-text-centered">
            <?php if ($priority !== null): ?>
                <span class="priority-pill is-<?= esc($priority, 'attr') ?>"><?= esc(SlaPolicy::PRIORITY_LABELS[$priority] ?? $priority) ?></span>
            <?php else: ?>
                <span class="has-text-grey">—</span>
            <?php endif ?>
        </td>
        <td class="has-text-centered"><span class="tag is-light is-rounded <?= $status->tagClass() ?>"><?= esc($status->label()) ?></span></td>
        <td>
            <p class="has-text-grey-dark"><?= esc(format_datetime($ticket['created_at'])) ?></p>
            <small class="has-text-danger has-text-weight-semibold">
                <?= esc(lang('Ticket.due')) ?>: <?= esc($ticket['resolution_due_at'] === null ? lang('Ticket.noSla') : format_datetime($ticket['resolution_due_at'])) ?>
            </small>
        </td>
        <td class="has-text-centered"><?= view('tickets/_sla_badge', ['ticket' => $ticket]) ?></td>
        <td>
            <?php if ($ticket['assignee_name'] !== null): ?>
                <div class="table-person">
                    <span class="table-avatar"><?= esc(initials($ticket['assignee_name'])) ?></span>
                    <span class="has-text-grey-dark"><?= esc($ticket['assignee_name']) ?></span>
                </div>
            <?php else: ?>
                <span class="table-chip is-pill"><i class="fa-solid fa-user-slash" aria-hidden="true"></i> <?= esc(lang('Ticket.unassigned')) ?></span>
            <?php endif ?>
        </td>
        <td>
            <div class="table-actions">
                <?php if ($ticket['assignee_name'] === null && $canAssign && ! $isDone): ?>
                    <a class="button is-small is-primary" href="<?= $detail ?>" data-ticket-modal><?= esc(lang('Ticket.assignAction')) ?></a>
                <?php else: ?>
                    <a class="button is-small is-light" href="<?= $detail ?>" data-ticket-modal><?= esc(lang('Ticket.detailAction')) ?></a>
                <?php endif ?>
                <a class="button is-small is-light" href="<?= $detail ?>" data-ticket-modal aria-label="<?= esc(lang('Ticket.detailAction'), 'attr') ?>">
                    <span class="icon is-small"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
                </a>
            </div>
        </td>
    </tr>
<?php endforeach ?>
