<?php
/**
 * Keadaan kosong: ikon (atau ilustrasi robot), judul, dan pesan singkat.
 * Ilustrasi dipakai di dalam tabel (data_table) agar area kosong tidak terasa sempit.
 *
 * @var string|null $icon         Ikon Font Awesome, mis. fa-ticket
 * @var bool        $illustration tampilkan robot menggantikan ikon
 * @var string|null $title
 * @var string|null $message
 */
$icon         ??= 'fa-inbox';
$illustration ??= false;
$title        ??= lang('Admin.placeholderTitle');
$message      ??= lang('Admin.placeholderMessage');
?>
<div class="empty-state <?= $illustration ? 'has-illustration' : '' ?>">
    <?php if ($illustration): ?>
        <img class="empty-state-illustration" src="<?= base_url('images/empty-robot.svg') ?>" alt="" width="200" height="170">
    <?php else: ?>
        <span class="empty-state-icon"><i class="fa-solid <?= esc($icon, 'attr') ?>" aria-hidden="true"></i></span>
    <?php endif ?>
    <p class="has-text-weight-bold has-text-grey-darker"><?= esc($title) ?></p>
    <p class="is-size-7 has-text-grey-dark"><?= esc($message) ?></p>
</div>
