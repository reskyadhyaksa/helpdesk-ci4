<?php
/**
 * Detail tiket admin (halaman penuh). Isinya sama dengan modal di daftar tiket: lihat _content.php.
 *
 * @var array       $ticket
 * @var list<array> $activities
 * @var list<array> $pics
 * @var array       $thread
 */
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title' => lang('Ticket.detailTitle'),
        'subtitle' => lang('Ticket.detailSubtitle'),
        'back' => ['href' => url_to('admin.tickets.index'), 'label' => 'Semua tiket'],
    ]) ?>

    <?= view('admin/tickets/_content', ['ticket' => $ticket, 'activities' => $activities, 'pics' => $pics, 'thread' => $thread]) ?>
</section>

<?= view('admin/tickets/_preview_modal') ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/ticket-detail.js') ?>"></script>
<?= $this->endSection() ?>