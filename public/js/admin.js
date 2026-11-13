// Sidebar admin (desktop) bisa diperkecil jadi ikon saja. Pilihan disimpan per browser.
// Tombol panah memperkecil; saat diperkecil, klik logo untuk membuka kembali.
// Dimuat di <head>: class dipasang sebelum halaman tampil agar sidebar tidak berkedip.
(function () {
    var STORAGE_KEY = 'adminSidebarCollapsed';
    var COLLAPSED_CLASS = 'is-sidebar-collapsed';
    var root = document.documentElement;

    function isSavedCollapsed() {
        try {
            return localStorage.getItem(STORAGE_KEY) === '1';
        } catch (error) {
            return false;
        }
    }

    function saveCollapsed(collapsed) {
        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        } catch (error) {
            // Penyimpanan diblokir (mode privat): sidebar tetap berfungsi, hanya tidak diingat.
        }
    }

    root.classList.toggle(COLLAPSED_CLASS, isSavedCollapsed());

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.querySelector('[data-sidebar-toggle]');
        var brand = document.querySelector('[data-sidebar-brand]');

        if (! toggle || ! brand) {
            return;
        }

        var brandTitle = brand.title;
        var expandLabel = toggle.dataset.labelExpand;

        // Saat diperkecil, logo berperan sebagai tombol "Perluas sidebar"
        function updateBrand(collapsed) {
            brand.title = collapsed ? expandLabel : brandTitle;

            if (collapsed) {
                brand.setAttribute('aria-label', expandLabel);
                brand.setAttribute('role', 'button');
            } else {
                brand.removeAttribute('aria-label');
                brand.removeAttribute('role');
            }
        }

        function setCollapsed(collapsed) {
            root.classList.toggle(COLLAPSED_CLASS, collapsed);
            saveCollapsed(collapsed);
            updateBrand(collapsed);
        }

        updateBrand(root.classList.contains(COLLAPSED_CLASS));

        toggle.addEventListener('click', function () {
            setCollapsed(true);
            brand.focus();
        });

        brand.addEventListener('click', function (event) {
            if (! root.classList.contains(COLLAPSED_CLASS)) {
                return;
            }

            event.preventDefault();
            setCollapsed(false);
            toggle.focus();
        });
    });
})();
