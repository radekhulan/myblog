<?php
declare(strict_types=1);

/* Samostatný entrypoint pro /sitemap.xml (IIS rewrite → /sitemap.php). */

require __DIR__ . '/cfg.php';
require __DIR__ . '/lib/log.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$base = (string) cfg('canonical_base');

/* ---- všechny publikované články všech blogů (JEDEN dotaz, žádné N+1) ---- */
$items = all(
    'SELECT iurltitle, itime
     FROM ' . tbl('item') . '
     WHERE idraft = 0 AND itime <= NOW() AND iurltitle IS NOT NULL AND iurltitle <> \'\'
     ORDER BY itime DESC'
);

/* Nejnovější článek = lastmod homepage. */
$homeLastmod = $items ? date('c', strtotime($items[0]['itime'])) : date('c');

/* ---- kategorie s aspoň 1 publikovaným článkem (lastmod = nejnovější článek rubriky) ---- */
$categories = all(
    'SELECT c.iurltitle, MAX(i.itime) AS lastmod
     FROM ' . tbl('category') . ' c
     JOIN ' . tbl('item') . ' i ON i.icat = c.catid
     WHERE i.idraft = 0 AND i.itime <= NOW()
       AND c.iurltitle IS NOT NULL AND c.iurltitle <> \'\'
     GROUP BY c.catid, c.iurltitle'
);

/* ---- skupiny subcategory (krom prázdných: musí mít kategorii s článkem) ---- */
$groups = all(
    'SELECT s.iurltitle, MAX(i.itime) AS lastmod
     FROM ' . tbl('subcategory') . ' s
     JOIN ' . tbl('category') . ' c ON c.cgroup = s.groupid
     JOIN ' . tbl('item') . ' i ON i.icat = c.catid
     WHERE i.idraft = 0 AND i.itime <= NOW()
       AND s.iurltitle IS NOT NULL AND s.iurltitle <> \'\'
     GROUP BY s.groupid, s.iurltitle'
);

/* ---- alba galerie (ffotek > 0, lastmod = fdatum) ---- */
$albums = all(
    'SELECT fid, fkategorie, fdatum
     FROM ' . tbl('foto') . '
     WHERE ffotek > 0
     ORDER BY fdatum DESC'
);

/* ---- výstup ---- */
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

/** Vypíše jeden <url> záznam. */
$url = static function (string $loc, ?string $lastmod = null, ?string $priority = null): void {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
    if ($lastmod !== null) {
        echo '    <lastmod>' . $lastmod . "</lastmod>\n";
    }
    if ($priority !== null) {
        echo '    <priority>' . $priority . "</priority>\n";
    }
    echo "  </url>\n";
};

/* homepage */
$url($base . '/', $homeLastmod, '1.0');

/* články */
foreach ($items as $row) {
    $url($base . url_item((string) $row['iurltitle']), date('c', strtotime($row['itime'])));
}

/* kategorie */
foreach ($categories as $row) {
    $url($base . url_category((string) $row['iurltitle']), date('c', strtotime($row['lastmod'])));
}

/* skupiny */
foreach ($groups as $row) {
    $url($base . url_group((string) $row['iurltitle']), date('c', strtotime($row['lastmod'])));
}

/* galerie (rozcestník) */
$url($base . url_group('galerie'));

/* alba */
foreach ($albums as $row) {
    $lastmod = !empty($row['fdatum']) ? date('c', strtotime($row['fdatum'])) : null;
    $url($base . url_album((int) $row['fid'], $row['fkategorie'] !== null ? (int) $row['fkategorie'] : null), $lastmod);
}

/* statická stránka */
$url($base . '/extra/cv');

echo '</urlset>' . "\n";
