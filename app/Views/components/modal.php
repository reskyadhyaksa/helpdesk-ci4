<?php
/**
 * Modal Bulma. Dibuka oleh tombol <button data-modal-open="ID">, ditutup oleh [data-modal-close],
 * klik latar, atau Esc (lihat public/js/app.js).
 *
 *   <?php component_start('modal', [
 *       'id'       => 'roleModal',
 *       'title'    => 'Ubah Role Pengguna',
 *       'subtitle' => 'Berlaku setelah pengguna login ulang.',
 *       'form'     => ['action' => url_to('...'), 'attributes' => ['data-role-form' => '']],
 *   ]) ?>
 *       ...isi modal ($slot)...
 *   <?php component_slot('footer') ?>
 *       <button type="button" class="button is-light" data-modal-close>Batal</button>
 *       <button type="submit" class="button is-primary">Simpan</button>
 *   <?= component_end() ?>
 *
 * @var string      $id
 * @var string      $title
 * @var string|null $subtitle
 * @var string      $slot      isi modal
 * @var string|null $footer    tombol aksi (rata kanan)
 * @var string      $size      'is-small' (28rem) | '' (36rem) | 'is-large' (48rem)
 * @var array|null  $form      jika diisi, kartu modal menjadi <form method="post"> + CSRF:
 *                             ['action' => string, 'attributes' => array<string, string>]
 */
$subtitle ??= null;
$footer   ??= null;
$size     ??= '';
$form     ??= null;

$titleId = $id . 'Title';
$tag     = $form === null ? 'div' : 'form';

$formAttributes = '';

if ($form !== null) {
    $formAttributes = ' method="post" action="' . esc($form['action'] ?? '', 'attr') . '"';

    foreach ($form['attributes'] ?? [] as $name => $value) {
        $formAttributes .= ' ' . esc($name, 'attr') . '="' . esc($value, 'attr') . '"';
    }
}
?>
<div class="modal app-modal <?= esc($size) ?>" id="<?= esc($id, 'attr') ?>">
    <div class="modal-background" data-modal-close></div>

    <<?= $tag ?> class="modal-card" role="dialog" aria-modal="true" aria-labelledby="<?= esc($titleId, 'attr') ?>"<?= $formAttributes ?>>
        <?php if ($form !== null): ?>
            <?= csrf_field() ?>
        <?php endif ?>

        <header class="modal-card-head">
            <div>
                <h2 class="app-modal-title" id="<?= esc($titleId, 'attr') ?>"><?= esc($title) ?></h2>
                <?php if ($subtitle !== null): ?>
                    <p class="app-modal-subtitle"><?= esc($subtitle) ?></p>
                <?php endif ?>
            </div>
            <button type="button" class="app-modal-close" aria-label="Tutup" data-modal-close>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <section class="modal-card-body">
            <?= $slot ?>
        </section>

        <?php if ($footer !== null && trim($footer) !== ''): ?>
            <footer class="modal-card-foot">
                <?= $footer ?>
            </footer>
        <?php endif ?>
    </<?= $tag ?>>
</div>
