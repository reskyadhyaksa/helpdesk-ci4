// Halaman System Parameters (views/admin/settings/index.php): bilah simpan menunjukkan ada tidaknya perubahan,
// tombol Simpan baru aktif bila ada perubahan dan semua isian sah, Batalkan mengembalikan nilai tersimpan,
// dan tab kelompok yang berisi isian tidak sah diberi tanda merah (tabnya diatur js/components/header-tabs.js).
// Nilai "tersimpan" dibaca dari data-saved (bukan dari value), jadi isian lama yang gagal disimpan tetap terhitung berubah.
(function () {
    var form = document.querySelector('[data-settings-form]');

    if (! form) {
        return;
    }

    var controls = Array.prototype.slice.call(form.querySelectorAll('[data-saved]'));
    var save = form.querySelector('[data-settings-save]');
    var reset = form.querySelector('[data-settings-reset]');
    var bar = form.querySelector('[data-savebar]');
    var barText = form.querySelector('[data-savebar-text] span');
    var barIcon = form.querySelector('[data-savebar-text] i');

    function currentValue(control) {
        return control.type === 'checkbox' ? (control.checked ? '1' : '0') : control.value.trim();
    }

    function isValid(control) {
        if (control.type === 'number') {
            var value = control.value.trim();
            var number = Number(value);

            return /^\d+$/.test(value) && number >= Number(control.min) && number <= Number(control.max);
        }

        return control.type !== 'text' || control.value.trim() !== '';
    }

    function update() {
        var changed = 0;
        var invalid = 0;

        controls.forEach(function (control) {
            var ok = isValid(control);

            control.classList.toggle('is-danger', ! ok);
            control.setAttribute('aria-invalid', String(! ok));

            if (! ok) {
                invalid++;
            }

            if (currentValue(control) !== control.dataset.saved) {
                changed++;
            }
        });

        form.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
            var tab = document.querySelector('[data-tab="' + panel.dataset.tabPanel + '"]');

            if (tab) {
                tab.classList.toggle('has-error', panel.querySelector('.is-danger') !== null);
            }
        });

        bar.classList.toggle('is-dirty', changed > 0 && invalid === 0);
        bar.classList.toggle('is-invalid', invalid > 0);
        save.disabled = changed === 0 || invalid > 0;
        reset.disabled = changed === 0;

        if (invalid > 0) {
            barIcon.className = 'fa-solid fa-circle-exclamation';
            barText.textContent = invalid + ' isian di luar rentang. Periksa angka yang ditandai merah.';
        } else if (changed > 0) {
            barIcon.className = 'fa-solid fa-pen';
            barText.textContent = changed + ' pengaturan diubah dan belum disimpan.';
        } else {
            barIcon.className = 'fa-solid fa-circle-check';
            barText.textContent = 'Belum ada perubahan.';
        }
    }

    form.addEventListener('input', update);
    form.addEventListener('change', update);

    reset.addEventListener('click', function () {
        controls.forEach(function (control) {
            if (control.type === 'checkbox') {
                control.checked = control.dataset.saved === '1';
            } else {
                control.value = control.dataset.saved;
            }
        });
        update();
    });

    update();
})();
