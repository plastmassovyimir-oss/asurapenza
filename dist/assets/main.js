document.addEventListener('DOMContentLoaded', function () {
    initSearch();
    initGalleries();
    initFeedFit();
});

function initSearch() {
    var searchbar = document.getElementById('searchbar');
    var searchBtn = document.querySelector('[data-action="search"]');
    var input = searchbar ? searchbar.querySelector('input[name="q"]') : null;
    var results = document.getElementById('search-results');
    var endpoint = searchbar ? searchbar.getAttribute('data-endpoint') : '';
    var timer = null;
    var lastQuery = '';
    var requestId = 0;

    function isOpen() {
        return !!searchbar && searchbar.classList.contains('is-open');
    }

    function hideResults() {
        if (results) results.hidden = true;
    }

    function openSearch() {
        if (!searchbar) return;
        searchbar.classList.add('is-open');
        if (searchBtn) searchBtn.setAttribute('aria-expanded', 'true');
        input.focus();
        if (input.value.trim()) runSearch(input.value);
    }

    function closeSearch() {
        if (!searchbar) return;
        searchbar.classList.remove('is-open');
        if (searchBtn) searchBtn.setAttribute('aria-expanded', 'false');
        hideResults();
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    }

    function render(data, q) {
        var items = data && data.items ? data.items : [];
        results.innerHTML = '';
        if (!items.length) {
            results.appendChild(el('div', 'search-empty', 'По запросу «' + q + '» ничего не найдено.'));
        } else {
            items.forEach(function (item) {
                var link = el('a', 'search-result' + (item.cover ? '' : ' search-result--noimg'));
                link.href = item.url;
                if (item.cover) {
                    var img = document.createElement('img');
                    img.src = item.cover;
                    img.alt = '';
                    img.loading = 'lazy';
                    link.appendChild(img);
                }
                var box = el('div');
                box.appendChild(el('div', 'search-result-title', item.title));
                box.appendChild(el('div', 'search-result-meta', item.date + ' · ' + item.section));
                link.appendChild(box);
                results.appendChild(link);
            });
            var all = el('a', 'search-all', 'Все результаты (' + data.total + ') »');
            all.href = searchbar.getAttribute('action') + '?q=' + encodeURIComponent(q);
            results.appendChild(all);
        }
        results.hidden = false;
    }

    function runSearch(value) {
        if (!results || !endpoint) return;
        var q = value.trim().replace(/^#+/, '');
        if (Array.from(q).length < 2) {
            lastQuery = '';
            results.innerHTML = '';
            hideResults();
            return;
        }
        if (q === lastQuery && results.childNodes.length) {
            results.hidden = false;
            return;
        }
        lastQuery = q;
        var current = ++requestId;
        fetch(endpoint + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) { if (current === requestId) render(data, q); })
            .catch(function () { if (current === requestId) hideResults(); });
    }

    if (input) {
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var value = input.value;
            timer = setTimeout(function () { runSearch(value); }, 250);
        });
        input.addEventListener('focus', function () {
            if (input.value.trim()) runSearch(input.value);
        });
    }

    document.addEventListener('click', function (ev) {
        var target = ev.target;
        if (!target.closest) return;

        var control = target.closest('[data-action]');
        if (control) {
            var action = control.getAttribute('data-action');
            if (action === 'search') {
                ev.preventDefault();
                if (isOpen()) closeSearch(); else openSearch();
                return;
            }
            if (action === 'search-close') {
                ev.preventDefault();
                closeSearch();
                return;
            }
        }

        if (results && !results.hidden && searchbar && !searchbar.contains(target)) hideResults();

        // Пока ссылки соцсетей не заданы, клик не прокручивает страницу наверх
        var social = target.closest('a.social');
        if (social && social.getAttribute('href') === '#') ev.preventDefault();
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && isOpen()) closeSearch();
    });
}

// Галерея-карусель в материалах и лонгридах
function svgArrow(d) {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + d + '"/></svg>';
}

