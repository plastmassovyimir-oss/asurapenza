<?php
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$q = trim(ltrim($q, '#'));

$items = [];
$total = 0;

if (char_count($q) >= 2) {
    $found = search_articles($q);
    $total = count($found);
    foreach (array_slice($found, 0, 8) as $a) {
        $items[] = [
            'url'     => article_url($a),
            'title'   => $a['title'],
            'date'    => ru_date($a['published_at']),
            'section' => section_label($a),
            'cover'   => $a['cover'] ?? '',
        ];
    }
}

echo json_encode(['ok' => true, 'total' => $total, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);