<?php
// Генератор статической версии сайта
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/longread-lib.php';

$dist = __DIR__ . '/dist';

// Рекурсивное копирование папки
function copy_dir($src, $dst) {
    if (!is_dir($src)) return;
    @mkdir($dst, 0775, true);
    foreach (scandir($src) as $f) {
        if ($f === '.' || $f === '..') continue;
        $from = "$src/$f";
        $to = "$dst/$f";
        if (is_dir($from)) copy_dir($from, $to);
        else @copy($from, $to);
    }
}

// Очистка и подготовка папки dist
if (is_dir($dist)) {
    foreach (glob("$dist/*") as $file) {
        if (is_file($file)) unlink($file);
    }
} else {
    mkdir($dist, 0775, true);
}

echo "Копирование ресурсов...\n";
copy_dir(__DIR__ . '/assets', "$dist/assets");
copy_dir(__DIR__ . '/uploads', "$dist/uploads");

// 1. Рендеринг главной страницы
echo "Генерация index.html...\n";
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_HOST'] = 'localhost';

ob_start();
require __DIR__ . '/index.php';
$html = ob_get_clean();

// Заменяем ссылки вида article.php?id=XXX на XXX.html
$html = preg_replace('~article\.php\?id=([a-zA-Z0-9_-]+)~', '$1.html', $html);
file_put_contents("$dist/index.html", $html);

// 2. Рендеринг всех опубликованных материалов и лонгридов
$articles = published_articles();
echo "Генерация статей (" . count($articles) . " шт.)...\n";

foreach ($articles as $a) {
    $_GET['id'] = $a['id'];
    ob_start();
    require __DIR__ . '/article.php';
    $pageHtml = ob_get_clean();

    $pageHtml = preg_replace('~article\.php\?id=([a-zA-Z0-9_-]+)~', '$1.html', $pageHtml);
    file_put_contents("$dist/" . $a['id'] . ".html", $pageHtml);
}

echo "Готово! Все файлы сохранены в папку dist/\n";