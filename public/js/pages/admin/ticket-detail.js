// Detail tiket admin: dropdown status, filter tab + urutan lini masa, pratinjau lampiran, dan form balas.
window.initTicketDetail = function (root) {
    // Dipanggil ulang tiap isi detail diganti (modal): listener lama di document dilepas lewat AbortController
    if (root._ticketDetailAbort) {
        root._ticketDetailAbort.abort();
    }

    var abort = new AbortController();
    var signal = abort.signal;

    root._ticketDetailAbort = abort;

    // --- Dropdown status (Bulma .dropdown + is-active) ---
    var dropdown = root.querySelector('[data-status-dropdown]');

    if (dropdown) {
        var trigger = dropdown.querySelector('button');

        var closeDropdown = function () {
            dropdown.classList.remove('is-active');
            trigger.setAttribute('aria-expanded', 'false');
        };

        trigger.addEventListener('click', function (event) {
            event.stopPropagation();

            var isOpen = dropdown.classList.toggle('is-active');

            trigger.setAttribute('aria-expanded', String(isOpen));

            if (isOpen) {
                dropdown.querySelector('.dropdown-item').focus();
            }
        });
        document.addEventListener('click', closeDropdown, { signal: signal });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && dropdown.classList.contains('is-active')) {
                // Esc menutup menu ini saja, bukan modal tempat menu berada
                event.stopPropagation();
                closeDropdown();
                trigger.focus();
            }
        // capture: dijalankan sebelum penutup modal di app.js, sehingga stopPropagation berlaku
        }, { capture: true, signal: signal });
    }

    // --- Tab filter + urutan lini masa ---
    var timeline = root.querySelector('[data-timeline]');
    var emptyNote = root.querySelector('[data-timeline-empty]');

    if (timeline) {
        var tabs = root.querySelectorAll('[data-filter]');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var filter = tab.dataset.filter;
                var shown = 0;

                tabs.forEach(function (other) {
                    var isActive = other === tab;

                    other.classList.toggle('is-active', isActive);
                    other.setAttribute('aria-pressed', String(isActive));
                });

                timeline.querySelectorAll('[data-kind]').forEach(function (entry) {
                    var visible = filter === 'all' || entry.dataset.kind === filter;

                    entry.hidden = ! visible;
                    shown += visible ? 1 : 0;
                });

                emptyNote.hidden = shown > 0;
            });
        });

        var sortButton = root.querySelector('[data-sort]');
        var sortLabel = root.querySelector('[data-sort-label]');
        var newestFirst = false;

        sortButton.addEventListener('click', function () {
            newestFirst = ! newestFirst;

            Array.prototype.slice.call(timeline.children).reverse().forEach(function (entry) {
                timeline.appendChild(entry);
            });
            sortLabel.textContent = newestFirst ? 'Terbaru Dahulu' : 'Terlama Dahulu';
            sortButton.setAttribute('aria-label', 'Urutkan: ' + sortLabel.textContent.toLowerCase());
        });
    }

    // --- Pratinjau lampiran (modal dibuka oleh app.js lewat data-modal-open) ---
    var previewModal = document.getElementById('attachmentPreview');

    if (previewModal) {
        var previewBody = previewModal.querySelector('[data-preview-body]');
        var previewTitle = previewModal.querySelector('.app-modal-title');
        var previewDownload = previewModal.querySelector('[data-preview-download]');

        root.querySelectorAll('[data-preview-url]').forEach(function (button) {
            button.addEventListener('click', function () {
                var type = button.dataset.previewType;
                var element = document.createElement(type === 'image' ? 'img' : 'iframe');

                element.src = button.dataset.previewUrl;
                element.className = 'preview-' + type;

                if (type === 'image') {
                    element.alt = button.dataset.previewName;
                } else {
                    element.title = button.dataset.previewName;
                }

                previewBody.replaceChildren(element);
                previewTitle.textContent = button.dataset.previewName;
                previewDownload.href = button.dataset.previewDownload;
            });
        });

        // Modal ditutup (oleh app.js): buang isinya supaya PDF berhenti dimuat. Cukup dipasang sekali per modal.
        if (! previewModal.dataset.observed) {
            previewModal.dataset.observed = '1';
            new MutationObserver(function () {
                if (! previewModal.classList.contains('is-active')) {
                    previewBody.replaceChildren();
                }
            }).observe(previewModal, { attributes: true, attributeFilter: ['class'] });
        }
    }

    // --- Form balas: editor visual (contenteditable). Tombol langsung memformat teks (tanpa sintaks mentah);
    // isinya dikirim sebagai HTML dan disaring ulang di server (CommentSanitizer). ---
    var reply = root.querySelector('[data-reply-form]');

    if (reply) {
        var hint = reply.querySelector('[data-reply-hint]');
        var editor = reply.querySelector('[data-editor]');
        var fallback = reply.querySelector('[data-fallback]');
        var toolbar = reply.querySelector('[data-toolbar]');
        var formatInput = reply.querySelector('[data-format]');
        var fileInput = reply.querySelector('input[type="file"]');
        var attachLabel = reply.querySelector('[data-attach-label]');
        var mentionables = JSON.parse(reply.dataset.mentionables || '[]');
        var mentionList = reply.querySelector('[data-mention-list]');
        var mentionMatches = [];
        var mentionIndex = 0;
        var placeholders = { PUBLIC: editor.dataset.placeholder, INTERNAL: 'Tulis catatan untuk tim Helpdesk…' };

        // Aktifkan editor; textarea hanya cadangan bila JS mati
        fallback.hidden = true;
        fallback.required = false;
        editor.hidden = false;
        toolbar.hidden = false;
        formatInput.value = 'html';
        document.execCommand('defaultParagraphSeparator', false, 'p');

        if (fallback.value.trim() !== '') {
            // Isian lama setelah validasi gagal: tampilkan sebagai teks (bukan HTML)
            editor.textContent = fallback.value;
        }

        var escapeHtml = function (text) {
            var div = document.createElement('div');

            div.textContent = text;

            return div.innerHTML;
        };

        // --- Perintah format ---
        var commands = {
            bold: function () { document.execCommand('bold'); },
            italic: function () { document.execCommand('italic'); },
            ul: function () { document.execCommand('insertUnorderedList'); },
            ol: function () { document.execCommand('insertOrderedList'); },
            code: function () {
                var selected = String(window.getSelection());

                document.execCommand('insertHTML', false, '<code>' + escapeHtml(selected || 'kode') + '</code>&nbsp;');
            },
            image: function () {
                fileInput.accept = 'image/*';
                fileInput.click();
            },
            mention: function () {
                document.execCommand('insertText', false, '@');
                openMentions();
            }
        };

        var states = { bold: 'bold', italic: 'italic', ul: 'insertUnorderedList', ol: 'insertOrderedList' };

        // Tombol tampak aktif sesuai format di posisi kursor
        var syncToolbar = function () {
            toolbar.querySelectorAll('[data-cmd]').forEach(function (button) {
                var command = states[button.dataset.cmd];

                if (command) {
                    button.setAttribute('aria-pressed', String(document.queryCommandState(command)));
                }
            });
        };

        document.addEventListener('selectionchange', function () {
            if (editor.contains(document.getSelection().anchorNode)) {
                syncToolbar();
            }
        }, { signal: signal });

        toolbar.querySelectorAll('[data-cmd]').forEach(function (button) {
            // mousedown dicegah supaya seleksi di editor tidak hilang saat tombol ditekan
            button.addEventListener('mousedown', function (event) {
                event.preventDefault();
            });
            button.addEventListener('click', function () {
                editor.focus();
                commands[button.dataset.cmd]();
                syncToolbar();
            });
        });

        // Tempel selalu sebagai teks polos: HTML dari aplikasi lain tidak ikut masuk
        editor.addEventListener('paste', function (event) {
            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        });

        // Editor kosong harus benar-benar kosong supaya placeholder (CSS :empty) tampil
        editor.addEventListener('input', function () {
            if (editor.textContent === '' && ! editor.querySelector('li, .mention')) {
                editor.innerHTML = '';
            }

            openMentions();
        });

        // --- @ mention: daftar saran di bawah editor; hasilnya <span class="mention" data-user-id> yang tidak bisa diedit ---
        // Pola listbox: fokus tetap di editor, opsi aktif diumumkan lewat aria-activedescendant
        editor.setAttribute('aria-controls', mentionList.id);
        editor.setAttribute('aria-expanded', 'false');

        var closeMentions = function () {
            mentionList.hidden = true;
            mentionMatches = [];
            editor.setAttribute('aria-expanded', 'false');
            editor.removeAttribute('aria-activedescendant');
        };

        var highlightMention = function () {
            mentionList.querySelectorAll('[role="option"]').forEach(function (item, i) {
                var isActive = i === mentionIndex;

                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-selected', String(isActive));

                if (isActive) {
                    editor.setAttribute('aria-activedescendant', item.id);
                    item.scrollIntoView({ block: 'nearest' });
                }
            });
        };

        // Bagian nama yang cocok dengan ketikan ditebalkan, sisanya normal
        var highlightedName = function (name, query) {
            var label = document.createElement('span');
            var at = query === '' ? -1 : name.toLowerCase().indexOf(query);

            label.className = 'mention-name';

            if (at === -1) {
                label.textContent = name;

                return label;
            }

            var match = document.createElement('b');

            match.textContent = name.slice(at, at + query.length);
            label.append(name.slice(0, at), match, name.slice(at + query.length));

            return label;
        };

        var mentionNote = function (className, text) {
            var item = document.createElement('li');

            item.setAttribute('role', 'presentation');
            item.className = className;
            item.textContent = text;

            return item;
        };

        // Teks "@kata" tepat sebelum kursor di node teks yang sedang diedit, atau null
        var mentionTrigger = function () {
            var selection = window.getSelection();

            if (mentionables.length === 0 || selection.rangeCount === 0 || selection.anchorNode.nodeType !== Node.TEXT_NODE || ! editor.contains(selection.anchorNode)) {
                return null;
            }

            var before = selection.anchorNode.data.slice(0, selection.anchorOffset);
            var match = /(^|\s)@([^\s@]{0,30})$/.exec(before);

            return match ? { node: selection.anchorNode, end: selection.anchorOffset, query: match[2] } : null;
        };

        var chooseMention = function (person) {
            var trigger = mentionTrigger();

            if (! trigger) {
                return;
            }

            var range = document.createRange();
            var chip = document.createElement('span');
            var space = document.createTextNode(' ');

            // Ganti "@kata" dengan penanda mention + spasi, lalu kursor di belakangnya
            range.setStart(trigger.node, trigger.end - trigger.query.length - 1);
            range.setEnd(trigger.node, trigger.end);
            range.deleteContents();

            chip.className = 'mention';
            chip.dataset.userId = person.id;
            chip.contentEditable = 'false';
            chip.textContent = '@' + person.name;

            range.insertNode(space);
            range.insertNode(chip);
            range.setStartAfter(space);
            range.collapse(true);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);

            closeMentions();
        };

        var openMentions = function () {
            var trigger = mentionTrigger();

            if (! trigger) {
                closeMentions();

                return;
            }

            var query = trigger.query.toLowerCase();

            mentionMatches = mentionables.filter(function (person) {
                return person.name.toLowerCase().indexOf(query) !== -1 || person.nip.indexOf(query) === 0;
            }).slice(0, 6);

            mentionList.replaceChildren(mentionNote('mention-head', 'Sebut rekan tim'));

            mentionMatches.forEach(function (person, i) {
                var item = document.createElement('li');
                var avatar = document.createElement('span');
                var text = document.createElement('span');
                var nip = document.createElement('small');

                item.id = mentionList.id + '-' + i;
                item.className = 'mention-option';
                item.setAttribute('role', 'option');
                avatar.className = 'ticket-avatar is-sm';
                avatar.setAttribute('aria-hidden', 'true');
                avatar.textContent = person.name.slice(0, 2).toUpperCase();
                text.className = 'mention-text';
                nip.textContent = 'NIK ' + person.nip;
                text.append(highlightedName(person.name, query), nip);
                item.append(avatar, text);
                // mousedown (bukan click) supaya editor tidak kehilangan fokus sebelum pilihan dibuat
                item.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    chooseMention(person);
                });
                item.addEventListener('mousemove', function () {
                    if (mentionIndex !== i) {
                        mentionIndex = i;
                        highlightMention();
                    }
                });
                mentionList.appendChild(item);
            });

            if (mentionMatches.length === 0) {
                mentionList.appendChild(mentionNote('mention-empty', 'Tidak ada rekan tim yang cocok'));
            } else {
                mentionList.appendChild(mentionNote('mention-foot', '↑↓ pilih · Enter sisipkan · Esc tutup'));
            }

            mentionIndex = 0;
            mentionList.hidden = false;
            editor.setAttribute('aria-expanded', 'true');
            highlightMention();
            positionMentions(trigger);
        };

        // Daftar muncul tepat di bawah "@kata" yang sedang diketik, bukan di bawah seluruh editor
        var positionMentions = function (trigger) {
            var range = document.createRange();

            range.setStart(trigger.node, trigger.end - trigger.query.length - 1);
            range.setEnd(trigger.node, trigger.end);

            var caret = range.getBoundingClientRect();
            var box = editor.parentElement.getBoundingClientRect();
            // Editor ada di dalam elemen ber-zoom: koordinat layar dibagi skala agar cocok dengan koordinat absolut
            var scale = editor.offsetWidth > 0 ? box.width / editor.offsetWidth : 1;
            var left = (caret.left - box.left) / scale;
            var top = (caret.bottom - box.top) / scale + 4;

            mentionList.style.left = Math.max(0, Math.min(left, editor.offsetWidth - mentionList.offsetWidth)) + 'px';
            mentionList.style.top = top + 'px';
        };

        editor.addEventListener('blur', closeMentions);
        editor.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                reply.requestSubmit();

                return;
            }

            if (mentionList.hidden) {
                return;
            }

            // Tidak ada hasil: hanya Esc yang ditangani, tombol lain bekerja seperti biasa di editor
            if (mentionMatches.length === 0) {
                if (event.key === 'Escape') {
                    event.stopPropagation();
                    closeMentions();
                }

                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                mentionIndex = (mentionIndex + (event.key === 'ArrowDown' ? 1 : -1) + mentionMatches.length) % mentionMatches.length;
                highlightMention();
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                chooseMention(mentionMatches[mentionIndex]);
            } else if (event.key === 'Escape') {
                event.stopPropagation();
                closeMentions();
            }
        });

        // --- Jenis balasan, lampiran, kirim, batal ---
        reply.querySelectorAll('input[name="visibility"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                hint.textContent = radio.dataset.hint;
                editor.dataset.placeholder = placeholders[radio.value];
            });
        });

        // Tombol Lampiran biasa membuka semua jenis; tombol gambar membatasi ke gambar
        attachLabel.closest('label').addEventListener('click', function (event) {
            // Klik yang dikirim ke input itu sendiri (dari tombol gambar atau label) tidak boleh menghapus batasan
            if (event.target !== fileInput) {
                fileInput.removeAttribute('accept');
            }
        });

        fileInput.addEventListener('change', function () {
            var count = fileInput.files.length;

            attachLabel.textContent = count === 0 ? 'Lampiran' : (count === 1 ? fileInput.files[0].name : count + ' berkas');
        });

        // HTML editor dipindah ke field "body" yang dikirim; server menyaringnya lagi
        reply.addEventListener('submit', function () {
            fallback.value = editor.innerHTML;
        });

        reply.addEventListener('reset', function () {
            editor.innerHTML = '';
            fallback.value = '';
            attachLabel.textContent = 'Lampiran';
            closeMentions();
        });
    }
};

window.initTicketDetail(document);
