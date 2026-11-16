// Modal detail tiket di daftar tiket (All Ticket / My Ticket). Tautan [data-ticket-modal] membuka modal; isinya
// diambil dari halaman detail yang sama (TicketController::show membalas tanpa layout untuk permintaan AJAX).
// Pembukaan/penutupan modal (fokus, Esc, klik latar) ditangani app.js (openModal / closeModal).
// Form di dalam modal (ubah status, ganti PIC, balas) dikirim lewat fetch dan isi modal diganti hasilnya,
// jadi pengguna tidak meninggalkan modal. Daftar di belakang dimuat ulang saat modal ditutup bila ada perubahan.
(function () {
    var modal = document.getElementById('ticketModal');

    if (! modal) {
        return;
    }

    var content = modal.querySelector('[data-ticket-modal-content]');
    var requestId = 0;     // hanya jawaban terbaru yang dipasang
    var dirty = false;     // ada aksi yang mengubah tiket
    var currentUrl = '';

    // Dipindah ke akhir <body>: lepas dari kontainer admin (zoom, overflow) dan bisa dicetak sendiri
    document.body.appendChild(modal);

    var liveRegion = document.createElement('p');

    liveRegion.className = 'is-sr-only';
    liveRegion.setAttribute('role', 'status');
    modal.appendChild(liveRegion);

    // ---------- Pengambilan isi ----------
    function request(url, options) {
        return fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }, options || {}))
            .then(function (response) {
                return response.text().then(function (html) {
                    return { response: response, html: html };
                });
            });
    }

    // Jawaban yang bukan kartu (mis. halaman login karena sesi habis) tidak boleh dimasukkan ke modal
    function isCard(html) {
        return html.indexOf('data-ticket-modal-card') !== -1;
    }

    function setBusy(isBusy) {
        content.setAttribute('aria-busy', String(isBusy));
        content.classList.toggle('is-busy', isBusy);
    }

    function skeleton(message, extra) {
        return '<div class="ticket-modal-inner">'
            + '<header class="ticket-modal-head"><span class="ticket-modal-logo" aria-hidden="true"><i class="fa-solid fa-headset"></i></span>'
            + '<strong class="ticket-modal-no" id="ticketModalTitle">Detail Tiket</strong>'
            + '<div class="ticket-modal-actions"><button type="button" class="ticket-modal-icon" data-modal-close aria-label="Tutup" title="Tutup (Esc)">'
            + '<i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div></header>'
            + '<div class="ticket-modal-state" role="status">' + message + (extra || '') + '</div></div>';
    }

    function showError() {
        setBusy(false);
        content.innerHTML = skeleton('<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Tiket tidak bisa dimuat.',
            ' <a href="' + currentUrl + '">Buka halaman penuh</a>');
    }

    function show(html) {
        content.innerHTML = html; // keluaran view server (sudah di-escape), bukan masukan pengguna
        setBusy(false);

        // Modal pratinjau lampiran ikut dipindah ke <body> agar tidak terkurung kartu ini
        var preview = content.querySelector('#attachmentPreview');
        var previous = document.querySelector('body > #attachmentPreview');

        if (previous) {
            previous.remove();
        }

        if (preview) {
            document.body.appendChild(preview);
        }

        window.initTicketDetail(content);

        if (window.enhanceSelects) {
            window.enhanceSelects(content);
        }
    }

    function open(url, opener) {
        var id = ++requestId;

        currentUrl = url;
        dirty = false;
        setBusy(true);
        content.innerHTML = skeleton('<span class="ticket-modal-spinner" aria-hidden="true"></span> Memuat tiket…');
        openModal(modal, opener);

        request(url).then(function (result) {
            if (id !== requestId) {
                return;
            }

            if (! result.response.ok || ! isCard(result.html)) {
                showError();

                return;
            }

            show(result.html);
            // Fokus ke kartu dialog (aria-modal); Tab pertama masuk ke tombol di headernya
            content.focus();
        }).catch(function () {
            if (id === requestId) {
                showError();
            }
        });
    }

    // Modal ditutup (oleh app.js): kosongkan isi, dan muat ulang daftar bila ada perubahan
    new MutationObserver(function () {
        if (modal.classList.contains('is-active')) {
            return;
        }

        requestId++;
        content.replaceChildren();

        if (dirty) {
            window.location.reload();
        }
    }).observe(modal, { attributes: true, attributeFilter: ['class'] });

    // ---------- Klik ----------
    function copyText(text, button) {
        var done = function () {
            var message = button.dataset.copyDone || 'Tersalin';

            button.dataset.feedback = message;
            button.classList.add('has-feedback');
            liveRegion.textContent = message;
            setTimeout(function () {
                button.classList.remove('has-feedback');
            }, 1600);
        };
        var fallback = function () {
            var field = document.createElement('textarea');

            field.value = text;
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();

            try {
                document.execCommand('copy');
                done();
            } catch (error) {
                // Salin gagal: tidak ada yang bisa dilakukan selain membiarkannya
            }

            field.remove();
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, fallback);
        } else {
            fallback();
        }
    }

    function closeMenus(except) {
        content.querySelectorAll('[data-menu].is-active').forEach(function (menu) {
            if (menu !== except) {
                menu.classList.remove('is-active');
                menu.querySelector('button').setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-ticket-modal]');

        // Ctrl/Cmd/Shift-klik dan klik tengah tetap membuka halaman penuh seperti tautan biasa
        if (link && event.button === 0 && ! (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) {
            event.preventDefault();
            open(link.href, link);

            return;
        }

        if (! content.contains(event.target)) {
            closeMenus(null);

            return;
        }

        var copy = event.target.closest('[data-copy]');
        var menuTrigger = event.target.closest('[data-menu] .dropdown-trigger button');
        var dismiss = event.target.closest('[data-dismiss]');

        if (copy) {
            copyText(copy.dataset.copy, copy);
        } else if (event.target.closest('[data-print]')) {
            window.print();
        } else if (dismiss) {
            dismiss.closest('.notification').remove();
        }

        if (menuTrigger) {
            var menu = menuTrigger.closest('[data-menu]');
            var isOpen = menu.classList.toggle('is-active');

            menuTrigger.setAttribute('aria-expanded', String(isOpen));
            closeMenus(menu);
        } else {
            closeMenus(null);
        }
    });

    // ---------- Form di dalam modal ----------
    content.addEventListener('submit', function (event) {
        var form = event.target;

        if (form.method.toLowerCase() !== 'post') {
            return;
        }

        event.preventDefault();

        var data = new FormData(form);
        var id = ++requestId;

        // Tombol yang menekan submit (mis. pilihan status di menu) ikut dikirim
        if (event.submitter && event.submitter.name) {
            data.append(event.submitter.name, event.submitter.value);
        }

        setBusy(true);

        request(form.action, { method: 'POST', body: data }).then(function (result) {
            if (id !== requestId) {
                return;
            }

            // Bukan kartu (sesi habis / error): biarkan browser membuka halamannya
            if (! isCard(result.html)) {
                window.location.href = result.response.url;

                return;
            }

            dirty = true;
            show(result.html);
        }).catch(function () {
            setBusy(false);
            liveRegion.textContent = 'Gagal mengirim. Periksa koneksi lalu coba lagi.';
        });
    });

    // ---------- Keyboard ----------
    function isTyping(target) {
        return target.closest('input, textarea, select, [contenteditable="true"]') !== null;
    }

    document.addEventListener('keydown', function (event) {
        // Pratinjau lampiran sedang terbuka di atas modal ini: tombol pintas tidak berlaku
        if (! modal.classList.contains('is-active') || document.querySelector('body > #attachmentPreview.is-active')) {
            return;
        }

        // Esc menutup menu "..." lebih dulu; capture + stopPropagation supaya modal tidak ikut tertutup
        if (event.key === 'Escape' && content.querySelector('[data-menu].is-active')) {
            event.stopPropagation();
            closeMenus(null);

            return;
        }

        if (event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target)) {
            return;
        }

        if (event.key === 'm') {
            var editor = content.querySelector('[data-editor]:not([hidden])') || content.querySelector('[data-fallback]');

            if (editor) {
                event.preventDefault();
                editor.scrollIntoView({ block: 'center' });
                editor.focus();
            }
        } else if (event.key === 'a') {
            var picTrigger = content.querySelector('#assignee-user-trigger');

            if (picTrigger) {
                event.preventDefault();
                picTrigger.scrollIntoView({ block: 'center' });
                picTrigger.click();
            }
        }
    }, true);

    // Fokus tidak keluar dari dialog (aria-modal): Tab berputar di dalam kartu
    modal.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab') {
            return;
        }

        var items = Array.prototype.filter.call(
            content.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [contenteditable="true"]'),
            function (element) {
                return element.offsetParent !== null;
            },
        );

        if (items.length === 0) {
            return;
        }

        var first = items[0];
        var last = items[items.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
