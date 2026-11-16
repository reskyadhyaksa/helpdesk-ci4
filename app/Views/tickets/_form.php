<?php

use App\Entities\SlaPolicy;

/**
 * Form pengajuan tiket (kartu utama + kolom samping), dipakai halaman tiket user dan tanpa login.
 * Halaman hanya menentukan isian kontaknya; sisanya (kategori, SLA, rincian, lampiran) sama.
 *
 * @var string                $action        URL tujuan POST
 * @var string                $cancelUrl
 * @var array                 $banner        tone (is-success|is-warning), title, text, tag
 * @var list<array>           $contactFields name, label, type, max, required?, placeholder?, help?
 * @var list<array>           $categories    dari RequestCategoryModel::selectableForTicket()
 * @var array                 $limits        dari AttachmentService::limits()
 * @var array<string, string> $errors
 * @var list<array>           $breadcrumb    item untuk components/breadcrumb; kosong = tanpa jejak halaman
 */
$breadcrumb ??= [];

// Kategori utama (pilar) dan tujuan tiketnya. Kategori utama tanpa sub menjadi tujuan tiket sendiri.
$pillars = [];
$leaves  = [];

foreach ($categories as $category) {
    $pillarId = $category['parent_id'] ?? $category['id'];

    $pillars[$pillarId] = $category['parent_name'] ?? $category['name'];
    $leaves[]           = [
        'id'     => $category['id'],
        'pillar' => $pillarId,
        'name'   => $category['name'],
        'sla'    => $category['sla_priority'] === null ? null : [
            'priority'   => SlaPolicy::PRIORITY_LABELS[$category['sla_priority']] ?? $category['sla_priority'],
            'response'   => format_duration((int) $category['sla_response_minutes']),
            'resolution' => format_duration((int) $category['sla_resolution_minutes']),
        ],
    ];
}

$oldCategory = (string) old('category_id');
$oldPillar   = (string) old('pillar_id');

foreach ($leaves as $leaf) {
    if ($leaf['id'] === $oldCategory) {
        $oldPillar = $leaf['pillar'];
    }
}

$subOptions = [];

foreach ($leaves as $leaf) {
    if ($leaf['pillar'] === $oldPillar) {
        $subOptions[$leaf['id']] = $leaf['name'];
    }
}

