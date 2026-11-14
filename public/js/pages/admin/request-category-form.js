// Form Request Category: job title hanya untuk kategori utama. Saat induk dipilih, field disembunyikan
// dan dinonaktifkan (tidak terkirim); saat induk dikosongkan tampil lagi.
(function () {
    var parent = document.querySelector('select[name="parent_id"]');
    var field = document.querySelector('[data-job-title-field]');
    var inherit = document.querySelector('[data-job-title-inherit]');

    if (!parent || !field) {
        return;
    }

    var jobTitles = field.querySelector('select');

    function update() {
        var isSub = parent.value !== '';

        field.classList.toggle('is-hidden', isSub);
        jobTitles.disabled = isSub;

        if (inherit) {
            inherit.classList.toggle('is-hidden', !isSub);
        }
    }

    // Dropdown kustom memicu event change (bubbling) pada <select> aslinya
    parent.addEventListener('change', update);
    update();
})();
