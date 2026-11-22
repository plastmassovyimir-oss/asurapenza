<?php
require_once __DIR__ . '/lib.php';

$section = isset($_GET['section']) && is_string($_GET['section']) ? $_GET['section'] : '';
$tag = isset($_GET['tag']) && is_string($_GET['tag']) ? $_GET['tag'] : '';
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$searchMode = $q !== '' || isset($_GET['search']);
$smallHeading = false;

if ($searchMode) {
    $activeNav = 'search';
    $smallHeading = true;
    $heading = $q !== '' ? 'Поиск: «' . $q . '»' : 'Поиск по сайту';
    $items = $q !== '' ? search_articles($q) : [];
} elseif ($tag !== '' && in_array($tag, TAGS, true)) {
    $activeNav = '';
    $heading = '#' . $tag;
    $items = array_values(array_filter(published_articles(), function ($a) use ($tag) {
        return in_array($tag, article_tags($a), true);
    }));
} elseif ($section !== '' && array_key_exists($section, BLOCKS)) {
    $heading = BLOCKS[$section];
    $activeNav = $section === 'feed' ? 'news' : $section;
    $items = articles_in_block($section);
} else {
    $heading = 'Новости';
    $activeNav = 'news';
    $items = published_articles();
}

$perPage = 20;
$total = count($items);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$items = array_slice($items, ($page - 1) * $perPage, $perPage);

$pageTitle = $heading;
include __DIR__ . '/partials/header.php';
?>
<main class="container page">
    <div class="section-head">
        <h1 class="section-title <?= $smallHeading ? 'section-title--small' : '' ?>"><?= e($heading) ?></h1>
    </div>

    <?php if ($searchMode): ?>
    <p class="search-summary">
        <?php if ($q !== ''): ?>
        Найдено материалов: <?= $total ?>.
        <?php else: ?>
        Введите слово или фразу в строке поиска выше. Поиск идёт по заголовкам, лидам, текстам, авторам и подрубрикам.
        <?php endif; ?>
    </p>
    <div class="tag-cloud">
        <span>Подрубрики:</span>
        <?php foreach (TAGS as $tagName): ?>
        <a class="tag" href="<?= e(tag_url($tagName)) ?>">#<?= e($tagName) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$items && !($searchMode && $q === '')): ?>
    <p class="empty">Здесь пока нет материалов.</p>
    <?php endif; ?>

    <div class="list">
        <?php foreach ($items as $a): $itemTags = article_tags($a); ?>
        <article class="list-item <?= empty($a['cover']) ? 'list-item--noimg' : '' ?>">
            <?php if (!empty($a['cover'])): ?>
            <a class="list-media" href="<?= e(article_url($a)) ?>" tabindex="-1">
                <img src="<?= e($a['cover']) ?>" alt="<?= e($a['title']) ?>" loading="lazy">
            </a>
            <?php endif; ?>
            <div>
                <div class="card-meta"><?= e(ru_datetime($a['published_at'])) ?><span class="dot">•</span><?= e(section_label($a)) ?></div>
                <h2 class="list-title"><a href="<?= e(article_url($a)) ?>"><?= e($a['title']) ?></a></h2>
                <?php if (!empty($a['lead'])): ?>
                <p class="list-lead"><?= e($a['lead']) ?></p>
                <?php endif; ?>
                <?php if (!empty($a['author'])): ?>
                <div class="byline"><span class="byline-author">Автор: <?= e($a['author']) ?></span></div>
                <?php endif; ?>
                <?php if ($itemTags): ?>
                <div class="list-tags">
                    <?php foreach ($itemTags as $tagName): ?>
                    <a class="tag" href="<?= e(tag_url($tagName)) ?>">#<?= e($tagName) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="pagination">
        <?php for ($n = 1; $n <= $pages; $n++): ?>
            <?php if ($n === $page): ?>
            <span class="is-current"><?= $n ?></span>
            <?php else: ?>
            <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $n]))) ?>"><?= $n ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>