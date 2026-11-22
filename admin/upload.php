<?php
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_logged()) {
    json_out(['ok' => false, 'error' => 'Нужно войти в админ-панель.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Неверный запрос.'], 405);
}
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
    json_out(['ok' => false, 'error' => 'Ошибка безопасности или файл слишком большой для сервера. Обновите страницу.'], 403);
}
if (empty($_FILES['image'])) {
    json_out(['ok' => false, 'error' => 'Файл не получен.'], 400);
}

try {
    $url = handle_upload($_FILES['image']);
    json_out(['ok' => true, 'url' => $url]);
} catch (RuntimeException $ex) {
    json_out(['ok' => false, 'error' => 'Ошибка: ' . $ex->getMessage()], 400);
}