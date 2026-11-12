document.querySelectorAll('.navbar-burger').forEach(function (burger) {
    burger.addEventListener('click', function () {
        var menu = document.getElementById(burger.dataset.target);
        var isOpen = burger.classList.toggle('is-active');

        menu.classList.toggle('is-active', isOpen);
        burger.setAttribute('aria-expanded', isOpen);
    });
});
