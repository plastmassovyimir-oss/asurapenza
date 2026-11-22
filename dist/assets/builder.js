(function () {
    var cfg = window.LR_CONFIG || {};
    var statusEl = document.getElementById('lr-status');
    var saveBtn = document.getElementById('lr-save');
    var dirty = false;
    var pending = null;
    var editor = null;

    function setStatus(text, kind) {
        statusEl.textContent = text || '';
        statusEl.className = 'lr-status' + (kind ? ' is-' + kind : '');
    }

    if (!window.grapesjs) {
        document.getElementById('gjs').innerHTML = '<div class="lr-load-error">Не удалось загрузить конструктор GrapesJS. Проверьте подключение к интернету и обновите страницу.</div>';
        setStatus('Конструктор не загрузился', 'error');
        return;
    }

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function cssUrl(u) {
        return esc(String(u).replace(/'/g, '%27'));
    }

    // Варианты для выпадающих списков (подходят для разных версий GrapesJS)
    function opts(list) {
        return list.map(function (o) {
            return { id: o[0], value: o[0], label: o[1], name: o[1] };
        });
    }

    // Заглушка вместо фото
    var PH = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1000" viewBox="0 0 1600 1000">' +
        '<rect width="1600" height="1000" fill="#e9dabd"/>' +
        '<path d="M0 1000 520 470l300 300 220-220 560 450z" fill="#d6c19d"/>' +
        '<circle cx="1160" cy="300" r="90" fill="#d6c19d"/>' +
        '<text x="800" y="160" text-anchor="middle" font-family="Georgia,serif" font-size="48" fill="#8c1c1c">Двойной клик — выбрать фото</text>' +
        '</svg>'
    );

    function img() {
        return '<img src="' + PH + '" alt="">';
    }

    var P1 = 'Текст абзаца. Дважды щёлкните, чтобы редактировать. Выделите слово, чтобы сделать его жирным, курсивом или ссылкой.';
    var P2 = 'Второй абзац. Блоки можно перетаскивать, копировать и удалять с помощью панели над выделенным элементом.';
    var P3 = 'Третий абзац. Чтобы изменить шрифт, цвет или отступы, выделите элемент и откройте вкладку оформления справа.';

    function photo(mod) {
        return '<figure class="lr-figure' + (mod ? ' ' + mod : '') + '" data-anim="fade-up">' +
            '<div class="lr-media">' + img() + '</div>' +
            '<figcaption class="lr-caption">Подпись к фото. Фото: автор</figcaption></figure>';
    }

    function numberBox(value, label) {
        return '<div class="lr-number" data-anim="fade-up"><div class="lr-number-value lr-count">' + value + '</div>' +
            '<div class="lr-number-label">' + label + '</div></div>';
    }

    function galleryItem() {
        return '<figure class="lr-gallery-item" data-anim="fade-up"><div class="lr-media">' + img() + '</div>' +
            '<figcaption class="lr-caption">Подпись</figcaption></figure>';
    }

    var EMBED_ALLOW = 'autoplay; encrypted-media; fullscreen; picture-in-picture; clipboard-write';

    var T = {
        hero: function (title, lead, cover) {
            return '<section class="lr-hero" style="background-image:url(\'' + cssUrl(cover || PH) + '\')">' +
                '<div class="lr-hero-inner">' +
                '<div class="lr-hero-kicker" data-anim="fade">Спецпроект</div>' +
                '<h1 class="lr-hero-title" data-anim="fade-up">' + esc(title || 'Заголовок лонгрида') + '</h1>' +
                '<p class="lr-hero-lead" data-anim="fade-up" data-anim-delay="300">' + esc(lead || 'Короткое описание: о чём эта история и почему её важно прочитать.') + '</p>' +
                '</div><div class="lr-scroll">Листайте вниз</div></section>';
        },
        heroVideo: function () {
            return '<section class="lr-hero lr-hero--video">' +
                '<video class="lr-hero-video" autoplay="autoplay" muted="muted" loop="loop" playsinline="playsinline"></video>' +
                '<div class="lr-hero-inner">' +
                '<h1 class="lr-hero-title" data-anim="fade-up">Заголовок поверх видео</h1>' +
                '<p class="lr-hero-lead" data-anim="fade-up" data-anim-delay="300">Щёлкните по фону обложки и укажите адрес mp4-файла в настройках элемента.</p>' +
                '</div></section>';
        },
        heroSplit: function () {
            return '<section class="lr-hero-split"><div class="lr-hero-split-text">' +
                '<div class="lr-hero-kicker" data-anim="fade">Спецпроект</div>' +
                '<h1 class="lr-hero-title" data-anim="fade-up">Заголовок лонгрида</h1>' +
                '<p class="lr-hero-lead" data-anim="fade-up" data-anim-delay="300">Короткое описание истории.</p>' +
                '</div><div class="lr-hero-split-media">' + img() + '</div></section>';
        },
        chapter: function (num, title) {
            return '<section class="lr-chapter"><div class="lr-chapter-num" data-anim="fade">' + num + '</div>' +
                '<h2 class="lr-chapter-title" data-anim="fade-up">' + title + '</h2></section>';
        },
        text: function () { return '<div class="lr-text"><p>' + P1 + '</p><p>' + P2 + '</p></div>'; },
        lead: function () { return '<div class="lr-text"><p class="lr-lead">Вводный абзац крупным шрифтом. Он задаёт тон всей истории и объясняет, о чём пойдёт речь.</p></div>'; },
        h2: function () { return '<div class="lr-text"><h2 class="lr-h2">Подзаголовок</h2></div>'; },
        dropcap: function () { return '<div class="lr-text"><p class="lr-dropcap">' + P1 + '</p><p>' + P2 + '</p></div>'; },
        floatPhoto: function (side) {
            return '<div class="lr-text"><figure class="lr-float-' + side + '"><div class="lr-media">' + img() + '</div>' +
                '<figcaption class="lr-caption">Подпись к фото</figcaption></figure>' +
                '<p>' + P1 + '</p><p>' + P2 + '</p><p>' + P3 + '</p></div>';
        },
        split: function (reverse) {
            return '<section class="lr-split' + (reverse ? ' lr-split--reverse' : '') + '">' +
                '<div class="lr-split-media" data-anim="' + (reverse ? 'slide-right' : 'slide-left') + '"><div class="lr-media">' + img() + '</div>' +
                '<div class="lr-caption">Подпись к фото</div></div>' +
                '<div class="lr-split-text" data-anim="fade-up"><h3 class="lr-split-title">Заголовок</h3><p>' + P1 + '</p><p>' + P2 + '</p></div>' +
                '</section>';
        },
        gallery: function (n) {
            var html = '<div class="lr-gallery' + (n === 2 ? ' lr-gallery--2' : '') + '">';
            for (var i = 0; i < n; i++) html += galleryItem();
            return html + '</div>';
        },
        parallax: function () {
            return '<section class="lr-parallax" style="background-image:url(\'' + PH + '\')">' +
                '<div class="lr-parallax-box" data-anim="zoom"><p>Текст поверх неподвижного фото. При прокрутке страница движется, а фото остаётся на месте.</p></div></section>';
        },
        sticky: function () {
            return '<section class="lr-sticky"><div class="lr-sticky-media">' + img() + '</div><div class="lr-sticky-text">' +
                '<div class="lr-sticky-card"><p>Первый фрагмент истории. Фото слева остаётся на месте, пока читатель прокручивает текст.</p></div>' +
                '<div class="lr-sticky-card"><p>Второй фрагмент. Такой приём хорошо подходит для рассказа о месте или событии по шагам.</p></div>' +
                '<div class="lr-sticky-card"><p>Третий фрагмент. Количество карточек можно менять копированием и удалением.</p></div>' +
                '</div></section>';
        },
        quote: function () {
            return '<blockquote class="lr-quote" data-anim="fade-up"><p>Текст цитаты: яркая фраза героя, которую хочется запомнить.</p>' +
                '<cite class="lr-quote-author">Имя Фамилия, кто это</cite></blockquote>';
        },
        number: function () { return numberBox('45 000', 'Подпись к цифре: что именно она означает'); },
        numbers: function () {
            return '<div class="lr-numbers">' + numberBox('12', 'Первая подпись') + numberBox('45 000', 'Вторая подпись') + numberBox('350', 'Третья подпись') + '</div>';
        },
        note: function () {
            return '<aside class="lr-note"><div class="lr-note-title">Справка</div><p>Короткая справка: даты, термины или контекст, который поможет читателю.</p></aside>';
        },
        embed: function (height, caption) {
            return '<div class="lr-embed lr-embed--empty">' +
                '<iframe frameborder="0" allowfullscreen="allowfullscreen" allow="' + EMBED_ALLOW + '"' + (height ? ' height="' + height + '"' : '') + '></iframe>' +
                '<div class="lr-caption">' + caption + '</div></div>';
        },
        video: function () {
            return '<figure class="lr-figure lr-figure--wide"><video class="lr-video" controls="controls" playsinline="playsinline" preload="metadata"></video>' +
                '<figcaption class="lr-caption">Подпись к видео</figcaption></figure>';
        },
        cols: function (n) {
            var html = '<div class="lr-cols lr-cols-' + n + '">';
            for (var i = 0; i < n; i++) html += '<div class="lr-col" data-anim="fade-up"><h3 class="lr-col-title">Заголовок</h3><p>' + P1 + '</p></div>';
            return html + '</div>';
        },
        dark: function () {
            return '<section class="lr-dark"><div class="lr-text"><h2 class="lr-h2">Заголовок на тёмном фоне</h2><p>' + P1 + '</p><p>' + P2 + '</p></div></section>';
        },
        section: function () { return '<section class="lr-section"></section>'; },
        button: function () { return '<div class="lr-text lr-center"><a class="lr-btn" href="#">Текст кнопки</a></div>'; },
        divider: function () { return '<div class="lr-divider"></div>'; },
        spacer: function () { return '<div class="lr-spacer"></div>'; }
    };

    function buildTemplate() {
        return T.hero(cfg.title, cfg.lead, cfg.cover) +
            T.lead() + T.text() +
            T.chapter('Глава 1', 'Название первой главы') +
            T.text() + photo('lr-figure--wide') + T.quote() + T.split(false) +
            T.chapter('Глава 2', 'Название второй главы') +
            T.sticky() + T.numbers() + T.parallax() + T.gallery(3) + T.note() + T.text();
    }

    var BLOCKS = [
        ['lr-hero', 'Обложка на весь экран', 'Обложки', function () { return T.hero('', '', ''); }],
        ['lr-hero-split', 'Обложка: фото справа', 'Обложки', T.heroSplit],
        ['lr-hero-video', 'Обложка с видеофоном', 'Обложки', T.heroVideo],
        ['lr-chapter', 'Глава', 'Структура', function () { return T.chapter('Глава', 'Название главы'); }],
        ['lr-section', 'Пустая секция', 'Структура', T.section],
        ['lr-dark', 'Тёмная секция', 'Структура', T.dark],
        ['lr-cols-2', 'Две колонки', 'Структура', function () { return T.cols(2); }],
        ['lr-cols-3', 'Три колонки', 'Структура', function () { return T.cols(3); }],
        ['lr-divider', 'Разделитель', 'Структура', T.divider],
        ['lr-spacer', 'Отступ', 'Структура', T.spacer],
        ['lr-text', 'Текст', 'Текст', T.text],
        ['lr-lead', 'Вводный абзац', 'Текст', T.lead],
        ['lr-h2', 'Подзаголовок', 'Текст', T.h2],
        ['lr-dropcap', 'Текст с буквицей', 'Текст', T.dropcap],
        ['lr-free-text', 'Свободный текст', 'Текст', function () { return { type: 'text', content: 'Новый текст', style: { padding: '10px' } }; }],
        ['lr-button', 'Кнопка-ссылка', 'Текст', T.button],
        ['lr-figure', 'Фото по ширине текста', 'Фото', function () { return photo(''); }],
        ['lr-figure-wide', 'Фото широкое', 'Фото', function () { return photo('lr-figure--wide'); }],
        ['lr-figure-full', 'Фото на всю ширину', 'Фото', function () { return photo('lr-figure--full'); }],
        ['lr-image', 'Отдельное фото', 'Фото', function () { return { type: 'image', src: PH, activate: true, classes: ['lr-img'] }; }],
        ['lr-float-left', 'Фото слева, текст обтекает', 'Фото', function () { return T.floatPhoto('left'); }],
        ['lr-float-right', 'Фото справа, текст обтекает', 'Фото', function () { return T.floatPhoto('right'); }],
        ['lr-split', 'Фото и текст рядом', 'Фото', function () { return T.split(false); }],
        ['lr-split-rev', 'Текст и фото рядом', 'Фото', function () { return T.split(true); }],
        ['lr-gallery-3', 'Галерея из трёх фото', 'Фото', function () { return T.gallery(3); }],
        ['lr-gallery-2', 'Два фото рядом', 'Фото', function () { return T.gallery(2); }],
        ['lr-parallax', 'Неподвижное фото с текстом', 'Фото', T.parallax],
        ['lr-sticky', 'Фото на месте, текст едет', 'Фото', T.sticky],
        ['lr-embed-video', 'Видео YouTube, Rutube, VK', 'Видео и встраивания', function () { return T.embed(0, 'Подпись к видео'); }],
        ['lr-embed-chart', 'График, карта, анимация (iframe)', 'Видео и встраивания', function () { return T.embed(500, 'Подпись к графику'); }],
        ['lr-video', 'Видеофайл mp4', 'Видео и встраивания', T.video],
        ['lr-quote', 'Цитата', 'Акценты', T.quote],
        ['lr-number', 'Большая цифра со счётчиком', 'Акценты', T.number],
        ['lr-numbers', 'Три цифры', 'Акценты', T.numbers],
        ['lr-note', 'Справка', 'Акценты', T.note]
    ];
T.carousel = function () {
        var html = '<div class="news-gallery lr-carousel">';
        for (var i = 0; i < 4; i++) {
            html += '<figure><img src="' + PH + '" alt=""><figcaption>Подпись к фото ' + (i + 1) + '</figcaption></figure>';
        }
        return html + '</div>';
    };
    BLOCKS.push(['lr-carousel', 'Галерея-карусель', 'Фото', T.carousel]);
    var ANIM = [
        ['', 'Без анимации'],
        ['fade', 'Проявление'],
        ['fade-up', 'Появление снизу'],
        ['fade-down', 'Появление сверху'],
        ['slide-left', 'Выезд слева'],
        ['slide-right', 'Выезд справа'],
        ['zoom', 'Увеличение'],
        ['zoom-out', 'Уменьшение'],
        ['blur', 'Из размытия'],
        ['kenburns', 'Медленный наезд (для фото)']
    ];

    // Ссылка на видео или код iframe в адрес для встраивания
    function toEmbedSrc(value) {
        var v = String(value || '').trim();
        if (!v) return '';
        var m = v.match(/<iframe[^>]*\ssrc=["']([^"']+)["']/i);
        if (m) v = m[1];
        v = v.replace(/&amp;/g, '&');
        if (v.indexOf('//') === 0) v = 'https:' + v;
        if (!/^https:\/\//i.test(v)) return null;
        m = v.match(/(?:youtube\.com\/watch\?(?:.*&)?v=|youtu\.be\/|youtube\.com\/shorts\/)([\w-]{11})/i);
        if (m) return 'https://www.youtube.com/embed/' + m[1];
        m = v.match(/rutube\.ru\/video\/([a-f0-9]{32})/i);
        if (m) return 'https://rutube.ru/play/embed/' + m[1];
        if (!/video_ext/i.test(v)) {
            m = v.match(/(?:vk\.com|vkvideo\.ru)\/video(-?\d+)_(\d+)/i);
            if (m) return 'https://vk.com/video_ext.php?oid=' + m[1] + '&id=' + m[2] + '&hd=2';
        }
        return v;
    }

    function ensureExtraTraits(c) {
        if (!c || !c.get || !c.getTrait) return;
        var type = c.get('type');
        if (['wrapper', 'textnode', 'comment', 'lr-iframe'].indexOf(type) !== -1) return;
        if (!c.get('tagName')) return;
        if (c.getTrait('data-anim')) return;
        c.addTrait([
            { type: 'select', name: 'data-anim', label: 'Анимация при прокрутке', options: opts(ANIM) },
            { type: 'number', name: 'data-anim-delay', label: 'Задержка анимации, мс', min: 0, max: 5000, step: 100 },
            { type: 'select', name: 'dragMode', label: 'Перемещение мышью', changeProp: true, options: opts([['', 'По порядку блоков'], ['absolute', 'Свободное, в любое место секции']]) }
        ]);
    }

    function walk(c) {
        ensureExtraTraits(c);
        c.components().forEach(walk);
    }

    function lrPlugin(ed) {
        var dc = ed.DomComponents;

        // iframe внутри встраивания: в редакторе не перехватывает клики
        dc.addType('lr-iframe', {
            isComponent: function (el) { return el.tagName === 'IFRAME' ? { type: 'lr-iframe' } : false; },
            model: { defaults: { tagName: 'iframe', droppable: false, selectable: false, hoverable: false, draggable: false, copyable: false, traits: [] } },
            view: { onRender: function () { this.el.style.pointerEvents = 'none'; } }
        });

        // Блок встраивания: ссылка и высота задаются в настройках
        dc.addType('lr-embed', {
            isComponent: function (el) { return el.classList && el.classList.contains('lr-embed') ? { type: 'lr-embed' } : false; },
            model: {
                defaults: {
                    traits: [
                        { type: 'text', name: 'embedSrc', label: 'Ссылка на видео или код iframe', changeProp: true },
                        { type: 'number', name: 'embedHeight', label: 'Высота, px (0 — пропорция 16:9)', min: 0, max: 3000, changeProp: true }
                    ]
                },
                init: function () {
                    this.readFrame();
                    this.on('change:embedSrc change:embedHeight', this.applyEmbed);
                },
                getFrame: function () {
                    var found = null;
                    (function search(c) {
                        c.components().forEach(function (ch) {
                            if (found) return;
                            if (ch.get('tagName') === 'iframe') found = ch; else search(ch);
                        });
                    })(this);
                    return found;
                },
                readFrame: function () {
                    var frame = this.getFrame();
                    if (!frame) return;
                    var attrs = frame.getAttributes();
                    this.set({ embedSrc: attrs.src || '', embedHeight: parseInt(attrs.height, 10) || 0 }, { silent: true });
                },
                applyEmbed: function () {
                    var frame = this.getFrame();
                    if (!frame) {
                        this.append('<iframe frameborder="0" allowfullscreen="allowfullscreen" allow="' + EMBED_ALLOW + '"></iframe>', { at: 0 });
                        frame = this.getFrame();
                    }
                    var src = toEmbedSrc(this.get('embedSrc'));
                    if (src === null) {
                        alert('Нужна ссылка, начинающаяся с https://, или код iframe с сайта-источника.');
                        return;
                    }
                    var h = parseInt(this.get('embedHeight'), 10) || 0;
                    if (src) frame.addAttributes({ src: src }); else frame.removeAttributes('src');
                    if (h > 0) frame.addAttributes({ height: String(h) }); else frame.removeAttributes('height');
                    if (src) this.removeClass('lr-embed--empty'); else this.addClass('lr-embed--empty');
                }
            }
        });

        // Видеофайл: адрес, обложка и параметры воспроизведения
        dc.addType('lr-video', {
            isComponent: function (el) { return el.tagName === 'VIDEO' ? { type: 'lr-video' } : false; },
            model: {
                defaults: {
                    tagName: 'video',
                    droppable: false,
                    traits: [
                        { type: 'text', name: 'src', label: 'Адрес видеофайла (mp4, https://...)' },
                        { type: 'text', name: 'poster', label: 'Обложка видео (адрес фото)' },
                        { type: 'checkbox', name: 'controls', label: 'Кнопки управления' },
                        { type: 'checkbox', name: 'autoplay', label: 'Автозапуск' },
                        { type: 'checkbox', name: 'muted', label: 'Без звука' },
                        { type: 'checkbox', name: 'loop', label: 'По кругу' },
                        { type: 'checkbox', name: 'playsinline', label: 'Не разворачивать на телефоне' }
                    ]
                }
            },
            view: { onRender: function () { this.el.muted = true; } }
        });

        // Служебные классы блоков скрыты: оформление применяется к выбранному элементу
        ed.on('selector:add', function (selector) {
            var name = selector.get('name') || '';
            if (/^lr-/.test(name) || name === 'is-in') selector.set({ private: true });
        });

        BLOCKS.forEach(function (b) {
            ed.BlockManager.add(b[0], { label: b[1], category: b[2], content: b[3](), attributes: { title: b[1] } });
        });

        ed.on('component:add', ensureExtraTraits);
        ed.on('component:selected', function (c) {
            ensureExtraTraits(c);
            if (c && c.get('type') === 'lr-embed' && c.readFrame) c.readFrame();
        });
    }

    function uploadFile(ev) {
        var files = ev.dataTransfer ? ev.dataTransfer.files : ev.target.files;
        if (!files || !files.length) return;
        Array.prototype.forEach.call(files, function (file) {
            var fd = new FormData();
            fd.append('image', file);
            fd.append('csrf', cfg.csrf);
            setStatus('Загружаем фото…');
            fetch('upload.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.error || 'Не удалось загрузить фото.');
                    editor.AssetManager.add({ src: res.url });
                    setStatus('Фото загружено. Нажмите на него, чтобы вставить.', 'ok');
                })
                .catch(function (err) {
                    setStatus('Ошибка загрузки', 'error');
                    alert(err.message);
                });
        });
    }

    var SECTORS = [
        {
            name: 'Размер и положение',
            open: true,
            properties: [
                { extend: 'width', label: 'Ширина' },
                { extend: 'max-width', label: 'Максимальная ширина' },
                { extend: 'height', label: 'Высота' },
                { extend: 'min-height', label: 'Минимальная высота' },
                { extend: 'margin', label: 'Внешние отступы' },
                { extend: 'padding', label: 'Внутренние отступы' },
                { extend: 'float', label: 'Обтекание' },
                { extend: 'display', label: 'Отображение' },
                { extend: 'position', label: 'Позиционирование' },
                { extend: 'top', label: 'Сверху' },
                { extend: 'right', label: 'Справа' },
                { extend: 'bottom', label: 'Снизу' },
                { extend: 'left', label: 'Слева' },
                { property: 'z-index', type: 'number', label: 'Слой (выше или ниже)', default: 'auto', min: -10, max: 100 }
            ]
        },
        {
            name: 'Фото, видео и сетки',
            open: false,
            properties: [
                { property: 'object-fit', type: 'select', label: 'Как вписать фото', default: 'fill', options: opts([['fill', 'Растянуть'], ['cover', 'Заполнить с обрезкой'], ['contain', 'Вписать целиком']]) },
                { property: 'object-position', type: 'select', label: 'Точка фокуса фото', default: '50% 50%', options: opts([['50% 50%', 'Центр'], ['50% 0%', 'Верх'], ['50% 100%', 'Низ'], ['0% 50%', 'Левый край'], ['100% 50%', 'Правый край']]) },
                { property: 'aspect-ratio', type: 'select', label: 'Пропорции', default: 'auto', options: opts([['auto', 'Как у файла'], ['16 / 9', '16:9'], ['3 / 2', '3:2'], ['4 / 3', '4:3'], ['1 / 1', 'Квадрат'], ['3 / 4', 'Вертикальное 3:4'], ['9 / 16', 'Вертикальное 9:16']]) },
                { property: 'grid-template-columns', type: 'select', label: 'Колонки сетки', default: '', options: opts([['', 'Как в блоке'], ['minmax(0,1fr)', 'Одна'], ['minmax(0,1fr) minmax(0,1fr)', 'Две равные'], ['minmax(0,2fr) minmax(0,1fr)', 'Широкая и узкая'], ['minmax(0,1fr) minmax(0,2fr)', 'Узкая и широкая'], ['repeat(3,minmax(0,1fr))', 'Три'], ['repeat(4,minmax(0,1fr))', 'Четыре']]) },
                { property: 'gap', type: 'number', label: 'Расстояние в сетке', units: ['px', 'vw'], default: '40px', min: 0 }
            ]
        },
        {
            name: 'Текст',
            open: false,
            properties: [
                { property: 'font-family', type: 'select', label: 'Шрифт', default: '', options: opts([['', 'Как на сайте'], ['Literata, Georgia, serif', 'Literata'], ['Cormorant, serif', 'Cormorant'], ['"Old Standard TT", serif', 'Old Standard'], ['Georgia, serif', 'Georgia'], ['Arial, sans-serif', 'Arial']]) },
                { extend: 'font-size', label: 'Размер шрифта' },
                { extend: 'font-weight', label: 'Насыщенность' },
                { property: 'font-style', type: 'select', label: 'Начертание', default: 'normal', options: opts([['normal', 'Обычное'], ['italic', 'Курсив']]) },
                { property: 'text-transform', type: 'select', label: 'Регистр', default: 'none', options: opts([['none', 'Как написано'], ['uppercase', 'Все прописные'], ['lowercase', 'Все строчные']]) },
                { extend: 'line-height', label: 'Межстрочный интервал' },
                { extend: 'letter-spacing', label: 'Разрядка' },
                { extend: 'color', label: 'Цвет текста' },
                { extend: 'text-align', label: 'Выравнивание' },
                { extend: 'text-shadow', label: 'Тень текста' }
            ]
        },
        {
            name: 'Фон и оформление',
            open: false,
            properties: [
                { extend: 'background-color', label: 'Цвет фона' },
                { extend: 'background', label: 'Фоновое фото' },
                { property: 'background-attachment', type: 'select', label: 'Фон при прокрутке', default: 'scroll', options: opts([['scroll', 'Прокручивается'], ['fixed', 'Неподвижный (параллакс)']]) },
                { extend: 'border-radius', label: 'Скругление углов' },
                { extend: 'border', label: 'Рамка' },
                { extend: 'box-shadow', label: 'Тень' },
                { property: 'opacity', type: 'slider', label: 'Непрозрачность', default: '1', min: 0, max: 1, step: 0.05 }
            ]
        }
    ];

    var I18N = {
        locale: 'ru',
        localeFallback: 'en',
        detectLocale: false,
        messages: {
            ru: {
                assetManager: {
                    addButton: 'Добавить',
                    inputPlh: 'https://адрес-изображения',
                    modalTitle: 'Выберите фото',
                    uploadTitle: 'Перетащите фото сюда или нажмите, чтобы загрузить'
                },
                domComponents: {
                    names: { '': 'Блок', wrapper: 'Страница', text: 'Текст', comment: 'Комментарий', image: 'Фото', video: 'Видео', label: 'Подпись', link: 'Ссылка', 'lr-embed': 'Встраивание', 'lr-video': 'Видеофайл' }
                },
                deviceManager: { device: 'Устройство', devices: { 'Компьютер': 'Компьютер', 'Планшет': 'Планшет', 'Телефон': 'Телефон' } },
                panels: {
                    buttons: {
                        titles: {
                            preview: 'Предпросмотр в редакторе',
                            fullscreen: 'Во весь экран',
                            'sw-visibility': 'Показать границы блоков',
                            'export-template': 'Посмотреть код',
                            'open-sm': 'Оформление',
                            'open-tm': 'Настройки элемента',
                            'open-layers': 'Слои',
                            'open-blocks': 'Блоки'
                        }
                    }
                },
                selectorManager: {
                    label: 'Классы',
                    selected: 'Выбрано',
                    emptyState: '- Состояние -',
                    states: { hover: 'При наведении', active: 'При нажатии', 'nth-of-type(2n)': 'Чётные' }
                },
                styleManager: { empty: 'Выберите элемент на странице, чтобы изменить его оформление', layer: 'Слой', fileButton: 'Фото' },
                traitManager: {
                    empty: 'Выберите элемент на странице, чтобы изменить его настройки',
                    label: 'Настройки элемента',
                    traits: {
                        labels: { id: 'Идентификатор', alt: 'Описание фото', title: 'Подсказка', href: 'Адрес ссылки', target: 'Открывать' },
                        options: { target: { 'false': 'В этом окне', _blank: 'В новом окне' } }
                    }
                }
            }
        }
    };

    var hasProject = !!(window.LR_PROJECT && window.LR_PROJECT.pages);

    editor = grapesjs.init({
        container: '#gjs',
        height: '100%',
        width: 'auto',
        storageManager: false,
        undoManager: { trackSelection: false },
        protectedCss: '',
        canvasCss: cfg.canvasCss || '',
        canvas: { styles: cfg.canvasStyles || [] },
        projectData: hasProject ? window.LR_PROJECT : { pages: [{ component: buildTemplate() }] },
        i18n: I18N,
        deviceManager: {
            devices: [
                { id: 'Компьютер', name: 'Компьютер', width: '' },
                { id: 'Планшет', name: 'Планшет', width: '820px', widthMedia: '992px' },
                { id: 'Телефон', name: 'Телефон', width: '390px', widthMedia: '560px' }
            ]
        },
        assetManager: {
            upload: 'upload.php',
            uploadName: 'image',
            multiUpload: true,
            embedAsBase64: false,
            uploadFile: uploadFile
        },
        styleManager: { sectors: SECTORS },
        plugins: [lrPlugin]
    });

    function save() {
        if (pending) return pending;
        saveBtn.disabled = true;
        setStatus('Сохраняем…');
        var fd = new FormData();
        fd.append('csrf', cfg.csrf);
        fd.append('id', cfg.id);
        fd.append('project', JSON.stringify(editor.getProjectData()));
        fd.append('html', editor.getHtml());
        fd.append('css', editor.getCss());
        pending = fetch('builder-save.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () {
                    throw new Error('Сервер вернул ошибку ' + r.status + '. Возможно, страница слишком большая для настроек сервера.');
                });
            })
            .then(function (res) {
                if (!res.ok) throw new Error(res.error || 'Не удалось сохранить.');
                dirty = false;
                setStatus('Сохранено в ' + res.saved_at, 'ok');
            })
            .catch(function (err) {
                setStatus('Ошибка сохранения', 'error');
                alert(err.message);
                throw err;
            })
            .finally(function () {
                pending = null;
                saveBtn.disabled = false;
            });
        return pending;
    }

    editor.on('load', function () {
        var blocksBtn = editor.Panels.getButton('views', 'open-blocks');
        if (blocksBtn) blocksBtn.set('active', true);
        if (cfg.assets && cfg.assets.length) editor.AssetManager.add(cfg.assets);
        walk(editor.getWrapper());
        setTimeout(function () {
            dirty = !hasProject;
            setStatus(hasProject ? 'Загружено' : 'Шаблон загружен, сохраните страницу');
            editor.on('update', function () {
                dirty = true;
                setStatus('Есть несохранённые изменения');
            });
        }, 800);
    });

    editor.Keymaps.add('lr:save', '⌘+s, ctrl+s', function () { save().catch(function () {}); }, { prevent: true });

    saveBtn.addEventListener('click', function () { save().catch(function () {}); });

    var undoBtn = document.getElementById('lr-undo');
    var redoBtn = document.getElementById('lr-redo');

    function updateUndoButtons() {
        var um = editor.UndoManager;
        undoBtn.disabled = !um.hasUndo();
        redoBtn.disabled = !um.hasRedo();
    }

    undoBtn.addEventListener('click', function () {
        editor.UndoManager.undo();
        updateUndoButtons();
    });
    redoBtn.addEventListener('click', function () {
        editor.UndoManager.redo();
        updateUndoButtons();
    });

    editor.on('update', updateUndoButtons);

    editor.Keymaps.add('lr:redo-y', '⌘+y, ctrl+y', function () {
        editor.UndoManager.redo();
        updateUndoButtons();
    }, { prevent: true });

    // Загрузка страницы в конструктор не считается действием для отмены
    editor.on('load', function () {
        setTimeout(function () {
            editor.UndoManager.clear();
            updateUndoButtons();
        }, 900);
    });

    document.getElementById('lr-device').addEventListener('change', function () {
        editor.setDevice(this.value);
    });

    document.getElementById('lr-preview').addEventListener('click', function () {
        var win = window.open('about:blank', '_blank');
        var go = function () { if (win) win.location.href = cfg.previewUrl; };
        if (dirty) {
            save().then(go, function () { if (win) win.close(); });
        } else {
            go();
        }
    });

    document.getElementById('lr-template').addEventListener('click', function () {
        if (!confirm('Заменить страницу стартовым шаблоном? Текущая вёрстка будет удалена.')) return;
        editor.DomComponents.clear();
        editor.CssComposer.clear();
        editor.setComponents(buildTemplate());
        walk(editor.getWrapper());
        dirty = true;
    });

    document.getElementById('lr-clear').addEventListener('click', function () {
        if (!confirm('Удалить всё содержимое страницы? Отменить можно кнопкой «Отменить» до сохранения.')) return;
        editor.DomComponents.clear();
        editor.CssComposer.clear();
        dirty = true;
    });

    document.getElementById('lr-html').addEventListener('click', function () {
        var box = document.createElement('div');
        box.innerHTML = '<p class="lr-modal-hint">Вставьте HTML-код. Он появится после выделенного блока или в конце страницы. Скрипты при сохранении удаляются, для внешних виджетов используйте блок «График, карта, анимация (iframe)».</p>' +
            '<textarea class="lr-modal-code" spellcheck="false"></textarea>' +
            '<div class="lr-modal-actions"><button type="button">Вставить</button></div>';
        var area = box.querySelector('textarea');
        box.querySelector('button').addEventListener('click', function () {
            var code = area.value.trim();
            if (!code) return;
            var sel = editor.getSelected();
            var parent = sel && sel.parent ? sel.parent() : null;
            var added = parent ? parent.append(code, { at: sel.index() + 1 }) : editor.getWrapper().append(code);
            (Array.isArray(added) ? added : [added]).forEach(function (c) { if (c) walk(c); });
            editor.Modal.close();
            dirty = true;
        });
        editor.Modal.open({ title: 'Вставить HTML-код', content: box });
    });

    window.addEventListener('beforeunload', function (ev) {
        if (dirty) {
            ev.preventDefault();
            ev.returnValue = '';
        }
    });
})();