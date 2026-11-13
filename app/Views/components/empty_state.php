<?php
/**
 * Keadaan kosong: ikon, judul, dan pesan singkat.
 *
 * @var string      $icon     Ikon Font Awesome, mis. fa-ticket
 * @var string|null $title
 * @var string|null $message
 */
$title   ??= lang('Admin.placeholderTitle');
$message ??= lang('Admin.placeholderMessage');
?>
<div class="empty-state">
    <span class="empty-state-icon"><i class="fa-solid <?= esc($icon, 'attr') ?>" aria-hidden="true"></i></span>
    <p class="has-text-weight-bold has-text-grey-darker"><?= esc($title) ?></p>
    <p class="is-size-7 has-text-grey-dark"><?= esc($message) ?></p>
</div>
