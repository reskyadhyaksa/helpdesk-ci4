<?php

use App\Entities\TicketStatus;

/**
 * Baris tabel tiket di portal user (admin memakai tickets/_admin_rows).
 *
 * @var list<array> $tickets
 * @var string      $detailRoute   nama route halaman detail
 */
// Kategori tampil sebagai "Induk › Sub" bila tiket menunjuk sub kategori
$categoryName = static fn (array $ticket): string => $ticket['parent_name'] !== null
    ? $ticket['parent_name'] . ' › ' . $ticket['category_name']
    : $ticket['category_name'];

$dueLabel = static fn (?string $value): string => $value === null ? lang('Ticket.noSla') : format_datetime($value);
?>
<?php foreach ($tickets as $ticket): ?>
    <?php $status = TicketStatus::from($ticket['status']) ?>
    <tr>
        <td>
            <a href="<?= url_to($detailRoute, $ticket['id']) ?>"><strong><?= esc($ticket['ticket_number']) ?></strong></a>
            <?php if ($ticket['channel'] === 'PUBLIC_FORM'): ?>
                <span class="tag is-light is-rounded is-warning"><?= esc(lang('Ticket.channelPublic')) ?></span>
            <?php endif ?>
        </td>
        <td><?= esc($ticket['subject']) ?></td>
        <td><?= esc($categoryName($ticket)) ?></td>
        <td><span class="tag is-light is-rounded <?= $status->tagClass() ?>"><?= esc($status->label()) ?></span></td>
        <td><?= esc($dueLabel($ticket['response_due_at'])) ?></td>
        <td><?= esc($dueLabel($ticket['resolution_due_at'])) ?></td>
        <td><?= esc(format_datetime($ticket['created_at'])) ?></td>
    </tr>
<?php endforeach ?>
