<?php
require_once __DIR__ . '/auth.php';
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
    exit('Материал не найден.');
}
if ($index !== null && ($articles[$index]['type'] ?? '') === 'longread') {
    header('Location: longread.php?id=' . rawurlencode($id));
    exit;
}
$article = $index !== null ? $articles[$index] : [
    'id'           => '',
    'blocks'       => ['feed'],
    'published_at' => time(),
    'draft'        => false,
];
$article += [
    'title'         => '',
    'lead'          => '',
    'author'        => '',
    'cover'         => '',
    'cover_caption' => '',
    'body'          => '',
    'tags'          => [],
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
    $article['body'] = sanitize_html((string)($_POST['body'] ?? ''));
    $article['draft'] = !empty($_POST['draft']);

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
            $errors[] = 'Заглавное фото: ' . $ex->getMessage();
        }
    }

    if ($article['title'] === '') {
        $errors[] = 'Заполните заголовок.';
    }
    if (!$article['blocks']) {
        $errors[] = 'Выберите хотя бы один блок, в котором будет показан материал.';
    }

    if (!$errors) {
        $article['updated_at'] = time();
        unset($article['rubric']);
        if ($index === null) {
            $article['id'] = new_article_id($articles);
            $article['created_at'] = time();
            $articles[] = $article;
        } else {
            $articles[$index] = $article;
        }
        try {
            save_articles($articles);
            header('Location: edit.php?id=' . rawurlencode($article['id']) . '&saved=1');
            exit;
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
}

$isNew = $article['id'] === '';
list($statusKey, $statusLabel) = article_status($article);
$adminTitle = $isNew ? 'Новый материал' : 'Редактирование';
include __DIR__ . '/layout-top.php';
?>
<div class="page-head">
    <h1><?= $isNew ? 'Новый материал' : 'Редактирование' ?></h1>
    <a class="btn btn-light" href="index.php">← К списку</a>
</div>

<?php if (isset($_GET['saved']) && !$errors): ?>
<div class="alert alert-ok">Материал сохранён.</div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form id="article-form" class="edit-grid" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="edit-main">
        <div class="panel">
            <div class="field">
                <label for="title">Заголовок</label>
                <input type="text" id="title" name="title" class="input-title" value="<?= e($article['title']) ?>" maxlength="300" required>
            </div>
            <div class="field">
                <label for="lead">Подзаголовок (лид)</label>
                <textarea id="lead" name="lead" class="input-lead" maxlength="1000"><?= e($article['lead']) ?></textarea>
                <div class="hint">Короткое описание. Показывается под заголовком на главной, в разделах и в самом материале.</div>
            </div>
            <div class="field">
                <label for="author">Автор</label>
                <input type="text" id="author" name="author" value="<?= e($article['author']) ?>" maxlength="150">
                <div class="hint">Выводится как «Автор: ... • дата». Можно оставить пустым.</div>
            </div>
            <div class="field">
                <span class="label">Текст материала</span>
                <div class="editor">
                    <div class="editor-toolbar">
                        <button type="button" data-cmd="undo" title="Отменить последнее действие (Ctrl+Z)" disabled>Отменить</button>
                        <button type="button" data-cmd="redo" title="Повторить отменённое (Ctrl+Y)" disabled>Повторить</button>
                        <span class="sep"></span>
                        <button type="button" data-cmd="bold" title="Жирный (Ctrl+B)"><b>Ж</b></button>
                        <button type="button" data-cmd="italic" title="Курсив (Ctrl+I)"><i>К</i></button>
                        <span class="sep"></span>
                        <button type="button" data-cmd="h2" title="Подзаголовок внутри текста">Подзаголовок</button>
                        <button type="button" data-cmd="quote" title="Цитата: выделите текст и нажмите. Повторное нажатие внутри цитаты убирает оформление">Цитата</button>
                        <button type="button" data-cmd="ul" title="Маркированный список">Список</button>
                        <span class="sep"></span>
                        <button type="button" data-cmd="link" title="Гиперссылка (Ctrl+K)">Ссылка</button>
                        <button type="button" data-cmd="unlink" title="Убрать ссылку">Убрать ссылку</button>
                        <button type="button" data-cmd="image" title="Вставить фото в текст">Фото в текст</button>
                        <button type="button" data-cmd="gallery" title="Галерея-карусель из нескольких фото">Галерея</button>
                        <button type="button" data-cmd="embed" title="Видео, график или другой элемент с внешнего сайта">Вставка iframe</button>
                        <span class="sep"></span>
                        <button type="button" data-cmd="clear" title="Убрать форматирование выделенного текста">Очистить формат</button>
                    </div>
                    <div class="editor-area" id="editor" contenteditable="true" data-placeholder="Начните писать текст материала…"><?= $article['body'] ?></div>
                    <div class="editor-status" id="editor-status"></div>
                </div>
                <textarea name="body" id="body-field" hidden><?= e($article['body']) ?></textarea>
                <input type="file" id="image-input" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
                <input type="file" id="gallery-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
            </div>
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
                <div class="hint">Московское время. Если указать время в будущем, материал появится на сайте автоматически.</div>
            </div>
            <label class="check"><input type="checkbox" name="draft" value="1" <?= !empty($article['draft']) ? 'checked' : '' ?>> Черновик (не показывать на сайте)</label>
            <div class="side-actions">
                <button type="submit" class="btn btn-green">Сохранить</button>
                <?php if (!$isNew): ?>
                <a class="btn btn-light" href="<?= e(article_url($article)) ?>" target="_blank">Просмотр</a>
                <?php endif; ?>
            </div>
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
            <div class="hint">Можно выбрать несколько блоков сразу. Подпись над заголовком берётся из выбранного раздела.</div>
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
            <div class="hint">Выводятся хэштегами в конце материала. Клик по хэштегу открывает все материалы этой подрубрики.</div>
        </div>

        <div class="panel">
            <h2 class="panel-title">Заглавное фото</h2>
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
            <div class="hint">Для центральной новости лучше подходят горизонтальные фото с пропорцией 3:2, для разделов подойдёт 5:4.</div>
        </div>
    </aside>
</form>

<script src="../assets/editor.js"></script>
<?php include __DIR__ . '/layout-bottom.php'; ?>