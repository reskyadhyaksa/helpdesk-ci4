// Halaman Holiday: kartu hari off mingguan terkunci sampai tombol Edit ditekan, supaya tidak terubah tanpa sengaja.
// Batal mengembalikan centang ke nilai tersimpan (checked dari server) dan mengunci kembali.
(function () {
    var form = document.querySelector('[data-off-days]');

    if (! form) {
        return;
    }

    var fieldset = form.querySelector('fieldset');
    var toggle = form.querySelector('[data-off-days-toggle]');
    var save = form.querySelector('[data-off-days-save]');

    if (! toggle || ! save) {
        return;
    }

    var cards = form.querySelectorAll('.option-card');
    var icon = toggle.querySelector('i');
    var label = toggle.querySelector('[data-label]');

    function setEditing(isEditing) {
        fieldset.disabled = ! isEditing;
        save.disabled = ! isEditing;

        cards.forEach(function (card) {
            card.classList.toggle('is-locked', ! isEditing);
        });

        icon.className = isEditing ? 'fa-solid fa-xmark' : 'fa-solid fa-pen';
        label.textContent = isEditing ? 'Batal' : 'Edit';
        toggle.setAttribute('aria-pressed', isEditing);
    }

    toggle.addEventListener('click', function () {
        var isEditing = ! fieldset.disabled;

        if (isEditing) {
            form.reset();
        }

        setEditing(! isEditing);
    });
})();
