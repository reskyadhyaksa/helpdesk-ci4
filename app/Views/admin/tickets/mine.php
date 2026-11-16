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
    <?= view('admin/tickets/_list_controls', [
        'action'    => url_to('admin.tickets.mine'),
        'title'     => lang('Admin.pages.myTickets.title'),
        'subtitle'  => lang('Admin.pages.myTickets.subtitle'),
        'filters'   => $filters,
        'tabCounts' => $tabCounts,
        'options'   => $options,
    ]) ?>

    <div data-list-region>
    <?php if ($filters['view'] === 'kanban'): ?>
        <?= view('admin/tickets/_board', ['board' => $board, 'action' => url_to('admin.tickets.mine')]) ?>
    <?php else: ?>
    <?php component_start('data_table', [
        'columns'      => [
            ['label' => 'Tiket & Detail Permohonan', 'width' => 19, 'flex' => true],
            ['label' => 'Pemohon', 'width' => 17],
            ['label' => 'Prioritas', 'width' => 8.5],
            ['label' => 'Status', 'width' => 8],
            ['label' => 'Dibuat & Jatuh Tempo', 'width' => 12],
            ['label' => 'Kategori', 'width' => 11],
            ['label' => 'Status SLA', 'width' => 12.5],
            ['label' => 'PIC Petugas', 'width' => 11.5],
            ['label' => 'Aksi', 'align' => 'end', 'width' => 12],
        ],
        'emptyIcon'    => 'fa-user-check',
        'emptyTitle'   => lang('Ticket.emptyTitle'),
        'emptyMessage' => lang('Ticket.emptyAssignedMessage'),
        'pager'        => $pager,
        'totalLabel'   => 'tiket',
    ]) ?>
        <?= view('tickets/_admin_rows', ['tickets' => $tickets]) ?>
    <?= component_end() ?>
    <?php endif ?>
    </div>
</section>

<?= view('admin/tickets/_modal') ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/ticket-detail.js') ?>"></script>
<script src="<?= base_url('js/pages/admin/ticket-modal.js') ?>"></script>
<script src="<?= base_url('js/pages/admin/ticket-board.js') ?>"></script>
<?= $this->endSection() ?>
