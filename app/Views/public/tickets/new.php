<?php
/**
 * Form tiket tanpa login. Isian kontak lengkap karena identitas dicocokkan dengan data HR setelah tiket dikirim.
 *
 * @var list<array> $categories
 * @var array       $limits
 */
$contactFields = [
    ['name' => 'nip', 'label' => lang('Ticket.form.nip'), 'type' => 'text', 'max' => 50, 'required' => true],
    ['name' => 'organization', 'label' => lang('Ticket.form.organization'), 'type' => 'text', 'max' => 150, 'required' => true],
    [
        'name' => 'phone', 'label' => lang('Ticket.form.phone'), 'type' => 'tel', 'max' => 30, 'required' => true,
        'placeholder' => lang('Ticket.form.phonePlaceholder'), 'help' => lang('Ticket.form.phoneHelp'),
    ],
    [
        'name' => 'email', 'label' => lang('Ticket.form.email'), 'type' => 'email', 'max' => 255,
        'placeholder' => lang('Ticket.form.emailPlaceholder'), 'help' => lang('Ticket.form.emailHelp'),
    ],
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('tickets/_form', [
    'action'        => url_to('public.tickets.create'),
    'cancelUrl'     => url_to('home'),
    'banner'        => [
        'tone'  => 'is-warning',
        'title' => lang('Ticket.form.pendingTitle'),
        'text'  => lang('Ticket.form.pendingText'),
        'tag'   => lang('Ticket.form.pendingTag'),
    ],
    'contactFields' => $contactFields,
    'categories'    => $categories,
    'limits'        => $limits,
    'errors'        => session('errors') ?? [],
]) ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/ticket-form.js') ?>"></script>
<?= $this->endSection() ?>
