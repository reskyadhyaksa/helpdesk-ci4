// Lonceng notifikasi dalam aplikasi: daftar, penanda belum dibaca, pop-up (toast) real-time lewat WebSocket,
// dan polling 60 detik sebagai cadangan selama WebSocket tidak tersambung.
// Semua teks ditulis dengan textContent (bukan innerHTML), jadi isi notifikasi tidak bisa menyisipkan HTML.
(function () {
    var roots = document.querySelectorAll('[data-notifications]');

    if (! roots.length) {
        return;
    }

    var config = roots[0].dataset;
    var csrf = config.csrf;
    var items = [];
    var unread = 0;
    var socket = null;
    var reconnectDelay = 3000;
    var pollTimer = null;
    var stopped = false;      // sesi berakhir: hentikan semua permintaan latar belakang
    var wsDisabled = false;   // server WebSocket belum dikonfigurasi: cukup polling

    // Sesi berakhir (dialihkan ke login / 401 / 403): berhenti total supaya tab yang ditinggalkan tidak terus
    // mengirim permintaan, yang juga akan menjaga sesi tidak pernah kedaluwarsa.
    function sessionExpired() {
        stopped = true;
        stopPolling();

        if (socket) {
            socket.onclose = null;
            socket.close();
            socket = null;
        }
    }

    function request(method, url) {
        var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
        headers[config.csrfHeader] = csrf;

        return fetch(url, { method: method, headers: headers, credentials: 'same-origin' })
            .then(function (response) {
                if (response.redirected || response.status === 401 || response.status === 403) {
                    sessionExpired();
                    throw new Error('sesi berakhir');
                }

                return response.json().then(function (data) {
                    if (data.csrf) {
                        csrf = data.csrf;
                    }

                    return data;
                });
            });
    }

    // Hanya path di aplikasi ini yang boleh dibuka dari notifikasi
    function safeUrl(url) {
        return typeof url === 'string' && url.charAt(0) === '/' && url.charAt(1) !== '/' ? url : '/';
    }

    function timeAgo(value) {
        var seconds = Math.max(0, Math.round((Date.now() - new Date(String(value).replace(' ', 'T')).getTime()) / 1000));

        if (isNaN(seconds) || seconds < 60) {
            return 'Baru saja';
        }

        if (seconds < 3600) {
            return Math.floor(seconds / 60) + ' menit lalu';
        }

        if (seconds < 86400) {
            return Math.floor(seconds / 3600) + ' jam lalu';
        }

        return Math.floor(seconds / 86400) + ' hari lalu';
    }

    // Jenis kejadian → ikon, warna, dan label singkat (nama kelas warna ada di css/components/notifications.css)
    var KINDS = {
        ticket_assigned: { icon: 'fa-user-check', tone: 'is-blue', label: 'Penugasan' },
        status_changed: { icon: 'fa-rotate', tone: 'is-purple', label: 'Status tiket' },
        comment_added: { icon: 'fa-comment-dots', tone: 'is-green', label: 'Balasan' },
        sla_warning: { icon: 'fa-triangle-exclamation', tone: 'is-amber', label: 'SLA hampir habis' },
        sla_breached: { icon: 'fa-circle-exclamation', tone: 'is-red', label: 'SLA terlewat' }
    };
    var FALLBACK_KIND = { icon: 'fa-bell', tone: 'is-blue', label: 'Info' };
    var filter = 'all'; // 'all' | 'unread'

    function parseDate(value) {
        return new Date(String(value).replace(' ', 'T'));
    }

    // Kelompok hari untuk judul pemisah daftar: Hari ini, Kemarin, Sebelumnya
    function dayGroup(value) {
        var date = parseDate(value);
        var today = new Date();

        today.setHours(0, 0, 0, 0);
        date.setHours(0, 0, 0, 0);

        var days = Math.round((today.getTime() - date.getTime()) / 86400000);

        if (isNaN(days) || days <= 0) {
            return 'Hari ini';
        }

        return days === 1 ? 'Kemarin' : 'Sebelumnya';
    }

    function buildItem(item) {
        var kind = KINDS[item.event_type] || FALLBACK_KIND;
        var isUnread = ! item.read_at;
        var li = document.createElement('li');
        var button = document.createElement('button');
        var icon = document.createElement('span');
        var main = document.createElement('span');
        var title = document.createElement('span');
        var body = document.createElement('span');
        var meta = document.createElement('span');
        var tag = document.createElement('span');
        var time = document.createElement('time');

        button.type = 'button';
        button.className = 'notif-item' + (isUnread ? ' is-unread' : '');
        icon.className = 'notif-item-icon ' + kind.tone;
        icon.innerHTML = '<i class="fa-solid ' + kind.icon + '" aria-hidden="true"></i>';
        main.className = 'notif-item-main';
        title.className = 'notif-item-title';
        body.className = 'notif-item-body';
        meta.className = 'notif-item-meta';
        tag.className = 'notif-item-kind';
        tag.textContent = kind.label;
        time.className = 'notif-item-time';
        time.textContent = timeAgo(item.created_at);
        time.dateTime = String(item.created_at).replace(' ', 'T');
        time.title = parseDate(item.created_at).toLocaleString('id-ID');

        // Status baca tidak hanya lewat warna: teks untuk pembaca layar dan titik penanda
        if (isUnread) {
            var hidden = document.createElement('span');

            hidden.className = 'is-sr-only';
            hidden.textContent = 'Belum dibaca: ';
            title.appendChild(hidden);
        }

        title.append(document.createTextNode(item.title));
        body.textContent = item.body;
        meta.append(tag, time);
        main.append(title, body, meta);
        button.append(icon, main);

        if (isUnread) {
            var dot = document.createElement('span');

            dot.className = 'notif-item-dot';
            dot.setAttribute('aria-hidden', 'true');
            button.appendChild(dot);
        }

        button.addEventListener('click', function () {
            open(item);
        });
        li.appendChild(button);

        return li;
    }

    function render() {
        var visible = filter === 'unread' ? items.filter(function (item) { return ! item.read_at; }) : items;

        roots.forEach(function (root) {
            var badge = root.querySelector('[data-notif-badge]');
            var list = root.querySelector('[data-notif-list]');
            var empty = root.querySelector('[data-notif-empty]');
            var count = root.querySelector('[data-notif-count]');
            var readAll = root.querySelector('[data-notif-read-all]');

            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.hidden = unread === 0;
            count.textContent = unread + ' baru';
            count.hidden = unread === 0;
            readAll.disabled = unread === 0;
            root.querySelector('[data-notif-unread-count]').textContent = String(unread);

            root.querySelectorAll('[data-notif-filter]').forEach(function (tab) {
                var isActive = tab.dataset.notifFilter === filter;

                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-pressed', String(isActive));
            });

            list.textContent = '';
            empty.hidden = visible.length > 0;

            if (visible.length === 0) {
                var isUnreadView = filter === 'unread';

                root.querySelector('[data-notif-empty-title]').textContent = isUnreadView ? 'Semua sudah dibaca' : 'Belum ada notifikasi';
                root.querySelector('[data-notif-empty-text]').textContent = isUnreadView
                    ? 'Tidak ada notifikasi yang belum dibaca.'
                    : 'Pembaruan tiket Anda akan muncul di sini.';
            }

            var lastGroup = null;

            visible.forEach(function (item) {
                var group = dayGroup(item.created_at);

                if (group !== lastGroup) {
                    var heading = document.createElement('li');

                    heading.className = 'notif-group';
                    heading.setAttribute('role', 'presentation');
                    heading.textContent = group;
                    list.appendChild(heading);
                    lastGroup = group;
                }

                list.appendChild(buildItem(item));
            });
        });
    }

    function open(item) {
        var done = function () {
            window.location.href = safeUrl(item.url);
        };

        if (item.read_at) {
            done();

            return;
        }

        request('POST', config.readUrl.replace('__ID__', encodeURIComponent(item.id))).then(done, done);
    }

    function load() {
        return request('GET', config.listUrl).then(function (data) {
            items = data.items || [];
            unread = data.unread || 0;
            render();
        }).catch(function () {});
    }

    // Pop-up memakai mesin toast bersama (js/components/toast.js). Selalu bergaya "info" (biru) dengan label
    // "Notifikasi baru" dan ikon sesuai jenis kejadian, supaya tidak tertukar dengan pesan sukses (hijau) atau gagal (merah).
    function toast(notification) {
        var kind = KINDS[notification.event_type] || FALLBACK_KIND;

        window.AppToast.show({
            type: 'info',
            icon: kind.icon,
            kicker: 'Notifikasi baru · ' + kind.label,
            title: notification.title,
            message: notification.body,
            href: safeUrl(notification.url),
            actionLabel: 'Buka tiket',
            timeout: 7000
        });
    }

    function onPush(notification) {
        items = [notification].concat(items.filter(function (i) { return i.id !== notification.id; })).slice(0, 20);
        unread += 1;
        render();
        toast(notification);
    }

    function startPolling() {
        if (pollTimer === null && ! stopped) {
            // Tab yang disembunyikan tidak perlu memeriksa; saat tab dibuka lagi, load() dijalankan oleh polling berikutnya
            pollTimer = setInterval(function () {
                if (! document.hidden) {
                    load();
                }
            }, 60000);
        }
    }

    function stopPolling() {
        clearInterval(pollTimer);
        pollTimer = null;
    }

    function connect() {
        if (stopped || wsDisabled) {
            return;
        }

        request('GET', config.tokenUrl).then(function (data) {
            // Server mengatakan WebSocket belum dikonfigurasi (WS_SECRET kosong): jangan mencoba lagi, cukup polling
            if (data.error || ! data.token || ! data.url) {
                wsDisabled = true;
                startPolling();

                return;
            }

            socket = new WebSocket(data.url + (data.url.indexOf('?') === -1 ? '?' : '&') + 'token=' + encodeURIComponent(data.token));

            socket.onopen = function () {
                reconnectDelay = 3000;
                stopPolling();
                load(); // menutup notifikasi yang terlewat selama terputus
            };

            socket.onmessage = function (event) {
                try {
                    var message = JSON.parse(event.data);

                    if (message.notification) {
                        onPush(message.notification);
                    }
                } catch (e) {}
            };

            socket.onclose = function () {
                socket = null;
                startPolling();
                setTimeout(connect, reconnectDelay);
                reconnectDelay = Math.min(reconnectDelay * 2, 60000);
            };
        }).catch(function () {
            if (stopped) {
                return;
            }

            // Jaringan bermasalah: coba lagi dengan jeda yang terus bertambah, bukan tiap beberapa detik
            startPolling();
            setTimeout(connect, reconnectDelay);
            reconnectDelay = Math.min(reconnectDelay * 2, 120000);
        });
    }

    roots.forEach(function (root) {
        var toggle = root.querySelector('[data-notif-toggle]');
        var panel = root.querySelector('[data-notif-panel]');

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            panel.hidden = ! panel.hidden;
            toggle.setAttribute('aria-expanded', String(! panel.hidden));
        });

        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        root.querySelectorAll('[data-notif-filter]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                filter = tab.dataset.notifFilter;
                render();
            });
        });

        root.querySelector('[data-notif-read-all]').addEventListener('click', function () {
            request('POST', config.readAllUrl).then(function () {
                items.forEach(function (item) {
                    item.read_at = item.read_at || new Date().toISOString();
                });
                unread = 0;
                render();
            });
        });
    });

    document.addEventListener('click', function () {
        roots.forEach(function (root) {
            root.querySelector('[data-notif-panel]').hidden = true;
            root.querySelector('[data-notif-toggle]').setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.dispatchEvent(new Event('click'));
        }
    });

    load().then(connect);
})();
