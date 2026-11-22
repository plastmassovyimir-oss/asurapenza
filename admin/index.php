<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../longread-lib.php';
require_login();

$loadError = '';
try {
    $all = load_articles(true);
} catch (RuntimeException $ex) {
    $all = [];
    $loadError = $ex->getMessage();
}

if (!$loadError && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    check_csrf();
    $deleteId = (string)($_POST['id'] ?? '');
    $rest = array_values(array_filter($all, function ($a) use ($deleteId) {
        return $a['id'] !== $deleteId;
    }));
    try {
        save_articles($rest);
        delete_longread($deleteId);
        header('Location: index.php?deleted=1');
        exit;
    } catch (RuntimeException $ex) {
        $loadError = $ex->getMessage();
    }
}

usort($all, function ($a, $b) {
    return $b['published_at'] - $a['published_at'];
});

$noBlock = function ($a) {
    return !array_intersect($a['blocks'], array_keys(BLOCKS));
};

$counts = [];
foreach (BLOCKS as $key => $label) {
    $counts[$key] = count(array_filter($all, function ($a) use ($key) {
        return in_array($key, $a['blocks'], true);
    }));
}
$counts['_none'] = count(array_filter($all, $noBlock));
$counts['_longread'] = count(array_filter($all, 'is_longread'));

