<?php
/**
 * @var list<array>              $tickets
 * @var \CodeIgniter\Pager\Pager $pager
 */
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.tickets.title'),
        'subtitle' => lang('Admin.pages.tickets.subtitle'),
    ]) ?>

    <?php component_start('data_table', [
        'columns'      => ['No. Tiket', 'Judul', 'Pelapor', 'Kategori', 'Status', 'PIC Helpdesk', 'Respons Sebelum', 'Selesai Sebelum', 'Dibuat'],
        'emptyIcon'    => 'fa-ticket',
        'emptyTitle'   => lang('Ticket.emptyTitle'),
        'emptyMessage' => lang('Ticket.emptyMessage'),
        'pager'        => $pager,
        'totalLabel'   => 'tiket',
    ]) ?>
        <?= view('tickets/_rows', ['tickets' => $tickets, 'showRequester' => true, 'detailRoute' => 'admin.tickets.show']) ?>
    <?= component_end() ?>
</section>
<?= $this->endSection() ?>
