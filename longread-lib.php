<?php
require_once __DIR__ . '/lib.php';

const LONGREAD_DIR = __DIR__ . '/data/longreads';

const LONGREAD_FONTS = 'https://fonts.googleapis.com/css2?family=Cormorant:ital,wght@0,400;0,600;0,700;1,400&family=Literata:ital,wght@0,400;0,600;0,700;1,400&family=Old+Standard+TT:wght@400;700&display=swap';

// Варианты шапки на странице лонгрида
const LONGREAD_HEADERS = [
    'mini'    => 'Компактная: тонкая полоса с логотипом',
    'overlay' => 'Поверх обложки: белый логотип на первом экране',
    'site'    => 'Полная шапка сайта с меню',
];

function is_longread(array $a)
{
    return ($a['type'] ?? '') === 'longread';
}

function longread_base($id)
{
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$id);
    if ($safe === '') {
        throw new RuntimeException('Некорректный идентификатор лонгрида.');
    }
    return LONGREAD_DIR . '/' . $safe;
}

function load_longread($id)
{
    $result = ['html' => '', 'css' => '', 'project_raw' => '', 'updated_at' => 0];
    try {
        $base = longread_base($id);
    } catch (RuntimeException $ex) {
        return $result;
    }
    if (is_file($base . '.json')) {
        $data = json_decode((string)file_get_contents($base . '.json'), true);
        if (is_array($data)) {
            $result['html'] = (string)($data['html'] ?? '');
            $result['css'] = (string)($data['css'] ?? '');
            $result['updated_at'] = (int)($data['updated_at'] ?? 0);
        }
    }
    if (is_file($base . '.project.json')) {
        $raw = (string)file_get_contents($base . '.project.json');
        if ($raw !== '' && json_decode($raw) !== null) {
            $result['project_raw'] = $raw;
        }
    }
    return $result;
}

function lr_write_file($file, $content)
{
    if (is_file($file) && filesize($file) > 0) {
        @copy($file, $file . '.bak');
    }
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $content, LOCK_EX) === false || !rename($tmp, $file)) {
        throw new RuntimeException('Не удалось сохранить лонгрид. Проверьте права на запись для папки data.');
    }
}

// Проект конструктора хранится как есть, без перекодирования, чтобы не повредить структуру
function save_longread($id, $projectRaw, $html, $css)
{
    $base = longread_base($id);
    if (!is_dir(LONGREAD_DIR) && !mkdir(LONGREAD_DIR, 0775, true)) {
        throw new RuntimeException('Не удалось создать папку data/longreads. Проверьте права на запись.');
    }
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $page = json_encode(['html' => $html, 'css' => $css, 'updated_at' => time()], $flags);
    if ($page === false) {
        throw new RuntimeException('Не удалось подготовить страницу к сохранению: ' . json_last_error_msg());
    }
    lr_write_file($base . '.json', $page);
    lr_write_file($base . '.project.json', $projectRaw);
}

function delete_longread($id)
{
    try {
        $base = longread_base($id);
    } catch (RuntimeException $ex) {
        return;
    }
    foreach (['.json', '.json.bak', '.project.json', '.project.json.bak'] as $suffix) {
        if (is_file($base . $suffix)) {
            @unlink($base . $suffix);
        }
    }
}

// Удаляем из вёрстки скрипты и обработчики событий, остальное сохраняем как есть
function clean_longread_html($html)
{
    $html = (string)$html;
    $html = preg_replace('~<body([^>]*)>~i', '<div$1>', $html);
    $html = preg_replace('~</body>~i', '</div>', $html);
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="lr-clean-root">' . $html . '</div></body></html>');
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);
    $root = $xpath->query('//div[@id="lr-clean-root"]')->item(0);
    if (!$root) {
        return '';
    }
    $remove = [];
    foreach ($xpath->query('.//*', $root) as $el) {
        $tag = strtolower($el->nodeName);
        if (in_array($tag, ['script', 'object', 'embed', 'base', 'meta', 'link', 'applet', 'frameset', 'frame'], true)) {
            $remove[] = $el;
            continue;
        }
        $names = [];
        foreach ($el->attributes as $attr) {
            $names[] = $attr->name;
        }
        foreach ($names as $name) {
            $lname = strtolower($name);
            $value = trim($el->getAttribute($name));
            if (strpos($lname, 'on') === 0) {
                $el->removeAttribute($name);
                continue;
            }
            if (in_array($lname, ['href', 'src', 'action', 'formaction', 'poster', 'data', 'xlink:href'], true)
                && preg_match('~^\s*(javascript|vbscript|data:text/html)~i', $value)) {
                $el->removeAttribute($name);
                continue;
            }
            if ($lname === 'style' && preg_match('~expression\s*\(|javascript\s*:~i', $value)) {
                $el->removeAttribute($name);
            }
        }
    }
    foreach ($remove as $el) {
        if ($el->parentNode) {
            $el->parentNode->removeChild($el);
        }
    }
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function clean_longread_css($css)
{
    $css = str_ireplace(['</style', '<!--', '-->'], '', (string)$css);
    $css = preg_replace('/expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', '', $css);
    return trim((string)$css);
}

// Текст лонгрида для поиска по сайту
function longread_plain_text($html)
{
    $html = preg_replace('/<(br|\/p|\/h\d|\/div|\/li|\/figcaption|\/blockquote|\/cite)[^>]*>/i', ' ', (string)$html);
    $text = html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8');
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    if (preg_match('/^.{20000}/us', $text, $m)) {
        $text = $m[0];
    }
    return $text;
}

function longread_first_image($html)
{
    if (preg_match_all('~<img[^>]+src="([^"]+)"~i', (string)$html, $m)) {
        foreach ($m[1] as $src) {
            $src = html_entity_decode($src, ENT_QUOTES, 'UTF-8');
            if (stripos($src, 'data:') !== 0) {
                return $src;
            }
        }
    }
    return '';
}