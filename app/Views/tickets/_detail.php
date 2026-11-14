<?php

use App\Entities\SlaPolicy;
use App\Entities\TicketStatus;

/**
 * Isi detail tiket (hanya baca), dipakai halaman detail user dan admin.
 *
 * @var array       $ticket     baris dari TicketModel::findDetail()
 * @var list<array> $activities riwayat aktivitas, terbaru dulu
 */
$status = TicketStatus::from($ticket['status']);
$none   = '<span class="has-text-grey">—</span>';
$text   = static fn (?string $value): string => $value === null || $value === '' ? '<span class="has-text-grey">—</span>' : esc($value);
$due    = static fn (?string $value): string => $value === null ? esc(lang('Ticket.noSla')) : esc(format_datetime($value));

// Pengaju hanya ditampilkan bila berbeda dengan pemohon, atau tiket dibuat tanpa login
$filedBy = $ticket['channel'] === 'PUBLIC_FORM'
    ? lang('Ticket.channelPublic')
    : ($ticket['created_by'] !== $ticket['requester_nip'] ? ($ticket['creator_name'] ?? $ticket['created_by']) : null);

$activityText = static function (array $activity): string {
    $label = lang('Ticket.activity.' . $activity['action']);

    if ($activity['action'] === 'status_changed') {
        $from = TicketStatus::tryFrom((string) $activity['from_value'])?->label() ?? $activity['from_value'];
        $to   = TicketStatus::tryFrom((string) $activity['to_value'])?->label() ?? $activity['to_value'];

        return $label . ': ' . $from . ' → ' . $to;
    }

    if ($activity['to_value'] !== null || $activity['from_value'] !== null) {
        return $label . ': ' . ($activity['from_value'] ?? '—') . ' → ' . ($activity['to_value'] ?? '—');
    }

    return $label;
};
?>
<div class="box">
    <p class="is-size-7 has-text-grey mb-1">
        <?= esc($ticket['ticket_number']) ?>
        <?php if ($ticket['channel'] === 'PUBLIC_FORM'): ?>
            <span class="tag is-light is-rounded is-warning"><?= esc(lang('Ticket.channelPublic')) ?></span>
        <?php endif ?>
        <?php if (! (int) $ticket['is_requester_verified']): ?>
            <span class="tag is-light is-rounded is-danger"><?= esc(lang('Ticket.unverified')) ?></span>
        <?php endif ?>
    </p>
    <h1 class="title is-4"><?= esc($ticket['subject']) ?></h1>
    <p class="mb-3"><span class="tag is-light is-rounded <?= $status->tagClass() ?>"><?= esc($status->label()) ?></span></p>
    <p class="form-section-label">Deskripsi Kendala</p>
    <div style="white-space: pre-wrap"><?= esc($ticket['description']) ?></div>
</div>

<div class="columns">
    <div class="column">
        <div class="box">
            <p class="form-section-label">Pelapor</p>
            <dl>
                <dt class="has-text-grey is-size-7">Nama</dt><dd class="mb-2"><?= esc($ticket['requester_name']) ?></dd>
                <dt class="has-text-grey is-size-7">NIK</dt><dd class="mb-2"><?= esc($ticket['requester_nip']) ?></dd>
                <dt class="has-text-grey is-size-7">Divisi / Regional</dt><dd class="mb-2"><?= $text($ticket['requester_organization']) ?></dd>
                <dt class="has-text-grey is-size-7">Nomor Handphone</dt><dd class="mb-2"><?= $text($ticket['requester_phone']) ?></dd>
                <dt class="has-text-grey is-size-7">E-mail</dt><dd class="mb-2"><?= $text($ticket['requester_email']) ?></dd>
                <?php if ($filedBy !== null): ?>
                    <dt class="has-text-grey is-size-7">Diajukan oleh</dt><dd><?= esc($filedBy) ?></dd>
                <?php endif ?>
            </dl>
        </div>
    </div>

    <div class="column">
        <div class="box">
            <p class="form-section-label">Penugasan</p>
            <dl>
                <dt class="has-text-grey is-size-7">PIC Helpdesk</dt><dd class="mb-2"><?= $text($ticket['assignee_name']) ?></dd>
                <dt class="has-text-grey is-size-7">PIC Resolver / Developer</dt><dd><?= $text($ticket['resolver_name']) ?></dd>
            </dl>
            <p class="form-section-label mt-4">Detail Tiket</p>
            <dl>
                <dt class="has-text-grey is-size-7">Prioritas</dt>
                <dd class="mb-2"><?= $ticket['priority'] === null ? $none : esc(SlaPolicy::PRIORITY_LABELS[$ticket['priority']] ?? $ticket['priority']) ?></dd>
                <dt class="has-text-grey is-size-7">Kategori</dt>
                <dd><?= esc($ticket['parent_name'] !== null ? $ticket['parent_name'] . ' › ' . $ticket['category_name'] : $ticket['category_name']) ?></dd>
            </dl>
        </div>
    </div>

    <div class="column">
        <div class="box">
            <p class="form-section-label">SLA dan Batas Waktu</p>
            <dl>
                <dt class="has-text-grey is-size-7">Target Respons</dt><dd class="mb-2"><?= $due($ticket['response_due_at']) ?></dd>
                <dt class="has-text-grey is-size-7">Target Resolusi</dt><dd class="mb-2"><?= $due($ticket['resolution_due_at']) ?></dd>
                <dt class="has-text-grey is-size-7">Dibuat</dt><dd><?= esc(format_datetime($ticket['created_at'])) ?></dd>
            </dl>
        </div>
    </div>
</div>

<div class="box">
    <p class="form-section-label">Riwayat Aktivitas</p>
    <?php if ($activities === []): ?>
        <p class="has-text-grey">Belum ada aktivitas.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($activities as $activity): ?>
                <li class="mb-2">
                    <strong><?= esc($activityText($activity)) ?></strong>
                    <span class="has-text-grey is-size-7">
                        · <?= esc($activity['actor_name'] ?? 'Tamu / sistem') ?> · <?= esc(format_datetime($activity['created_at'])) ?>
                    </span>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</div>
