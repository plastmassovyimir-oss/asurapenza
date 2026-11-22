<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../longread-lib.php';
require_login();

try {
    $articles = load_articles(true);
} catch (RuntimeException $ex) {
    $adminTitle = 'Ошибка данных';
    include __DIR__ . '/layout-top.php';
    echo '<div class="alert alert-error">' . e($ex->getMessage()) . '</div>';
    include __DIR__ . '/layout-bottom.php';
    exit;
}

$id = (string)($_GET['id'] ?? '');
$index = null;
foreach ($articles as $k => $a) {
    if ($a['id'] === $id) {
        $index = $k;
        break;
    }
}
if ($id !== '' && $index === null) {
    http_response_code(404);
    exit('Лонгрид не найден.');
}
if ($index !== null && !is_longread($articles[$index])) {
    header('Location: edit.php?id=' . rawurlencode($id));
    exit;
}

$article = $index !== null ? $articles[$index] : [
    'id'           => '',
    'type'         => 'longread',
    'blocks'       => ['main', 'feed'],
    'published_at' => time(),
    'draft'        => true,
];
$article += [
    'title'         => '',
    'lead'          => '',
    'author'        => '',
    'cover'         => '',
    'cover_caption' => '',
    'body'          => '',
    'search_text'   => '',
    'tags'          => [],
    'lr_header'     => 'mini',
];
if (!is_array($article['tags'])) {
    $article['tags'] = [];
}

