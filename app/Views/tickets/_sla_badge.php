<?php

use App\Entities\TicketStatus;

/**
 * Lencana kondisi SLA satu tiket (tabel dan papan Kanban admin). sla_remaining_minutes dihitung
 * TicketService::withSlaRemaining (jam kerja, hari libur, dan hari off ikut): null = tanpa SLA atau sudah selesai,
 * negatif = terlambat. Tiket Resolved/Closed/Cancelled tidak lagi dihitung.
 *
 * @var array $ticket
 */
$status    = TicketStatus::from($ticket['status']);
$remaining = $ticket['sla_remaining_minutes'];
$isDone    = $status === TicketStatus::Resolved || $status->isFinal();
?>
<?php if ($isDone): ?>
    <span class="sla-badge is-done"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> <?= esc(lang('Ticket.sla.done')) ?></span>
<?php elseif ($remaining === null): ?>
    <span class="sla-badge is-none"><?= esc(lang('Ticket.noSla')) ?></span>
<?php elseif ($remaining < 0): ?>
    <span class="sla-badge is-late"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> OVERDUE (<?= esc(format_duration_compact(-$remaining)) ?>)</span>
    <small class="has-text-danger has-text-weight-semibold"><?= esc(lang('Ticket.sla.late', [format_duration(-$remaining)])) ?></small>
<?php else: ?>
    <span class="sla-badge is-left"><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= esc(lang('Ticket.sla.left', [format_duration_compact($remaining)])) ?></span>
    <small><?= esc(lang('Ticket.sla.limit', [format_duration((int) $ticket['sla_limit_minutes'])])) ?></small>
<?php endif ?>
