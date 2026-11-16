<?php

use App\Entities\TicketStatus;
use App\Services\TicketService;
use CodeIgniter\I18n\Time;

/**
 * Dashboard user: ringkasan status, tiket terbaru (bisa dicari/difilter), dan standar SLA.
 *
 * @var array<string, int>                  $summary         kelompok status => jumlah
 * @var list<array>                         $tickets
 * @var list<array>                         $slaStandards
 * @var array{keyword: string, status: list<string>, category: list<string>} $filters
 * @var array<string, string>               $statusOptions
 * @var array<string, string>               $categoryOptions
 */
$statTones = ['open' => 'is-info', 'progress' => 'is-warning', 'need_response' => 'is-purple', 'done' => 'is-success'];
$statIcons = ['open' => 'fa-inbox', 'progress' => 'fa-hourglass-half', 'need_response' => 'fa-message', 'done' => 'fa-circle-check'];

$totalTickets = array_sum($summary);
$now = format_datetime('now');
$clock = Time::now()->format('H:i');

// Satu kalimat konteks di bawah sapaan: ajak user bertindak bila ada tiket yang menunggu balasannya
$greetingNote = $summary['need_response'] > 0
    ? lang('Ticket.dashboard.noteNeedResponse', [$summary['need_response']])
    : lang('Ticket.dashboard.noteDefault');
$isFiltered = $filters['keyword'] !== '' || $filters['status'] !== [] || $filters['category'] !== [];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="section user-dashboard">
    <div class="container page-container">
        <header class="user-dashboard-greeting">
            <div>
                <h1 class="title is-3"><?= esc(lang('Ticket.dashboard.greeting')) ?> <span
                        class="has-text-primary"><?= esc(auth_user()['name']) ?></span> <span
                        aria-hidden="true">👋</span></h1>
                <p class="user-dashboard-subtitle"><?= esc($greetingNote) ?></p>
            </div>

            <div class="user-dashboard-clock">
                <span class="user-dashboard-clock-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span>
                <p>
                    <strong><?= esc(format_date('now')) ?></strong>
                    <span><?= esc($clock) ?> WIB</span>
                </p>
            </div>
        </header>

        <?php
        $steps = [];

        foreach (array_keys(TicketStatus::dashboardGroups()) as $group) {
            $steps[] = [
                'label' => lang('Ticket.dashboard.stats.' . $group . '.label'),
                'value' => $summary[$group],
                'caption' => lang('Ticket.dashboard.stats.' . $group . '.caption'),
                'icon' => $statIcons[$group],
                'tone' => $statTones[$group],
                'alert' => $group === 'need_response',
            ];
        }
        ?>
        <?= component('ticket_pipeline', [
            'title' => lang('Ticket.dashboard.pipelineTitle'),
            'hint' => lang('Ticket.dashboard.pipelineHint'),
            'total' => $totalTickets,
            'steps' => $steps,
        ]) ?>

        <div class="columns is-variable is-4">
            <div class="column is-8-desktop">
                <section class="box user-dashboard-summary" aria-labelledby="ticketSummary">
                    <header class="user-dashboard-summary-head">
                        <div>
                            <h2 class="title is-5 mb-1" id="ticketSummary">
                                <?= esc(lang('Ticket.dashboard.summaryTitle')) ?></h2>
                            <p class="is-size-7">
                                <?= esc(lang('Ticket.dashboard.summaryNote', [TicketService::DASHBOARD_TICKET_LIMIT])) ?>
                            </p>
                        </div>
                        <a class="button is-primary"
                            href="<?= url_to('user.tickets.index') ?>"><?= esc(lang('Ticket.dashboard.viewAll')) ?></a>
                        <p class="is-size-7 user-dashboard-updated">
                            <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                            <?= esc(lang('Ticket.dashboard.updatedAt', [$now])) ?>
                        </p>
                    </header>

                    <?= component('filter_bar', [
                        'action' => url_to('user.dashboard'),
                        'keyword' => $filters['keyword'],
                        'isFiltered' => $isFiltered,
                        'placeholder' => lang('Ticket.dashboard.searchHint'),
                        'filters' => [
                            'status' => ['placeholder' => lang('Ticket.dashboard.allStatus'), 'options' => $statusOptions, 'selected' => $filters['status'], 'searchable' => false],
                            'category' => ['placeholder' => lang('Ticket.dashboard.allCategory'), 'options' => $categoryOptions, 'selected' => $filters['category']],
                        ],
                    ]) ?>

                    <?php component_start('data_table', [
                        'flat' => true,
                        'columns' => ['ID & Subjek', 'Tanggal Dibuat', 'Status', 'Petugas HC', ['label' => lang('Ticket.dashboard.detail'), 'align' => 'right']],
                        'emptyIcon' => 'fa-ticket',
                        'emptyTitle' => lang('Ticket.emptyTitle'),
                        'emptyMessage' => lang('Ticket.emptyMessage'),
                    ]) ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php $status = TicketStatus::from($ticket['status']) ?>
                        <tr>
                            <td>
                                <a
                                    href="<?= url_to('user.tickets.show', $ticket['id']) ?>"><strong><?= esc($ticket['ticket_number']) ?></strong></a>
                                <span
                                    class="is-block has-text-weight-semibold has-text-dark"><?= esc($ticket['subject']) ?></span>
                                <small>Kategori: <?= esc($ticket['category_name']) ?></small>
                            </td>
                            <td><?= esc(format_datetime($ticket['created_at'])) ?></td>
                            <td><span
                                    class="tag is-light is-rounded <?= $status->tagClass() ?>"><?= esc($status->label()) ?></span>
                            </td>
                            <td>
                                <?php if ($ticket['assignee_name'] !== null): ?>
                                    <span class="user-dashboard-assignee">
                                        <span class="avatar"
                                            aria-hidden="true"><?= esc(initials($ticket['assignee_name'])) ?></span>
                                        <span>
                                            <?= esc($ticket['assignee_name']) ?>
                                            <small><?= esc((string) $ticket['assignee_job_title']) ?></small>
                                        </span>
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="has-text-grey is-italic"><?= esc(lang('Ticket.dashboard.waitingProcess')) ?></span>
                                <?php endif ?>
                            </td>
                            <td class="has-text-right">
                                <a class="button is-small is-light"
                                    href="<?= url_to('user.tickets.show', $ticket['id']) ?>"><?= esc(lang('Ticket.dashboard.detail')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <?= component_end() ?>
                </section>
            </div>

            <div class="column is-4-desktop">
                <?php if ($slaStandards !== []): ?>
                    <section class="box" aria-labelledby="slaStandards">
                        <h2 class="title is-6 mb-4" id="slaStandards">
                            <i class="fa-solid fa-certificate mr-1" aria-hidden="true"></i>
                            <?= esc(lang('Ticket.dashboard.slaTitle')) ?>
                        </h2>
                        <ul class="user-dashboard-sla">
                            <?php foreach ($slaStandards as $category): ?>
                                <li>
                                    <strong><?= esc($category['name']) ?></strong>
                                    <span
                                        class="user-dashboard-sla-max"><?= esc(lang('Ticket.dashboard.slaMax', [format_duration((int) $category['sla_resolution_minutes'])])) ?></span>
                                    <?php if ((string) $category['description'] !== ''): ?>
                                        <small><?= esc($category['description']) ?></small>
                                    <?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    </section>
                <?php endif ?>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>