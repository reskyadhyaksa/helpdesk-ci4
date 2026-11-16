// Toast (css/components/toast.css): hilang sendiri setelah data-toast-timeout ms. Hover atau fokus di dalam toast
// menahan hitungan agar pesan sempat dibaca; tombol ✕ menutup langsung.
// Dua sumber memakai mesin yang sama:
//   1. Flash message dari server (partials/flash.php): elemen [data-toast] yang sudah ada di halaman.
//   2. Notifikasi lonceng real-time (js/components/notifications.js): AppToast.show({ type: 'info', ... }).
// Teks selalu ditulis dengan textContent, jadi isi pesan tidak bisa menyisipkan HTML.
(function () {
    function getRegion() {
        var region = document.querySelector('.toast-region');

        if (! region) {
            region = document.createElement('div');
            region.className = 'toast-region';
            region.setAttribute('aria-label', 'Notifikasi');
            document.body.appendChild(region);
        }

        return region;
    }

    // Pasang hitungan mundur, jeda saat hover/fokus, dan tombol tutup pada satu elemen toast
    function mount(toast) {
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

        toast.querySelector('[data-toast-close]').addEventListener('click', function (event) {
            event.stopPropagation();
            dismiss();
        });
        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', start);
        toast.addEventListener('focusin', pause);
        toast.addEventListener('focusout', start);

        start();
    }

    function element(tag, className, text) {
        var el = document.createElement(tag);

        el.className = className;

        if (text !== undefined) {
            el.textContent = text;
        }

        return el;
    }

    // options: type ('success' | 'danger' | 'info'), icon (kelas Font Awesome), kicker (label kecil di atas judul),
    // title, message, href (bila diisi toast bisa diklik dan ada tautan aksi), actionLabel, timeout (ms)
    function show(options) {
        var type = options.type || 'info';
        var toast = element('div', 'toast is-' + type);
        var icon = element('span', 'toast-icon');
        var body = element('div', 'toast-body');
        var heading = element('p', 'toast-heading');
        var close = element('button', 'toast-close');

        toast.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        toast.dataset.toastTimeout = String(options.timeout || 7000);

        icon.innerHTML = '<i class="fa-solid ' + (options.icon || 'fa-bell') + '" aria-hidden="true"></i>';

        if (options.kicker) {
            body.appendChild(element('p', 'toast-kicker', options.kicker));
        }

        heading.appendChild(element('span', 'toast-title', options.title));
        heading.appendChild(element('span', 'toast-time', 'Baru saja'));
        body.appendChild(heading);
        body.appendChild(element('p', 'toast-message', options.message));

        if (options.href) {
            var action = element('a', 'toast-action', options.actionLabel || 'Lihat');

            action.href = options.href;
            action.insertAdjacentHTML('beforeend', ' <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>');
            body.appendChild(action);

            toast.classList.add('is-clickable');
            toast.addEventListener('click', function () {
                window.location.href = options.href;
            });
        }

        close.type = 'button';
        close.setAttribute('aria-label', 'Tutup notifikasi');
        close.dataset.toastClose = '';
        close.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

        toast.append(icon, body, close, element('span', 'toast-progress'));
        toast.lastChild.setAttribute('aria-hidden', 'true');

        getRegion().appendChild(toast);
        mount(toast);
    }

    window.AppToast = { show: show };

    document.querySelectorAll('[data-toast]').forEach(mount);
})();
