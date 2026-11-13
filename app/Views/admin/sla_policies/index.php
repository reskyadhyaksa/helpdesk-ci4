<?php

use App\Models\PermissionModel;

// Tombol aksi hanya tampil sesuai permission (route tetap menjaga aksesnya)
$canCreate = auth_can(PermissionModel::ADMIN_SLA_CREATE);
$canUpdate = auth_can(PermissionModel::ADMIN_SLA_UPDATE);
$canDelete = auth_can(PermissionModel::ADMIN_SLA_DELETE);

// Tanpa izin aksi apa pun: kolom Aksi disembunyikan, diganti satu keterangan di atas tabel
$hasActions = $canUpdate || $canDelete;
$columns    = ['Nama Kebijakan', 'Prioritas', 'Respons Pertama', 'Penyelesaian', 'Hari Dilewati', 'Status'];

if ($hasActions) {
    $columns[] = ['label' => 'Aksi', 'align' => 'right'];
}
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<section class="section">
    <?= component('page_header', [
        'title'      => lang('Admin.pages.slaPolicies.title'),
        'subtitle'   => lang('Admin.pages.slaPolicies.subtitle'),
        'action'     => $canCreate ? lang('Admin.pages.slaPolicies.action') : null,
        'actionHref' => url_to('admin.sla-policies.new'),
    ]) ?>

    <?php if (! $hasActions): ?>
        <?= component('readonly_notice', ['message' => 'Anda tidak punya izin mengubah atau menghapus kebijakan SLA.']) ?>
    <?php endif ?>

    <?php component_start('data_table', [
        'columns'      => $columns,
        'emptyIcon'    => 'fa-clock',
        'emptyTitle'   => lang('Admin.sla.emptyTitle'),
        'emptyMessage' => lang('Admin.sla.emptyMessage'),
    ]) ?>
        <?php foreach ($policies as $policy): ?>
            <tr>
                <td>
                    <strong class="is-block"><?= esc($policy->name) ?></strong>
                    <?php if ($policy->description !== null): ?>
                        <small><?= esc($policy->description) ?></small>
                    <?php endif ?>
                </td>
                <td><span class="tag is-light is-rounded has-text-weight-bold <?= $policy->priorityTagClass() ?>"><?= esc($policy->priorityLabel()) ?></span></td>
                <td><?= esc(format_duration($policy->response_minutes)) ?></td>
                <td><?= esc(format_duration($policy->resolution_minutes)) ?></td>
                <td>
                    <?php if (! $policy->is_holiday && ! $policy->is_off_day): ?>
                        <span class="has-text-grey">Semua hari dihitung</span>
                    <?php else: ?>
                        <div class="tags">
                            <?php if ($policy->is_holiday): ?>
                                <span class="tag is-light is-rounded">Hari libur</span>
                            <?php endif ?>
                            <?php if ($policy->is_off_day): ?>
                                <span class="tag is-light is-rounded">Hari off</span>
                            <?php endif ?>
                        </div>
                    <?php endif ?>
                </td>
                <td>
                    <?php if ($policy->is_active): ?>
                        <span class="tag is-success is-light is-rounded">Aktif</span>
                    <?php else: ?>
                        <span class="tag is-light is-rounded">Nonaktif</span>
                    <?php endif ?>
                </td>
                <?php if ($hasActions): ?>
                    <td>
                        <div class="buttons is-right is-flex-wrap-nowrap">
                            <?php if ($canUpdate): ?>
                                <a class="button is-small is-light" href="<?= url_to('admin.sla-policies.edit', $policy->id) ?>">
                                    <span class="icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></span>
                                    <span>Ubah</span>
                                </a>
                            <?php endif ?>
                            <?php if ($canDelete): ?>
                                <form action="<?= url_to('admin.sla-policies.delete', $policy->id) ?>" method="post" data-confirm="<?= esc(lang('Admin.sla.confirmDelete'), 'attr') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="button is-small is-danger is-light" aria-label="Hapus <?= esc($policy->name, 'attr') ?>">
                                        <span class="icon"><i class="fa-solid fa-trash" aria-hidden="true"></i></span>
                                        <span>Hapus</span>
                                    </button>
                                </form>
                            <?php endif ?>
                        </div>
                    </td>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
    <?= component_end() ?>
</section>
<?= $this->endSection() ?>
