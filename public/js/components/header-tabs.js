// Tab di header halaman (components/page_header, slot "tabs"). Markup:
//   <div data-header-tabs data-storage-key="..." data-default="nama" [data-query-tab="nama"]>
//       <button data-tab="nama" role="tab" id="tab-nama" aria-controls="panel-nama">...</button>
//   </div>
//   <div data-tab-panel="nama" id="panel-nama" role="tabpanel">...</div>
// Tab awal: data-query-tab bila url berisi query string (mis. hasil filter), selain itu tab terakhir yang dibuka
// (sessionStorage, supaya setelah simpan/hapus kembali ke tab yang sama), lalu data-default. Panah kiri/kanan berpindah tab.
(function () {
    var tabs = document.querySelector('[data-header-tabs]');

    if (! tabs) {
        return;
    }

    var buttons = Array.prototype.slice.call(tabs.querySelectorAll('[data-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-tab-panel]'));
    var storeKey = tabs.dataset.storageKey || 'header-tabs';
    var names = buttons.map(function (button) {
        return button.dataset.tab;
    });

    function remembered() {
        try {
            return sessionStorage.getItem(storeKey);
        } catch (e) {
            return null;
        }
    }

    function show(name, focus) {
        buttons.forEach(function (button) {
            var active = button.dataset.tab === name;

            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.tabIndex = active ? 0 : -1;

            if (active && focus) {
                button.focus();
            }
        });

        panels.forEach(function (panel) {
            panel.hidden = panel.dataset.tabPanel !== name;
        });

        try {
            sessionStorage.setItem(storeKey, name);
        } catch (e) {
            // penyimpanan tidak tersedia: abaikan
        }
    }

    buttons.forEach(function (button, index) {
        button.addEventListener('click', function () {
            show(button.dataset.tab, false);
        });

        button.addEventListener('keydown', function (event) {
            var step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;

            if (step !== 0) {
                event.preventDefault();
                show(buttons[(index + step + buttons.length) % buttons.length].dataset.tab, true);
            }
        });
    });

    var start = window.location.search !== '' && tabs.dataset.queryTab ? tabs.dataset.queryTab : (remembered() || tabs.dataset.default);

    show(names.indexOf(start) >= 0 ? start : names[0], false);
})();
