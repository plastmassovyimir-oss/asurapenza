<?php
require_once __DIR__ . '/auth.php';

if (is_logged()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (hash_equals(ADMIN_LOGIN, $login) && hash_equals(ADMIN_PASSWORD, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    }
    sleep(1);
    $error = 'Неверный логин или пароль.';
}

$adminTitle = 'Вход';
include __DIR__ . '/layout-top.php';
?>
<div class="login-box panel">
    <h1 class="panel-title">Вход в админ-панель</h1>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field">
            <label for="login">Логин</label>
            <input type="text" id="login" name="login" required autofocus autocomplete="username">
        </div>
        <div class="field">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-green btn-wide">Войти</button>
    </form>
</div>
<?php include __DIR__ . '/layout-bottom.php'; ?>