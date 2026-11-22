<?php
// Страница лонгрида. Подключается из article.php, переменные $article и $isAdmin уже заданы
$lr = load_longread($article['id']);
$lrHeader = $article['lr_header'] ?? 'mini';
if (!array_key_exists($lrHeader, LONGREAD_HEADERS)) {
    $lrHeader = 'mini';
}
$articleTags = article_tags($article);
$sectionKey = primary_section($article);
$pageTitle = $article['title'];
$metaDescription = !empty($article['lead']) ? $article['lead'] : $article['title'];
$ogImage = $article['cover'] ?? '';
$activeNav = $sectionKey !== '' ? $sectionKey : 'news';
$extraHead = '<link href="' . e(LONGREAD_FONTS) . '" rel="stylesheet">' . "\n"
    . '<link rel="stylesheet" href="' . e(url('assets/longread.css')) . '">' . "\n"
    . '<script>document.documentElement.classList.add("lr-anim");</script>' . "\n"
    . ($lr['css'] !== '' ? '<style>' . $lr['css'] . '</style>' . "\n" : '');

if ($lrHeader === 'site') {
    include __DIR__ . '/partials/header.php';
} else {
    $fullTitle = $pageTitle . ' — ' . SITE_NAME;
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <?php if ($ogImage): ?>
    <meta property="og:image" content="<?= e(abs_url($ogImage)) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
    <?= $extraHead ?>
</head>
<body class="lr-body">
<header class="lr-topbar lr-topbar--<?= e($lrHeader) ?>">
    <a class="lr-topbar-logo" href="<?= e(url('')) ?>" aria-label="<?= e(SITE_NAME) ?> — на главную">
        <img src="<?= e(url('assets/logo.png')) ?>" alt="<?= e(SITE_NAME) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
        <span style="display:none"><?= e(SITE_NAME) ?></span>
    </a>
    <a class="lr-topbar-back" href="<?= e(url('')) ?>">На главную »</a>
</header>
    <?php
}
?>
<div class="lr-progress" aria-hidden="true"><span></span></div>

<?php if (!is_published($article)): list(, $statusLabel) = article_status($article); ?>
<div class="lr-preview-note">Предпросмотр для редактора. Статус: <?= e($statusLabel) ?>, публикация <?= e(ru_datetime($article['published_at'])) ?>. Читатели этот лонгрид пока не видят.</div>
<?php endif; ?>

<main class="lr-page">
<?= $lr['html'] !== '' ? $lr['html'] : '<div class="lr-empty">Страница лонгрида пока пустая. Откройте конструктор в админ-панели и сохраните страницу.</div>' ?>

<?php if ($articleTags): ?>
<div class="lr-tags" aria-label="Подрубрики">
    <?php foreach ($articleTags as $tagName): ?>
    <a class="tag" href="<?= e(tag_url($tagName)) ?>">#<?= e($tagName) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</main>

<script src="<?= e(url('assets/longread.js')) ?>"></script>
<?php include __DIR__ . '/partials/footer.php'; ?>