<?php

use App\Entities\TicketStatus;
use App\Models\PermissionModel;

/**
 * @var array       $ticket
 * @var list<array> $activities
 * @var list<array> $pics kandidat PIC: id, name, employee_no
 * @var array       $thread komentar dan lampiran (TicketCommentService::thread)
 */
$status       = TicketStatus::from($ticket['status']);
$canUpdate    = auth_can(PermissionModel::ADMIN_TICKETS_UPDATE);
$canAssign    = auth_can(PermissionModel::ADMIN_TICKETS_ASSIGN);

$statusOptions = [];

foreach ($status->allowedTransitions() as $next) {
    $statusOptions[$next->value] = $next->label();
}

$picOptions = [];

foreach ($pics as $pic) {
    $picOptions[$pic['id']] = $pic['name'] . ' (' . $pic['employee_no'] . ')';
}

$picForms = [
    'assignee' => ['label' => 'PIC Helpdesk', 'selected' => $ticket['assignee_id']],
    'resolver' => ['label' => 'PIC Resolver / Developer', 'selected' => $ticket['resolver_id']],
];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <p class="mb-3"><a href="<?= url_to('admin.tickets.index') ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Semua tiket</a></p>

    <?= view('tickets/_detail', ['ticket' => $ticket, 'activities' => $activities]) ?>

    <?= view('tickets/_thread', [
        'thread'          => $thread,
        'attachmentRoute' => 'admin.tickets.attachment',
        'commentRoute'    => 'admin.tickets.comment',
        'ticketId'        => $ticket['id'],
        'isAgent'         => true,
        'canReply'        => auth_can(PermissionModel::ADMIN_TICKETS_COMMENT) && ! $status->isFinal(),
    ]) ?>

    <?php if ($canUpdate || $canAssign): ?>
        <div class="box">
            <p class="form-section-label">Tindakan</p>

            <?php if ($canUpdate): ?>
                <?php if ($statusOptions === []): ?>
                    <p class="has-text-grey mb-4">Status <?= esc($status->label()) ?> sudah final dan tidak bisa diubah.</p>
                <?php else: ?>
                    <form class="mb-4" action="<?= url_to('admin.tickets.status', $ticket['id']) ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="field has-addons">
                            <div class="control">
                                <?= component('select', [
                                    'name' => 'status', 'label' => 'Ubah status', 'options' => $statusOptions,
                                    'placeholder' => 'Pilih status berikutnya', 'required' => true,
                                ]) ?>
                            </div>
                            <div class="control">
                                <button type="submit" class="button is-primary">Simpan Status</button>
                            </div>
                        </div>
                    </form>
                <?php endif ?>
            <?php endif ?>

            <?php if ($canAssign): ?>
                <div class="columns">
                    <?php foreach ($picForms as $role => $form): ?>
                        <div class="column">
                            <form action="<?= url_to('admin.tickets.assign', $ticket['id']) ?>" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="role" value="<?= $role ?>">
                                <div class="field has-addons">
                                    <div class="control">
                                        <?= component('select', [
                                            'name' => 'user_id', 'id' => $role . '-user', 'label' => $form['label'],
                                            'options' => $picOptions, 'selected' => $form['selected'],
                                            'placeholder' => 'Tanpa PIC', 'searchable' => true,
                                        ]) ?>
                                    </div>
                                    <div class="control">
                                        <button type="submit" class="button is-light">Simpan</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
