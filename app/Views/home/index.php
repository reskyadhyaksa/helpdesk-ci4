<?= $this->extend('layouts/main') ?>

<?php
// Data sementara. Pindahkan ke controller saat sudah berasal dari database.
$quickActions = [
    ['icon' => 'reset.svg', 'bg' => 'has-background-link-light', 'title' => 'Reset Sandi Aplikasi HC Eazy', 'desc' => 'Ganti/lupa password', 'label' => 'Form Reset Password', 'href' => '#'],
    ['icon' => 'shield-question.svg', 'bg' => 'has-background-grey-lighter', 'title' => 'Pusat Bantuan & FAQ', 'desc' => 'Cari panduan kekaryawanan', 'label' => 'Buka Pusat Bantuan HC', 'href' => '#'],
    ['icon' => 'call.png', 'bg' => 'has-background-warning-light', 'title' => 'Hotline Darurat HC', 'desc' => '[nomor handphone PIC Ticketing]', 'label' => 'Jam Operasional 08.30 - 17.30', 'modal' => 'hotlineModal'],
];

$services = [
    ['tag' => 'Payroll', 'title' => 'Slip Gaji & Bukti Pajak', 'desc' => 'Permintaan bukti potong PPh 21 (1721-A1), klarifikasi tunjangan, dan rincian payroll.', 'sla' => '12 Jam'],
    ['tag' => 'Surat Keterangan', 'title' => 'Surat Keterangan Kepegawaian', 'desc' => 'Ajukan permohonan surat keterangan aktif bekerja untuk keperluan instansi atau visa.', 'sla' => '12 Jam'],
    ['tag' => 'Presensi', 'title' => 'Koreksi Kehadiran & Presensi', 'desc' => 'Sinkronisasi kehadiran HC Eazy, lupa tap absensi, cuti mendesak, dan lembur.', 'sla' => '12 Jam'],
    ['tag' => 'Hubungan Kerja', 'title' => 'Konsultasi & Pengaduan HR', 'desc' => 'Saluran konfidensial hubungan industrial, etika kerja, dan mediasi permasalahan internal.', 'sla' => '10 Jam'],
];

$values = ['Kepercayaan', 'Kewirausahaan', 'Inovatif', 'Kegembiraan'];
?>

