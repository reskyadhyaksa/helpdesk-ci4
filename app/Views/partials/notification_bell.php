<?php
/**
 * Lonceng notifikasi dalam aplikasi: tombol + penanda belum dibaca + panel daftar (filter Semua / Belum dibaca,
 * dikelompokkan per hari). Perilakunya di public/js/components/notifications.js. Hanya untuk pengguna yang login.
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
            <div class="notif-head-title">
                <strong>Notifikasi</strong>
                <span class="notif-count" data-notif-count hidden></span>
            </div>
            <button type="button" class="notif-read-all" data-notif-read-all disabled>
                <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                <span>Tandai semua dibaca</span>
            </button>
        </div>

        <div class="notif-tabs" role="group" aria-label="Filter notifikasi">
            <button type="button" class="notif-tab is-active" data-notif-filter="all" aria-pressed="true">Semua</button>
            <button type="button" class="notif-tab" data-notif-filter="unread" aria-pressed="false">
                Belum dibaca <span class="notif-tab-count" data-notif-unread-count>0</span>
            </button>
        </div>

        <ul class="notif-list" data-notif-list></ul>

        <div class="notif-empty" data-notif-empty hidden>
            <span class="notif-empty-icon"><i class="fa-regular fa-bell-slash" aria-hidden="true"></i></span>
            <p class="notif-empty-title" data-notif-empty-title>Belum ada notifikasi</p>
            <p class="notif-empty-text" data-notif-empty-text>Pembaruan tiket Anda akan muncul di sini.</p>
        </div>
    </div>
</div>
