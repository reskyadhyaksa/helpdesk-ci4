// Seret-dan-lepas papan Kanban (admin/tickets/_board). Kartu [draggable] dipindah ke jalur status lain; server
// (TicketController::move -> TicketService::changeStatus) tetap memeriksa aturan transisi, jadi di sini hanya
// petunjuk visual: jalur yang sah menyala, yang tidak sah meredup. Jalur yang memuat beberapa status sah (mis. Waiting)
// menampilkan menu pilihan kecil. Setelah dilepas, papan dimuat ulang lewat refreshTable() (app.js).
// Layar sentuh tidak mendukung HTML5 drag-and-drop; alternatifnya ubah status di modal detail (juga untuk keyboard).
(function () {
    var drag = null;   // { card, board }
    var choice = null; // menu pilihan yang sedang terbuka

    function targetsFor(card, lane) {
        var statuses = JSON.parse(lane.dataset.laneStatuses);
        var allowed = card.dataset.allowed.split(',');

        return Object.keys(statuses).filter(function (value) {
            return allowed.indexOf(value) !== -1;
        }).map(function (value) {
            return { value: value, label: statuses[value] };
        });
    }

    function clearDrag() {
        document.querySelectorAll('.board.is-dragging').forEach(function (board) {
            board.classList.remove('is-dragging');
        });
        document.querySelectorAll('.board-lane').forEach(function (lane) {
            lane.classList.remove('can-drop', 'cannot-drop', 'is-over');
        });
        document.querySelectorAll('.board-card.is-dragging').forEach(function (card) {
            card.classList.remove('is-dragging');
        });
        drag = null;
    }

    function closeChoice() {
        if (choice) {
            choice.remove();
            choice = null;
        }
    }

    // Toast bergaya components/toast (partials/flash); hilang sendiri setelah 5 detik
    function toast(type, message) {
        var region = document.querySelector('.toast-region');

        if (! region) {
            region = document.createElement('div');
            region.className = 'toast-region';
            region.setAttribute('aria-label', 'Notifikasi');
            document.body.appendChild(region);
        }

        var el = document.createElement('div');
        var icon = document.createElement('span');
        var body = document.createElement('div');
        var title = document.createElement('p');
        var text = document.createElement('p');

        el.className = 'toast is-' + type;
        el.setAttribute('role', type === 'success' ? 'status' : 'alert');
        icon.className = 'toast-icon';
        icon.innerHTML = '<i class="fa-solid ' + (type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation') + '" aria-hidden="true"></i>';
        body.className = 'toast-body';
        title.className = 'toast-heading';
        title.innerHTML = '<span class="toast-title"></span>';
        title.firstChild.textContent = type === 'success' ? 'Berhasil' : 'Gagal';
        text.className = 'toast-message';
        text.textContent = message;
        body.append(title, text);
        el.append(icon, body);
        region.appendChild(el);

        setTimeout(function () {
            el.remove();
        }, 5000);
    }

    function refresh() {
        var form = document.querySelector('form[data-autosubmit]');

        if (form && typeof refreshTable === 'function') {
            refreshTable(form);
        } else {
            window.location.reload();
        }
    }

    function move(card, status) {
        var board = card.closest('.board');
        var body = new FormData();

        body.append(board.dataset.csrfName, board.dataset.csrfValue);
        body.append('status', status);
        card.classList.add('is-moving');

        fetch(card.dataset.moveUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, message: data.message || 'Status diperbarui.' };
            }, function () {
                return { ok: false, message: 'Gagal memindahkan tiket (sesi mungkin berakhir). Muat ulang halaman.' };
            });
        }).then(function (result) {
            toast(result.ok ? 'success' : 'danger', card.dataset.ticketNo + ': ' + result.message);
        }).catch(function () {
            toast('danger', 'Gagal memindahkan tiket. Periksa koneksi lalu coba lagi.');
        }).then(refresh); // berhasil atau gagal: papan (dan token CSRF-nya) dimuat ulang
    }

    function openChoice(x, y, card, targets) {
        closeChoice();

        var menu = document.createElement('div');
        var title = document.createElement('p');

        menu.className = 'board-choice';
        menu.setAttribute('role', 'menu');
        title.className = 'board-choice-title';
        title.textContent = 'Pindahkan ' + card.dataset.ticketNo + ' ke';
        menu.appendChild(title);

        targets.forEach(function (target) {
            var button = document.createElement('button');

            button.type = 'button';
            button.setAttribute('role', 'menuitem');
            button.textContent = target.label;
            button.addEventListener('click', function () {
                closeChoice();
                move(card, target.value);
            });
            menu.appendChild(button);
        });

        document.body.appendChild(menu);
        menu.style.left = Math.max(8, Math.min(x, window.innerWidth - menu.offsetWidth - 8)) + 'px';
        menu.style.top = Math.max(8, Math.min(y, window.innerHeight - menu.offsetHeight - 8)) + 'px';
        menu.querySelector('button').focus();
        choice = menu;
    }

    document.addEventListener('dragstart', function (event) {
        var card = event.target.closest ? event.target.closest('.board-card[draggable="true"]') : null;

        if (! card) {
            return;
        }

        var board = card.closest('.board');

        drag = { card: card, board: board };
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.ticketNo); // Firefox butuh data agar seretan dimulai

        // Kelas ditambah setelah "hantu seret" diambil browser, supaya hantunya tidak ikut pudar
        setTimeout(function () {
            if (! drag) {
                return;
            }

            card.classList.add('is-dragging');
            board.classList.add('is-dragging');
            board.querySelectorAll('.board-lane').forEach(function (lane) {
                var isHome = lane.contains(card);
                var ok = ! isHome && targetsFor(card, lane).length > 0;

                lane.classList.toggle('can-drop', ok);
                lane.classList.toggle('cannot-drop', ! ok && ! isHome);
            });
        }, 0);
    });

    document.addEventListener('dragover', function (event) {
        var lane = drag ? event.target.closest('.board-lane.can-drop') : null;

        if (lane) {
            event.preventDefault(); // tanda bahwa jalur ini menerima lepasan
            event.dataTransfer.dropEffect = 'move';
            lane.classList.add('is-over');
        }
    });

    document.addEventListener('dragleave', function (event) {
        var lane = event.target.closest ? event.target.closest('.board-lane') : null;

        if (lane && ! lane.contains(event.relatedTarget)) {
            lane.classList.remove('is-over');
        }
    });

    document.addEventListener('drop', function (event) {
        var lane = drag ? event.target.closest('.board-lane.can-drop') : null;

        if (! lane) {
            return;
        }

        event.preventDefault();

        var card = drag.card;
        var targets = targetsFor(card, lane);
        var x = event.clientX;
        var y = event.clientY;

        clearDrag();

        if (targets.length === 1) {
            move(card, targets[0].value);
        } else if (targets.length > 1) {
            openChoice(x, y, card, targets);
        }
    });

    document.addEventListener('dragend', clearDrag);

    // Menu pilihan: klik di luar atau Esc membatalkan
    document.addEventListener('mousedown', function (event) {
        if (choice && ! choice.contains(event.target)) {
            closeChoice();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && choice) {
            event.stopPropagation();
            closeChoice();
        }
    }, true);
})();
