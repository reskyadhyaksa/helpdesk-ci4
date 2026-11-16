// Burger menu navbar (mobile)
document.querySelectorAll('.navbar-burger').forEach(function (burger) {
    burger.addEventListener('click', function () {
        var menu = document.getElementById(burger.dataset.target);
        var isOpen = burger.classList.toggle('is-active');

        menu.classList.toggle('is-active', isOpen);
        burger.setAttribute('aria-expanded', isOpen);
    });
});

// Esc menutup menu burger dan mengembalikan fokus ke tombolnya
document.addEventListener('keydown', function (event) {
    var burger = document.querySelector('.navbar-burger.is-active');

    if (event.key !== 'Escape' || ! burger) {
        return;
    }

    burger.click();
    burger.focus();
});

// Konfirmasi sebelum kirim form berbahaya (mis. hapus): <form data-confirm="Pesan konfirmasi">.
// Form POST yang jadi dikirim: tombol submit tampil loading (umpan balik + cegah klik ganda).
document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.dataset.confirm;

    if (message && ! window.confirm(message)) {
        event.preventDefault();
        return;
    }

    if (form.method.toLowerCase() === 'post') {
        var submitButton = event.submitter || form.querySelector('[type="submit"]');

        if (submitButton) {
            submitButton.classList.add('is-loading');
        }
    }
});

// Form filter (components/filter_bar): memilih dropdown (tunggal maupun multi-pilih) atau menekan Cari
// mengambil halaman dengan filter baru lewat fetch, lalu hanya kartu tabelnya yang diganti. Dropdown filter
// tidak ikut diganti, jadi menunya tetap terbuka saat mencentang beberapa pilihan. Gagal → muat halaman biasa.
var latestFilterRequest = 0;

function refreshTable(form) {
    var params = new URLSearchParams(new FormData(form));
    var empty = [];

    params.forEach(function (value, key) {
        if (value === '') {
            empty.push(key);
        }
    });
    empty.forEach(function (key) {
        params.delete(key);
    });

    var query = params.toString();
    var url = form.getAttribute('action') + (query ? '?' + query : '');
    var request = ++latestFilterRequest;

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) {
            return response.ok ? response.text() : Promise.reject(new Error(response.status));
        })
        .then(function (html) {
            if (request !== latestFilterRequest) {
                return; // jawaban lama, sudah ada permintaan yang lebih baru
            }

            var page = new DOMParser().parseFromString(html, 'text/html');
            // Daftar tiket punya wilayah [data-list-region] (tabel atau papan Kanban); halaman lain hanya kartu tabel
            var card = document.querySelector('[data-list-region]') || document.querySelector('.admin-table-card');
            var freshCard = page.querySelector('[data-list-region]') || page.querySelector('.admin-table-card');

            if (! card || ! freshCard) {
                window.location.href = url;

                return;
            }

            card.replaceWith(freshCard);
            window.enhanceSelects(freshCard);

            // Tab cepat (components di atas filter bar): angka dan tab aktif ikut diperbarui
            var quick = document.querySelector('[data-quick-filters]');
            var freshQuick = page.querySelector('[data-quick-filters]');

            if (quick && freshQuick) {
                quick.replaceWith(freshQuick);
            }

            var reset = document.querySelector('[data-filter-reset]');
            var freshReset = page.querySelector('[data-filter-reset]');

            if (reset && freshReset) {
                reset.classList.toggle('is-hidden', freshReset.classList.contains('is-hidden'));
            }

            window.history.pushState(null, '', url);
        })
        .catch(function () {
            window.location.href = url;
        });
}

document.addEventListener('change', function (event) {
    var isControl = event.target.tagName === 'SELECT' || event.target.type === 'checkbox';
    var form = isControl ? event.target.closest('form[data-autosubmit]') : null;

    if (form) {
        refreshTable(form);
    }
});

// Tab cepat dan pilihan tampilan di atas filter bar (admin/tickets/_list_controls): mengisi input tersembunyi
// "tab" / "view" pada form filter lalu memperbaruinya. Nilai bawaan (all / table) dikirim kosong agar URL tetap pendek.
document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-quick-tab]');
    var view = event.target.closest('[data-view-toggle]');
    var form = tab || view ? document.querySelector('form[data-autosubmit]') : null;

    if (! form) {
        return;
    }

    if (tab) {
        form.elements.tab.value = tab.dataset.quickTab === 'all' ? '' : tab.dataset.quickTab;
    } else {
        form.elements.view.value = view.dataset.viewToggle === 'kanban' ? 'kanban' : '';
    }

    refreshTable(form);
});

document.addEventListener('submit', function (event) {
    if (event.target.matches('form[data-autosubmit]')) {
        event.preventDefault();
        refreshTable(event.target);
    }
});

// Tombol Back/Forward setelah filter berubah lewat fetch: muat ulang sesuai URL
window.addEventListener('popstate', function () {
    window.location.reload();
});

// Pilihan "baris per halaman" di footer tabel (components/data_table): filter lain tetap, kembali ke halaman 1
document.addEventListener('change', function (event) {
    if (event.target.name !== 'per_page') {
        return;
    }

    var url = new URL(window.location.href);

    url.searchParams.set('per_page', event.target.value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
});

// Kembali lewat tombol Back (halaman dari cache browser): tombol tidak boleh tertahan loading
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        document.querySelectorAll('.button.is-loading').forEach(function (button) {
            button.classList.remove('is-loading');
        });
    }
});

// Modal (components/modal): <button data-modal-open="idModal"> membuka, [data-modal-close] atau Esc menutup.
// Saat dibuka fokus pindah ke isi modal dan halaman di belakang tidak ikut tergulir;
// saat ditutup fokus kembali ke tombol pembukanya.
var modalOpener = null;

function openModal(modal, opener) {
    modalOpener = opener;
    modal.classList.add('is-active');
    document.documentElement.classList.add('is-clipped');

    // Elemen pertama yang terlihat (mis. <select> asli yang diganti dropdown kustom tidak terlihat)
    var fields = modal.querySelectorAll('.modal-card-body button, .modal-card-body input, .modal-card-body select, .modal-card-body textarea, .modal-card-body a[href]');
    var firstField = Array.prototype.find.call(fields, function (field) {
        return field.offsetParent !== null && ! field.disabled;
    });
    var focusTarget = firstField || modal.querySelector('[data-modal-close]:not(.modal-background)');

    if (focusTarget) {
        focusTarget.focus();
    }
}

function closeModal(modal) {
    modal.classList.remove('is-active');
    document.documentElement.classList.remove('is-clipped');

    if (modalOpener) {
        modalOpener.focus();
        modalOpener = null;
    }
}

document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-modal-open]');

    if (opener) {
        openModal(document.getElementById(opener.dataset.modalOpen), opener);
        return;
    }

    if (event.target.closest('[data-modal-close]')) {
        closeModal(event.target.closest('.modal'));
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal.is-active').forEach(closeModal);
    }
});
