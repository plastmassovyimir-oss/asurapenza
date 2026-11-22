<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../longread-lib.php';
require_login();

$id = (string)($_GET['id'] ?? '');
$article = $id !== '' ? find_article($id) : null;
if (!$article || !is_longread($article)) {
    http_response_code(404);
    exit('Лонгрид не найден. Создайте его через кнопку «Создать лонгрид» в админ-панели.');
}

$lr = load_longread($id);

$assets = [];
if (is_dir(UPLOAD_DIR)) {
    $files = [];
    foreach (scandir(UPLOAD_DIR) as $f) {
        if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) {
            $files[$f] = filemtime(UPLOAD_DIR . '/' . $f);
        }
    }
    arsort($files);
    foreach (array_slice(array_keys($files), 0, 300) as $f) {
        $assets[] = url('uploads/' . $f);
    }
}

$config = [
    'id'           => $article['id'],
    'csrf'         => csrf_token(),
    'title'        => $article['title'],
    'lead'         => $article['lead'] ?? '',
    'cover'        => $article['cover'] ?? '',
    'previewUrl'   => article_url($article),
    'assets'       => $assets,
    'canvasStyles' => [LONGREAD_FONTS, url('assets/longread.css')],
    'canvasCss'    => '*{box-sizing:border-box}body{margin:0;background:#f4e8d2 url("' . url('assets/bg.jpg') . '") center top / cover;font-family:Literata,Georgia,serif;font-size:16px;line-height:1.5;color:#2a2522}img{max-width:100%}',
];
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$projectJs = $lr['project_raw'] !== '' ? str_replace('</', '<\/', $lr['project_raw']) : 'null';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Конструктор: <?= e($article['title']) ?> — <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21/dist/css/grapes.min.css">
    <link rel="stylesheet" href="../assets/builder.css">
</head>
<body class="lr-builder">
<header class="lr-bar">
    <a class="lr-bar-btn" href="longread.php?id=<?= e(rawurlencode($article['id'])) ?>">← Настройки</a>
    <div class="lr-bar-title" title="<?= e($article['title']) ?>"><?= e($article['title']) ?></div>
    <select id="lr-device" class="lr-bar-select" aria-label="Размер экрана">
        <option value="Компьютер">Компьютер</option>
        <option value="Планшет">Планшет</option>
        <option value="Телефон">Телефон</option>
    </select>
    <button type="button" id="lr-undo" class="lr-bar-btn" title="Отменить (Ctrl+Z)">Отменить</button>
    <button type="button" id="lr-redo" class="lr-bar-btn" title="Повторить (Ctrl+Shift+Z)">Повторить</button>
    <button type="button" id="lr-html" class="lr-bar-btn" title="Вставить свой HTML-код">HTML-код</button>
    <button type="button" id="lr-template" class="lr-bar-btn" title="Заменить страницу стартовым шаблоном">Шаблон</button>
    <button type="button" id="lr-clear" class="lr-bar-btn" title="Удалить всё со страницы">Очистить</button>
    <span id="lr-status" class="lr-status"></span>
    <button type="button" id="lr-preview" class="lr-bar-btn" title="Сохранить и открыть страницу на сайте">Открыть на сайте</button>
    <button type="button" id="lr-save" class="lr-bar-btn lr-bar-btn--main" title="Сохранить (Ctrl+S)">Сохранить</button>
</header>
<div id="gjs"></div>

<script>
window.LR_CONFIG = <?= json_encode($config, $jsonFlags) ?>;
window.LR_PROJECT = <?= $projectJs ?>;
</script>
<script src="https://unpkg.com/grapesjs@0.21/dist/grapes.min.js"></script>
<script src="../assets/builder.js"></script>
</body>
</html>