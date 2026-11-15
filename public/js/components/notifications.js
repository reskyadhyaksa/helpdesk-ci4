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

    function render() {
        roots.forEach(function (root) {
            var badge = root.querySelector('[data-notif-badge]');
            var list = root.querySelector('[data-notif-list]');
            var empty = root.querySelector('[data-notif-empty]');

            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.hidden = unread === 0;

            list.textContent = '';
            empty.hidden = items.length > 0;

            items.forEach(function (item) {
                var li = document.createElement('li');
                var button = document.createElement('button');
                var title = document.createElement('span');
                var body = document.createElement('span');
                var time = document.createElement('span');

                button.type = 'button';
                button.className = 'notif-item' + (item.read_at ? '' : ' is-unread');
                title.className = 'notif-item-title';
                title.textContent = item.title;
                body.className = 'notif-item-body';
                body.textContent = item.body;
                time.className = 'notif-item-time';
                time.textContent = timeAgo(item.created_at);

                button.append(title, body, time);
                button.addEventListener('click', function () {
                    open(item);
                });
                li.appendChild(button);
                list.appendChild(li);
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

    // Pop-up memakai gaya toast yang sudah ada (css/components/toast.css)
    function toast(notification) {
        var region = document.querySelector('.toast-region');

        if (! region) {
            region = document.createElement('div');
            region.className = 'toast-region';
            region.setAttribute('aria-label', 'Notifikasi');
            document.body.appendChild(region);
        }

        var el = document.createElement('div');
        var body = document.createElement('div');
        var heading = document.createElement('p');
        var title = document.createElement('span');
        var message = document.createElement('p');

        el.className = 'toast is-info';
        el.setAttribute('role', 'status');
        el.style.cursor = 'pointer';
        body.className = 'toast-body';
        heading.className = 'toast-heading';
        title.className = 'toast-title';
        title.textContent = notification.title;
        message.className = 'toast-message';
        message.textContent = notification.body;

        heading.appendChild(title);
        body.append(heading, message);
        el.appendChild(body);
        region.appendChild(el);

        el.addEventListener('click', function () {
            window.location.href = safeUrl(notification.url);
        });
        setTimeout(function () {
            el.remove();
        }, 7000);
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
