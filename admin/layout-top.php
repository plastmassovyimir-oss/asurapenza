<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($adminTitle ?? 'Админ-панель') ?> — <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Old+Standard+TT:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="admin">
<header class="admin-top">
    <div class="admin-top-inner">
        <a class="admin-logo" href="index.php"><?= e(SITE_NAME) ?><span>админ-панель</span></a>
        <?php if (is_logged()): ?>
        <nav class="admin-nav">
            <a href="index.php">Материалы</a>
            <a href="edit.php" class="btn btn-yellow btn-sm">+ Новый материал</a>
                        <a href="longread.php" class="btn btn-yellow btn-sm">+ Создать лонгрид</a>
            <a href="<?= e(url('')) ?>" target="_blank">Открыть сайт</a>
            <a href="logout.php">Выйти</a>
        </nav>
        <?php endif; ?>
    </div>
</header>
<main class="admin-main">