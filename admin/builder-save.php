<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../longread-lib.php';

header('Content-Type: application/json; charset=utf-8');

function lr_out(array $data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_logged()) {
    lr_out(['ok' => false, 'error' => 'Сессия закончилась. Войдите в админ-панель в соседней вкладке, вернитесь сюда и нажмите «Сохранить» ещё раз. Изменения на странице не пропадут.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lr_out(['ok' => false, 'error' => 'Неверный запрос.'], 405);
}
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
    lr_out(['ok' => false, 'error' => 'Ошибка безопасности или страница слишком большая для настроек сервера. Обновите страницу конструктора.'], 403);
}

$id = (string)($_POST['id'] ?? '');
$projectRaw = (string)($_POST['project'] ?? '');
$html = (string)($_POST['html'] ?? '');
$css = (string)($_POST['css'] ?? '');

if (!is_object(json_decode($projectRaw))) {
    lr_out(['ok' => false, 'error' => 'Данные страницы не получены или повреждены.'], 400);
}

try {
    $articles = load_articles(true);
    $index = null;
    foreach ($articles as $k => $a) {
        if ($a['id'] === $id) {
            $index = $k;
            break;
        }
    }
    if ($index === null || !is_longread($articles[$index])) {
        lr_out(['ok' => false, 'error' => 'Лонгрид не найден. Возможно, он был удалён.'], 404);
    }

    $cleanHtml = clean_longread_html($html);
    $cleanCss = clean_longread_css($css);
    save_longread($id, $projectRaw, $cleanHtml, $cleanCss);

    $articles[$index]['search_text'] = longread_plain_text($cleanHtml);
    $articles[$index]['updated_at'] = time();
    if (empty($articles[$index]['cover'])) {
        $first = longread_first_image($cleanHtml);
        if ($first !== '') {
            $articles[$index]['cover'] = $first;
        }
    }
    save_articles($articles);
} catch (RuntimeException $ex) {
    lr_out(['ok' => false, 'error' => $ex->getMessage()], 500);
}

lr_out(['ok' => true, 'saved_at' => date('H:i')]);