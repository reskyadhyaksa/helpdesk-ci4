<?php
/**
 * @var array       $ticket
 * @var list<array> $activities
 * @var array       $thread komentar dan lampiran (TicketCommentService::thread)
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="section">
    <div class="container">
        <?= component('breadcrumb', ['items' => [
            ['label' => lang('Ticket.dashboard.title'), 'href' => url_to('user.dashboard'), 'icon' => 'home.svg'],
            ['label' => lang('Ticket.historyTitle'), 'href' => url_to('user.tickets.index'), 'icon' => 'doc-search.svg'],
            ['label' => $ticket['ticket_number']],
        ]]) ?>

        <?= view('tickets/_detail', ['ticket' => $ticket, 'activities' => $activities]) ?>

        <?= view('tickets/_thread', [
            'thread'          => $thread,
            'attachmentRoute' => 'user.tickets.attachment',
            'commentRoute'    => 'user.tickets.comment',
            'ticketId'        => $ticket['id'],
            'isAgent'         => false,
            'canReply'        => ! \App\Entities\TicketStatus::from($ticket['status'])->isFinal(),
        ]) ?>
    </div>
</section>
<?= $this->endSection() ?>
