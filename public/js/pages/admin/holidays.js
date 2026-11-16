// Halaman Holiday, jadwal kerja mingguan: ringkasan dan durasi dihitung ulang tiap ada perubahan, hari libur
// menonaktifkan jamnya (jadi tidak ikut terkirim), dan form terkunci sampai tombol Edit ditekan supaya tidak
// terubah tanpa sengaja. Batal mengembalikan nilai tersimpan (dari server) dan mengunci kembali.
(function () {
    var form = document.querySelector('[data-off-days]');

    if (! form) {
        return;
    }

    var rows = Array.prototype.slice.call(form.querySelectorAll('[data-day]'));
    var summaryWork = form.querySelector('[data-summary-work]');
    var summaryHours = form.querySelector('[data-summary-hours]');
    var summaryOff = form.querySelector('[data-summary-off]');
    var summaryTotal = form.querySelector('[data-summary-total]');
    var onChange = function () {}; // diganti di bawah bila ada izin mengubah

    function toMinutes(value) {
        var parts = value.split(':');

        return Number(parts[0]) * 60 + Number(parts[1]);
    }

    function formatDuration(minutes) {
        var hours = Math.floor(minutes / 60);
        var rest = minutes % 60;

        if (hours === 0) {
            return rest + ' menit';
        }

        return rest === 0 ? hours + ' jam' : hours + ' jam ' + rest + ' menit';
    }

    // Hari berurutan dipadatkan: [1,2,3,4,5] → "Senin – Jumat", [1,3] → "Senin, Rabu"
    function dayLabel(selected) {
        var groups = [];

        selected.forEach(function (row) {
            var last = groups[groups.length - 1];

            if (last && Number(row.dataset.day) === Number(last[last.length - 1].dataset.day) + 1) {
                last.push(row);
            } else {
                groups.push([row]);
            }
        });

        return groups.map(function (group) {
            var first = group[0].dataset.dayName;
            var last = group[group.length - 1].dataset.dayName;

            if (group.length >= 3) {
                return first + ' – ' + last;
            }

            return group.length === 2 ? first + ', ' + last : first;
        }).join(', ');
    }

    function inputsOf(row) {
        var times = row.querySelectorAll('input[type="time"]');

        return { off: row.querySelector('input[type="checkbox"]'), start: times[0], end: times[1] };
    }

    function syncRow(row) {
        var fields = inputsOf(row);
        var duration = row.querySelector('[data-duration]');
        var isOff = fields.off.checked;

        row.classList.toggle('is-off', isOff);
        fields.start.disabled = isOff;
        fields.end.disabled = isOff;
        duration.classList.remove('is-invalid');

        if (isOff) {
            duration.textContent = '—';
            fields.start.removeAttribute('aria-invalid');
            fields.end.removeAttribute('aria-invalid');

            return;
        }

        var minutes = fields.start.value && fields.end.value ? toMinutes(fields.end.value) - toMinutes(fields.start.value) : 0;
        var isValid = minutes > 0;
        var fill = row.querySelector('[data-fill]');

        // Bar rentang pada skala 24 jam (1440 menit)
        fill.style.setProperty('--start', isValid ? (toMinutes(fields.start.value) / 1440 * 100) + '%' : '0%');
        fill.style.setProperty('--span', isValid ? (minutes / 1440 * 100) + '%' : '0%');
        fill.querySelector('[data-fill-text]').textContent = isValid ? fields.start.value + ' – ' + fields.end.value : '';

        duration.textContent = isValid ? formatDuration(minutes) : 'Jam selesai harus setelah jam mulai';
        duration.classList.toggle('is-invalid', ! isValid);
        fields.start.setAttribute('aria-invalid', String(! isValid));
        fields.end.setAttribute('aria-invalid', String(! isValid));
    }

    function syncSummary() {
        var workRows = rows.filter(function (row) {
            return ! inputsOf(row).off.checked;
        });
        var offRows = rows.filter(function (row) {
            return inputsOf(row).off.checked;
        });
        var ranges = [];
        var totalMinutes = 0;

        workRows.forEach(function (row) {
            var fields = inputsOf(row);
            var range = fields.start.value + ' – ' + fields.end.value;
            var minutes = fields.start.value && fields.end.value ? toMinutes(fields.end.value) - toMinutes(fields.start.value) : 0;

            totalMinutes += Math.max(minutes, 0);

            if (ranges.indexOf(range) === -1) {
                ranges.push(range);
            }
        });

        summaryTotal.textContent = totalMinutes > 0 ? formatDuration(totalMinutes) : '—';

        summaryWork.textContent = workRows.length > 0 ? dayLabel(workRows) + ' (' + workRows.length + ' hari)' : 'Tidak ada';
        summaryHours.textContent = ranges.length === 1 ? ranges[0] : (ranges.length > 1 ? 'Berbeda tiap hari' : '—');
        summaryOff.textContent = offRows.length > 0 ? dayLabel(offRows) : 'Tidak ada';
    }

    function syncAll() {
        rows.forEach(syncRow);
        syncSummary();
        onChange();
    }

    // Isi form saat ini sebagai satu teks, untuk mendeteksi perubahan yang belum disimpan
    function snapshot() {
        return rows.map(function (row) {
            var fields = inputsOf(row);

            return [fields.off.checked, fields.start.value, fields.end.value].join(',');
        }).join('|');
    }

    form.addEventListener('input', syncAll);
    form.addEventListener('change', syncAll);
    syncAll();

    var fieldset = form.querySelector('fieldset');
    var toggle = form.querySelector('[data-off-days-toggle]');
    var save = form.querySelector('[data-off-days-save]');
    var copy = form.querySelector('[data-copy-hours]');

    // Tanpa izin ubah tombol-tombol ini tidak ada: halaman tetap menampilkan jadwal saja
    if (! toggle || ! save) {
        return;
    }

    var icon = toggle.querySelector('i');
    var label = toggle.querySelector('[data-label]');
    var modeNote = form.querySelector('[data-mode-note]');
    var modeIcon = form.querySelector('[data-mode-icon]');
    var modeText = form.querySelector('[data-mode-text]');
    var savedState = snapshot();

    // Teks status: terkunci → mengedit → ada perubahan belum disimpan (semua teks tetap, tanpa data pengguna)
    function syncMode() {
        var editing = ! fieldset.disabled;
        var dirty = editing && snapshot() !== savedState;

        modeNote.classList.toggle('is-editing', editing && ! dirty);
        modeNote.classList.toggle('is-dirty', dirty);

        if (dirty) {
            modeIcon.className = 'fa-solid fa-triangle-exclamation';
            modeText.innerHTML = '<strong>Ada perubahan yang belum disimpan.</strong> Tekan <strong>Simpan Jadwal</strong> untuk menerapkannya, atau <strong>Batal</strong> untuk membuangnya.';
        } else if (editing) {
            modeIcon.className = 'fa-solid fa-pen';
            modeText.innerHTML = '<strong>Mode edit:</strong> klik <strong>Kerja</strong> atau <strong>Libur</strong> pada tiap hari, atur jamnya, lalu tekan <strong>Simpan Jadwal</strong>.';
        } else {
            modeIcon.className = 'fa-solid fa-lock';
            modeText.innerHTML = 'Jadwal terkunci. Tekan <strong>Edit</strong> di bawah untuk mengubahnya.';
        }
    }

    onChange = syncMode;

    function setEditing(isEditing) {
        fieldset.disabled = ! isEditing;
        save.disabled = ! isEditing;
        copy.hidden = ! isEditing;
        syncMode();

        icon.className = isEditing ? 'fa-solid fa-xmark' : 'fa-solid fa-pen';
        label.textContent = isEditing ? 'Batal' : 'Edit';
        toggle.setAttribute('aria-pressed', isEditing);
    }

    toggle.addEventListener('click', function () {
        var isEditing = ! fieldset.disabled;

        if (isEditing) {
            form.reset();
            syncAll();
        }

        setEditing(! isEditing);
    });

    // Jam hari kerja pertama disalin ke semua hari kerja lainnya
    copy.addEventListener('click', function () {
        var workRows = rows.filter(function (row) {
            return ! inputsOf(row).off.checked;
        });

        if (workRows.length === 0) {
            return;
        }

        var source = inputsOf(workRows[0]);

        workRows.forEach(function (row) {
            var fields = inputsOf(row);

            fields.start.value = source.start.value;
            fields.end.value = source.end.value;
        });

        syncAll();
    });
})();