<?= $this->section('content') ?>
<div class="container page-container page-zoom home-page py-5">

    <div class="columns is-desktop is-3 is-7-desktop">
        <!-- Kiri: banner dan aksi cepat -->
        <div class="column">
            <section class="box hero-card">
                <span class="tag tag-wrap is-primary is-medium is-size-7 mb-4">Sistem Layanan Mandiri Karyawan PT
                    Mandiri Tunas Finance</span>
                <h1 class="title is-3 is-size-4-mobile">Selamat Datang di portal <span class="has-text-primary">HC
                        Helpdesk</span></h1>
                <p class="mb-5" style="max-width: 30rem">
                    Satu portal terpadu untuk bantuan layanan kepegawaian, pengajuan tiket kendala, panduan SOP, dan
                    resolusi akun HC Eazy.
                </p>
                <?php if (auth_user()): ?>
                    <!-- TODO: arahkan ke route buat tiket setelah modul tiket user dibuat -->
                    <a class="button is-primary is-medium btn-cta" href="#">
                        <span class="cta-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                        <span>Buat Tiket Sekarang</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                <?php else: ?>
                    <a class="button is-primary is-medium btn-cta" href="<?= url_to('login') ?>">
                        <span class="cta-icon"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></span>
                        <span>Masuk untuk Ajukan Tiket</span>
                    </a>
                <?php endif ?>
            </section>

            <?php // Grid (bukan .columns Bulma) agar iPad portrait 768px sudah 3 kolom; lihat .quick-actions di home.css ?>
            <div class="quick-actions">
                <?php foreach ($quickActions as $action): ?>
                    <div class="box p-4 h-100 is-flex is-flex-direction-column">
                        <div class="is-flex is-align-items-center mb-4" style="gap: 0.9rem">
                            <span class="icon-box <?= $action['bg'] ?>">
                                <img src="<?= base_url('images/' . $action['icon']) ?>" alt="" width="24" height="24">
                            </span>
                            <div>
                                <p class="has-text-weight-bold has-text-grey-dark"><?= esc($action['title']) ?></p>
                                <p class="is-size-7 has-text-grey"><?= esc($action['desc']) ?></p>
                            </div>
                        </div>

                        <?php if (isset($action['modal'])): ?>
                            <button type="button" class="button is-success is-outlined is-fullwidth is-size-7 px-2 mt-auto"
                                data-modal-open="<?= esc($action['modal'], 'attr') ?>">
                                <?= esc($action['label']) ?>
                            </button>
                        <?php else: ?>
                            <a class="button is-primary is-fullwidth mt-auto"
                                href="<?= esc($action['href']) ?>"><?= esc($action['label']) ?></a>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <!-- Kanan: lacak tiket -->
        <div class="column track-column" id="lacak-tiket">
            <section class="box p-5 h-100 track-card">
                <div class="track-intro">
                    <div class="is-flex is-align-items-center mb-4" style="gap: 0.9rem">
                        <span class="icon-box has-background-grey-lighter">
                            <img src="<?= base_url('images/global-search.svg') ?>" alt="" width="24" height="24">
                        </span>
                        <div>
                            <h2 class="title is-5 mb-1">Lacak Status Tiket</h2>
                            <p class="is-size-7 has-text-grey">Cek progres penanganan tiket secara instan</p>
                        </div>
                    </div>

                    <p class="is-size-7 mb-4">
                        Sudah pernah mengajukan keluhan atau tiket bantuan sebelumnya? Masukkan nomor referensi untuk
                        melacak status SLA.
                    </p>
                </div>

                <div class="track-fields">
                    <div class="field mb-5">
                        <label class="label is-size-7" for="ticketNumber">Nomor Tiket <span
                                class="has-text-danger">*</span></label>
                        <div class="control">
                            <input class="input" id="ticketNumber" type="text" placeholder="CTH: #HC-2025-0042"
                                aria-describedby="ticketNumberHelp">
                        </div>
                        <p class="help" id="ticketNumberHelp">Nomor tiket dikirim ke email Anda setelah tiket dibuat.</p>
                    </div>

                    <div class="field">
                        <label class="label is-size-7" for="reporterEmail">Email Pelapor <span
                                class="has-text-weight-normal has-text-grey">(Opsional)</span></label>
                        <div class="control">
                            <input class="input" id="reporterEmail" type="email" placeholder="nama@perusahaan.co.id"
                                aria-describedby="reporterEmailHelp">
                        </div>
                        <p class="help" id="reporterEmailHelp">Isi untuk memastikan tiket yang dilacak adalah milik Anda.
                        </p>
                    </div>
                </div>

                <div class="track-actions mt-auto pt-4">
                    <button type="button" class="button is-primary is-fullwidth">Lacak Progres Penanganan</button>

                    <p class="is-size-7 has-text-centered has-text-grey track-note">
                        Ingin melihat detail tiket?
                        <a class="has-text-link has-text-weight-semibold" href="<?= url_to('login') ?>">Masuk ke Akun
                            →</a>
                    </p>
                </div>
            </section>
        </div>
    </div>

    <!-- Layanan populer -->
    <section class="mt-6">
        <div class="is-flex is-justify-content-space-between is-align-items-flex-end mb-5" style="gap: 1rem">
            <div>
                <span class="tag is-link is-light is-medium is-size-7 is-uppercase has-text-weight-bold mb-3">Kategori
                    Layanan Populer</span>
                <h2 class="title is-4 mb-1">Layanan &amp; Bantuan Kepegawaian</h2>
                <p class="is-size-7">Pilih kategori layanan cepat untuk memproses tiket bantuan Anda.</p>
            </div>
            <div class="buttons is-hidden-mobile">
                <button type="button" class="button is-rounded" aria-label="Sebelumnya" data-scroll-target="servicesRow"
                    data-scroll-dir="-1">←</button>
                <button type="button" class="button is-rounded" aria-label="Berikutnya" data-scroll-target="servicesRow"
                    data-scroll-dir="1">→</button>
            </div>
        </div>

        <div class="scroll-row" id="servicesRow">
            <?php foreach ($services as $service): ?>
                <article class="box mb-0 is-flex is-flex-direction-column">
                    <span
                        class="tag is-info is-light is-uppercase has-text-weight-bold is-align-self-flex-start mb-4"><?= esc($service['tag']) ?></span>
                    <h3 class="title is-6 mb-2"><?= esc($service['title']) ?></h3>
                    <p class="is-size-7 is-flex-grow-1 mb-5"><?= esc($service['desc']) ?></p>
                    <div class="is-flex is-justify-content-space-between is-size-7 pt-4"
                        style="border-top: 1px solid #eef0f4">
                        <a class="has-text-weight-bold has-text-grey-dark" href="#">Ajukan Tiketing →</a>
                        <span class="has-text-grey">SLA <?= esc($service['sla']) ?></span>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
    </section>

    <!-- Nilai budaya perusahaan -->
    <hr class="mt-6">
    <section class="columns is-multiline is-mobile has-text-centered py-5">
        <?php foreach ($values as $value): ?>
            <div class="column is-6-mobile is-3-desktop">
                <img class="mb-3" src="<?= base_url('images/' . strtolower($value) . '.png') ?>" alt="<?= esc($value) ?>"
                    style="height: 4rem">
                <p class="has-text-weight-bold has-text-grey-dark is-uppercase"><?= esc($value) ?></p>
            </div>
        <?php endforeach ?>
    </section>

</div>

<?php component_start('modal', [
    'id' => 'hotlineModal',
    'title' => 'Hotline Darurat Human Capital',
    'subtitle' => 'Untuk kendala mendesak atau darurat operasional kepegawaian.',
]) ?>
<dl>
    <dt class="has-text-weight-bold">PIC Layanan Helpdesk</dt>
    <dd class="mb-3">Divisi Human Capital MTF</dd>
    <dt class="has-text-weight-bold">WhatsApp Hotline</dt>
    <dd class="mb-3"><a class="has-text-success" href="https://wa.me/6281119500000" target="_blank" rel="noopener">+62
            811-1950-0000</a></dd>
    <dt class="has-text-weight-bold">Email Support</dt>
    <dd class="mb-3">hc.helpdesk@mtf.co.id</dd>
    <dt class="has-text-weight-bold">Jam Operasional</dt>
    <dd>Senin - Jumat, 08.30 - 17.30 WIB</dd>
</dl>
<?php component_slot('footer') ?>
<button type="button" class="button is-primary" data-modal-close>Tutup</button>
<?= component_end() ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('js/pages/home.js') ?>"></script>
<?= $this->endSection() ?>