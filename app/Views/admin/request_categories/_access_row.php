<?php
/**
 * Satu baris tabel akses di form kategori. Baris baru yang ditambahkan di browser dibuat oleh
 * js/pages/admin/request-category-form.js dengan struktur dan class yang sama.
 * Nilai dikirim lewat input tersembunyi: job_titles[], role_ids[], user_access[id user] = allow|deny.
 *
 * @var string      $type     job_title | role | user
 * @var string      $value    job title, id role, atau id user
 * @var string      $label
 * @var string|null $detail   NIK (hanya user)
 * @var string|null $effect   allow | deny (hanya user)
 * @var bool        $locked   baris akun sendiri: tetap terkirim, tidak bisa dihapus
 * @var bool        $readonly baris user tanpa izin mengelola pengecualian: tidak terkirim, tidak bisa dihapus
 */
$detail   ??= null;
$effect   ??= null;
$locked   ??= false;
$readonly ??= false;

$icons = ['job_title' => 'fa-id-badge', 'role' => 'fa-user-shield', 'user' => 'fa-user'];
$names = [
    'job_title' => lang('Admin.requestCategory.typeJobTitle'),
    'role'      => lang('Admin.requestCategory.typeRole'),
    'user'      => lang('Admin.requestCategory.typeUser'),
];

$inputName = match ($type) {
    'job_title' => 'job_titles[]',
    'role'      => 'role_ids[]',
    default     => 'user_access[' . $value . ']',
};
$inputValue = $type === 'user' ? $effect : $value;
$isDeny     = $type === 'user' && $effect === 'deny';
?>
<tr data-access-row data-type="<?= esc($type, 'attr') ?>" data-value="<?= esc($value, 'attr') ?>">
    <td>
        <span class="tag is-light is-rounded access-tag is-<?= esc($type, 'attr') ?>">
            <span class="icon is-small"><i class="fa-solid <?= $icons[$type] ?>" aria-hidden="true"></i></span>
            <span><?= esc($names[$type]) ?></span>
        </span>
    </td>
    <td>
        <strong class="is-block"><?= esc($label) ?></strong>
        <?php if ($detail !== null): ?>
            <small>NIK <?= esc($detail) ?></small>
        <?php endif ?>
        <?php if (! $readonly): ?>
            <input type="hidden" name="<?= esc($inputName, 'attr') ?>" value="<?= esc((string) $inputValue, 'attr') ?>" data-access-input>
        <?php endif ?>
    </td>
    <td>
        <span class="tag is-light is-rounded <?= $isDeny ? 'is-danger' : 'is-success' ?>" data-access-right>
            <?= esc($isDeny ? lang('Admin.requestCategory.rightDeny') : lang('Admin.requestCategory.rightAllow')) ?>
        </span>
    </td>
    <td class="has-text-right">
        <?php if ($locked): ?>
            <span class="is-size-7 has-text-grey" title="<?= esc(lang('Admin.requestCategory.ownLocked'), 'attr') ?>">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <span class="is-sr-only"><?= esc(lang('Admin.requestCategory.ownLocked')) ?></span>
            </span>
        <?php elseif (! $readonly): ?>
            <button type="button" class="button is-small is-light access-remove" data-access-remove
                aria-label="<?= esc(str_replace(':name', $label, lang('Admin.requestCategory.removeLabel')), 'attr') ?>">
                <span class="icon"><i class="fa-solid fa-trash" aria-hidden="true"></i></span>
            </button>
        <?php endif ?>
    </td>
</tr>