$filter = (string)($_GET['block'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$list = $all;

if ($filter === '_none') {
    $list = array_values(array_filter($list, $noBlock));
} elseif ($filter === '_longread') {
    $list = array_values(array_filter($list, 'is_longread'));
} elseif ($filter !== '' && array_key_exists($filter, BLOCKS)) {
    $list = array_values(array_filter($list, function ($a) use ($filter) {
        return in_array($filter, $a['blocks'], true);
    }));
} else {
    $filter = '';
}

if ($q !== '') {
    $list = array_values(array_filter($list, function ($a) use ($q) {
        return text_contains($a['title'] . ' ' . ($a['lead'] ?? '') . ' ' . ($a['author'] ?? ''), $q);
    }));
}

$perPage = 30;
$total = count($list);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$shown = array_slice($list, ($page - 1) * $perPage, $perPage);

function admin_link(array $params)
{
    $merged = array_merge($_GET, ['deleted' => ''], $params);
    $merged = array_filter($merged, function ($v) {
        return $v !== '' && $v !== null;
    });
    return 'index.php' . ($merged ? '?' . http_build_query($merged) : '');
}

function edit_link(array $a)
{
    return (is_longread($a) ? 'longread.php' : 'edit.php') . '?id=' . rawurlencode($a['id']);
}

$adminTitle = 'Материалы';
include __DIR__ . '/layout-top.php';
?>
<div class="page-head">
    <h1>Материалы</h1>
    <div class="page-head-actions">
        <a class="btn btn-yellow" href="edit.php">+ Новый материал</a>
        <a class="btn btn-green" href="longread.php">+ Создать лонгрид</a>
    </div>
</div>

<?php if ($loadError): ?>
<div class="alert alert-error"><?= e($loadError) ?></div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-ok">Материал удалён.</div>
<?php endif; ?>

<div class="tabs">
    <a class="tab <?= $filter === '' ? 'is-active' : '' ?>" href="<?= e(admin_link(['block' => '', 'page' => ''])) ?>">Все<span><?= count($all) ?></span></a>
    <?php foreach (BLOCKS as $key => $label): ?>
    <a class="tab <?= $filter === $key ? 'is-active' : '' ?>" href="<?= e(admin_link(['block' => $key, 'page' => ''])) ?>"><?= e($label) ?><span><?= $counts[$key] ?></span></a>
    <?php endforeach; ?>
    <a class="tab <?= $filter === '_longread' ? 'is-active' : '' ?>" href="<?= e(admin_link(['block' => '_longread', 'page' => ''])) ?>">Лонгриды<span><?= $counts['_longread'] ?></span></a>
    <?php if ($counts['_none'] > 0): ?>
    <a class="tab <?= $filter === '_none' ? 'is-active' : '' ?>" href="<?= e(admin_link(['block' => '_none', 'page' => ''])) ?>">Без блока<span><?= $counts['_none'] ?></span></a>
    <?php endif; ?>
</div>

<form class="filters" method="get">
    <?php if ($filter !== ''): ?>
    <input type="hidden" name="block" value="<?= e($filter) ?>">
    <?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Поиск по заголовку, лиду или автору">
    <button type="submit" class="btn btn-green">Найти</button>
    <?php if ($q !== ''): ?>
    <a class="btn btn-light" href="<?= e(admin_link(['q' => '', 'page' => ''])) ?>">Сбросить</a>
    <?php endif; ?>
</form>

<p class="list-summary">Найдено материалов: <?= $total ?>. Страница <?= $page ?> из <?= $pages ?>.</p>

<div class="panel panel-flush">
    <?php if (!$shown): ?>
    <p class="empty-admin">Материалов не найдено. Нажмите «Новый материал» или «Создать лонгрид».</p>
    <?php else: ?>
    <table class="list-table">
        <thead>
            <tr>
                <th class="hide-m">Фото</th>
                <th>Заголовок</th>
                <th class="hide-m">Автор</th>
                <th class="hide-m">Блоки</th>
                <th>Публикация</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($shown as $a): list($statusKey, $statusLabel) = article_status($a); $knownBlocks = array_intersect($a['blocks'], array_keys(BLOCKS)); ?>
            <tr>
                <td class="hide-m">
                    <?php if (!empty($a['cover'])): ?>
                    <img class="t-thumb" src="<?= e($a['cover']) ?>" alt="">
                    <?php else: ?>
                    <span class="t-thumb"></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (is_longread($a)): ?>
                    <span class="chip chip-longread">Лонгрид</span>
                    <?php endif; ?>
                    <a class="t-title" href="<?= e(edit_link($a)) ?>"><?= e($a['title'] !== '' ? $a['title'] : 'Без заголовка') ?></a>
                    <?php if (!empty($a['lead'])): ?>
                    <div class="t-lead"><?= e(excerpt($a['lead'], 140)) ?></div>
                    <?php endif; ?>
                </td>
                <td class="hide-m"><?= e($a['author'] ?? '') ?></td>
                <td class="hide-m">
                    <?php foreach ($knownBlocks as $b): ?>
                    <span class="chip"><?= e(BLOCKS[$b]) ?></span>
                    <?php endforeach; ?>
                    <?php if (!$knownBlocks): ?>
                    <span class="chip chip-warn">нет блока</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="status status-<?= e($statusKey) ?>"><?= e($statusLabel) ?></div>
                    <div class="t-date"><?= e(ru_datetime($a['published_at'])) ?></div>
                </td>
                <td>
                    <div class="actions">
                        <a class="btn btn-light btn-sm" href="<?= e(edit_link($a)) ?>">Редактировать</a>
                        <?php if (is_longread($a)): ?>
                        <a class="btn btn-light btn-sm" href="builder.php?id=<?= e(rawurlencode($a['id'])) ?>">Конструктор</a>
                        <?php endif; ?>
                        <a class="btn btn-light btn-sm" href="<?= e(article_url($a)) ?>" target="_blank">Открыть</a>
                        <form method="post" data-confirm="<?= e('Удалить материал «' . $a['title'] . '»?') ?>" onsubmit="return confirm(this.getAttribute('data-confirm'));">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<nav class="admin-pages">
    <?php for ($n = 1; $n <= $pages; $n++): ?>
        <?php if ($n === $page): ?>
        <span class="is-current"><?= $n ?></span>
        <?php else: ?>
        <a href="<?= e(admin_link(['page' => $n])) ?>"><?= $n ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif; ?>
<?php include __DIR__ . '/layout-bottom.php'; ?>