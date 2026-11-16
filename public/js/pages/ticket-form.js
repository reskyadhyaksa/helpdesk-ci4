// Form tiket (views/tickets/_form.php): kategori utama → sub kategori, info SLA, drop lampiran, dan persetujuan.
// Dropdown kustomnya dibuat js/components/select.js; di sini isi sub kategori diganti lewat event "options-changed".
(function () {
    var form = document.querySelector('[data-ticket-form]');

    if (! form) {
        return;
    }

    var leaves = JSON.parse(form.dataset.categories);
    var slaText = JSON.parse(form.dataset.slaText);
    var pillarSelect = form.querySelector('#pillar_id');
    var subSelect = form.querySelector('#category_id');
    var subPlaceholder = subSelect.options[0].text;
    var slaBar = form.querySelector('[data-sla-bar]');
    var slaMessage = form.querySelector('[data-sla-message]');
    var slaTag = form.querySelector('[data-sla-tag]');

    function leafById(id) {
        return leaves.filter(function (leaf) {
            return leaf.id === id;
        })[0] || null;
    }

    function showSla() {
        var leaf = leafById(subSelect.value);
        var sla = leaf ? leaf.sla : null;

        slaBar.classList.toggle('is-ready', sla !== null);

        if (! leaf) {
            slaMessage.textContent = slaText.empty;
            slaTag.textContent = slaText.unavailable;
        } else if (! sla) {
            slaMessage.textContent = slaText.none;
            slaTag.textContent = slaText.unavailable;
        } else {
            slaMessage.textContent = slaText.response + ' ≤ ' + sla.response + ' • ' + slaText.resolution + ' ≤ ' + sla.resolution;
            slaTag.textContent = sla.priority;
        }
    }

    // Kategori utama tanpa sub menjadi tujuan tiket sendiri, jadi langsung terpilih
    function fillSubOptions() {
        var pillarId = pillarSelect.value;
        var matches = leaves.filter(function (leaf) {
            return leaf.pillar === pillarId;
        });

        subSelect.innerHTML = '';
        subSelect.add(new Option(subPlaceholder, ''));
        matches.forEach(function (leaf) {
            subSelect.add(new Option(leaf.name, leaf.id));
        });

        if (matches.length === 1 && matches[0].id === pillarId) {
            subSelect.value = pillarId;
        }

        subSelect.disabled = matches.length === 0;
        subSelect.dispatchEvent(new Event('options-changed'));
        showSla();
    }

    pillarSelect.addEventListener('change', fillSubOptions);
    subSelect.addEventListener('change', showSla);
    showSla();

    // Lampiran: file yang dipilih atau di-drop digabung ke satu daftar, lalu dikembalikan ke input
    var zone = form.querySelector('[data-dropzone]');
    var input = form.querySelector('#attachments');
    var fileList = form.querySelector('[data-file-list]');
    var removeLabel = fileList.dataset.removeLabel;
    var chosenFiles = [];

    function formatSize(bytes) {
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function syncInput() {
        var transfer = new DataTransfer();

        chosenFiles.forEach(function (file) {
            transfer.items.add(file);
        });
        input.files = transfer.files;
    }

    function renderFiles() {
        fileList.textContent = '';

        chosenFiles.forEach(function (file, index) {
            var item = document.createElement('li');
            var name = document.createElement('span');
            var size = document.createElement('span');
            var remove = document.createElement('button');

            item.className = 'ticket-file';
            name.className = 'ticket-file-name';
            name.textContent = file.name;
            size.className = 'ticket-file-size';
            size.textContent = formatSize(file.size);

            remove.type = 'button';
            remove.className = 'button is-small is-light';
            remove.setAttribute('aria-label', removeLabel.replace('{0}', file.name));
            remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
            remove.addEventListener('click', function () {
                chosenFiles.splice(index, 1);
                syncInput();
                renderFiles();
            });

            item.append(name, size, remove);
            fileList.appendChild(item);
        });
    }

    function addFiles(files) {
        Array.prototype.forEach.call(files, function (file) {
            var isDuplicate = chosenFiles.some(function (known) {
                return known.name === file.name && known.size === file.size;
            });

            if (! isDuplicate) {
                chosenFiles.push(file);
            }
        });

        syncInput();
        renderFiles();
    }

    input.addEventListener('change', function () {
        addFiles(input.files);
    });

    ['dragenter', 'dragover'].forEach(function (type) {
        zone.addEventListener(type, function (event) {
            event.preventDefault();
            zone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (type) {
        zone.addEventListener(type, function () {
            zone.classList.remove('is-dragover');
        });
    });

    zone.addEventListener('drop', function (event) {
        event.preventDefault();
        addFiles(event.dataTransfer.files);
    });

    // Tombol kirim baru aktif setelah pernyataan dicentang
    var agreement = form.querySelector('[data-agreement]');
    var submit = form.querySelector('[data-submit]');

    function syncSubmit() {
        submit.disabled = ! agreement.checked;
    }

    agreement.addEventListener('change', syncSubmit);
    syncSubmit();
})();
