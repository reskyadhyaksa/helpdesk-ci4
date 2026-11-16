<?php

use App\Entities\TicketStatus;
use App\Models\PermissionModel;

/**
 * Isi detail tiket admin (dua kolom), dipakai halaman penuh (show.php) dan modal di daftar tiket (_modal_card.php).
 * Kiri = judul, status, deskripsi, percakapan + riwayat, form balas; kanan = SLA, penugasan, pelapor, detail, batas waktu.
 * Aksi (ubah status, ganti PIC, balas) dijaga permission-nya masing-masing.
 *
 * @var array       $ticket   baris dari TicketModel::findDetail() + sla_remaining_minutes
 * @var list<array> $activities
 * @var list<array> $pics     kandidat PIC: id, name, employee_no
 * @var array       $thread   komentar dan lampiran (TicketCommentService::thread)
 */
$status = TicketStatus::from($ticket['status']);

$statusOptions = [];

foreach ($status->allowedTransitions() as $next) {
    $statusOptions[$next->value] = $next->label();
}

$picOptions = [];

foreach ($pics as $pic) {
    $picOptions[$pic['id']] = $pic['name'] . ' (' . $pic['employee_no'] . ')';
}
?>
<div class="columns is-variable is-5 ticket-layout">
    <div class="column is-8 ticket-main">
      <div class="ticket-zoom">
        <?= view('admin/tickets/_main', [
            'ticket'        => $ticket,
            'activities'    => $activities,
            'thread'        => $thread,
            'mentionNames'  => array_column($pics, 'name'),
            'mentionables'  => array_values(array_filter($pics, static fn (array $pic): bool => $pic['id'] !== auth_user()['id'])),
            'status'        => $status,
            'statusOptions' => $statusOptions,
            'canUpdate'     => auth_can(PermissionModel::ADMIN_TICKETS_UPDATE),
            'canReply'      => auth_can(PermissionModel::ADMIN_TICKETS_COMMENT) && ! $status->isFinal(),
        ]) ?>
      </div>
    </div>

    <aside class="column is-4 ticket-aside" aria-label="Informasi tiket">
      <div class="ticket-zoom">
        <?= view('admin/tickets/_sidebar', [
            'ticket'     => $ticket,
            'status'     => $status,
            'picOptions' => $picOptions,
            'canAssign'  => auth_can(PermissionModel::ADMIN_TICKETS_ASSIGN),
        ]) ?>
      </div>
    </aside>
</div>
