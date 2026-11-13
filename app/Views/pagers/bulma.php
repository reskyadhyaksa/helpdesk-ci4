<?php

use CodeIgniter\Pager\PagerRenderer;

/**
 * Pager bergaya Bulma. Pakai: <?= $pager->links('default', 'bulma') ?>
 * Kata kunci pencarian di query string ikut terbawa di setiap link.
 *
 * @var PagerRenderer $pager
 */
$pager->setSurroundCount(1);

if ($pager->getPageCount() <= 1) {
    return;
}

$links     = $pager->links();
$firstShown = (int) $links[0]['title'];
$lastShown  = (int) end($links)['title'];
$pageCount  = $pager->getPageCount();
?>
<nav class="pagination is-small is-rounded app-pagination" role="navigation" aria-label="Navigasi halaman">
    <?php if ($pager->hasPreviousPage()): ?>
        <a class="pagination-previous" href="<?= $pager->getPreviousPage() ?>">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>&nbsp;Sebelumnya
        </a>
    <?php else: ?>
        <span class="pagination-previous is-disabled" aria-disabled="true">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>&nbsp;Sebelumnya
        </span>
    <?php endif ?>

    <?php if ($pager->hasNextPage()): ?>
        <a class="pagination-next" href="<?= $pager->getNextPage() ?>">
            Berikutnya&nbsp;<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </a>
    <?php else: ?>
        <span class="pagination-next is-disabled" aria-disabled="true">
            Berikutnya&nbsp;<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </span>
    <?php endif ?>

    <ul class="pagination-list">
        <?php if ($firstShown > 1): ?>
            <li><a class="pagination-link" href="<?= $pager->getFirst() ?>" aria-label="Halaman 1">1</a></li>
        <?php endif ?>
        <?php if ($firstShown > 2): ?>
            <li><span class="pagination-ellipsis">&hellip;</span></li>
        <?php endif ?>

        <?php foreach ($links as $link): ?>
            <li>
                <a class="pagination-link <?= $link['active'] ? 'is-current' : '' ?>" href="<?= $link['uri'] ?>"
                    aria-label="Halaman <?= $link['title'] ?>" <?= $link['active'] ? 'aria-current="page"' : '' ?>>
                    <?= $link['title'] ?>
                </a>
            </li>
        <?php endforeach ?>

        <?php if ($lastShown < $pageCount - 1): ?>
            <li><span class="pagination-ellipsis">&hellip;</span></li>
        <?php endif ?>
        <?php if ($lastShown < $pageCount): ?>
            <li><a class="pagination-link" href="<?= $pager->getLast() ?>" aria-label="Halaman <?= $pageCount ?>"><?= $pageCount ?></a></li>
        <?php endif ?>
    </ul>
</nav>
