// Kartu hover generik: <button data-popover="ID"> menampilkan <div id="ID" class="hover-card" hidden> saat disorot atau
// difokus (keyboard), dan menyembunyikannya saat pointer/fokus pergi, halaman digulir, atau Esc ditekan. Kartu memakai
// position: fixed, jadi tidak terpotong tabel/kartu ber-overflow; membuka ke bawah, atau ke atas bila ruang bawah kurang.
// Memakai delegasi event, jadi tetap bekerja pada isi tabel yang diganti lewat fetch.
(function () {
    var GAP = 6;
    var MARGIN = 8;
    var active = null;

    function hide() {
        if (active) {
            active.hidden = true;
            active = null;
        }
    }

    function show(trigger) {
        var card = document.getElementById(trigger.dataset.popover);

        if (! card || card === active) {
            return;
        }

        hide();
        card.hidden = false;

        var rect = trigger.getBoundingClientRect();
        var left = Math.min(rect.left, window.innerWidth - card.offsetWidth - MARGIN);
        var below = rect.bottom + GAP;
        var top = below + card.offsetHeight > window.innerHeight - MARGIN ? rect.top - card.offsetHeight - GAP : below;

        card.style.left = Math.max(MARGIN, left) + 'px';
        card.style.top = Math.max(MARGIN, top) + 'px';
        active = card;
    }

    function triggerOf(target) {
        return target.closest ? target.closest('[data-popover]') : null;
    }

    document.addEventListener('mouseover', function (event) {
        var trigger = triggerOf(event.target);

        if (trigger) {
            show(trigger);
        }
    });

    document.addEventListener('mouseout', function (event) {
        var trigger = triggerOf(event.target);

        if (trigger && ! trigger.contains(event.relatedTarget)) {
            hide();
        }
    });

    document.addEventListener('focusin', function (event) {
        var trigger = triggerOf(event.target);

        if (trigger) {
            show(trigger);
        }
    });

    document.addEventListener('focusout', function (event) {
        if (triggerOf(event.target)) {
            hide();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hide();
        }
    });

    window.addEventListener('scroll', hide, true);
})();
