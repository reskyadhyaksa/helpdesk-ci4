// Burger menu navbar (mobile)
document.querySelectorAll('.navbar-burger').forEach(function (burger) {
    burger.addEventListener('click', function () {
        var menu = document.getElementById(burger.dataset.target);
        var isOpen = burger.classList.toggle('is-active');

        menu.classList.toggle('is-active', isOpen);
        burger.setAttribute('aria-expanded', isOpen);
    });
});

// Esc menutup menu burger dan mengembalikan fokus ke tombolnya
document.addEventListener('keydown', function (event) {
    var burger = document.querySelector('.navbar-burger.is-active');

    if (event.key !== 'Escape' || ! burger) {
        return;
    }

    burger.click();
    burger.focus();
});

// Modal Bulma: <button data-modal-open="idModal"> membuka, [data-modal-close] atau Esc menutup
document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-modal-open]');

    if (opener) {
        document.getElementById(opener.dataset.modalOpen).classList.add('is-active');
        return;
    }

    if (event.target.closest('[data-modal-close]')) {
        event.target.closest('.modal').classList.remove('is-active');
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal.is-active').forEach(function (modal) {
            modal.classList.remove('is-active');
        });
    }
});

// Tombol panah geser: <button data-scroll-target="idBaris" data-scroll-dir="-1|1">
document.querySelectorAll('[data-scroll-target]').forEach(function (button) {
    button.addEventListener('click', function () {
        var row = document.getElementById(button.dataset.scrollTarget);

        row.scrollBy({ left: Number(button.dataset.scrollDir) * row.clientWidth, behavior: 'smooth' });
    });
});
