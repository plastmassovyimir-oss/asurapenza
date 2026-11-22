<?php
require_once __DIR__ . '/config.php';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function abs_url($path)
{
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . $path;
}

// $strict = true используется в админке: при повреждённом файле выбрасывается ошибка,
// чтобы следующее сохранение не затёрло материалы
function load_articles($strict = false)
{
    if (!is_file(DATA_FILE)) {
        return [];
    }
    $raw = @file_get_contents(DATA_FILE);
    if ($raw === false) {
        if ($strict) {
            throw new RuntimeException('Не удалось прочитать файл данных data/articles.json. Проверьте права доступа.');
        }
        return [];
    }
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    if (trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        if ($strict) {
            throw new RuntimeException('Файл data/articles.json повреждён (' . json_last_error_msg() . '). Сохранение остановлено, чтобы не потерять материалы. Восстановите файл из резервной копии data/articles.json.bak.');
        }
        return [];
    }
    $out = [];
    foreach ($data as $a) {
        if (!is_array($a) || empty($a['id'])) {
            continue;
        }
        $a['id'] = (string)$a['id'];
        $a['blocks'] = isset($a['blocks']) && is_array($a['blocks']) ? array_values($a['blocks']) : [];
        $a['published_at'] = (int)($a['published_at'] ?? 0);
        $a['title'] = (string)($a['title'] ?? '');
        $out[] = $a;
    }
    return $out;
}

function save_articles(array $articles)
{
    $dir = dirname(DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode(array_values($articles), $flags);
    if ($json === false) {
        throw new RuntimeException('Не удалось подготовить данные к сохранению: ' . json_last_error_msg());
    }
    if (is_file(DATA_FILE) && filesize(DATA_FILE) > 0) {
        @copy(DATA_FILE, DATA_FILE . '.bak');
    }
    $tmp = DATA_FILE . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, DATA_FILE)) {
        throw new RuntimeException('Не удалось сохранить данные. Проверьте права на запись для папки data.');
    }
}

function new_article_id(array $articles)
{
    $ids = array_column($articles, 'id');
    do {
        $id = date('ymd') . bin2hex(random_bytes(4));
    } while (in_array($id, $ids, true));
    return $id;
}

function is_published(array $a)
{
    return empty($a['draft']) && !empty($a['published_at']) && (int)$a['published_at'] <= time();
}

function article_status(array $a)
{
    if (!empty($a['draft'])) {
        return ['draft', 'Черновик'];
    }
    if ((int)($a['published_at'] ?? 0) > time()) {
        return ['scheduled', 'Запланировано'];
    }
    return ['published', 'Опубликовано'];
}

function published_articles()
{
    static $cache = null;
    if ($cache === null) {
        $cache = array_values(array_filter(load_articles(), 'is_published'));
        usort($cache, function ($a, $b) {
            return (int)$b['published_at'] - (int)$a['published_at'];
        });
    }
    return $cache;
}

function articles_in_block($block, $limit = 0)
{
    $list = array_values(array_filter(published_articles(), function ($a) use ($block) {
        return in_array($block, $a['blocks'], true);
    }));
    return $limit > 0 ? array_slice($list, 0, $limit) : $list;
}

function find_article($id)
{
    foreach (load_articles() as $a) {
        if ($a['id'] === $id) {
            return $a;
        }
    }
    return null;
}

function article_url(array $a)
{
    return url('article.php?id=' . rawurlencode($a['id']));
}

// Основной раздел материала для подписи над заголовком
function primary_section(array $a)
{
    foreach (['opinion', 'pov', 'square', 'main'] as $key) {
        if (in_array($key, $a['blocks'] ?? [], true)) {
            return $key;
        }
    }
    return '';
}

function section_label(array $a)
{
    $key = primary_section($a);
    return $key !== '' ? BLOCKS[$key] : 'Новости';
}

function section_url($key)
{
    return $key === '' ? url('list.php') : url('list.php?section=' . rawurlencode($key));
}

