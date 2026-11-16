// Dropdown kustom untuk components/select (<div class="select" data-select><select>…</select></div>).
// <select> asli tetap ada (disembunyikan) dan menjadi sumber nilai yang dikirim form.
// Isi <select> yang diganti dari luar: ubah options/disabled lalu kirim select.dispatchEvent(new Event('options-changed')).
// Opsional per pemakaian: data-searchable (kotak cari di menu) dan <select multiple> (checkbox, multi-pilih).
// Multi-pilih punya baris "Pilih semua" berkotak centang tiga keadaan: kosong (tanpa pilihan), setengah (sebagian),
// penuh (semua). Bila ada kotak cari, "Pilih semua" hanya berlaku untuk hasil yang tampil.
// Pola aksesibilitas: WAI-ARIA combobox + listbox. Tanpa kotak cari fokus tetap di tombol ("select-only combobox");
// dengan kotak cari fokus pindah ke kotak cari dan panah/Enter tetap menggerakkan daftar (aria-activedescendant).
(function () {
    var GAP = 6; // jarak menu dari tombol, px
    var VIEWPORT_MARGIN = 8;
    var ALL = -1;   // indeks baris "Pilih semua"
    var NONE = -2;  // tidak ada baris aktif
    var openDropdown = null;

    function createDropdown(wrapper) {
        var select = wrapper.querySelector('select');
        var isMultiple = select.multiple;
        var isSearchable = wrapper.hasAttribute('data-searchable');
        var placeholderText = wrapper.dataset.placeholder || '';
        var emptyLabel = wrapper.dataset.emptyLabel || 'Belum ada pilihan';
        var label = document.querySelector('label[for="' + select.id + '"]');
        var listId = select.id + '-listbox';
        var activeIndex = NONE;
        var typed = '';
        var typedTimer = null;

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = select.id + '-trigger';
        trigger.className = 'select-trigger';
        trigger.disabled = select.disabled;
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', listId);
        trigger.innerHTML = '<span class="select-trigger-text"></span>'
            + '<span class="select-trigger-count" hidden></span>'
            + '<i class="fa-solid fa-chevron-down select-trigger-icon" aria-hidden="true"></i>';

        var triggerText = trigger.querySelector('.select-trigger-text');
        var triggerCount = trigger.querySelector('.select-trigger-count');

        ['aria-invalid', 'aria-describedby'].forEach(function (name) {
            if (select.hasAttribute(name)) {
                trigger.setAttribute(name, select.getAttribute(name));
            }
        });

        var menu = document.createElement('div');
        menu.className = 'select-menu' + (isSearchable || isMultiple ? ' is-wide' : '');
        menu.hidden = true;

        var searchInput = null;

        if (isSearchable) {
            var searchPlaceholder = wrapper.dataset.searchPlaceholder || 'Cari…';
            var searchBox = document.createElement('div');

            searchBox.className = 'select-search';
            searchBox.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>';

            searchInput = document.createElement('input');
            searchInput.type = 'search';
            searchInput.className = 'select-search-input';
            searchInput.autocomplete = 'off';
            searchInput.placeholder = searchPlaceholder;
            searchInput.setAttribute('role', 'combobox');
            searchInput.setAttribute('aria-expanded', 'true');
            searchInput.setAttribute('aria-controls', listId);
            searchInput.setAttribute('aria-autocomplete', 'list');
            searchInput.setAttribute('aria-label', searchPlaceholder);

            searchBox.appendChild(searchInput);
            menu.appendChild(searchBox);
        }

        var list = document.createElement('ul');
        list.id = listId;
        list.className = 'select-options';
        list.setAttribute('role', 'listbox');

        if (isMultiple) {
            list.setAttribute('aria-multiselectable', 'true');
        }

        // Label kini menunjuk tombol; klik label membuka fokus ke dropdown kustom
        if (label) {
            label.id = label.id || select.id + '-label';
            label.htmlFor = trigger.id;
            trigger.setAttribute('aria-labelledby', label.id + ' ' + trigger.id);
            list.setAttribute('aria-labelledby', label.id);
        }

        // Baris "Pilih semua" (hanya multi-pilih): kotak centang tiga keadaan lewat data-state none|mixed|all
        var allItem = null;
        var allLabel = null;

        if (isMultiple) {
            allItem = document.createElement('li');
            allItem.id = listId + '-all';
            allItem.className = 'select-option select-all';
            allItem.setAttribute('role', 'option');
            allItem.innerHTML = '<span class="select-option-box">'
                + '<i class="fa-solid fa-check select-box-check" aria-hidden="true"></i>'
                + '<i class="fa-solid fa-minus select-box-mixed" aria-hidden="true"></i></span>'
                + '<span class="select-option-label"></span>';
            allLabel = allItem.querySelector('.select-option-label');

            allItem.addEventListener('click', function () {
                choose(ALL);
            });
            allItem.addEventListener('mousemove', function () {
                if (activeIndex !== ALL) {
                    setActive(ALL);
                }
            });

            list.appendChild(allItem);
        }

        var items = [];

        // Dipanggil saat awal dan setiap kali isi <select> diganti kode lain (event "options-changed")
        function buildItems() {
            items.forEach(function (item) {
                item.remove();
            });

            items = Array.prototype.map.call(select.options, function (option, index) {
                var item = document.createElement('li');
                item.id = listId + '-' + index;
                item.className = 'select-option' + (option.value === '' ? ' is-placeholder' : '');
                item.setAttribute('role', 'option');
                item.innerHTML = isMultiple
                    ? '<span class="select-option-box"><i class="fa-solid fa-check select-box-check" aria-hidden="true"></i></span><span class="select-option-label"></span>'
                    : '<span class="select-option-label"></span><i class="fa-solid fa-check select-option-check" aria-hidden="true"></i>';
                item.querySelector('.select-option-label').textContent = option.text;

                item.addEventListener('click', function () {
                    choose(index);
                });
                item.addEventListener('mousemove', function () {
                    if (activeIndex !== index) {
                        setActive(index);
                    }
                });

                list.appendChild(item);

                return item;
            });
        }

        buildItems();

        menu.appendChild(list);

        var emptyMessage = null;

        if (isSearchable) {
            emptyMessage = document.createElement('p');
            emptyMessage.className = 'select-empty';
            emptyMessage.textContent = 'Tidak ada hasil';
            emptyMessage.hidden = true;
            emptyMessage.setAttribute('role', 'status');
            menu.appendChild(emptyMessage);
        }

        var footerCount = null;
        var footerClear = null;

        if (isMultiple) {
            var footer = document.createElement('div');

            footer.className = 'select-footer';
            footer.innerHTML = '<span class="select-footer-count"></span>'
                + '<button type="button" class="select-footer-clear">Bersihkan</button>';
            footerCount = footer.querySelector('.select-footer-count');
            footerClear = footer.querySelector('.select-footer-clear');

            footerClear.addEventListener('click', function () {
                Array.prototype.forEach.call(select.options, function (option) {
                    option.selected = false;
                });
                select.dispatchEvent(new Event('change', { bubbles: true }));
                syncFromSelect();
            });

            menu.appendChild(footer);
        }

        // Fokus tidak boleh lepas dari tombol/kotak cari saat mengklik pilihan atau tombol di menu
        menu.addEventListener('mousedown', function (event) {
            if (event.target !== searchInput) {
                event.preventDefault();
            }
        });

        // Elemen yang memegang fokus selama menu terbuka
        var focusElement = searchInput || trigger;

        function rowFor(index) {
            return index === ALL ? allItem : items[index];
        }

        function visibleIndexes() {
            var indexes = [];

            items.forEach(function (item, i) {
                if (! item.hidden) {
                    indexes.push(i);
                }
            });

            return indexes;
        }

        // Keadaan "Pilih semua" dihitung dari pilihan yang sedang tampil (semua pilihan bila tanpa pencarian)
        function syncAllItem() {
            if (! allItem) {
                return;
            }

            var shown = visibleIndexes();
            var selectedCount = shown.filter(function (i) {
                return select.options[i].selected;
            }).length;
            var state = selectedCount === 0 ? 'none' : (selectedCount === shown.length ? 'all' : 'mixed');
            var isFiltering = searchInput && searchInput.value.trim() !== '';
            var text = isFiltering ? 'Pilih semua hasil' : 'Pilih semua';

            allItem.dataset.state = state;
            allItem.setAttribute('aria-selected', String(state === 'all'));
            allItem.setAttribute('aria-label', text + (state === 'mixed' ? ', sebagian dipilih' : ''));
            allItem.hidden = shown.length === 0;
            allLabel.textContent = text;
        }

        function syncFromSelect() {
            trigger.disabled = select.disabled;

            if (isMultiple) {
                var chosen = Array.prototype.filter.call(select.options, function (option) {
                    return option.selected;
                });
                var isEverything = chosen.length > 0 && chosen.length === select.options.length;

                if (isEverything) {
                    triggerText.textContent = 'Semua dipilih';
                } else {
                    triggerText.textContent = chosen.length > 0 ? chosen[0].text : placeholderText;
                }

                trigger.classList.toggle('is-placeholder', chosen.length === 0);
                triggerCount.hidden = isEverything || chosen.length < 2;
                triggerCount.textContent = '+' + (chosen.length - 1);

                if (chosen.length === 0) {
                    footerCount.textContent = emptyLabel;
                } else {
                    footerCount.textContent = isEverything ? 'Semua dipilih (' + chosen.length + ')' : chosen.length + ' dipilih';
                }

                footerClear.disabled = chosen.length === 0;

                items.forEach(function (item, i) {
                    item.setAttribute('aria-selected', String(select.options[i].selected));
                });

                syncAllItem();

                return;
            }

            var index = select.selectedIndex;
            var option = select.options[index];

            triggerText.textContent = option ? option.text : '';
            trigger.classList.toggle('is-placeholder', ! option || option.value === '');

            items.forEach(function (item, i) {
                item.setAttribute('aria-selected', String(i === index));
            });
        }

        // Urutan baris yang bisa dijangkau panah: "Pilih semua" (bila ada) lalu pilihan yang tampil
        function navigationOrder() {
            var order = [];

            if (allItem && ! allItem.hidden) {
                order.push(ALL);
            }

            return order.concat(visibleIndexes());
        }

        function move(step) {
            var order = navigationOrder();
            var position = order.indexOf(activeIndex);
            var next = position === -1 ? (step > 0 ? 0 : order.length - 1) : position + step;

            if (next >= 0 && next < order.length) {
                setActive(order[next]);
            }
        }

        function clearActive() {
            if (activeIndex !== NONE) {
                rowFor(activeIndex).classList.remove('is-active');
                activeIndex = NONE;
            }

            focusElement.removeAttribute('aria-activedescendant');
        }

        function setActive(index) {
            clearActive();

            var row = index === NONE || index < ALL ? null : rowFor(index);

            if (! row || row.hidden) {
                return;
            }

            activeIndex = index;
            row.classList.add('is-active');
            row.scrollIntoView({ block: 'nearest' });
            focusElement.setAttribute('aria-activedescendant', row.id);
        }

        // Menu memakai position: fixed agar tidak terpotong tabel/kartu yang overflow: hidden.
        // Membuka ke atas memakai bottom, jadi tinggi menu boleh berubah (hasil cari) tanpa bergeser dari tombol.
        function position() {
            var rect = trigger.getBoundingClientRect();

            // Dropdown di dalam elemen ber-zoom (mis. halaman detail tiket): koordinat layar (rect) dibagi skala
            // supaya menu yang position: fixed tetap jatuh tepat di bawah tombol. Tanpa zoom, scale = 1.
            var scale = trigger.offsetWidth > 0 ? rect.width / trigger.offsetWidth : 1;

            // Lebar minimum menu = lebar tombol; harus diset sebelum menu diukur
            menu.style.setProperty('--select-trigger-width', rect.width / scale + 'px');

            var spaceBelow = window.innerHeight - rect.bottom;
            var openUp = spaceBelow < menu.offsetHeight * scale + GAP && rect.top > spaceBelow;
            var left = Math.min(rect.left, window.innerWidth - menu.offsetWidth * scale - VIEWPORT_MARGIN);

            menu.style.left = Math.max(VIEWPORT_MARGIN, left) / scale + 'px';
            menu.style.top = openUp ? 'auto' : (rect.bottom + GAP) / scale + 'px';
            menu.style.bottom = openUp ? (window.innerHeight - rect.top + GAP) / scale + 'px' : 'auto';
            menu.classList.toggle('is-up', openUp);
        }

        function showAllItems() {
            if (! searchInput) {
                return;
            }

            searchInput.value = '';
            items.forEach(function (item) {
                item.hidden = false;
            });
            emptyMessage.hidden = true;
            syncAllItem();
        }

        function filter() {
            var term = searchInput.value.trim().toLowerCase();
            var firstMatch = NONE;

            items.forEach(function (item, i) {
                var isMatch = term === '' || select.options[i].text.toLowerCase().indexOf(term) !== -1;

                item.hidden = ! isMatch;

                if (isMatch && firstMatch === NONE) {
                    firstMatch = i;
                }
            });

            emptyMessage.hidden = firstMatch !== NONE;
            syncAllItem();
            setActive(firstMatch);
        }

        function open() {
            if (openDropdown && openDropdown !== api) {
                openDropdown.close(false);
            }

            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            wrapper.classList.add('is-open');
            position();

            var firstSelected = isMultiple
                ? Array.prototype.findIndex.call(select.options, function (option) { return option.selected; })
                : select.selectedIndex;

            setActive(Math.max(firstSelected, 0));
            openDropdown = api;

            if (searchInput) {
                searchInput.focus();
            }
        }

        function close(returnFocus) {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            wrapper.classList.remove('is-open');
            clearActive();
            showAllItems();

            if (openDropdown === api) {
                openDropdown = null;
            }

            if (returnFocus) {
                trigger.focus();
            }
        }

        // Semua pilihan yang tampil terpilih → kosongkan; selain itu → pilih semuanya
        function toggleAll() {
            var shown = visibleIndexes();
            var shouldSelect = ! shown.every(function (i) {
                return select.options[i].selected;
            });

            shown.forEach(function (i) {
                select.options[i].selected = shouldSelect;
            });
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncFromSelect();
        }

        function choose(index) {
            if (index === NONE) {
                return;
            }

            if (index === ALL) {
                toggleAll();

                return;
            }

            if (isMultiple) {
                select.options[index].selected = ! select.options[index].selected;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                syncFromSelect();

                return;
            }

            if (select.selectedIndex !== index) {
                select.selectedIndex = index;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }

            syncFromSelect();
            close(true);
        }

        // Ketik huruf untuk lompat ke pilihan yang diawali huruf tersebut (hanya tanpa kotak cari)
        function typeahead(character) {
            clearTimeout(typedTimer);
            typed += character.toLowerCase();
            typedTimer = setTimeout(function () {
                typed = '';
            }, 500);

            var start = activeIndex >= 0 ? activeIndex : select.selectedIndex;

            for (var step = 1; step <= items.length; step++) {
                var index = (start + step) % items.length;

                if (select.options[index].text.toLowerCase().indexOf(typed) === 0) {
                    return index;
                }
            }

            return NONE;
        }

        function isOpen() {
            return ! menu.hidden;
        }

        // Tombol keyboard saat menu terbuka; dipakai tombol (tanpa kotak cari) maupun kotak cari
        function handleOpenKey(event) {
            var key = event.key;

            if (key === 'ArrowDown' || key === 'ArrowUp') {
                event.preventDefault();
                move(key === 'ArrowDown' ? 1 : -1);
            } else if ((key === 'Home' || key === 'End') && ! searchInput) {
                event.preventDefault();
                var order = navigationOrder();

                setActive(order.length > 0 ? order[key === 'Home' ? 0 : order.length - 1] : NONE);
            } else if (key === 'Enter' || (key === ' ' && ! searchInput)) {
                event.preventDefault();
                choose(activeIndex);
            } else if (key === 'Escape') {
                event.preventDefault();
                event.stopPropagation(); // jangan ikut menutup modal tempat dropdown berada
                close(true);
            } else if (key === 'Tab') {
                close(false);
            } else if (key.length === 1 && ! searchInput && ! event.ctrlKey && ! event.metaKey) {
                setActive(typeahead(key));
            }
        }

        trigger.addEventListener('click', function () {
            if (isOpen()) {
                close(true);
            } else {
                open();
            }
        });

        trigger.addEventListener('keydown', function (event) {
            var key = event.key;

            if (isOpen()) {
                handleOpenKey(event);

                return;
            }

            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(key) !== -1) {
                event.preventDefault();
                open();
            } else if (key.length === 1 && ! isMultiple) {
                var match = typeahead(key);

                if (match !== NONE) {
                    choose(match);
                }
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', filter);
            searchInput.addEventListener('keydown', handleOpenKey);
        }

        // Nilai diubah dari kode lain (mis. modal mengisi nilai awal lalu memicu change): tampilan ikut
        select.addEventListener('change', syncFromSelect);
        select.addEventListener('options-changed', function () {
            buildItems();
            syncFromSelect();
        });

        select.hidden = true;
        wrapper.classList.add('is-enhanced');
        wrapper.appendChild(trigger);
        wrapper.appendChild(menu);
        syncFromSelect();

        var api = { close: close, menu: menu, wrapper: wrapper };

        return api;
    }

    // Juga dipanggil app.js setelah kartu tabel diganti lewat fetch (pilihan "baris per halaman" ikut diganti)
    window.enhanceSelects = function (root) {
        root.querySelectorAll('[data-select]:not(.is-enhanced)').forEach(createDropdown);
    };

    window.enhanceSelects(document);

    // Klik di luar, gulir halaman, atau ubah ukuran jendela menutup menu yang terbuka
    document.addEventListener('mousedown', function (event) {
        if (openDropdown && ! openDropdown.wrapper.contains(event.target)) {
            openDropdown.close(false);
        }
    });

    window.addEventListener('scroll', function (event) {
        if (openDropdown && ! openDropdown.menu.contains(event.target)) {
            openDropdown.close(false);
        }
    }, true);

    window.addEventListener('resize', function () {
        if (openDropdown) {
            openDropdown.close(false);
        }
    });
})();
