<?php

use CodeIgniter\Pager\Pager;
use Config\Pager as PagerConfig;

/**
 * Tabel data admin: kartu, kepala kolom, keadaan kosong, total, dan pager.
 * Baris <tr> ditulis di halaman lewat slot, jadi logika tiap baris tetap di halamannya.
 *
 *   <?php component_start('data_table', [
 *       'columns'    => ['Nama', 'Status', ['label' => 'Aksi', 'align' => 'right']],
 *       'emptyIcon'  => 'fa-users',
 *       'pager'      => $pager,
 *       'totalLabel' => 'pengguna',
 *   ]) ?>
 *       <?php foreach ($rows as $row): ?>
 *           <tr>...</tr>
 *       <?php endforeach ?>
 *   <?= component_end() ?>
 *
 * Tanpa baris (slot kosong) → keadaan kosong tampil di dalam tabel.
 *
 * @var list<string|array{label: string, align?: 'left'|'center'|'right'}> $columns
 * @var string      $slot          baris <tr>
 * @var string      $emptyIcon     ikon Font Awesome keadaan kosong
 * @var string|null $emptyTitle    default: "Halaman ini sedang dikembangkan"
 * @var string|null $emptyMessage
 * @var Pager|null  $pager         tampilkan total + navigasi halaman
 * @var string|null $totalLabel    satuan di footer, mis. "pengguna"
 */
$slot         ??= '';
$emptyIcon    ??= 'fa-inbox';
$emptyTitle   ??= null;
$emptyMessage ??= null;
$pager        ??= null;
$totalLabel   ??= 'data';

$isEmpty = trim($slot) === '';
?>
<div class="box p-0 admin-table-card">
    <div class="table-container">
        <table class="table is-fullwidth <?= $isEmpty ? '' : 'is-hoverable' ?>">
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <?php
                        $label = is_array($column) ? $column['label'] : $column;
                        $align = is_array($column) ? ($column['align'] ?? 'left') : 'left';
                        ?>
                        <th scope="col" class="<?= $align === 'left' ? '' : 'has-text-' . esc($align) ?>"><?= esc($label) ?></th>
                    <?php endforeach ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($isEmpty): ?>
                    <tr>
                        <td colspan="<?= count($columns) ?>">
                            <?= component('empty_state', [
                                'icon'    => $emptyIcon,
                                'title'   => $emptyTitle,
                                'message' => $emptyMessage,
                            ]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?= $slot ?>
                <?php endif ?>
            </tbody>
        </table>
    </div>

    <?php if ($pager !== null && ! $isEmpty): ?>
        <?php
        $total = $pager->getTotal();
        $from  = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1;
        $to    = min($from + $pager->getPerPage() - 1, $total);
        ?>
        <div class="admin-table-footer">
            <div class="admin-table-footer-start">
                <?php // Pilihan baris per halaman: js/app.js menulis ?per_page=… ke URL dan kembali ke halaman 1 ?>
                <div class="admin-table-size">
                    <?= component('select', [
                        'name'      => 'per_page',
                        'label'     => 'Baris per halaman',
                        'hideLabel' => true,
                        'options'   => array_combine(PagerConfig::PER_PAGE_OPTIONS, PagerConfig::PER_PAGE_OPTIONS),
                        'selected'  => (string) $pager->getPerPage(),
                        'size'      => 'is-small',
                        'fullwidth' => true,
                    ]) ?>
                </div>
                <p class="admin-table-info">
                    Menampilkan <strong><?= number_format($from, 0, ',', '.') ?>–<?= number_format($to, 0, ',', '.') ?></strong>
                    dari <strong><?= number_format($total, 0, ',', '.') ?></strong> <?= esc($totalLabel) ?>
                </p>
            </div>
            <?= $pager->links('default', 'bulma') ?>
        </div>
    <?php endif ?>
</div>
