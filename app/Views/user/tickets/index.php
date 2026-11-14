<?php
/**
 * @var list<array>                $tickets
 * @var \CodeIgniter\Pager\Pager   $pager
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="section">
    <div class="container">
        <?= component('page_header', [
            'title'      => lang('Ticket.mineTitle'),
            'subtitle'   => lang('Ticket.mineSubtitle'),
            'action'     => lang('Ticket.newTitle'),
            'actionHref' => url_to('user.tickets.new'),
        ]) ?>

        <?php component_start('data_table', [
            'columns'      => ['No. Tiket', 'Judul', 'Kategori', 'Status', 'Respons Sebelum', 'Selesai Sebelum', 'Dibuat'],
            'emptyIcon'    => 'fa-ticket',
            'emptyTitle'   => lang('Ticket.emptyTitle'),
            'emptyMessage' => lang('Ticket.emptyMessage'),
            'pager'        => $pager,
            'totalLabel'   => 'tiket',
        ]) ?>
            <?= view('tickets/_rows', ['tickets' => $tickets, 'detailRoute' => 'user.tickets.show']) ?>
        <?= component_end() ?>
    </div>
</section>
<?= $this->endSection() ?>
