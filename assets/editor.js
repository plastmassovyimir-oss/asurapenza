(function () {
    var editor = document.getElementById('editor');
    if (!editor) return;

    var form = document.getElementById('article-form');
    var field = document.getElementById('body-field');
    var toolbar = document.querySelector('.editor-toolbar');
    var imageInput = document.getElementById('image-input');
    var galleryInput = document.getElementById('gallery-input');
    var statusEl = document.getElementById('editor-status');
    var csrf = form.querySelector('input[name="csrf"]').value;
    var savedRange = null;
    var dirty = false;
    var galleryTarget = null;

    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function setStatus(text, autoHide) {
        statusEl.textContent = text || '';
        if (autoHide) {
            setTimeout(function () {
                if (statusEl.textContent === text) statusEl.textContent = '';
            }, 3500);
        }
    }

    function syncField() {
        field.value = editor.innerHTML.trim() === '<br>' ? '' : editor.innerHTML;
    }

    // Галереи в редакторе не редактируются как текст, только через двойной щелчок
    function lockGalleries() {
        editor.querySelectorAll('.news-gallery').forEach(function (g) {
            g.setAttribute('contenteditable', 'false');
        });
    }

    /* История изменений: отмена и повтор */
    var undoStack = [];
    var undoIndex = -1;
    var historyTimer = null;
    var HISTORY_LIMIT = 200;

    function getCaret() {
        var sel = window.getSelection();
        if (!sel.rangeCount) return null;
        var range = sel.getRangeAt(0);
        if (!editor.contains(range.startContainer)) return null;
        var pre = document.createRange();
        pre.selectNodeContents(editor);
        pre.setEnd(range.startContainer, range.startOffset);
        return pre.toString().length;
    }

    function setCaret(pos) {
        editor.focus();
        var sel = window.getSelection();
        var range = document.createRange();
        var placed = false;
        if (pos !== null && pos !== undefined) {
            var walker = document.createTreeWalker(editor, NodeFilter.SHOW_TEXT, null);
            var node;
            var left = pos;
            while ((node = walker.nextNode())) {
                if (left <= node.length) {
                    range.setStart(node, left);
                    placed = true;
                    break;
                }
                left -= node.length;
            }
        }
        if (!placed) range.selectNodeContents(editor);
        range.collapse(placed);
        sel.removeAllRanges();
        sel.addRange(range);
        savedRange = range.cloneRange();
    }

    function updateHistoryButtons() {
        var undoBtn = toolbar.querySelector('[data-cmd="undo"]');
        var redoBtn = toolbar.querySelector('[data-cmd="redo"]');
        if (undoBtn) undoBtn.disabled = !(undoIndex > 0 || historyTimer);
        if (redoBtn) redoBtn.disabled = !(undoIndex < undoStack.length - 1) || !!historyTimer;
    }

    function record() {
        clearTimeout(historyTimer);
        historyTimer = null;
        var html = editor.innerHTML;
        var caret = getCaret();
        var top = undoStack[undoIndex];
        if (top && top.html === html) {
            top.caret = caret;
            updateHistoryButtons();
            return;
        }
        undoStack = undoStack.slice(0, undoIndex + 1);
        undoStack.push({ html: html, caret: caret });
        if (undoStack.length > HISTORY_LIMIT) undoStack.shift();
        undoIndex = undoStack.length - 1;
        updateHistoryButtons();
    }

    function scheduleRecord() {
        clearTimeout(historyTimer);
        historyTimer = setTimeout(record, 500);
        updateHistoryButtons();
    }

    function flushRecord() {
        if (historyTimer) record();
    }

    function applyState(state) {
        editor.innerHTML = state.html;
        lockGalleries();
        setCaret(state.caret);
        syncField();
        dirty = true;
        updateToolbar();
        updateHistoryButtons();
    }

    function undo() {
        flushRecord();
        if (undoIndex <= 0) return;
        undoIndex--;
        applyState(undoStack[undoIndex]);
        setStatus('Действие отменено.', true);
    }

    function redo() {
        flushRecord();
        if (undoIndex >= undoStack.length - 1) return;
        undoIndex++;
        applyState(undoStack[undoIndex]);
        setStatus('Действие повторено.', true);
    }

    function commit() {
        lockGalleries();
        syncField();
        dirty = true;
        record();
    }

    function saveSelection() {
        var sel = window.getSelection();
        if (sel.rangeCount && editor.contains(sel.getRangeAt(0).commonAncestorContainer)) {
            savedRange = sel.getRangeAt(0).cloneRange();
        }
    }

    function restoreSelection() {
        editor.focus();
        var sel = window.getSelection();
        sel.removeAllRanges();
        if (savedRange) {
            sel.addRange(savedRange);
        } else {
            var range = document.createRange();
            range.selectNodeContents(editor);
            range.collapse(false);
            sel.addRange(range);
        }
    }

    function closestTag(name) {
        var sel = window.getSelection();
        if (!sel.rangeCount || !editor.contains(sel.anchorNode)) return null;
        var node = sel.anchorNode;
        while (node && node !== editor) {
            if (node.nodeName === name) return node;
            node = node.parentNode;
        }
        return null;
    }

    function closestLink() {
        return closestTag('A');
    }

    function setTarget(a, newTab) {
        if (newTab) a.setAttribute('target', '_blank');
        else a.removeAttribute('target');
    }

    function toggleBlock(tag) {
        var current = (document.queryCommandValue('formatBlock') || '').toLowerCase();
        document.execCommand('formatBlock', false, current === tag ? '<p>' : '<' + tag + '>');
    }

    // Абзац верхнего уровня, в котором стоит курсор
    function topBlock(node) {
        if (!node || node === editor || !editor.contains(node)) return null;
        while (node.parentNode !== editor) node = node.parentNode;
        return node;
    }

    // Вставка цитаты, фото, галереи или iframe отдельным блоком после текущего абзаца
    function insertBlocks(html) {
        flushRecord();
        restoreSelection();
        var sel = window.getSelection();
        var range = sel.rangeCount ? sel.getRangeAt(0) : null;
        var block = range ? topBlock(range.startContainer) : null;
        if (!block && range && range.startContainer === editor && range.startOffset > 0) {
            block = editor.childNodes[range.startOffset - 1] || null;
        }
        var tpl = document.createElement('template');
        tpl.innerHTML = html;
        var nodes = Array.prototype.slice.call(tpl.content.childNodes);
        if (block) {
            editor.insertBefore(tpl.content, block.nextSibling);
            var empty = !block.textContent.trim() && !(block.nodeType === 1 && block.querySelector('img, iframe'));
            if (empty) editor.removeChild(block);
        } else {
            editor.appendChild(tpl.content);
        }
        var last = nodes[nodes.length - 1];
        if (last) {
            var caret = document.createRange();
            caret.selectNodeContents(last);
            caret.collapse(true);
            sel.removeAllRanges();
            sel.addRange(caret);
            savedRange = caret.cloneRange();
        }
        commit();
    }

    // Разбор кода iframe или ссылки на видео
    function parseEmbed(value) {
        var src = value;
        var height = 0;
        if (/<iframe/i.test(value)) {
            var tpl = document.createElement('template');
            tpl.innerHTML = value;
            var frame = tpl.content.querySelector('iframe');
            if (!frame) return null;
            src = frame.getAttribute('src') || '';
            var h = parseInt(frame.getAttribute('height'), 10);
            if (h > 0) height = h;
        }
        src = src.trim();
        if (src.indexOf('//') === 0) src = 'https:' + src;
        if (!/^https:\/\//i.test(src)) return null;

        var m = src.match(/(?:youtube\.com\/watch\?(?:.*&)?v=|youtu\.be\/|youtube\.com\/shorts\/)([\w-]{11})/i);
        if (m) return { src: 'https://www.youtube.com/embed/' + m[1], height: 0 };
        m = src.match(/rutube\.ru\/video\/([a-f0-9]{32})/i);
        if (m) return { src: 'https://rutube.ru/play/embed/' + m[1], height: 0 };

        var isVideo = /youtube\.com\/embed|youtube-nocookie\.com\/embed|rutube\.ru\/play\/embed|vk\.com\/video_ext|vkvideo\.ru\/video_ext|player\.vimeo\.com/i.test(src);
        return { src: src, height: isVideo ? 0 : (height || -1) };
    }

    function uploadImage(file) {
        var data = new FormData();
        data.append('image', file);
        data.append('csrf', csrf);
        return fetch('upload.php', { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (res) {
                if (!res.ok) throw new Error(res.error || 'Не удалось загрузить фото.');
                return res.url;
            });
    }

    function figureHtml(url, caption) {
        return '<figure><img src="' + esc(url) + '" alt="' + esc(caption) + '">' +
            (caption ? '<figcaption>' + esc(caption) + '</figcaption>' : '') + '</figure>';
    }

    function askCaptions(count) {
        var list = [];
        var i;
        if (count > 1 && confirm('Добавить подпись к каждому фото отдельно?\n\nОК — к каждому фото, Отмена — одна общая подпись для всех.')) {
            for (i = 0; i < count; i++) {
                list.push((prompt('Подпись к фото ' + (i + 1) + ' из ' + count + ' (можно оставить пустой):', '') || '').trim());
            }
        } else {
            var common = (prompt(count > 1 ? 'Общая подпись ко всем фото, например автор снимков (можно оставить пустой):' : 'Подпись к фото (можно оставить пустой):', '') || '').trim();
            for (i = 0; i < count; i++) list.push(common);
        }
        return list;
    }

    var actions = {
        undo: undo,
        redo: redo,
        bold: function () { document.execCommand('bold'); },
        italic: function () { document.execCommand('italic'); },
        h2: function () { toggleBlock('h2'); },
        ul: function () { document.execCommand('insertUnorderedList'); },
        unlink: function () {
            var link = closestLink();
            if (link && window.getSelection().isCollapsed) {
                var range = document.createRange();
                range.selectNodeContents(link);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }
            document.execCommand('unlink');
        },
        clear: function () {
            document.execCommand('removeFormat');
            document.execCommand('unlink');
            document.execCommand('formatBlock', false, '<p>');
        },
        link: function () {
            var existing = closestLink();
            var sel = window.getSelection();
            var collapsed = !sel.rangeCount || sel.isCollapsed;
            if (!existing && collapsed) {
                alert('Сначала выделите текст, к которому нужно прикрепить ссылку.');
                return;
            }
            saveSelection();
            var href = prompt('Адрес ссылки (например, https://penza.ru):', existing ? existing.getAttribute('href') : 'https://');
            if (href === null || !href.trim() || href.trim() === 'https://') {
                restoreSelection();
                return;
            }
            href = href.trim();
            if (!/^(https?:\/\/|mailto:|\/|#)/i.test(href)) href = 'https://' + href;
            var newTab = confirm('Открывать ссылку в новой вкладке?\n\nОК — в новой вкладке, Отмена — в той же.');
            if (existing && collapsed) {
                existing.setAttribute('href', href);
                setTarget(existing, newTab);
                return;
            }
            restoreSelection();
            document.execCommand('createLink', false, href);
            editor.querySelectorAll('a').forEach(function (a) {
                if (a.getAttribute('href') === href) setTarget(a, newTab);
            });
        },
        quote: function () {
            var existing = closestTag('BLOCKQUOTE');
            if (existing) {
                if (!confirm('Убрать оформление цитаты? Текст останется обычным абзацем.')) return;
                var cite = existing.querySelector('cite');
                if (cite) cite.parentNode.removeChild(cite);
                var p = document.createElement('p');
                p.textContent = existing.textContent.trim();
                existing.parentNode.replaceChild(p, existing);
                return;
            }
            saveSelection();
            var sel = window.getSelection();
            var selected = sel.rangeCount && editor.contains(sel.anchorNode) ? sel.toString().trim() : '';
            var text = selected || prompt('Текст цитаты:', '');
            if (!text || !text.trim()) {
                restoreSelection();
                return;
            }
            var author = (prompt('Автор цитаты (можно оставить пустым):', '') || '').trim();
            if (selected && savedRange) savedRange.deleteContents();
            insertBlocks('<blockquote><p>' + esc(text.trim()) + '</p>' +
                (author ? '<cite>' + esc(author) + '</cite>' : '') +
                '</blockquote><p><br></p>');
        },
        embed: function () {
            saveSelection();
            var value = prompt('Вставьте код для встраивания (iframe) или ссылку на видео YouTube или Rutube:', '');
            if (value === null || !value.trim()) {
                restoreSelection();
                return;
            }
            var data = parseEmbed(value.trim());
            if (!data) {
                alert('Не удалось распознать вставку. Нужен код iframe с сайта-источника или ссылка, начинающаяся с https://');
                restoreSelection();
                return;
            }
            if (data.height === -1) {
                var h = parseInt(prompt('Высота блока в пикселях (например, 450):', '450'), 10);
                data.height = h >= 50 && h <= 3000 ? h : 450;
            }
            var caption = (prompt('Подпись под вставкой (можно оставить пустой):', '') || '').trim();
            var attrs = ' src="' + esc(data.src) + '"' +
                (data.height ? ' height="' + data.height + '"' : '') +
                ' title="' + esc(caption || 'Встроенный материал') + '"' +
                ' allow="autoplay; encrypted-media; fullscreen; picture-in-picture; clipboard-write" allowfullscreen';
            insertBlocks('<figure><iframe' + attrs + '></iframe>' +
                (caption ? '<figcaption>' + esc(caption) + '</figcaption>' : '') +
                '</figure><p><br></p>');
            setStatus('Вставка добавлена. В редакторе она показана рамкой, на сайте будет работать полностью.', true);
        },
        image: function () {
            saveSelection();
            imageInput.click();
        },
        gallery: function () {
            saveSelection();
            galleryTarget = null;
            if (galleryInput) galleryInput.click();
        }
    };

    function updateToolbar() {
        ['bold', 'italic'].forEach(function (cmd) {
            var btn = toolbar.querySelector('[data-cmd="' + cmd + '"]');
            var state = false;
            try { state = document.queryCommandState(cmd); } catch (e) {}
            if (btn) btn.classList.toggle('is-active', state);
        });
        var quoteBtn = toolbar.querySelector('[data-cmd="quote"]');
        if (quoteBtn) quoteBtn.classList.toggle('is-active', !!closestTag('BLOCKQUOTE'));
    }

    var promptCommands = ['image', 'link', 'quote', 'embed', 'gallery'];

    toolbar.querySelectorAll('[data-cmd]').forEach(function (btn) {
        btn.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
        btn.addEventListener('click', function () {
            var cmd = btn.getAttribute('data-cmd');
            if (cmd === 'undo' || cmd === 'redo') {
                actions[cmd]();
                return;
            }
            flushRecord();
            if (promptCommands.indexOf(cmd) === -1) editor.focus();
            actions[cmd]();
            updateToolbar();
            commit();
        });
    });

    document.addEventListener('selectionchange', function () {
        if (document.activeElement === editor) {
            saveSelection();
            updateToolbar();
        }
    });

    // Набор текста объединяется в один шаг отмены, остальные действия записываются сразу
    function isTyping(type) {
        return /^(insertText|insertCompositionText|deleteContent)/.test(type || '');
    }

    editor.addEventListener('beforeinput', function (ev) {
        if (ev.inputType === 'historyUndo') {
            ev.preventDefault();
            undo();
            return;
        }
        if (ev.inputType === 'historyRedo') {
            ev.preventDefault();
            redo();
            return;
        }
        if (!isTyping(ev.inputType)) flushRecord();
    });

    editor.addEventListener('input', function (ev) {
        syncField();
        dirty = true;
        if (ev.inputType && !isTyping(ev.inputType)) record(); else scheduleRecord();
    });

    editor.addEventListener('keydown', function (ev) {
        if (!(ev.ctrlKey || ev.metaKey)) return;
        if (ev.code === 'KeyZ' && !ev.shiftKey) {
            ev.preventDefault();
            undo();
        } else if (ev.code === 'KeyY' || (ev.code === 'KeyZ' && ev.shiftKey)) {
            ev.preventDefault();
            redo();
        } else if (ev.code === 'KeyK') {
            ev.preventDefault();
            flushRecord();
            actions.link();
            commit();
        }
    });

    imageInput.addEventListener('change', function () {
        var file = imageInput.files[0];
        if (!file) return;
        setStatus('Загружаем фото…');
        uploadImage(file)
            .then(function (url) {
                var caption = (prompt('Подпись к фото (можно оставить пустой):', '') || '').trim();
                insertBlocks(figureHtml(url, caption) + '<p><br></p>');
                setStatus('Фото добавлено в текст.', true);
            })
            .catch(function (err) {
                setStatus('');
                alert(err.message);
            })
            .finally(function () {
                imageInput.value = '';
            });
    });

    if (galleryInput) {
        galleryInput.addEventListener('change', function () {
            var files = Array.prototype.slice.call(galleryInput.files, 0, 30);
            if (!files.length) return;
            var urls = [];
            var failed = 0;
            var chain = Promise.resolve();
            files.forEach(function (file, i) {
                chain = chain.then(function () {
                    setStatus('Загружаем фото ' + (i + 1) + ' из ' + files.length + '…');
                    return uploadImage(file).then(function (url) { urls.push(url); }, function () { failed++; });
                });
            });
            chain.then(function () {
                galleryInput.value = '';
                if (!urls.length) {
                    setStatus('');
                    alert('Не удалось загрузить ни одного фото. Проверьте формат и размер файлов.');
                    galleryTarget = null;
                    return;
                }
                var captions = askCaptions(urls.length);
                var figs = urls.map(function (u, k) { return figureHtml(u, captions[k]); }).join('');
                if (galleryTarget && editor.contains(galleryTarget)) {
                    flushRecord();
                    galleryTarget.insertAdjacentHTML('beforeend', figs);
                    commit();
                } else {
                    insertBlocks('<div class="news-gallery">' + figs + '</div><p><br></p>');
                }
                galleryTarget = null;
                setStatus('Галерея: добавлено фото ' + urls.length + (failed ? ', не загрузилось ' + failed : '') + '.', true);
            });
        });
    }

    // Двойной щелчок по галерее: добавить фото, изменить подписи или удалить
    editor.addEventListener('dblclick', function (ev) {
        var gallery = ev.target.closest ? ev.target.closest('.news-gallery') : null;
        if (!gallery || !editor.contains(gallery)) return;
        ev.preventDefault();
        var choice = prompt('Галерея. Введите цифру:\n1 — добавить фото\n2 — изменить подписи\n3 — удалить галерею', '1');
        if (choice === null) return;
        choice = choice.trim();
        if (choice === '1') {
            galleryTarget = gallery;
            galleryInput.click();
        } else if (choice === '2') {
            flushRecord();
            var figs = gallery.querySelectorAll('figure');
            for (var i = 0; i < figs.length; i++) {
                var cap = figs[i].querySelector('figcaption');
                var value = prompt('Подпись к фото ' + (i + 1) + ' из ' + figs.length + ':', cap ? cap.textContent : '');
                if (value === null) break;
                value = value.trim();
                var img = figs[i].querySelector('img');
                if (img) img.setAttribute('alt', value);
                if (value) {
                    if (!cap) {
                        cap = document.createElement('figcaption');
                        figs[i].appendChild(cap);
                    }
                    cap.textContent = value;
                } else if (cap) {
                    cap.parentNode.removeChild(cap);
                }
            }
            commit();
        } else if (choice === '3') {
            flushRecord();
            gallery.parentNode.removeChild(gallery);
            commit();
            setStatus('Галерея удалена. Вернуть её можно кнопкой «Отменить».', true);
        }
    });

    var coverInput = document.getElementById('cover-input');
    var coverPreview = document.getElementById('cover-preview');
    if (coverInput && coverPreview) {
        coverInput.addEventListener('change', function () {
            var file = coverInput.files[0];
            if (!file) return;
            coverPreview.innerHTML = '';
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            coverPreview.appendChild(img);
            dirty = true;
        });
    }

    form.querySelectorAll('input, textarea, select').forEach(function (el) {
        el.addEventListener('change', function () { dirty = true; });
    });

    form.addEventListener('submit', function () {
        syncField();
        dirty = false;
    });

    window.addEventListener('beforeunload', function (ev) {
        if (dirty) {
            ev.preventDefault();
            ev.returnValue = '';
        }
    });

    lockGalleries();
    syncField();
    record();
})();