function initGalleries() {
    var roots = document.querySelectorAll('.news-gallery');
    Array.prototype.forEach.call(roots, function (root) {
        if (root.classList.contains('is-ready')) return;
        var figures = Array.prototype.filter.call(root.children, function (el) {
            return el.tagName === 'FIGURE' && el.querySelector('img');
        });
        if (!figures.length) return;

        var total = figures.length;
        var index = 0;
        var startX = null;

        var stage = document.createElement('div');
        stage.className = 'ng-stage';
        var track = document.createElement('div');
        track.className = 'ng-track';
        figures.forEach(function (fig) {
            fig.classList.add('ng-slide');
            track.appendChild(fig);
        });
        stage.appendChild(track);
        root.insertBefore(stage, root.firstChild);
        root.classList.add('is-ready');

        if (total < 2) return;

        var frame = document.createElement('div');
        frame.className = 'ng-frame';
        frame.innerHTML = '<button type="button" class="ng-arrow ng-prev" aria-label="Предыдущее фото">' + svgArrow('M15 5l-7 7 7 7') + '</button>' +
            '<button type="button" class="ng-arrow ng-next" aria-label="Следующее фото">' + svgArrow('M9 5l7 7-7 7') + '</button>' +
            '<span class="ng-count"></span>';
        stage.appendChild(frame);
        var count = frame.querySelector('.ng-count');

        var strip = document.createElement('div');
        strip.className = 'ng-thumbs';
        var thumbs = figures.map(function (fig, k) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ng-thumb';
            b.setAttribute('aria-label', 'Фото ' + (k + 1) + ' из ' + total);
            var im = document.createElement('img');
            im.src = fig.querySelector('img').getAttribute('src');
            im.alt = '';
            im.loading = 'lazy';
            b.appendChild(im);
            b.addEventListener('click', function () { go(k); });
            strip.appendChild(b);
            return b;
        });
        root.insertBefore(strip, stage.nextSibling);

        function go(i) {
            index = (i + total) % total;
            track.style.transform = 'translateX(' + (-100 * index) + '%)';
            figures.forEach(function (fig, k) { fig.setAttribute('aria-hidden', k === index ? 'false' : 'true'); });
            thumbs.forEach(function (t, k) { t.classList.toggle('is-active', k === index); });
            count.textContent = (index + 1) + ' / ' + total;
            var t = thumbs[index];
            if (strip.scrollWidth > strip.clientWidth) {
                var left = t.offsetLeft - (strip.clientWidth - t.offsetWidth) / 2;
                strip.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
            }
        }

        frame.querySelector('.ng-prev').addEventListener('click', function () { go(index - 1); });
        frame.querySelector('.ng-next').addEventListener('click', function () { go(index + 1); });

        stage.addEventListener('touchstart', function (ev) {
            startX = ev.touches[0].clientX;
        }, { passive: true });
        stage.addEventListener('touchend', function (ev) {
            if (startX === null) return;
            var dx = ev.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 40) go(dx < 0 ? index + 1 : index - 1);
            startX = null;
        });

        root.setAttribute('tabindex', '0');
        root.setAttribute('aria-roledescription', 'галерея');
        root.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowLeft') { ev.preventDefault(); go(index - 1); }
            if (ev.key === 'ArrowRight') { ev.preventDefault(); go(index + 1); }
        });

        go(0);
    });
}

// Лента на главной подстраивается под высоту главной новости
function initFeedFit() {
    var feed = document.querySelector('.top-feed');
    if (!feed) return;
    var wide = window.matchMedia('(min-width: 761px)');

    function fit() {
        var items = feed.querySelectorAll('.feed-item');
        feed.classList.remove('top-feed--free');
        Array.prototype.forEach.call(items, function (it) { it.hidden = false; });
        if (!wide.matches) return;
        var shown = items.length;
        while (shown > 1 && feed.scrollHeight > feed.clientHeight + 1) {
            shown--;
            items[shown].hidden = true;
        }
        if (feed.scrollHeight > feed.clientHeight + 1) feed.classList.add('top-feed--free');
    }

    var timer = null;
    function later() {
        clearTimeout(timer);
        timer = setTimeout(fit, 120);
    }

    fit();
    window.addEventListener('load', fit);
    window.addEventListener('resize', later);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
    Array.prototype.forEach.call(document.querySelectorAll('.top-main img, .top-side img'), function (im) {
        if (!im.complete) im.addEventListener('load', later);
    });
}