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
<section class="section ticket-detail">
    <p class="mb-4"><a href="<?= url_to('admin.tickets.index') ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Semua tiket</a></p>

    <?= view('admin/tickets/_content', ['ticket' => $ticket, 'activities' => $activities, 'pics' => $pics, 'thread' => $thread]) ?>
</section>

<?= view('admin/tickets/_preview_modal') ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/ticket-detail.js') ?>"></script>
<?= $this->endSection() ?>
