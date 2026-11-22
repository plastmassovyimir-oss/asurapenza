(function () {
    var page = document.querySelector('.lr-page');
    if (!page) return;

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var items = Array.prototype.slice.call(page.querySelectorAll('[data-anim]:not([data-anim=""])'));

    items.forEach(function (el) {
        var delay = parseInt(el.getAttribute('data-anim-delay'), 10);
        if (delay > 0) el.style.transitionDelay = delay + 'ms';
    });

    if (!('IntersectionObserver' in window)) {
        items.forEach(function (el) { el.classList.add('is-in'); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
        items.forEach(function (el) { io.observe(el); });
    }

    // Счётчик для больших цифр: от нуля до значения
    function runCounter(el) {
        var original = el.textContent;
        var match = original.match(/\d[\d\s\u00a0]*/);
        if (!match) return;
        var raw = match[0].trim();
        if (!/^(\d{1,3}([\s\u00a0]\d{3})+|\d+)$/.test(raw)) return;
        var target = parseInt(raw.replace(/\D/g, ''), 10);
        if (!target || target > 1e12) return;
        var grouped = /[\s\u00a0]/.test(raw);
        var start = null;
        var duration = 1800;
        function format(n) {
            var s = String(n);
            return grouped ? s.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') : s;
        }
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min(1, (ts - start) / duration);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = original.replace(raw, format(Math.round(target * eased)));
            if (p < 1) requestAnimationFrame(step); else el.textContent = original;
        }
        requestAnimationFrame(step);
    }

    var counters = page.querySelectorAll('.lr-count');
    if (!reduce && counters.length && 'IntersectionObserver' in window) {
        var co = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    runCounter(entry.target);
                    co.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        Array.prototype.forEach.call(counters, function (el) { co.observe(el); });
    }

    // Видео на фоне обложки запускаются без звука
    Array.prototype.forEach.call(page.querySelectorAll('video[autoplay]'), function (v) {
        v.muted = true;
        var played = v.play();
        if (played && played.catch) played.catch(function () {});
    });

    // Полоса прогресса чтения
    var bar = document.querySelector('.lr-progress span');
    if (bar) {
        var update = function () {
            var h = document.documentElement.scrollHeight - window.innerHeight;
            var value = h > 0 ? Math.min(100, window.pageYOffset / h * 100) : 0;
            bar.style.width = value + '%';
        };
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    }
})();
.lr-carousel { max-width: 1000px; margin: 56px auto; padding: 0 24px; }
.news-gallery:not(.is-ready) {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
}
.news-gallery:not(.is-ready) > figure { margin: 0; }
.news-gallery:not(.is-ready) img { width: 100%; height: auto; aspect-ratio: 4 / 3; object-fit: cover; }
.news-gallery figcaption { margin-top: 6px; font-size: 14px; color: #75695e; }