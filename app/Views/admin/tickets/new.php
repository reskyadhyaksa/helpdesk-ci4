<?php
/**
 * Admin membuatkan tiket untuk karyawan lain (permission admin.tickets.create).
 *
 * @var list<array> $categories id, name, parent_name
 */
$errors = session('errors') ?? [];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'    => lang('Ticket.adminNewTitle'),
        'subtitle' => lang('Ticket.adminNewSubtitle'),
    ]) ?>

    <form class="box p-0" action="<?= url_to('admin.tickets.create') ?>" method="post" enctype="multipart/form-data" novalidate style="max-width: 48rem">
        <?= csrf_field() ?>

        <div class="form-card-body">
            <div class="field">
                <label class="label" for="for_nip">NIK pemohon <span class="has-text-danger" aria-hidden="true">*</span></label>
                <div class="control">
                    <input class="input <?= isset($errors['for_nip']) ? 'is-danger' : '' ?>" id="for_nip" name="for_nip"
                        type="text" maxlength="50" required value="<?= esc(old('for_nip'), 'attr') ?>"
                        placeholder="NIK karyawan yang dibuatkan tiket">
                </div>
                <p class="help">Pemohon dan Anda sebagai pengaju sama-sama dapat melihat tiketnya.</p>
                <?php if (isset($errors['for_nip'])): ?>
                    <p class="help is-danger"><?= esc($errors['for_nip']) ?></p>
                <?php endif ?>
            </div>

            <?= view('tickets/_fields', ['categories' => $categories, 'errors' => $errors]) ?>
        </div>

        <div class="form-card-footer">
            <a class="button is-light" href="<?= url_to('admin.tickets.index') ?>">Batal</a>
            <button type="submit" class="button is-primary">
                <span class="icon"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></span>
                <span>Buat Tiket</span>
            </button>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