$blockHints = [
    'feed'    => 'Левая колонка «Лента» на главной',
    'main'    => 'Центр главной: самый свежий материал в центре, два предыдущих справа',
    'pov'     => 'Раздел POV на главной и в меню',
    'opinion' => 'Раздел «Мнение»: самый свежий показывается крупно',
    'square'  => 'Раздел «Белый квадрат» на главной и в меню',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $article['title'] = trim((string)($_POST['title'] ?? ''));
    $article['lead'] = trim((string)($_POST['lead'] ?? ''));
    $article['author'] = trim((string)($_POST['author'] ?? ''));
    $article['cover_caption'] = trim((string)($_POST['cover_caption'] ?? ''));
    $article['draft'] = !empty($_POST['draft']);

    $postedHeader = (string)($_POST['lr_header'] ?? 'mini');
    $article['lr_header'] = array_key_exists($postedHeader, LONGREAD_HEADERS) ? $postedHeader : 'mini';

    $postedBlocks = isset($_POST['blocks']) && is_array($_POST['blocks']) ? $_POST['blocks'] : [];
    $article['blocks'] = array_values(array_filter($postedBlocks, function ($b) {
        return is_string($b) && array_key_exists($b, BLOCKS);
    }));

    $postedTags = isset($_POST['tags']) && is_array($_POST['tags']) ? $_POST['tags'] : [];
    $article['tags'] = array_values(array_filter(TAGS, function ($t) use ($postedTags) {
        return in_array($t, $postedTags, true);
    }));

    $dt = DateTime::createFromFormat('!Y-m-d\TH:i', (string)($_POST['published_at'] ?? ''));
    if ($dt) {
        $article['published_at'] = $dt->getTimestamp();
    } else {
        $errors[] = 'Укажите корректные дату и время публикации.';
    }

    if (!empty($_POST['remove_cover'])) {
        $article['cover'] = '';
    }
    if (!empty($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $article['cover'] = handle_upload($_FILES['cover']);
        } catch (RuntimeException $ex) {
            $errors[] = 'Обложка: ' . $ex->getMessage();
        }
    }

    if ($article['title'] === '') {
        $errors[] = 'Заполните заголовок.';
    }
    if (!$article['blocks']) {
        $errors[] = 'Выберите хотя бы один блок, в котором будет показан лонгрид.';
    }

    if (!$errors) {
        $article['type'] = 'longread';
        $article['updated_at'] = time();
        unset($article['rubric']);
        $isCreate = $index === null;
        if ($isCreate) {
            $article['id'] = new_article_id($articles);
            $article['created_at'] = time();
            $articles[] = $article;
        } else {
            $articles[$index] = $article;
        }
        try {
            save_articles($articles);
            $goBuilder = $isCreate || ($_POST['go'] ?? '') === 'builder';
            header('Location: ' . ($goBuilder ? 'builder.php?id=' : 'longread.php?saved=1&id=') . rawurlencode($article['id']));
            exit;
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
}

$isNew = $article['id'] === '';
$lrInfo = $isNew ? null : load_longread($article['id']);
list($statusKey, $statusLabel) = article_status($article);
$adminTitle = $isNew ? 'Новый лонгрид' : 'Лонгрид: настройки';
include __DIR__ . '/layout-top.php';
?>
<div class="page-head">
    <h1><?= $isNew ? 'Новый лонгрид' : 'Лонгрид: настройки' ?></h1>
    <a class="btn btn-light" href="index.php">← К списку</a>
</div>

<?php if (isset($_GET['saved']) && !$errors): ?>
<div class="alert alert-ok">Настройки лонгрида сохранены.</div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="edit-grid" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="edit-main">
        <div class="panel">
            <div class="field">
                <label for="title">Заголовок</label>
                <input type="text" id="title" name="title" class="input-title" value="<?= e($article['title']) ?>" maxlength="300" required>
                <div class="hint">Показывается в ленте, разделах и поиске. На самой странице лонгрида заголовок задаётся в конструкторе.</div>
            </div>
            <div class="field">
                <label for="lead">Подзаголовок (лид)</label>
                <textarea id="lead" name="lead" class="input-lead" maxlength="1000"><?= e($article['lead']) ?></textarea>
            </div>
            <div class="field">
                <label for="author">Автор</label>
                <input type="text" id="author" name="author" value="<?= e($article['author']) ?>" maxlength="150">
            </div>
        </div>

        <div class="panel">
            <h2 class="panel-title">Страница лонгрида</h2>
            <?php if ($isNew): ?>
            <p class="lr-meta-note">После создания откроется конструктор со стартовым шаблоном. В шаблон подставятся заголовок, лид и обложка.</p>
            <button type="submit" class="btn btn-green">Создать и открыть конструктор</button>
            <?php else: ?>
            <p class="lr-meta-note">
                <?php if ($lrInfo && $lrInfo['updated_at']): ?>
                Страница последний раз сохранена <?= e(ru_datetime($lrInfo['updated_at'])) ?>.
                <?php else: ?>
                Страница ещё не сохранялась в конструкторе.
                <?php endif; ?>
            </p>
            <div class="page-head-actions">
                <a class="btn btn-green" href="builder.php?id=<?= e(rawurlencode($article['id'])) ?>">Открыть конструктор</a>
                <button type="submit" name="go" value="builder" class="btn btn-light">Сохранить настройки и открыть конструктор</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="sticky-side">
        <div class="panel">
            <h2 class="panel-title">Публикация</h2>
            <?php if (!$isNew): ?>
            <p class="status status-<?= e($statusKey) ?>"><?= e($statusLabel) ?></p>
            <?php endif; ?>
            <div class="field">
                <label for="published_at">Дата и время публикации</label>
                <input type="datetime-local" id="published_at" name="published_at" value="<?= e(date('Y-m-d\TH:i', (int)$article['published_at'])) ?>" required>
            </div>
            <label class="check"><input type="checkbox" name="draft" value="1" <?= !empty($article['draft']) ? 'checked' : '' ?>> Черновик (не показывать на сайте)</label>
            <div class="hint">Новый лонгрид создаётся черновиком. Снимите галочку, когда страница будет готова.</div>
            <div class="side-actions">
                <button type="submit" class="btn btn-green">Сохранить</button>
                <?php if (!$isNew): ?>
                <a class="btn btn-light" href="<?= e(article_url($article)) ?>" target="_blank">Просмотр</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <h2 class="panel-title">Шапка лонгрида</h2>
            <?php foreach (LONGREAD_HEADERS as $key => $label): ?>
            <label class="check"><input type="radio" name="lr_header" value="<?= e($key) ?>" <?= $article['lr_header'] === $key ? 'checked' : '' ?>> <?= e($label) ?></label>
            <?php endforeach; ?>
        </div>

        <div class="panel">
            <h2 class="panel-title">Где показывать</h2>
            <?php foreach (BLOCKS as $key => $label): ?>
            <label class="check">
                <input type="checkbox" name="blocks[]" value="<?= e($key) ?>" <?= in_array($key, $article['blocks'], true) ? 'checked' : '' ?>>
                <?= e($label) ?>
            </label>
            <span class="block-desc"><?= e($blockHints[$key] ?? '') ?></span>
            <?php endforeach; ?>
        </div>

        <div class="panel">
            <h2 class="panel-title">Подрубрики</h2>
            <div class="tags-grid">
                <?php foreach (TAGS as $tagName): ?>
                <label class="check">
                    <input type="checkbox" name="tags[]" value="<?= e($tagName) ?>" <?= in_array($tagName, $article['tags'], true) ? 'checked' : '' ?>>
                    <?= e($tagName) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <h2 class="panel-title">Обложка для ленты и соцсетей</h2>
            <div class="cover-preview" id="cover-preview">
                <?php if (!empty($article['cover'])): ?>
                <img src="<?= e($article['cover']) ?>" alt="">
                <?php else: ?>
                <span>Фото не выбрано</span>
                <?php endif; ?>
            </div>
            <input type="file" name="cover" id="cover-input" accept="image/jpeg,image/png,image/webp,image/gif">
            <?php if (!empty($article['cover'])): ?>
            <label class="check"><input type="checkbox" name="remove_cover" value="1"> Удалить текущее фото</label>
            <?php endif; ?>
            <div class="field field-top">
                <label for="cover_caption">Подпись или автор фото</label>
                <input type="text" id="cover_caption" name="cover_caption" value="<?= e($article['cover_caption']) ?>" maxlength="300">
            </div>
            <div class="hint">Если обложку не загрузить, при сохранении в конструкторе возьмётся первое фото со страницы.</div>
        </div>
    </aside>
</form>

<script>
(function () {
    var input = document.getElementById('cover-input');
    var preview = document.getElementById('cover-preview');
    if (!input || !preview) return;
    input.addEventListener('change', function () {
        var file = input.files[0];
        if (!file) return;
        preview.innerHTML = '';
        var img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        preview.appendChild(img);
    });
})();
</script>
<?php include __DIR__ . '/layout-bottom.php'; ?>