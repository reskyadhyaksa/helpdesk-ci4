<?php

use App\Entities\SlaPolicy;
use App\Entities\TicketStatus;

/**
 * Kolom kanan detail tiket admin: kondisi SLA, penugasan PIC, pelapor, detail, dan batas waktu.
 *
 * @var array                 $ticket     termasuk sla_remaining_minutes (null = tanpa SLA atau sudah selesai)
 * @var TicketStatus          $status
 * @var array<string, string> $picOptions id user => "Nama (NIK)"
 * @var bool                  $canAssign
 */
$none = '<span class="has-text-grey">—</span>';
$text = static fn (?string $value): string => $value === null || $value === '' ? '<span class="has-text-grey">—</span>' : esc($value);

$remaining     = $ticket['sla_remaining_minutes'];
$limit         = (int) ($ticket['sla_limit_minutes'] ?? 0);
$isDone        = $status === TicketStatus::Resolved || $status->isFinal();
$isLate        = $remaining !== null && $remaining < 0;
$usedPercent   = $remaining !== null && $limit > 0 ? max(0, (int) round(($limit - $remaining) / $limit * 100)) : 0;
$priorityLabel = SlaPolicy::PRIORITY_LABELS[$ticket['priority'] ?? ''] ?? null;
$priorityCode  = $priorityLabel === null ? 'SLA' : explode(' ', $priorityLabel)[0];

// Pengaju hanya ditampilkan bila berbeda dengan pemohon, atau tiket dibuat tanpa login
$filedBy = $ticket['channel'] === 'PUBLIC_FORM'
    ? lang('Ticket.channelPublic')
    : ($ticket['created_by'] !== $ticket['requester_nip'] ? ($ticket['creator_name'] ?? $ticket['created_by']) : null);

$category    = $ticket['parent_name'] ?? $ticket['category_name'];
$subCategory = $ticket['parent_name'] !== null ? $ticket['category_name'] : null;

$picForms = [
    'assignee' => ['label' => 'PIC Helpdesk (Assignee)', 'name' => $ticket['assignee_name'], 'selected' => $ticket['assignee_id']],
    'resolver' => ['label' => 'PIC Resolver / Developer', 'name' => $ticket['resolver_name'], 'selected' => $ticket['resolver_id']],
];
?>
<?php // ---- Kondisi SLA ---- ?>
<?php if ($isDone): ?>
    <div class="notification is-success is-light ticket-sla-card">
        <p class="ticket-sla-title"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Status: Tiket Selesai</p>
        <p>SLA tidak lagi dihitung untuk tiket ini.</p>
    </div>
<?php elseif ($remaining === null): ?>
    <div class="notification is-light ticket-sla-card">
        <p class="ticket-sla-title"><i class="fa-regular fa-clock" aria-hidden="true"></i> Status: Tanpa SLA</p>
        <p>Kategori tiket ini tidak punya kebijakan SLA.</p>
    </div>
<?php else: ?>
    <div class="notification <?= $isLate ? 'is-danger' : 'is-info' ?> is-light ticket-sla-card">
        <p class="ticket-sla-title">
            <i class="fa-solid <?= $isLate ? 'fa-triangle-exclamation' : 'fa-stopwatch' ?>" aria-hidden="true"></i>
            Status: <?= $isLate ? 'Overdue SLA Breach' : 'Dalam Batas SLA' ?>
        </p>
        <p>
            <?= $isLate
                ? esc(lang('Ticket.sla.late', [format_duration(-$remaining)]))
                : esc(lang('Ticket.sla.left', [format_duration($remaining)])) ?>
            dari batas resolusi <?= esc($priorityCode) ?>.
        </p>
        <div class="ticket-sla-used">
            <span>Waktu Terpakai</span>
            <strong><?= $usedPercent ?>% SLA</strong>
        </div>
        <progress class="progress is-small <?= $isLate ? 'is-danger' : 'is-info' ?>" value="<?= min($usedPercent, 100) ?>" max="100"
            aria-label="Waktu SLA terpakai <?= $usedPercent ?> persen"><?= $usedPercent ?>%</progress>
    </div>
<?php endif ?>

