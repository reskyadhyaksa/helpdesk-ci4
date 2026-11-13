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

// Form filter (components/filter_bar): memilih dropdown langsung mengirim form.
// Dropdown multi-pilih baru mengirim saat menunya ditutup (event "commit" dari js/components/select.js),
// supaya mencentang beberapa pilihan tidak memuat ulang halaman di tiap centang.
function submitAutosubmitForm(event) {
    var form = event.target.closest('form[data-autosubmit]');

    if (form) {
        form.requestSubmit();
    }
}

document.addEventListener('change', function (event) {
    if (event.target.tagName === 'SELECT' && ! event.target.multiple) {
        submitAutosubmitForm(event);
    }
});

document.addEventListener('commit', submitAutosubmitForm);

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
