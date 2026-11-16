<?php
/**
 * @var list<array>              $tickets
 * @var \CodeIgniter\Pager\Pager $pager
 */
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?php // Judul, tab, filter, dan tabel/papan dibungkus satu kartu (css/pages/admin-ticket-list.css) ?>
    <div class="list-panel">
    <?= view('admin/tickets/_list_controls', [
        'action'    => url_to('admin.tickets.index'),
        'title'     => lang('Admin.pages.tickets.title'),
        'subtitle'  => lang('Admin.pages.tickets.subtitle'),
        'createUrl' => auth_can(\App\Models\PermissionModel::ADMIN_TICKETS_CREATE) ? url_to('admin.tickets.new') : null,
        'filters'   => $filters,
        'tabCounts' => $tabCounts,
        'options'   => $options,
    ]) ?>

    <div data-list-region>
    <?php if ($filters['view'] === 'kanban'): ?>
        <?= view('admin/tickets/_board', ['board' => $board, 'action' => url_to('admin.tickets.index')]) ?>
    <?php else: ?>
    <?php component_start('data_table', [
        'columns'      => [
            ['label' => 'Tiket & Detail Permohonan', 'width' => 19],
            ['label' => 'Pemohon', 'width' => 17],
            ['label' => 'Prioritas', 'width' => 8.5],
            ['label' => 'Status', 'width' => 8],
            ['label' => 'Dibuat & Jatuh Tempo', 'width' => 12],
            ['label' => 'Status SLA', 'width' => 12.5],
            ['label' => 'PIC Petugas', 'width' => 11.5],
            ['label' => 'Aksi', 'width' => 12],
        ],
        'emptyIcon'    => 'fa-ticket',
        'emptyTitle'   => lang('Ticket.emptyTitle'),
        'emptyMessage' => lang('Ticket.emptyMessage'),
        'pager'        => $pager,
        'totalLabel'   => 'tiket',
    ]) ?>
        <?= view('tickets/_admin_rows', ['tickets' => $tickets]) ?>
    <?= component_end() ?>
    <?php endif ?>
    </div>
    </div>
</section>

<?= view('admin/tickets/_modal') ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/admin/ticket-detail.js') ?>"></script>
<script src="<?= base_url('js/pages/admin/ticket-modal.js') ?>"></script>
<?= $this->endSection() ?>
