<?php

use CodeIgniter\Pager\Pager;
use Config\Pager as PagerConfig;

/**
 * Tabel data admin: kartu, kepala kolom, keadaan kosong, total, dan pager.
 * Baris <tr> ditulis di halaman lewat slot, jadi logika tiap baris tetap di halamannya.
 *
 *   <?php component_start('data_table', [
 *       'columns'    => ['Nama', 'Status', ['label' => 'Aksi', 'width' => 12]],
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
 * Dengan 'flat' => true kartunya dilepas, untuk tabel yang sudah berada di dalam kartu lain (dashboard).
 *
 * @var list<string|array{label: string, width?: int|float, align?: 'end'}> $columns
 *                                 align 'end': kolom aksi, kepala dan isi rata kanan dengan jarak dari tepi kartu (table.css).
 *                                 Teks kepala kolom rata tengah, kecuali kolom pertama yang rata kiri (dengan inden, lihat table.css); perataan isi sel ditulis di <td> halamannya.
 *                                 width (rem): bila SEMUA kolom punya width, lebar kolom dikunci (table-layout: fixed)
 *                                 dan lebar minimum tabel = jumlahnya; layar yang lebih sempit menggeser tabel
 *                                 horizontal, kolom tidak dijepit. Satu kolom boleh diberi 'flex' => true: tanpa lebar tetap,
 *                                 jadi kolom itu yang menyerap sisa ruang saat wadah lebih lebar dari jumlah lebar kolom
 *                                 (lebar kolom lain tidak berubah, dan tidak ada ruang kosong di kanan tabel).
 * @var string      $slot          baris <tr>
 * @var string      $emptyIcon     ikon Font Awesome keadaan kosong
 * @var string|null $emptyTitle    default: "Halaman ini sedang dikembangkan"
 * @var string|null $emptyMessage
 * @var Pager|null  $pager         tampilkan total + navigasi halaman
 * @var string|null $totalLabel    satuan di footer, mis. "pengguna"
 * @var bool        $flat          tanpa kartu pembungkus
 */
$flat ??= false;
$slot ??= '';
$emptyIcon ??= 'fa-inbox';
$emptyTitle ??= null;
$emptyMessage ??= null;
$pager ??= null;
$totalLabel ??= 'data';

// Komentar HTML (mis. penanda DEBUG-VIEW milik CodeIgniter di mode development) bukan baris data
$isEmpty = trim(preg_replace('/<!--.*?-->/s', '', $slot)) === '';

$widths = array_map(static fn($column): float => is_array($column) ? (float) ($column['width'] ?? 0) : 0.0, $columns);
$isFixed = !in_array(0.0, $widths, true);
?>
            <div class="<?= $flat ? '' : 'box p-0' ?> admin-table-card">
                <div class="table-container">
                    <table
                        class="table is-fullwidth <?= $isEmpty ? '' : 'is-hoverable' ?> <?= $isFixed ? 'is-fixed' : '' ?>"
                        <?= $isFixed ? ' style="min-width: ' . array_sum($widths) . 'rem"' : '' ?>>
                        <thead>
                            <tr>
                                <?php foreach ($columns as $i => $column): ?>
                                    <th scope="col" class="<?= $i === 0 ? 'has-text-left' : (is_array($column) && ($column['align'] ?? null) === 'end' ? 'is-end' : 'has-text-centered') ?>" <?= $isFixed && empty(is_array($column) ? $column['flex'] ?? null : null) ? ' style="width: ' . $widths[$i] . 'rem"' : '' ?>>
                                        <?= esc(is_array($column) ? $column['label'] : $column) ?>
                                    </th>
                                <?php endforeach ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($isEmpty): ?>
                                <tr>
                                    <td colspan="<?= count($columns) ?>">
                                        <?= component('empty_state', [
                                            'illustration' => true,
                                            'icon' => $emptyIcon,
                                            'title' => $emptyTitle,
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

                <?php if ($pager !== null && !$isEmpty): ?>
                    <?php
                    $total = $pager->getTotal();
                    $from = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1;
                    $to = min($from + $pager->getPerPage() - 1, $total);
                    ?>
                    <div class="admin-table-footer">
                        <div class="admin-table-footer-start">
                            <?php // Pilihan baris per halaman: js/app.js menulis ?per_page=… ke URL dan kembali ke halaman 1 ?>
                            <div class="admin-table-size">
                                <?= component('select', [
                                    'name' => 'per_page',
                                    'label' => 'Baris per halaman',
                                    'hideLabel' => true,
                                    'options' => array_combine(PagerConfig::PER_PAGE_OPTIONS, PagerConfig::PER_PAGE_OPTIONS),
                                    'selected' => (string) $pager->getPerPage(),
                                    'size' => 'is-small',
                                    'fullwidth' => true,
                                ]) ?>
                            </div>
                            <p class="admin-table-info">
                                Menampilkan
                                <strong>
                                    <?= number_format($from, 0, ',', '.') ?>–
                                    <?= number_format($to, 0, ',', '.') ?>
                                </strong>
                                dari <strong>
                                    <?= number_format($total, 0, ',', '.') ?>
                                </strong>
                                <?= esc($totalLabel) ?>
                            </p>
                        </div>
                        <?= $pager->links('default', 'bulma') ?>
                    </div>
                <?php endif ?>
            </div>