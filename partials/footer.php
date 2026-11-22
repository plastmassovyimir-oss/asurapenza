<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a class="footer-logo" href="<?= e(url('')) ?>" aria-label="<?= e(SITE_NAME) ?> — на главную">
                    <img src="<?= e(url('assets/logo.png')) ?>" alt="<?= e(SITE_NAME) ?>" onerror="this.replaceWith(document.createTextNode(this.alt));">
                </a>
                <p class="footer-tagline"><?= e(SITE_TAGLINE) ?></p>
                <div class="social-list footer-social">
                    <?php foreach (SOCIALS as $socialKey => $social): $external = $social[1] !== '#' && stripos($social[1], 'mailto:') !== 0; ?>
                    <a class="social" href="<?= e($social[1]) ?>" aria-label="<?= e($social[0]) ?>" title="<?= e($social[0]) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= social_icon($socialKey) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <nav class="footer-col" aria-label="Разделы">
                <div class="footer-col-title">Разделы</div>
                <?php foreach (NAV as $navItem): ?>
                <a href="<?= e(section_url($navItem[1])) ?>"><?= e($navItem[0]) ?></a>
                <?php endforeach; ?>
            </nav>

            <nav class="footer-col" aria-label="Подрубрики">
                <div class="footer-col-title">Подрубрики</div>
                <?php foreach (TAGS as $tagName): ?>
                <a href="<?= e(tag_url($tagName)) ?>"><?= e($tagName) ?></a>
                <?php endforeach; ?>
            </nav>

            <nav class="footer-col" aria-label="Редакция">
                <div class="footer-col-title">Редакция</div>
                <a href="#">О редакции</a>
                <a href="#">Контакты</a>
                <a href="#">Реклама на сайте</a>
                <a href="#">Предложить новость</a>
                <a href="#">Политика конфиденциальности</a>
            </nav>
        </div>

        <div class="footer-legal">
            <p>Сетевое издание «<?= e(SITE_NAME) ?>». Свидетельство о регистрации СМИ ЭЛ № ФС 77-00000 от 1 января 2026 года выдано Федеральной службой по надзору в сфере связи, информационных технологий и массовых коммуникаций (Роскомнадзор).</p>
            <p>Учредитель: Фамилия Имя Отчество. Главный редактор: Фамилия Имя Отчество. Адрес редакции: 440000, г. Пенза, ул. Примерная, д. 1. Телефон: +7 (8412) 00-00-00. Электронная почта: redaktsiya@example.ru</p>
            <p>Все права на материалы сайта принадлежат редакции. При полном или частичном использовании материалов ссылка на «<?= e(SITE_NAME) ?>» обязательна. Мнение авторов может не совпадать с позицией редакции. Редакция не несёт ответственности за содержание рекламных материалов.</p>
        </div>

        <div class="footer-bottom">
            <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?>. Все права защищены.</span>
            <span class="footer-age">18+</span>
        </div>
    </div>
</footer>
<script src="<?= e(url('assets/main.js')) ?>"></script>
</body>
</html>