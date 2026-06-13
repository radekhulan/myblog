<?php
declare(strict_types=1);

/* RSS 2.0 feed — /feed/rss2. Include z index.php (cfg + lib už načtené). */

$items = all(
    'SELECT i.ititle, i.iurltitle, i.ibody, i.itime
     FROM ' . tbl('item') . ' i
     WHERE i.iblog = ? AND i.idraft = 0 AND i.itime <= NOW()
     ORDER BY i.itime DESC
     LIMIT ' . PER_PAGE,
    [MAIN_BLOG]
);

$latest = $items[0]['itime'] ?? date('Y-m-d H:i:s');

/* Podmíněné GET — ETag dle nejnovějšího článku a počtu položek. */
$etag = '"' . md5($latest . '|' . count($items)) . '"';
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=900');
header('Content-Type: application/rss+xml; charset=utf-8');

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

$base = (string) cfg('canonical_base');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
echo "<channel>\n";
echo '<title>' . htmlspecialchars((string) cfg('name'), ENT_XML1, 'UTF-8') . "</title>\n";
echo '<link>' . htmlspecialchars($base . '/', ENT_XML1, 'UTF-8') . "</link>\n";
echo '<atom:link href="' . htmlspecialchars($base . '/feed/rss2', ENT_XML1, 'UTF-8') . '" rel="self" type="application/rss+xml" />' . "\n";
echo '<description>' . htmlspecialchars((string) cfg('claim'), ENT_XML1, 'UTF-8') . "</description>\n";
echo "<language>cs</language>\n";
echo '<lastBuildDate>' . date('r', strtotime($latest)) . "</lastBuildDate>\n";

foreach ($items as $row) {
    $title = htmlspecialchars(title_text($row['ititle']), ENT_XML1, 'UTF-8');
    $url   = htmlspecialchars($base . url_item((string) $row['iurltitle']), ENT_XML1, 'UTF-8');
    $desc  = htmlspecialchars(truncate_text($row['ibody'], 400), ENT_XML1, 'UTF-8');
    echo "<item>\n";
    echo '<title>' . $title . "</title>\n";
    echo '<link>' . $url . "</link>\n";
    echo '<guid isPermaLink="true">' . $url . "</guid>\n";
    echo '<pubDate>' . date('r', strtotime($row['itime'])) . "</pubDate>\n";
    echo '<description>' . $desc . "</description>\n";
    echo "</item>\n";
}

echo "</channel>\n";
echo "</rss>\n";
