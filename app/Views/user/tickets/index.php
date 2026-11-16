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
        <?= component('breadcrumb', [
            'items' => [
                ['label' => lang('Ticket.dashboard.title'), 'href' => url_to('user.dashboard'), 'icon' => 'home.svg'],
                ['label' => lang('Ticket.historyTitle'), 'icon' => 'doc-search.svg'],
            ]
        ]) ?>

        
        <?= component('page_header', [
            'title' => lang('Ticket.mineTitle'),
            'subtitle' => lang('Ticket.mineSubtitle'),
            'action' => lang('Ticket.newTitle'),
            'actionHref' => url_to('user.tickets.new'),
        ]) ?>

        <?php component_start('data_table', [
            'columns' => [
                ['label' => 'No. Tiket', 'width' => 12],
                ['label' => 'Judul', 'width' => 18],
                ['label' => 'Kategori', 'width' => 14],
                ['label' => 'Status', 'width' => 9],
                ['label' => 'Respons Sebelum', 'width' => 12],
                ['label' => 'Selesai Sebelum', 'width' => 12],
                ['label' => 'Dibuat', 'width' => 12],
            ],
            'emptyIcon' => 'fa-ticket',
            'emptyTitle' => lang('Ticket.emptyTitle'),
            'emptyMessage' => lang('Ticket.emptyMessage'),
            'pager' => $pager,
            'totalLabel' => 'tiket',
        ]) ?>
            <?= view('tickets/_rows', ['tickets' => $tickets, 'detailRoute' => 'user.tickets.show']) ?>
        <?= component_end() ?>
    </div>
</section>
<?= $this->endSection() ?>
