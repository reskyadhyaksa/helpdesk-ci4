<?php
/**
 * Alur tiket: satu panel yang menunjukkan perjalanan tiket (Open → Progress → Need Response → Done).
 * Bar bersegmen di atas memperlihatkan proporsi, langkah di bawahnya memberi angka dan penjelasan.
 * Langkah bertanda $alert dan bernilai > 0 disorot sebagai tindakan untuk user. Gaya: public/css/components/ticket-pipeline.css.
 *
 *   <?= component('ticket_pipeline', [
 *       'title' => 'Alur Tiket Anda', 'hint' => '…', 'total' => 12,
 *       'steps' => [['label' => 'Open', 'value' => 3, 'caption' => '…', 'icon' => 'fa-inbox', 'tone' => 'is-info', 'alert' => false]],
 *   ]) ?>
 *
 * @var string                                                                                       $title
 * @var string                                                                                       $hint
 * @var int                                                                                          $total
 * @var list<array{label: string, value: int, caption: string, icon: string, tone: string, alert?: bool}> $steps
 */
$hint ??= '';
?>
<section class="box ticket-pipeline" aria-labelledby="ticketPipelineTitle">
    <header class="ticket-pipeline-head">
        <div>
            <h2 class="ticket-pipeline-title" id="ticketPipelineTitle"><?= esc($title) ?></h2>
            <?php if ($hint !== ''): ?>
                <p class="ticket-pipeline-hint"><?= esc($hint) ?></p>
            <?php endif ?>
        </div>
        <p class="ticket-pipeline-total">
            <strong><?= (int) $total ?></strong>
            <span><?= esc(lang('Ticket.dashboard.pipelineTotal')) ?></span>
        </p>
    </header>

    <div class="ticket-pipeline-bar<?= $total === 0 ? ' is-empty' : '' ?>" role="img"
         aria-label="<?= esc(implode(', ', array_map(static fn (array $s): string => $s['label'] . ' ' . $s['value'], $steps)), 'attr') ?>">
        <?php foreach ($steps as $step): ?>
            <?php if ($step['value'] > 0): ?>
                <span class="ticket-pipeline-seg <?= esc($step['tone'], 'attr') ?>" style="flex-grow: <?= (int) $step['value'] ?>"></span>
            <?php endif ?>
        <?php endforeach ?>
    </div>

    <ol class="ticket-pipeline-steps">
        <?php foreach ($steps as $i => $step): ?>
            <?php $isAlert = ($step['alert'] ?? false) && $step['value'] > 0; ?>
            <li class="ticket-pipeline-step <?= esc($step['tone'], 'attr') ?><?= $isAlert ? ' is-alert' : '' ?><?= $step['value'] === 0 ? ' is-zero' : '' ?>">
                <span class="ticket-pipeline-node"><i class="fa-solid <?= esc($step['icon'], 'attr') ?>" aria-hidden="true"></i></span>
                <div class="ticket-pipeline-info">
                    <p class="ticket-pipeline-label"><span class="ticket-pipeline-no"><?= $i + 1 ?></span><?= esc($step['label']) ?></p>
                    <p class="ticket-pipeline-caption"><?= esc($step['caption']) ?></p>
                    <?php if ($isAlert): ?>
                        <p class="ticket-pipeline-flag"><i class="fa-solid fa-bolt" aria-hidden="true"></i> <?= esc(lang('Ticket.dashboard.needAction')) ?></p>
                    <?php endif ?>
                </div>
                <p class="ticket-pipeline-value"><?= (int) $step['value'] ?></p>
            </li>
        <?php endforeach ?>
    </ol>
</section>