$slaText = [
    'empty'      => lang('Ticket.form.slaEmpty'),
    'unavailable' => lang('Ticket.form.slaUnavailable'),
    'none'       => lang('Ticket.form.slaNone'),
    'response'   => lang('Ticket.form.slaResponse'),
    'resolution' => lang('Ticket.form.slaResolution'),
];
?>
<section class="section px-0 user-page">
    <div class="container page-container page-zoom ticket-form-page">
        <?php if ($breadcrumb !== []): ?>
            <?= component('breadcrumb', ['items' => $breadcrumb]) ?>
        <?php endif ?>

        <div class="columns is-variable is-5">
            <div class="column is-8-desktop">
                <form class="box p-0 ticket-form" action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data" novalidate
                    data-ticket-form data-categories="<?= esc(json_encode($leaves), 'attr') ?>"
                    data-sla-text="<?= esc(json_encode($slaText), 'attr') ?>"
                    data-selected-category="<?= esc($oldCategory, 'attr') ?>">
                    <?= csrf_field() ?>

                    <header class="ticket-form-header">
                        <div>
                            <h1 class="title is-5 mb-1"><?= esc(lang('Ticket.form.title')) ?></h1>
                            <p class="is-size-7"><?= esc(lang('Ticket.form.subtitle')) ?></p>
                        </div>
                        <a class="button is-danger" href="<?= esc($cancelUrl, 'attr') ?>"><?= esc(lang('Ticket.form.cancel')) ?></a>
                    </header>

                    <div class="form-card-body">
                        <div class="ticket-banner <?= esc($banner['tone'], 'attr') ?>" role="status">
                            <span class="ticket-banner-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                            <div class="ticket-banner-text">
                                <strong><?= esc($banner['title']) ?></strong>
                                <span><?= esc($banner['text']) ?></span>
                            </div>
                            <span class="tag is-rounded ticket-banner-tag"><?= esc($banner['tag']) ?></span>
                        </div>

                        <div class="ticket-section-head">
                            <h2 class="ticket-section-title"><?= esc(lang('Ticket.form.sectionCategory')) ?></h2>
                            <span class="ticket-section-note"><?= esc(lang('Ticket.form.required')) ?></span>
                        </div>
                        <p class="mb-4"><?= esc(lang('Ticket.form.categoryIntro')) ?></p>

                        <div class="ticket-panel">
                            <div class="columns is-variable is-4 mb-0">
                                <div class="column is-half">
                                    <?= component('select', [
                                        'name'        => 'pillar_id',
                                        'label'       => lang('Ticket.form.pillar'),
                                        'options'     => $pillars,
                                        'selected'    => $oldPillar,
                                        'placeholder' => lang('Ticket.form.pillarPlaceholder'),
                                        'help'        => lang('Ticket.form.pillarHelp'),
                                        'required'    => true,
                                        'fullwidth'   => true,
                                    ]) ?>
                                </div>
                                <div class="column is-half">
                                    <?= component('select', [
                                        'name'        => 'category_id',
                                        'label'       => lang('Ticket.form.sub'),
                                        'options'     => $subOptions,
                                        'selected'    => $oldCategory,
                                        'placeholder' => lang('Ticket.form.subPlaceholder'),
                                        'help'        => lang('Ticket.form.subHelp'),
                                        'searchable'  => true,
                                        'required'    => true,
                                        'disabled'    => $subOptions === [],
                                        'fullwidth'   => true,
                                        'error'       => $errors['category_id'] ?? null,
                                    ]) ?>
                                </div>
                            </div>
                        </div>

                        <div class="ticket-sla" data-sla-bar aria-live="polite">
                            <span class="icon is-small"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                            <span class="ticket-sla-text" data-sla-message><?= esc($slaText['empty']) ?></span>
                            <span class="tag ticket-sla-tag" data-sla-tag><?= esc($slaText['unavailable']) ?></span>
                        </div>

                        <div class="ticket-section-head">
                            <h2 class="ticket-section-title"><?= esc(lang('Ticket.form.sectionDetail')) ?></h2>
                            <span class="ticket-section-note"><?= esc(lang('Ticket.form.requiredMark')) ?></span>
                        </div>

                        <div class="columns is-multiline is-variable is-3">
                            <?php foreach ($contactFields as $field): ?>
                                <?php
                                $name     = $field['name'];
                                $required = $field['required'] ?? false;
                                $help     = $field['help'] ?? null;
                                $describedBy = trim(($help !== null ? $name . 'Help ' : '') . (isset($errors[$name]) ? $name . 'Error' : ''));
                                ?>
                                <div class="column is-half">
                                    <div class="field">
                                        <label class="label" for="<?= esc($name, 'attr') ?>"><?= esc($field['label']) ?>
                                            <?php if ($required): ?><span class="has-text-danger" aria-hidden="true">*</span><?php endif ?>
                                        </label>
                                        <div class="control">
                                            <input class="input <?= isset($errors[$name]) ? 'is-danger' : '' ?>" id="<?= esc($name, 'attr') ?>"
                                                name="<?= esc($name, 'attr') ?>" type="<?= esc($field['type'], 'attr') ?>"
                                                maxlength="<?= (int) $field['max'] ?>" <?= $required ? 'required' : '' ?>
                                                value="<?= esc(old($name), 'attr') ?>"
                                                placeholder="<?= esc($field['placeholder'] ?? '', 'attr') ?>"
                                                <?= $describedBy !== '' ? 'aria-describedby="' . esc($describedBy, 'attr') . '"' : '' ?>
                                                <?= isset($errors[$name]) ? 'aria-invalid="true"' : '' ?>>
                                        </div>
                                        <?php if ($help !== null): ?>
                                            <p class="help" id="<?= esc($name, 'attr') ?>Help"><?= esc($help) ?></p>
                                        <?php endif ?>
                                        <?php if (isset($errors[$name])): ?>
                                            <p class="help is-danger" id="<?= esc($name, 'attr') ?>Error"><?= esc($errors[$name]) ?></p>
                                        <?php endif ?>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>

                        <div class="field">
                            <label class="label" for="subject"><?= esc(lang('Ticket.form.subject')) ?> <span class="has-text-danger" aria-hidden="true">*</span></label>
                            <div class="control">
                                <input class="input <?= isset($errors['subject']) ? 'is-danger' : '' ?>" id="subject" name="subject"
                                    type="text" maxlength="150" required value="<?= esc(old('subject'), 'attr') ?>"
                                    placeholder="<?= esc(lang('Ticket.form.subjectPlaceholder'), 'attr') ?>"
                                    <?= isset($errors['subject']) ? 'aria-invalid="true" aria-describedby="subjectError"' : '' ?>>
                            </div>
                            <?php if (isset($errors['subject'])): ?>
                                <p class="help is-danger" id="subjectError"><?= esc($errors['subject']) ?></p>
                            <?php endif ?>
                        </div>

                        <div class="field">
                            <label class="label" for="description"><?= esc(lang('Ticket.form.description')) ?> <span class="has-text-danger" aria-hidden="true">*</span></label>
                            <div class="control">
                                <textarea class="textarea <?= isset($errors['description']) ? 'is-danger' : '' ?>" id="description"
                                    name="description" rows="5" maxlength="2000" required
                                    placeholder="<?= esc(lang('Ticket.form.descriptionPlaceholder'), 'attr') ?>"
                                    <?= isset($errors['description']) ? 'aria-invalid="true" aria-describedby="descriptionError"' : '' ?>><?= esc(old('description')) ?></textarea>
                            </div>
                            <?php if (isset($errors['description'])): ?>
                                <p class="help is-danger" id="descriptionError"><?= esc($errors['description']) ?></p>
                            <?php endif ?>
                        </div>

                        <?= view('tickets/_attachments', ['limits' => $limits]) ?>
                    </div>
                </form>
            </div>

            <div class="column is-4-desktop">
                <?= view('tickets/_aside') ?>
            </div>
        </div>
    </div>
</section>
