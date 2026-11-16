<?php
/**
 * @var array       $ticket
 * @var list<array> $activities
 * @var array       $thread komentar dan lampiran (TicketCommentService::thread)
 * Isi memakai admin/tickets/_content dengan userView = true.
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="section px-0 user-page">
    <div class="container page-container page-zoom">
        <?= component('breadcrumb', [
            'items' => [
                ['label' => lang('Ticket.dashboard.title'), 'href' => url_to('user.dashboard'), 'icon' => 'home.svg'],
                ['label' => lang('Ticket.historyTitle'), 'href' => url_to('user.tickets.index'), 'icon' => 'doc-search.svg'],
                ['label' => $ticket['ticket_number']],
            ]
        ]) ?>

        <?php // Tampilan yang sama dengan detail tiket admin (dua kolom + lini masa), hanya baca dan pemohon cukup membalas ?>
        <?= view('admin/tickets/_content', ['ticket' => $ticket, 'activities' => $activities, 'pics' => [], 'thread' => $thread, 'userView' => true]) ?>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/ticket-detail.js') ?>"></script>
<?= $this->endSection() ?>
