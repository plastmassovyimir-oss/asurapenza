<?php
require_once __DIR__ . '/lib.php';

// Главное: самый свежий материал в центре, два предыдущих справа
$main = articles_in_block('main', 3);
$leadNews = $main[0] ?? null;
$sideNews = array_slice($main, 1, 2);

$feed = articles_in_block('feed', HOME_LIMITS['feed']);
$square = articles_in_block('square', HOME_LIMITS['square']);
$pov = articles_in_block('pov', HOME_LIMITS['pov']);
$opinions = articles_in_block('opinion', HOME_LIMITS['opinion']);

function render_section_head($key, $title)
{
    ?>
    <div class="section-head">
        <h2 class="section-title"><a href="<?= e(section_url($key)) ?>"><?= e($title) ?></a></h2>
        <a class="section-more" href="<?= e(section_url($key)) ?>">Смотреть все »</a>
    </div>
    <?php
}

function render_card(array $a, $label)
{
    ?>
    <article class="card">
        <a class="card-media" href="<?= e(article_url($a)) ?>" tabindex="-1">
            <?php if (!empty($a['cover'])): ?>
            <img src="<?= e($a['cover']) ?>" alt="<?= e($a['title']) ?>" loading="lazy">
            <?php else: ?>
            <span class="noimg"><?= e(SITE_NAME) ?></span>
            <?php endif; ?>
        </a>
        <h3 class="card-title"><a href="<?= e(article_url($a)) ?>"><?= e($a['title']) ?></a></h3>
        <?php if (!empty($a['lead'])): ?>
        <p class="card-lead"><?= e(excerpt($a['lead'], 170)) ?></p>
        <?php endif; ?>
        <div class="card-meta"><?= e(ru_date($a['published_at'])) ?><span class="dot">•</span><?= e($label) ?></div>
    </article>
    <?php
}

function render_cards_section($key, array $items)
{
    ?>
    <section class="section-wrap" aria-label="<?= e(BLOCKS[$key]) ?>">
        <div class="container section">
            <?php render_section_head($key, BLOCKS[$key]); ?>
            <?php if (!$items): ?>
            <p class="empty">Материалы появятся здесь после публикации в админ-панели.</p>
            <?php else: ?>
            <div class="cards">
                <?php foreach ($items as $item) { render_card($item, BLOCKS[$key]); } ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

$pageTitle = '';
$activeNav = '';
include __DIR__ . '/partials/header.php';
?>
<main>
    <div class="container">
        <section class="top">
            <aside class="top-feed" aria-label="Лента новостей">
                <a class="feed-head" href="<?= e(section_url('feed')) ?>">Лента</a>
                <?php if (!$feed): ?>
                <p class="empty">Новости появятся здесь после публикации.</p>
                <?php endif; ?>
                <?php foreach ($feed as $item): ?>
                <article class="feed-item <?= empty($item['cover']) ? 'feed-item--noimg' : '' ?>">
                    <?php if (!empty($item['cover'])): ?>
                    <a class="feed-thumb" href="<?= e(article_url($item)) ?>" tabindex="-1">
                        <img src="<?= e($item['cover']) ?>" alt="" loading="lazy">
                    </a>
                    <?php endif; ?>
                    <a class="feed-title" href="<?= e(article_url($item)) ?>"><?= e($item['title']) ?></a>
                    <?php if (!empty($item['lead'])): ?>
                    <p class="feed-lead"><?= e(excerpt($item['lead'], 100)) ?></p>
                    <?php endif; ?>
                    <time class="feed-time"><?= e(feed_time($item['published_at'])) ?></time>
                </article>
                <?php endforeach; ?>
                <a class="more-link" href="<?= e(section_url('feed')) ?>">Вся лента »</a>
            </aside>

            <div class="top-main">
                <?php if ($leadNews): ?>
                <article class="lead-news">
                    <a class="lead-media" href="<?= e(article_url($leadNews)) ?>" tabindex="-1">
                        <?php if (!empty($leadNews['cover'])): ?>
                        <img src="<?= e($leadNews['cover']) ?>" alt="<?= e($leadNews['title']) ?>">
                        <?php else: ?>
                        <span class="noimg noimg--big"><?= e(SITE_NAME) ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="kicker" href="<?= e(section_url('main')) ?>">Главное</a>
                    <h1 class="lead-title"><a href="<?= e(article_url($leadNews)) ?>"><?= e($leadNews['title']) ?></a></h1>
                    <?php if (!empty($leadNews['lead'])): ?>
                    <p class="lead-text"><?= e($leadNews['lead']) ?></p>
                    <?php endif; ?>
                    <?= byline_html($leadNews) ?>
                </article>
                <?php else: ?>
                <div class="lead-news">
                    <div class="lead-media"><span class="noimg noimg--big"><?= e(SITE_NAME) ?></span></div>
                    <div class="kicker">Главное</div>
                    <p class="lead-text">Отметьте материал блоком «Главное» в админ-панели, и он появится здесь.</p>
                </div>
                <?php endif; ?>
            </div>

            <aside class="top-side" aria-label="Другие главные новости">
                <?php foreach ($sideNews as $s): ?>
                <article class="side-item">
                    <a class="side-media" href="<?= e(article_url($s)) ?>" tabindex="-1">
                        <?php if (!empty($s['cover'])): ?>
                        <img src="<?= e($s['cover']) ?>" alt="<?= e($s['title']) ?>" loading="lazy">
                        <?php else: ?>
                        <span class="noimg"><?= e(SITE_NAME) ?></span>
                        <?php endif; ?>
                    </a>
                    <h2 class="side-title"><a href="<?= e(article_url($s)) ?>"><?= e($s['title']) ?></a></h2>
                    <?php if (!empty($s['lead'])): ?>
                    <p class="side-lead"><?= e(excerpt($s['lead'], 120)) ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </aside>
        </section>
    </div>

    <?php render_cards_section('square', $square); ?>
    <?php render_cards_section('pov', $pov); ?>

    <section class="section-wrap" aria-label="Мнение">
        <div class="container section">
            <?php render_section_head('opinion', BLOCKS['opinion']); ?>
            <?php if (!$opinions): ?>
            <p class="empty">Материалы появятся здесь после публикации в админ-панели.</p>
            <?php else: $op = $opinions[0]; ?>
            <article class="opinion-feature">
                <a class="opinion-media" href="<?= e(article_url($op)) ?>" tabindex="-1">
                    <?php if (!empty($op['cover'])): ?>
                    <img src="<?= e($op['cover']) ?>" alt="<?= e($op['title']) ?>" loading="lazy">
                    <?php else: ?>
                    <span class="noimg noimg--big"><?= e(SITE_NAME) ?></span>
                    <?php endif; ?>
                </a>
                <div class="opinion-body">
                    <div class="kicker">Мнение</div>
                    <h3 class="opinion-title"><a href="<?= e(article_url($op)) ?>"><?= e($op['title']) ?></a></h3>
                    <?php if (!empty($op['lead'])): ?>
                    <p class="opinion-lead"><?= e($op['lead']) ?></p>
                    <?php endif; ?>
                    <?= byline_html($op) ?>
                </div>
            </article>
            <?php if (count($opinions) > 1): ?>
            <div class="cards cards--after">
                <?php foreach (array_slice($opinions, 1) as $item) { render_card($item, BLOCKS['opinion']); } ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>