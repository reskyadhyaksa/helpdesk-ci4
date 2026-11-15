<?php
/**
 * Lonceng notifikasi dalam aplikasi: tombol + penanda belum dibaca + daftar. Perilakunya di
 * public/js/components/notifications.js. Hanya ditampilkan untuk pengguna yang login.
 */
if (auth_user() === null) {
    return;
}
?>
<div class="notif" data-notifications
    data-list-url="<?= esc(url_to('notifications.index'), 'attr') ?>"
    data-read-all-url="<?= esc(url_to('notifications.read-all'), 'attr') ?>"
    data-read-url="<?= esc(site_url('notifications/__ID__/read'), 'attr') ?>"
    data-token-url="<?= esc(url_to('notifications.token'), 'attr') ?>"
    data-csrf-header="<?= esc(csrf_header(), 'attr') ?>"
    data-csrf="<?= esc(csrf_hash(), 'attr') ?>">
    <button type="button" class="button is-light is-rounded icon-button notif-toggle" aria-label="Notifikasi"
        aria-haspopup="true" aria-expanded="false" data-notif-toggle>
        <i class="fa-regular fa-bell" aria-hidden="true"></i>
        <span class="notif-badge" data-notif-badge hidden>0</span>
    </button>

    <div class="notif-panel" data-notif-panel hidden role="dialog" aria-label="Daftar notifikasi">
        <div class="notif-head">
            <strong>Notifikasi</strong>
            <button type="button" class="button is-small is-text" data-notif-read-all>Tandai semua dibaca</button>
        </div>
        <ul class="notif-list" data-notif-list></ul>
        <p class="notif-empty has-text-grey" data-notif-empty hidden>Belum ada notifikasi.</p>
    </div>
</div>