// Поиск без учёта регистра, работает без расширения mbstring
function text_contains($haystack, $needle)
{
    if ($needle === '') {
        return true;
    }
    return preg_match('/' . preg_quote($needle, '/') . '/iu', (string)$haystack) === 1;
}

function excerpt($text, $len = 160)
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$text)));
    if (preg_match('/^.{' . (int)$len . '}/us', $text, $m) && strlen($m[0]) < strlen($text)) {
        return rtrim($m[0]) . '…';
    }
    return $text;
}

function ru_date($ts)
{
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $ts = (int)$ts;
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function ru_datetime($ts)
{
    return ru_date($ts) . ', ' . date('H:i', (int)$ts);
}

// Сегодня показываем только время, для прошлых дней показываем дату
function feed_time($ts)
{
    $ts = (int)$ts;
    return date('Y-m-d', $ts) === date('Y-m-d') ? date('H:i', $ts) : ru_date($ts);
}

// Строка «Автор: ... • дата»
function byline_html(array $a, $withTime = false)
{
    $parts = [];
    if (!empty($a['author'])) {
        $parts[] = '<span class="byline-author">Автор: ' . e($a['author']) . '</span>';
    }
    $parts[] = '<time>' . e($withTime ? ru_datetime($a['published_at']) : ru_date($a['published_at'])) . '</time>';
    return '<div class="byline">' . implode('<span class="dot">•</span>', $parts) . '</div>';
}

function social_icon($key)
{
    $icons = [
        'vk'       => '<path class="fill" d="M3.5 7.5h2.8c.4 3.6 1.9 5.4 3.2 5.8V7.5h2.7v3.1c1.3-.2 2.6-1.7 3.1-3.1H18c-.4 2-1.9 3.6-3 4.3 1.1.5 2.8 1.9 3.5 4.2h-2.9c-.6-1.5-2-2.7-3.4-2.9V16h-.6C6.8 16 3.8 12.9 3.5 7.5z"/>',
        'ok'       => '<circle cx="12" cy="7.5" r="3.2"/><path d="M8 12.8c2.4 1.6 5.6 1.6 8 0M9 20l3-4.5 3 4.5"/>',
        'telegram' => '<path d="M21 4 3 11.2l6.3 2.2L11.5 20l3.2-4.2 4.7 3.6z"/><path d="M9.3 13.4 18 7"/>',
        'mail'     => '<rect x="3.5" y="6" width="17" height="12" rx="1.5"/><path d="m4 7 8 6 8-6"/>',
        'max'      => '<path d="M12 4a8 8 0 1 1-4.6 14.6L4 20l1.3-3.4A8 8 0 0 1 12 4z"/><circle cx="12" cy="12" r="3.2"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($icons[$key] ?? '<circle cx="12" cy="12" r="6"/>') . '</svg>';
}

function handle_upload(array $file)
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? -1;
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('файл слишком большой.');
        }
        throw new RuntimeException('файл не загружен (код ошибки ' . $code . ').');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('файл больше ' . round(MAX_UPLOAD_BYTES / 1048576) . ' МБ.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('допустимы только изображения JPG, PNG, WEBP или GIF.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('не удалось сохранить файл. Проверьте права на папку uploads.');
    }
    return url('uploads/' . $name);
}

// Подрубрики материала (только из списка TAGS, в порядке этого списка)
function article_tags(array $a)
{
    $tags = isset($a['tags']) && is_array($a['tags']) ? $a['tags'] : [];
    return array_values(array_filter(TAGS, function ($t) use ($tags) {
        return in_array($t, $tags, true);
    }));
}

function tag_url($tag)
{
    return url('list.php?tag=' . rawurlencode($tag));
}

// Количество символов без расширения mbstring
function char_count($s)
{
    return (int)preg_match_all('/./us', (string)$s);
}

