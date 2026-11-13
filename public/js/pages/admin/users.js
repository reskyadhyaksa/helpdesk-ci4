// User Management: tombol "Ubah Role" mengisi modal #roleModal dengan data pengguna di barisnya.
// Buka/tutup modal dan fokus ditangani app.js (data-modal-open / data-modal-close).
(function () {
    var modal = document.getElementById('roleModal');

    if (! modal) {
        return;
    }

    var form = modal.querySelector('[data-role-form]');
    var select = modal.querySelector('select[name="role_id"]');

    document.querySelectorAll('[data-role-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            form.action = button.dataset.roleAction;
            modal.querySelector('[data-role-name]').textContent = button.dataset.userName;
            modal.querySelector('[data-role-nik]').textContent = button.dataset.userNik;
            modal.querySelector('[data-role-initials]').textContent = button.dataset.userInitials;

            // change memperbarui tampilan dropdown kustom (js/components/select.js)
            select.value = button.dataset.roleId;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
})();
