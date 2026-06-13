<?php
declare(strict_types=1);

/* AJAX donačítání komentářů — /ajax/comments?item={id}&offset={n} → JSON {html, remaining}. */

header('Content-Type: application/json; charset=utf-8');

$itemId = (int) ($_GET['item'] ?? 0);
$cOffset = max(0, (int) ($_GET['offset'] ?? 0));

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad request']);
    exit;
}

$exists = scalar(
    'SELECT inumber FROM ' . tbl('item') . ' WHERE inumber = ? AND idraft = 0 AND itime <= NOW()',
    [$itemId]
);
if (!$exists) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

$total = comments_count($itemId);
$rows = comments_for_item($itemId, $cOffset, COMMENTS_CHUNK);

echo json_encode([
    'html'      => view('comments-items', ['comments' => $rows, 'seq' => $cOffset + 1]),
    'remaining' => max(0, $total - $cOffset - count($rows)),
    'offset'    => $cOffset + count($rows),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
