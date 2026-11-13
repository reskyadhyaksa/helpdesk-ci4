// Toast flash message (partials/flash.php): hilang sendiri setelah data-toast-timeout ms.
// Hover atau fokus di dalam toast menahan hitungan agar pesan sempat dibaca; tombol ✕ menutup langsung.
(function () {
    document.querySelectorAll('[data-toast]').forEach(function (toast) {
        var timeout = Number(toast.dataset.toastTimeout) || 5000;
        var remaining = timeout;
        var startedAt = 0;
        var timer = null;

        toast.style.setProperty('--toast-duration', timeout + 'ms');

        function dismiss() {
            clearTimeout(timer);
            toast.classList.add('is-leaving');

            // Hapus setelah animasi keluar; tanpa animasi (reduced motion) langsung hilang
            setTimeout(function () {
                toast.remove();
            }, 200);
        }

        function start() {
            startedAt = Date.now();
            timer = setTimeout(dismiss, remaining);
            toast.classList.remove('is-paused');
        }

        function pause() {
            clearTimeout(timer);
            remaining -= Date.now() - startedAt;
            toast.classList.add('is-paused');
        }

        toast.querySelector('[data-toast-close]').addEventListener('click', dismiss);
        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', start);
        toast.addEventListener('focusin', pause);
        toast.addEventListener('focusout', start);

        start();
    });
})();
