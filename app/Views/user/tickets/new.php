<?php
/**
 * @var list<array> $categories id, name, parent_name
 */
$errors = session('errors') ?? [];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="section">
    <div class="container" style="max-width: 48rem">
        <?= component('page_header', [
            'title'    => lang('Ticket.newTitle'),
            'subtitle' => lang('Ticket.newSubtitle'),
        ]) ?>

        <form class="box p-0" action="<?= url_to('user.tickets.create') ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <div class="form-card-body">
                <div class="field">
                    <label class="label" for="for_nip">Diajukan untuk NIK <span
                            class="has-text-weight-normal has-text-grey-dark">(Opsional)</span></label>
                    <div class="control">
                        <input class="input <?= isset($errors['for_nip']) ? 'is-danger' : '' ?>" id="for_nip" name="for_nip"
                            type="text" maxlength="50" value="<?= esc(old('for_nip'), 'attr') ?>"
                            placeholder="Kosongkan bila untuk diri sendiri">
                    </div>
                    <p class="help">Isi NIK rekan bila Anda mengajukan atas namanya. Tiket akan terlihat oleh Anda dan rekan tersebut.</p>
                    <?php if (isset($errors['for_nip'])): ?>
                        <p class="help is-danger"><?= esc($errors['for_nip']) ?></p>
                    <?php endif ?>
                </div>

                <?= view('tickets/_fields', ['categories' => $categories, 'errors' => $errors]) ?>
            </div>

            <div class="form-card-footer">
                <a class="button is-light" href="<?= url_to('user.tickets.index') ?>">Batal</a>
                <button type="submit" class="button is-primary">
                    <span class="icon"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></span>
                    <span>Kirim Tiket</span>
                </button>
            </div>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
