<?php
require_once __DIR__ . '/../lib.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate');

function is_logged()
{
    return !empty($_SESSION['admin']);
}

function require_login()
{
    if (!is_logged()) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf()
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Ошибка безопасности формы. Обновите страницу и попробуйте снова. Если вы загружали большой файл, возможно, он превышает лимит сервера.');
    }
}