// Поиск по опубликованным материалам: все слова запроса должны встретиться в материале
function search_articles($q)
{
    $q = trim(ltrim(trim((string)$q), '#'));
    $words = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        return [];
    }
    $found = [];
    foreach (published_articles() as $a) {
        $title = $a['title'];
        $body = html_entity_decode(preg_replace('/<[^>]+>/', ' ', (string)($a['body'] ?? '')), ENT_QUOTES, 'UTF-8') . ' ' . (string)($a['search_text'] ?? '');
        $text = $title . ' ' . ($a['lead'] ?? '') . ' ' . ($a['author'] ?? '') . ' ' . implode(' ', article_tags($a)) . ' ' . $body;
        $titleHits = 0;
        $ok = true;
        foreach ($words as $w) {
            $w = ltrim($w, '#');
            if ($w === '') {
                continue;
            }
            if (!text_contains($text, $w)) {
                $ok = false;
                break;
            }
            if (text_contains($title, $w)) {
                $titleHits++;
            }
        }
        if (!$ok) {
            continue;
        }
        $score = text_contains($title, $q) ? 2 : ($titleHits === count($words) ? 1 : 0);
        $found[] = ['score' => $score, 'a' => $a];
    }
    usort($found, function ($x, $y) {
        if ($x['score'] !== $y['score']) {
            return $y['score'] - $x['score'];
        }
        return (int)$y['a']['published_at'] - (int)$x['a']['published_at'];
    });
    return array_column($found, 'a');
}

// Очистка HTML из редактора: оставляем только безопасные теги и атрибуты
function sanitize_html($html)
{
    $html = trim((string)$html);
    if ($html === '') {
        return '';
    }
    $allowed = [
        'p' => [], 'br' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [],
        'a' => ['href', 'target'], 'img' => ['src', 'alt'],
        'h2' => [], 'h3' => [], 'blockquote' => [], 'cite' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'figure' => [], 'figcaption' => [], 'div' => ['class'], 'span' => [],
        'iframe' => ['src', 'height', 'title', 'allow', 'allowfullscreen', 'frameborder', 'scrolling', 'referrerpolicy'],
    ];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="asura-root">' . $html . '</div></body></html>');
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);
    $root = $xpath->query('//div[@id="asura-root"]')->item(0);
    if (!$root) {
        return '';
    }
    sanitize_node($root, $allowed);
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function sanitize_node(DOMNode $node, array $allowed)
{
    for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
        $child = $node->childNodes->item($i);
        if ($child instanceof DOMText) {
            continue;
        }
        if (!($child instanceof DOMElement)) {
            $node->removeChild($child);
            continue;
        }
        $tag = strtolower($child->nodeName);
        if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select'], true)) {
            $node->removeChild($child);
            continue;
        }
        if ($tag === 'iframe') {
            while ($child->firstChild) {
                $child->removeChild($child->firstChild);
            }
        } else {
            sanitize_node($child, $allowed);
        }
        if (!isset($allowed[$tag])) {
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }
        for ($j = $child->attributes->length - 1; $j >= 0; $j--) {
            $attr = $child->attributes->item($j);
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            if (!in_array($name, $allowed[$tag], true)) {
                $child->removeAttribute($attr->name);
                continue;
            }
            if ($name === 'href' || $name === 'src') {
                $ok = $tag === 'iframe'
                    ? preg_match('~^https://~i', $value)
                    : preg_match('~^(https?://|/|mailto:|#)~i', $value);
                if (!$ok) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($name === 'height' && !preg_match('/^\d{2,4}$/', $value)) {
                $child->removeAttribute($attr->name);
            }
            if ($name === 'class') {
                $classes = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
                $keep = array_values(array_intersect($classes, ['news-gallery']));
                if ($keep) {
                    $child->setAttribute('class', implode(' ', $keep));
                } else {
                    $child->removeAttribute($attr->name);
                }
            }
        }
        if ($tag === 'a') {
            if ($child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            } else {
                $child->removeAttribute('target');
            }
        }
        if ($tag === 'img') {
            $child->setAttribute('loading', 'lazy');
        }
        if ($tag === 'iframe') {
            if (!$child->hasAttribute('src')) {
                $node->removeChild($child);
                continue;
            }
            $child->setAttribute('loading', 'lazy');
            if (!$child->hasAttribute('referrerpolicy')) {
                $child->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            }
        }
    }
}