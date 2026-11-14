<?php
/**
 * Form tiket tanpa login.
 *
 * @var list<array> $categories id, name, parent_name
 */
$errors = session('errors') ?? [];

$contactFields = [
    'nip'          => ['label' => 'Nomor Induk Pegawai', 'required' => true, 'type' => 'text', 'max' => 50],
    'organization' => ['label' => 'Divisi / Regional', 'required' => true, 'type' => 'text', 'max' => 150],
    'phone'        => ['label' => 'Nomor Handphone (WhatsApp aktif)', 'required' => true, 'type' => 'tel', 'max' => 30],
    'email'        => ['label' => 'E-mail perusahaan', 'required' => false, 'type' => 'email', 'max' => 255],
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="section">
    <div class="container" style="max-width: 48rem">
        <?= component('page_header', [
            'title'    => lang('Ticket.publicTitle'),
            'subtitle' => lang('Ticket.publicSubtitle'),
        ]) ?>

        <form class="box p-0" action="<?= url_to('public.tickets.create') ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <div class="form-card-body">
                <p class="form-section-label">Data Kepegawaian</p>
                <p class="help mb-4"><?= esc(lang('Ticket.publicNotice')) ?></p>

                <div class="columns is-multiline is-variable is-3">
                    <?php foreach ($contactFields as $name => $field): ?>
                        <div class="column is-half">
                            <div class="field">
                                <label class="label" for="<?= $name ?>"><?= esc($field['label']) ?>
                                    <?php if ($field['required']): ?><span class="has-text-danger" aria-hidden="true">*</span><?php endif ?>
                                </label>
                                <div class="control">
                                    <input class="input <?= isset($errors[$name]) ? 'is-danger' : '' ?>" id="<?= $name ?>"
                                        name="<?= $name ?>" type="<?= $field['type'] ?>" maxlength="<?= $field['max'] ?>"
                                        <?= $field['required'] ? 'required' : '' ?> value="<?= esc(old($name), 'attr') ?>"
                                        <?= isset($errors[$name]) ? 'aria-invalid="true" aria-describedby="' . $name . 'Error"' : '' ?>>
                                </div>
                                <?php if (isset($errors[$name])): ?>
                                    <p class="help is-danger" id="<?= $name ?>Error"><?= esc($errors[$name]) ?></p>
                                <?php endif ?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>

                <p class="form-section-label mt-4">Kendala</p>
                <?= view('tickets/_fields', ['categories' => $categories, 'errors' => $errors]) ?>
            </div>

            <div class="form-card-footer">
                <a class="button is-light" href="<?= url_to('home') ?>">Batal</a>
                <button type="submit" class="button is-primary">
                    <span class="icon"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></span>
                    <span>Kirim Tiket</span>
                </button>
            </div>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
