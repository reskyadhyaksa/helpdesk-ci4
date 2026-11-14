<?php
/**
 * Tiket yang ditugaskan kepada admin yang login (sebagai PIC Helpdesk atau PIC Resolver).
 *
 * @var list<array>              $tickets
 * @var \CodeIgniter\Pager\Pager $pager
 */
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Admin.pages.myTickets.title'),
        'subtitle' => lang('Admin.pages.myTickets.subtitle'),
    ]) ?>

    <?php component_start('data_table', [
        'columns'      => ['No. Tiket', 'Judul', 'Pelapor', 'Kategori', 'Status', 'PIC Helpdesk', 'Respons Sebelum', 'Selesai Sebelum', 'Dibuat'],
        'emptyIcon'    => 'fa-user-check',
        'emptyTitle'   => lang('Ticket.emptyTitle'),
        'emptyMessage' => lang('Ticket.emptyAssignedMessage'),
        'pager'        => $pager,
        'totalLabel'   => 'tiket',
    ]) ?>
        <?= view('tickets/_rows', ['tickets' => $tickets, 'showRequester' => true, 'detailRoute' => 'admin.tickets.show']) ?>
    <?= component_end() ?>
</section>
<?= $this->endSection() ?>
