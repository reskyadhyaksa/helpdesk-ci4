// Form Request Category: tabel akses (job title, role, pengecualian user).
// - Baris ditambah/dihapus di browser dan dikirim saat form disimpan lewat input tersembunyi:
//   job_titles[], role_ids[], user_access[id user] = allow|deny.
// - Job title dan role hanya untuk kategori utama: saat induk dipilih barisnya disembunyikan dan inputnya
//   dinonaktifkan (tidak terkirim); hanya pengecualian user yang tersisa.
(function () {
    var parent = document.querySelector('select[name="parent_id"]');
    var section = document.querySelector('[data-access]');

    if (! parent || ! section) {
        return;
    }

    var RULE_TYPES = ['job_title', 'role'];
    var ICONS = { job_title: 'fa-id-badge', role: 'fa-user-shield', user: 'fa-user' };
    var LABELS = {
        job_title: section.dataset.labelJobTitle,
        role: section.dataset.labelRole,
        user: section.dataset.labelUser,
    };

    var body = section.querySelector('[data-access-body]');
    var emptyRow = section.querySelector('[data-access-empty]');
    var typeRadios = section.querySelectorAll('[data-access-type]');
    var effects = section.querySelector('[data-access-effects]');
    var addButton = section.querySelector('[data-access-add]');
    var errorBox = section.querySelector('[data-access-error]');
    var status = section.querySelector('[data-access-status]');
    var pickers = {};

    section.querySelectorAll('[data-access-picker]').forEach(function (picker) {
        pickers[picker.dataset.accessPicker] = picker;
        // Pilihan di baris tambah hanya alat bantu: tidak boleh ikut terkirim
        picker.querySelector('select').removeAttribute('name');
    });

    function create(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== undefined) {
            node.textContent = text;
        }

        return node;
    }

    function icon(name, extraClass) {
        var wrapper = create('span', 'icon' + (extraClass ? ' ' + extraClass : ''));
        var glyph = create('i', 'fa-solid ' + name);

        glyph.setAttribute('aria-hidden', 'true');
        wrapper.appendChild(glyph);

        return wrapper;
    }

    function format(template, name) {
        return template.replace(':name', name);
    }

    function announce(template, name) {
        status.textContent = format(template, name);
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('is-hidden');
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.classList.add('is-hidden');
    }

    function activeType() {
        var checked = section.querySelector('[data-access-type]:checked');

        return checked ? checked.value : 'job_title';
    }

    function findRow(type, value) {
        return Array.prototype.find.call(body.querySelectorAll('[data-access-row]'), function (row) {
            return row.dataset.type === type && row.dataset.value === value;
        });
    }

    function updateEmpty() {
        var hasRows = Array.prototype.some.call(body.querySelectorAll('[data-access-row]'), function (row) {
            return ! row.hidden;
        });

        emptyRow.hidden = hasRows;
    }

    function setRight(badge, effect) {
        var isDeny = effect === 'deny';

        badge.className = 'tag is-light is-rounded ' + (isDeny ? 'is-danger' : 'is-success');
        badge.textContent = isDeny ? section.dataset.labelDeny : section.dataset.labelAllow;
    }

    // Struktur sama dengan views/admin/request_categories/_access_row.php
    function buildRow(type, value, label, detail, effect) {
        var row = create('tr');
        var typeCell = create('td');
        var nameCell = create('td');
        var rightCell = create('td');
        var actionCell = create('td', 'has-text-right');
        var tag = create('span', 'tag is-light is-rounded access-tag is-' + type);
        var input = create('input');
        var badge = create('span');
        var remove = create('button', 'button is-small is-light access-remove');

        row.setAttribute('data-access-row', '');
        row.dataset.type = type;
        row.dataset.value = value;

        tag.appendChild(icon(ICONS[type], 'is-small'));
        tag.appendChild(create('span', '', LABELS[type]));
        typeCell.appendChild(tag);

        nameCell.appendChild(create('strong', 'is-block', label));

        if (detail) {
            nameCell.appendChild(create('small', '', 'NIK ' + detail));
        }

        input.type = 'hidden';
        input.setAttribute('data-access-input', '');
        input.name = type === 'job_title' ? 'job_titles[]' : (type === 'role' ? 'role_ids[]' : 'user_access[' + value + ']');
        input.value = type === 'user' ? effect : value;
        nameCell.appendChild(input);

        badge.setAttribute('data-access-right', '');
        setRight(badge, type === 'user' ? effect : 'allow');
        rightCell.appendChild(badge);

        remove.type = 'button';
        remove.setAttribute('data-access-remove', '');
        remove.setAttribute('aria-label', format(section.dataset.msgRemoveLabel, label));
        remove.appendChild(icon('fa-trash'));
        actionCell.appendChild(remove);

        [typeCell, nameCell, rightCell, actionCell].forEach(function (cell) {
            row.appendChild(cell);
        });

        return row;
    }

    function resetPicker(select) {
        select.value = '';
        // Dropdown kustom ikut diperbarui lewat event change pada <select> aslinya
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function addEntry() {
        clearError();

        var type = activeType();
        var select = pickers[type] ? pickers[type].querySelector('select') : null;

        if (! select || select.value === '') {
            showError(section.dataset.msgChoose);

            return;
        }

        var value = select.value;
        var label = select.options[select.selectedIndex].text;
        var effect = type === 'user' ? section.querySelector('[name="access_effect"]:checked').value : null;
        var existing = findRow(type, value);

        if (existing && type !== 'user') {
            showError(section.dataset.msgDuplicate);

            return;
        }

        if (existing) {
            // User yang sudah ada: hanya efeknya yang diganti
            existing.querySelector('[data-access-input]').value = effect;
            setRight(existing.querySelector('[data-access-right]'), effect);
        } else {
            var detail = null;
            var match = type === 'user' ? label.match(/^(.*) \(([^()]*)\)$/) : null;

            if (match) {
                label = match[1];
                detail = match[2];
            }

            body.appendChild(buildRow(type, value, label, detail, effect));
        }

        announce(section.dataset.msgAdded, label);
        updateEmpty();
        resetPicker(select);
    }

    function removeEntry(row) {
        var label = row.querySelector('strong').textContent;

        row.remove();
        announce(section.dataset.msgRemoved, label);
        updateEmpty();
    }

    function showPicker() {
        var type = activeType();

        Object.keys(pickers).forEach(function (key) {
            pickers[key].classList.toggle('is-hidden', key !== type);
        });

        if (effects) {
            effects.classList.toggle('is-hidden', type !== 'user');
        }

        clearError();
    }

    function updateParent() {
        var isSub = parent.value !== '';

        section.classList.toggle('is-sub', isSub);
        section.querySelector('[data-access-hint]').classList.toggle('is-hidden', isSub);
        section.querySelector('[data-access-sub-hint]').classList.toggle('is-hidden', ! isSub);

        body.querySelectorAll('[data-access-row]').forEach(function (row) {
            if (RULE_TYPES.indexOf(row.dataset.type) !== -1) {
                row.hidden = isSub;
                row.querySelectorAll('input').forEach(function (input) {
                    input.disabled = isSub;
                });
            }
        });

        typeRadios.forEach(function (radio) {
            if (RULE_TYPES.indexOf(radio.value) !== -1) {
                radio.disabled = isSub;
                radio.closest('label').classList.toggle('is-disabled', isSub);
            }
        });

        if (isSub && RULE_TYPES.indexOf(activeType()) !== -1) {
            var userRadio = section.querySelector('[data-access-type][value="user"]');

            if (userRadio && ! userRadio.disabled) {
                userRadio.checked = true;
            }
        }

        // Tanpa tipe yang tersedia (sub kategori dan tanpa izin kelola user) tombol tambah dinonaktifkan
        addButton.disabled = ! Array.prototype.some.call(typeRadios, function (radio) {
            return ! radio.disabled;
        });

        showPicker();
        updateEmpty();
    }

    typeRadios.forEach(function (radio) {
        radio.addEventListener('change', showPicker);
    });

    addButton.addEventListener('click', addEntry);

    body.addEventListener('click', function (event) {
        var button = event.target.closest('[data-access-remove]');

        if (button) {
            removeEntry(button.closest('[data-access-row]'));
        }
    });

    // Dropdown kustom memicu event change (bubbling) pada <select> aslinya
    parent.addEventListener('change', updateParent);
    updateParent();
})();
