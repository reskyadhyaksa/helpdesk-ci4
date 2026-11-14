<?php
/**
 * @var array       $ticket
 * @var list<array> $activities
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="section">
    <div class="container">
        <p class="mb-3"><a href="<?= url_to('user.tickets.index') ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?= esc(lang('Ticket.mineTitle')) ?></a></p>

        <?= view('tickets/_detail', ['ticket' => $ticket, 'activities' => $activities]) ?>
    </div>
</section>
<?= $this->endSection() ?>
