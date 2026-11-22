<?php
require_once __DIR__ . '/longread-lib.php';

if (isset($_COOKIE[session_name()])) {
    session_start();
}
$isAdmin = !empty($_SESSION['admin']);

$article = find_article((string)($_GET['id'] ?? ''));

if (!$article || (!is_published($article) && !$isAdmin)) {
    http_response_code(404);
    $pageTitle = 'Материал не найден';
    include __DIR__ . '/partials/header.php';
    ?>
    <main class="container page">
        <div class="notfound">
            <h1 class="list-title-big">Материал не найден</h1>
            <p>Возможно, он был удалён или ещё не опубликован.</p>
            <a class="more-link" href="<?= e(url('')) ?>">На главную »</a>
        </div>
    </main>
    <?php
    include __DIR__ . '/partials/footer.php';
    exit;
}
if (is_longread($article)) {
    require __DIR__ . '/longread-view.php';
    exit;
}
$related = array_slice(array_values(array_filter(articles_in_block('feed'), function ($a) use ($article) {
    return $a['id'] !== $article['id'];
})), 0, 8);

$articleTags = article_tags($article);
$sectionKey = primary_section($article);
$pageTitle = $article['title'];
$metaDescription = !empty($article['lead']) ? $article['lead'] : $article['title'];
$ogImage = $article['cover'] ?? '';
$activeNav = $sectionKey !== '' ? $sectionKey : 'news';
include __DIR__ . '/partials/header.php';
?>
<main class="container page article-page">
    <article class="article">
        <?php if (!is_published($article)): list(, $statusLabel) = article_status($article); ?>
        <div class="preview-note">Предпросмотр для редактора. Статус: <?= e($statusLabel) ?>, публикация <?= e(ru_datetime($article['published_at'])) ?>. Читатели этот материал пока не видят.</div>
        <?php endif; ?>

        <a class="kicker" href="<?= e(section_url($sectionKey)) ?>"><?= e(section_label($article)) ?></a>
        <h1 class="article-title"><?= e($article['title']) ?></h1>

        <?php if (!empty($article['lead'])): ?>
        <p class="article-lead"><?= e($article['lead']) ?></p>
        <?php endif; ?>

        <?= byline_html($article, true) ?>

        <?php if (!empty($article['cover'])): ?>
        <figure class="article-cover">
            <img src="<?= e($article['cover']) ?>" alt="<?= e($article['title']) ?>">
            <?php if (!empty($article['cover_caption'])): ?>
            <figcaption><?= e($article['cover_caption']) ?></figcaption>
            <?php endif; ?>
        </figure>
        <?php endif; ?>

        <div class="article-body"><?= $article['body'] ?? '' ?></div>

        <?php if ($articleTags): ?>
        <div class="article-tags" aria-label="Подрубрики">
            <?php foreach ($articleTags as $tagName): ?>
            <a class="tag" href="<?= e(tag_url($tagName)) ?>">#<?= e($tagName) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </article>

    <aside class="article-aside">
        <a class="feed-head" href="<?= e(section_url('feed')) ?>">Лента</a>
        <?php foreach ($related as $item): ?>
        <article class="feed-item feed-item--noimg">
            <div class="feed-text">
                <a class="feed-title" href="<?= e(article_url($item)) ?>"><?= e($item['title']) ?></a>
                <time class="feed-time"><?= e(feed_time($item['published_at'])) ?></time>
            </div>
        </article>
        <?php endforeach; ?>
        <a class="more-link" href="<?= e(section_url('feed')) ?>">Вся лента »</a>
    </aside>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>