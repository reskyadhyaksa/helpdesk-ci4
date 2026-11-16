<?php
/**
 * Form tiket user login. Identitas diambil dari session, jadi hanya NIK pemohon (bila atas nama rekan) yang diisi.
 *
 * @var list<array> $categories
 * @var array       $limits
 */
$user = auth_user();

$contactFields = [
    [
        'name' => 'for_nip',
        'label' => lang('Ticket.form.forNip'),
        'type' => 'text',
        'max' => 50,
        'placeholder' => lang('Ticket.form.forNipPlaceholder'),
        'help' => lang('Ticket.form.forNipHelp'),
    ],
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('tickets/_form', [
    'action' => url_to('user.tickets.create'),
    'cancelUrl' => url_to('user.tickets.index'),
    'breadcrumb' => [
        ['label' => lang('Ticket.dashboard.title'), 'href' => url_to('user.dashboard'), 'icon' => 'home.svg'],
        ['label' => lang('Ticket.historyTitle'), 'href' => url_to('user.tickets.index'), 'icon' => 'doc-search.svg'],
        ['label' => lang('Ticket.newTitle')],
    ],
    'banner' => [
        'tone' => 'is-success',
        'title' => lang('Ticket.form.verifiedTitle'),
        'text' => implode(' • ', array_filter([$user['name'], 'NIP: ' . $user['employee_no'], $user['job_title']])),
        'tag' => lang('Ticket.form.verifiedTag'),
    ],
    'contactFields' => $contactFields,
    'categories' => $categories,
    'limits' => $limits,
    'errors' => session('errors') ?? [],
]) ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/ticket-form.js') ?>"></script>
<?= $this->endSection() ?>