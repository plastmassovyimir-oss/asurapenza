<?php
$fullTitle = !empty($pageTitle) ? $pageTitle . ' — ' . SITE_NAME : SITE_NAME;
$activeNav = $activeNav ?? '';
$metaDescription = $metaDescription ?? SITE_NAME . ' — ' . SITE_TAGLINE;
$ogImage = $ogImage ?? '';
$searchOpen = $activeNav === 'search';
$searchValue = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
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
    <link href="https://fonts.googleapis.com/css2?family=Literata:ital,wght@0,400;0,600;0,700;1,400&family=Cormorant:wght@600;700&family=Old+Standard+TT:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
    <?= $extraHead ?? '' ?>
</head>
<body>

<div class="container">
    <header class="masthead">
        <div class="masthead-left">
            <a class="icon-btn" href="<?= e(url('list.php?search=1')) ?>" data-action="search" aria-label="Поиск по сайту" title="Поиск по сайту" aria-controls="searchbar" aria-expanded="<?= $searchOpen ? 'true' : 'false' ?>">
                <svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>
            </a>
        </div>
        <a class="logo" href="<?= e(url('')) ?>" aria-label="<?= e(SITE_NAME) ?> — на главную">
            <img class="logo-img" src="<?= e(url('assets/logo.png')) ?>" alt="<?= e(SITE_NAME) ?>"
                 onerror="this.parentNode.classList.add('logo--text'); this.remove();">
            <span class="logo-text"><?= e(SITE_NAME) ?></span>
            <span class="tagline"><?= e(SITE_TAGLINE) ?></span>
        </a>
        <div class="masthead-right social-list">
            <?php foreach (SOCIALS as $socialKey => $social): $external = $social[1] !== '#' && stripos($social[1], 'mailto:') !== 0; ?>
            <a class="social" href="<?= e($social[1]) ?>" aria-label="<?= e($social[0]) ?>" title="<?= e($social[0]) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= social_icon($socialKey) ?></a>
            <?php endforeach; ?>
        </div>
    </header>

    <nav class="mainnav" aria-label="Разделы">
        <?php foreach (NAV as $navIndex => $navItem):
            $navKey = $navItem[1];
            $isActive = ($navKey === '' && $activeNav === 'news') || ($navKey !== '' && $activeNav === $navKey); ?>
            <?php if ($navIndex > 0): ?><span class="mainnav-dot" aria-hidden="true">•</span><?php endif; ?>
            <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e(section_url($navKey)) ?>"><?= e($navItem[0]) ?></a>
        <?php endforeach; ?>
    </nav>

    <form class="searchbar <?= $searchOpen ? 'is-open' : '' ?>" id="searchbar" action="<?= e(url('list.php')) ?>" method="get" role="search" data-endpoint="<?= e(url('search.php')) ?>">
        <div class="searchbar-row">
            <input type="search" name="q" placeholder="Поиск по новостям: слово, фамилия или подрубрика" value="<?= e($searchValue) ?>" aria-label="Поиск по новостям" autocomplete="off"<?= $searchOpen && trim($searchValue) === '' ? ' autofocus' : '' ?>>
            <button type="submit">Найти</button>
            <button type="button" class="searchbar-close" data-action="search-close" aria-label="Закрыть поиск" title="Закрыть поиск">
                <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div class="search-results" id="search-results" hidden></div>
    </form>
</div>