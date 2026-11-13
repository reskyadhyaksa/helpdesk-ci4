// Tombol panah geser: <button data-scroll-target="idBaris" data-scroll-dir="-1|1">
document.querySelectorAll('[data-scroll-target]').forEach(function (button) {
    button.addEventListener('click', function () {
        var row = document.getElementById(button.dataset.scrollTarget);

        row.scrollBy({ left: Number(button.dataset.scrollDir) * row.clientWidth, behavior: 'smooth' });
    });
});
