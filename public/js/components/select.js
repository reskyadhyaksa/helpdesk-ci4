// Dropdown kustom untuk components/select (<div class="select" data-select><select>…</select></div>).
// <select> asli tetap ada (disembunyikan) dan menjadi sumber nilai yang dikirim form.
// Pola aksesibilitas: WAI-ARIA "select-only combobox" (tombol combobox + listbox).
(function () {
    var GAP = 6; // jarak menu dari tombol, px
    var openDropdown = null;

    function createDropdown(wrapper) {
        var select = wrapper.querySelector('select');
        var label = document.querySelector('label[for="' + select.id + '"]');
        var listId = select.id + '-listbox';
        var activeIndex = -1;
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
            + '<i class="fa-solid fa-chevron-down select-trigger-icon" aria-hidden="true"></i>';

        ['aria-invalid', 'aria-describedby'].forEach(function (name) {
            if (select.hasAttribute(name)) {
                trigger.setAttribute(name, select.getAttribute(name));
            }
        });

        var list = document.createElement('ul');
        list.id = listId;
        list.className = 'select-menu';
        list.hidden = true;
        list.setAttribute('role', 'listbox');

        // Label kini menunjuk tombol; klik label membuka fokus ke dropdown kustom
        if (label) {
            label.id = label.id || select.id + '-label';
            label.htmlFor = trigger.id;
            trigger.setAttribute('aria-labelledby', label.id + ' ' + trigger.id);
            list.setAttribute('aria-labelledby', label.id);
        }

        var items = Array.prototype.map.call(select.options, function (option, index) {
            var item = document.createElement('li');
            item.id = listId + '-' + index;
            item.className = 'select-option' + (option.value === '' ? ' is-placeholder' : '');
            item.setAttribute('role', 'option');
            item.innerHTML = '<span></span><i class="fa-solid fa-check select-option-check" aria-hidden="true"></i>';
            item.firstChild.textContent = option.text;

            item.addEventListener('mousedown', function (event) {
                event.preventDefault(); // fokus tetap di tombol
            });
            item.addEventListener('click', function () {
                choose(index);
            });
            item.addEventListener('mousemove', function () {
                setActive(index);
            });

            list.appendChild(item);

            return item;
        });

        function syncFromSelect() {
            var index = select.selectedIndex;
            var option = select.options[index];

            trigger.querySelector('.select-trigger-text').textContent = option ? option.text : '';
            trigger.classList.toggle('is-placeholder', ! option || option.value === '');

            items.forEach(function (item, i) {
                item.setAttribute('aria-selected', String(i === index));
            });
        }

        function setActive(index) {
            if (index < 0 || index >= items.length) {
                return;
            }

            if (activeIndex >= 0) {
                items[activeIndex].classList.remove('is-active');
            }

            activeIndex = index;
            items[index].classList.add('is-active');
            items[index].scrollIntoView({ block: 'nearest' });
            trigger.setAttribute('aria-activedescendant', items[index].id);
        }

        // Menu memakai position: fixed agar tidak terpotong tabel/kartu yang overflow: hidden
        function position() {
            var rect = trigger.getBoundingClientRect();
            var spaceBelow = window.innerHeight - rect.bottom;
            var openUp = spaceBelow < list.offsetHeight + GAP && rect.top > spaceBelow;

            list.style.minWidth = rect.width + 'px';
            list.style.left = rect.left + 'px';
            list.style.top = (openUp ? rect.top - list.offsetHeight - GAP : rect.bottom + GAP) + 'px';
            list.classList.toggle('is-up', openUp);
        }

        function open() {
            if (openDropdown && openDropdown !== api) {
                openDropdown.close(false);
            }

            list.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            wrapper.classList.add('is-open');
            position();
            setActive(Math.max(select.selectedIndex, 0));
            openDropdown = api;
        }

        function close(returnFocus) {
            list.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.removeAttribute('aria-activedescendant');
            wrapper.classList.remove('is-open');

            if (activeIndex >= 0) {
                items[activeIndex].classList.remove('is-active');
                activeIndex = -1;
            }

            if (openDropdown === api) {
                openDropdown = null;
            }

            if (returnFocus) {
                trigger.focus();
            }
        }

        function choose(index) {
            if (select.selectedIndex !== index) {
                select.selectedIndex = index;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }

            syncFromSelect();
            close(true);
        }

        // Ketik huruf untuk lompat ke pilihan yang diawali huruf tersebut
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

            return -1;
        }

        function isOpen() {
            return ! list.hidden;
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

            if (! isOpen()) {
                if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(key) !== -1) {
                    event.preventDefault();
                    open();
                } else if (key.length === 1) {
                    var match = typeahead(key);

                    if (match >= 0) {
                        choose(match);
                    }
                }

                return;
            }

            if (key === 'ArrowDown' || key === 'ArrowUp' || key === 'Home' || key === 'End') {
                event.preventDefault();
                var targets = { ArrowDown: activeIndex + 1, ArrowUp: activeIndex - 1, Home: 0, End: items.length - 1 };
                setActive(targets[key]);
            } else if (key === 'Enter' || key === ' ') {
                event.preventDefault();
                choose(activeIndex);
            } else if (key === 'Escape') {
                event.preventDefault();
                event.stopPropagation(); // jangan ikut menutup modal tempat dropdown berada
                close(true);
            } else if (key === 'Tab') {
                close(false);
            } else if (key.length === 1) {
                setActive(typeahead(key));
            }
        });

        // Nilai diubah dari kode lain (mis. modal mengisi nilai awal lalu memicu change): tampilan ikut
        select.addEventListener('change', syncFromSelect);

        select.hidden = true;
        wrapper.classList.add('is-enhanced');
        wrapper.appendChild(trigger);
        wrapper.appendChild(list);
        syncFromSelect();

        var api = { close: close, list: list, wrapper: wrapper };

        return api;
    }

    document.querySelectorAll('[data-select]').forEach(createDropdown);

    // Klik di luar, gulir halaman, atau ubah ukuran jendela menutup menu yang terbuka
    document.addEventListener('mousedown', function (event) {
        if (openDropdown && ! openDropdown.wrapper.contains(event.target)) {
            openDropdown.close(false);
        }
    });

    window.addEventListener('scroll', function (event) {
        if (openDropdown && ! openDropdown.list.contains(event.target)) {
            openDropdown.close(false);
        }
    }, true);

    window.addEventListener('resize', function () {
        if (openDropdown) {
            openDropdown.close(false);
        }
    });
})();
