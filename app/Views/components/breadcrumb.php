<?php
/**
 * Jejak halaman: item terakhir = halaman saat ini (tanpa link), item sebelumnya berupa link redup.
 * Ikon memakai berkas di public/images (mis. home.svg, doc-search.svg). Gaya di css/components/breadcrumb.css.
 *
 *   <?= component('breadcrumb', ['items' => [
 *       ['label' => 'Dashboard', 'href' => url_to('user.dashboard'), 'icon' => 'home.svg'],
 *       ['label' => 'Riwayat & Pengajuan Tiket', 'icon' => 'doc-search.svg'],
 *   ]]) ?>
 *
 * @var list<array{label: string, href?: string, icon?: string}> $items
 */
$lastIndex = count($items) - 1;
?>
<nav class="app-breadcrumb" aria-label="Breadcrumb">
    <ol>
        <?php foreach ($items as $index => $item): ?>
            <?php $isCurrent = $index === $lastIndex ?>
            <li class="<?= $isCurrent ? 'is-current' : '' ?>">
                <?php if (! $isCurrent): ?>
                    <a href="<?= esc($item['href'], 'attr') ?>">
                        <?php if (isset($item['icon'])): ?><img src="<?= base_url('images/' . $item['icon']) ?>" alt="" width="24" height="24"><?php endif ?>
                        <span><?= esc($item['label']) ?></span>
                    </a>
                    <span class="app-breadcrumb-separator" aria-hidden="true">/</span>
                <?php else: ?>
                    <span aria-current="page">
                        <?php if (isset($item['icon'])): ?><img src="<?= base_url('images/' . $item['icon']) ?>" alt="" width="24" height="24"><?php endif ?>
                        <span><?= esc($item['label']) ?></span>
                    </span>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ol>
</nav>
