<?= $this->extend('layouts/admin') ?>

<?php
// Kartu ringkasan. Nilainya diisi dari service laporan saat fiturnya dibuat.
$stats = [
    ['label' => 'Total Tiket', 'icon' => 'fa-ticket'],
    ['label' => 'Tiket Terbuka', 'icon' => 'fa-folder-open'],
    ['label' => 'Tiket Selesai', 'icon' => 'fa-circle-check'],
    ['label' => 'Kepatuhan SLA', 'icon' => 'fa-stopwatch'],
];
?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'      => lang('Admin.pages.reports.title'),
        'subtitle'   => lang('Admin.pages.reports.subtitle'),
        'action'     => lang('Admin.pages.reports.action'),
        'actionIcon' => 'fa-file-export',
    ]) ?>

    <div class="columns is-multiline">
        <?php foreach ($stats as $stat): ?>
            <div class="column is-6-tablet is-3-desktop">
                <div class="box stat-card">
                    <span class="empty-state-icon"><i class="fa-solid <?= esc($stat['icon'], 'attr') ?>" aria-hidden="true"></i></span>
                    <div>
                        <p class="is-size-7 has-text-grey-dark"><?= esc($stat['label']) ?></p>
                        <p class="title is-4">&mdash;</p>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <div class="box">
        <?= component('empty_state', ['icon' => 'fa-chart-column']) ?>
    </div>
</section>
<?= $this->endSection() ?>
