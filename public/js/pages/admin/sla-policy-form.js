// Form SLA: input jam + menit dijumlahkan ke menit dan dikirim lewat input hidden [data-duration-total].
// Jam saja (menit kosong) otomatis dikonversi: 8 jam → 480 menit.
(function () {
    function toNumber(input) {
        return parseInt(input.value, 10) || 0;
    }

    document.querySelectorAll('[data-duration]').forEach(function (group) {
        var hours = group.querySelector('[data-duration-hours]');
        var minutes = group.querySelector('[data-duration-minutes]');
        var total = group.querySelector('[data-duration-total]');
        var preview = group.querySelector('[data-duration-preview]');

        function update() {
            var sum = toNumber(hours) * 60 + toNumber(minutes);

            // Kosong (bukan "0") agar validasi "wajib diisi" di server tetap berlaku
            total.value = sum > 0 ? sum : '';
            preview.textContent = sum + ' menit';
        }

        hours.addEventListener('input', update);
        minutes.addEventListener('input', update);
    });
})();