<?php // ---- Status & Penugasan ---- ?>
<details class="ticket-side-card" open>
    <summary><i class="fa-solid fa-user-group" aria-hidden="true"></i> Status &amp; Penugasan</summary>

    <?php foreach ($picForms as $role => $form): ?>
        <div class="ticket-pic">
            <p class="ticket-label"><?= esc($form['label']) ?></p>
            <?php if ($form['name'] !== null): ?>
                <div class="ticket-pic-row">
                    <span class="ticket-avatar <?= $role === 'assignee' ? 'is-agent' : 'is-requester' ?>"><?= esc(initials($form['name'])) ?></span>
                    <p class="has-text-weight-semibold has-text-grey-dark"><?= esc($form['name']) ?></p>
                </div>
            <?php else: ?>
                <div class="ticket-pic-empty">
                    <span class="ticket-avatar is-outline" aria-hidden="true"><i class="fa-regular fa-user"></i></span>
                    <p class="ticket-pic-empty-text">Unassigned</p>
                </div>
            <?php endif ?>

            <?php // Dropdown hanya untuk yang berhak menetapkan PIC; isinya pengguna yang boleh mengakses tiket ini ?>
            <?php if ($canAssign): ?>
                <form class="ticket-pic-form" action="<?= url_to('admin.tickets.assign', $ticket['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" value="<?= $role ?>">
                    <div class="field has-addons mb-0">
                        <div class="control is-expanded">
                            <?= component('select', [
                                'name' => 'user_id', 'id' => $role . '-user', 'label' => $form['label'], 'hideLabel' => true,
                                'options' => $picOptions, 'selected' => $form['selected'],
                                'placeholder' => 'Pilih PIC…', 'searchable' => true, 'fullwidth' => true,
                            ]) ?>
                        </div>
                        <div class="control">
                            <button type="submit" class="button is-link">Simpan</button>
                        </div>
                    </div>
                    <?php if ($picOptions === []): ?>
                        <p class="help">Belum ada pengguna yang punya akses ke tiket ini.</p>
                    <?php endif ?>
                </form>
            <?php endif ?>
        </div>
    <?php endforeach ?>
</details>

<?php // ---- Pelapor ---- ?>
<section class="ticket-side-card" aria-labelledby="reporterTitle">
    <p class="ticket-label" id="reporterTitle">Pelapor (Reporter)</p>
    <div class="ticket-pic-row">
        <span class="ticket-avatar is-requester"><?= esc(initials($ticket['requester_name'])) ?></span>
        <div>
            <p class="has-text-weight-semibold has-text-grey-dark"><?= esc($ticket['requester_name']) ?></p>
            <p class="is-size-7"><?= $text($ticket['requester_organization']) ?></p>
        </div>
        <?php if (! empty($ticket['requester_email'])): ?>
            <a class="ticket-icon-link ml-auto" href="mailto:<?= esc($ticket['requester_email'], 'attr') ?>" aria-label="Kirim email ke pelapor" title="Kirim email">
                <i class="fa-regular fa-envelope" aria-hidden="true"></i>
            </a>
        <?php endif ?>
    </div>
    <dl class="ticket-info">
        <div><dt>NIK</dt><dd><?= esc($ticket['requester_nip']) ?></dd></div>
        <div><dt>Nomor Handphone</dt><dd><?= $text($ticket['requester_phone']) ?></dd></div>
        <div><dt>Email Kantor</dt><dd><?= $text($ticket['requester_email']) ?></dd></div>
        <?php if ($filedBy !== null): ?>
            <div><dt>Diajukan oleh</dt><dd><?= esc($filedBy) ?></dd></div>
        <?php endif ?>
    </dl>
</section>

<?php // ---- Detail Tiket ---- ?>
<details class="ticket-side-card" open>
    <summary><i class="fa-solid fa-sliders" aria-hidden="true"></i> Detail Tiket</summary>
    <dl class="ticket-info">
        <div>
            <dt>Prioritas</dt>
            <dd>
                <?php if ($priorityLabel !== null): ?>
                    <span class="priority-pill is-<?= esc($ticket['priority'], 'attr') ?>"><?= esc($priorityLabel) ?></span>
                <?php else: ?>
                    <?= $none ?>
                <?php endif ?>
            </dd>
        </div>
        <div><dt>Kategori</dt><dd><?= esc($category) ?></dd></div>
        <div><dt>Sub-kategori</dt><dd class="has-text-link"><?= $subCategory === null ? $none : esc($subCategory) ?></dd></div>
    </dl>
</details>

<?php // ---- SLA & Batas Waktu ---- ?>
<details class="ticket-side-card" open>
    <summary><i class="fa-regular fa-clock" aria-hidden="true"></i> SLA &amp; Batas Waktu</summary>
    <dl class="ticket-info">
        <div>
            <dt>Target Respons</dt>
            <dd><?= $ticket['response_due_at'] === null ? esc(lang('Ticket.noSla')) : esc(format_datetime($ticket['response_due_at'])) . ' WIB' ?></dd>
        </div>
        <div>
            <dt>Target Resolusi</dt>
            <dd class="<?= $isLate ? 'has-text-danger has-text-weight-semibold' : '' ?>">
                <?= $ticket['resolution_due_at'] === null ? esc(lang('Ticket.noSla')) : esc(format_datetime($ticket['resolution_due_at'])) . ' WIB' ?>
            </dd>
        </div>
        <div><dt>Waktu Tiket Dibuat</dt><dd><?= esc(format_datetime($ticket['created_at'])) ?> WIB</dd></div>
        <div><dt>Terakhir Diperbarui</dt><dd><?= esc(format_datetime($ticket['updated_at'])) ?> WIB</dd></div>
    </dl>
</details>
