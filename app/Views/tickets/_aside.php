<?php
/**
 * Kolom samping form tiket: komitmen SLA umum dan tips pengisian (teks di app/Language/id/Ticket.php).
 */
$commitments = [
    ['icon' => 'fa-bolt',          'title' => lang('Ticket.aside.firstResponse'), 'text' => lang('Ticket.aside.firstResponseText')],
    ['icon' => 'fa-circle-check',  'title' => lang('Ticket.aside.target'),        'text' => lang('Ticket.aside.targetText')],
    ['icon' => 'fa-calendar-days', 'title' => lang('Ticket.aside.hours'),         'text' => lang('Ticket.aside.hoursText')],
];
?>
<aside class="ticket-aside">
    <section class="box ticket-aside-card" aria-labelledby="slaCommitment">
        <div class="ticket-aside-head">
            <span class="ticket-aside-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
            <div>
                <h2 class="ticket-aside-title" id="slaCommitment"><?= esc(lang('Ticket.aside.slaTitle')) ?></h2>
                <p class="is-size-7"><?= esc(lang('Ticket.aside.slaSubtitle')) ?></p>
            </div>
        </div>
        <ul class="ticket-commitments">
            <?php foreach ($commitments as $item): ?>
                <li>
                    <span class="ticket-aside-icon is-small"><i class="fa-solid <?= esc($item['icon'], 'attr') ?>" aria-hidden="true"></i></span>
                    <p><strong><?= esc($item['title']) ?></strong><br><?= esc($item['text']) ?></p>
                </li>
            <?php endforeach ?>
        </ul>
    </section>

    <section class="box ticket-aside-card is-tips" aria-labelledby="ticketTips">
        <div class="ticket-aside-head">
            <span class="ticket-aside-icon is-tips"><i class="fa-regular fa-lightbulb" aria-hidden="true"></i></span>
            <h2 class="ticket-aside-title" id="ticketTips"><?= esc(lang('Ticket.aside.tipsTitle')) ?></h2>
        </div>
        <ol class="ticket-tips">
            <?php foreach (lang('Ticket.aside.tips') as $tip): ?>
                <li><?= esc($tip) ?></li>
            <?php endforeach ?>
        </ol>
    </section>
</aside>
