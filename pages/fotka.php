<?php
declare(strict_types=1);

require_once DIR_ROOT . '/lib/gallery.php';

/* Detail fotky — /fotka/{oid}. */

$fotka = one('SELECT oid, fid, onazev, opopis, odatum, onahled, osoubor, ow, oh, oviews FROM ' . tbl('foto_fotka') . ' WHERE oid = ?', [$fotkaId]);
if (!$fotka) {
    not_found();
}

exec_q('UPDATE ' . tbl('foto_fotka') . ' SET oviews = oviews + 1 WHERE oid = ?', [$fotkaId]);

$fid = (int) $fotka['fid'];
$album = one('SELECT fid, fnazev, fkategorie FROM ' . tbl('foto') . ' WHERE fid = ?', [$fid]);
$catid = $album ? (int) $album['fkategorie'] : null;

// Sousedé dle pořadí v albu (respektuje ruční oporadi, ne jen oid).
$orderedOids = array_map(
    static fn(array $r): int => (int) $r['oid'],
    all('SELECT oid FROM ' . tbl('foto_fotka') . ' WHERE fid = ? AND otyp = 0 ORDER BY ' . GALLERY_PHOTO_ORDER, [$fid])
);
$pos  = array_search($fotkaId, $orderedOids, true);
$prev = ($pos !== false && $pos > 0) ? $orderedOids[$pos - 1] : null;
$next = ($pos !== false && $pos < count($orderedOids) - 1) ? $orderedOids[$pos + 1] : null;

$ogImage = cfg('canonical_base') . foto_medium_url($fotkaId, $fotka['onahled']);

$content = view('gallery-foto', [
    'fotka' => $fotka,
    'album' => $album,
    'catid' => $catid,
    'prev'  => $prev,
    'next'  => $next,
]);

echo view('layout', [
    'meta' => build_meta([
        'title'       => $fotka['onazev'],
        'description' => truncate_text((string) $fotka['opopis'], 200) ?: ('Fotografie ' . $fotka['onazev']),
        'canonical'   => url_fotka($fotkaId, $catid),
        'og_type'     => 'article',
        'og_image'    => $ogImage,
    ]),
    'content' => $content,
]);
