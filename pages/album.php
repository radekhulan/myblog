<?php
declare(strict_types=1);

require_once DIR_ROOT . '/lib/gallery.php';

/* Detail alba — /album/{fid}. */

$album = one('SELECT fid, fnazev, fpopis, fdatum, fkategorie, ffotek, fviews FROM ' . tbl('foto') . ' WHERE fid = ?', [$albumId]);
if (!$album) {
    not_found();
}

exec_q('UPDATE ' . tbl('foto') . ' SET fviews = fviews + 1 WHERE fid = ?', [$albumId]);

$catid = (int) $album['fkategorie'];

$fotky = all(
    'SELECT oid, onazev, onahled, osoubor FROM ' . tbl('foto_fotka') . '
     WHERE fid = ? AND otyp = 0
     ORDER BY ' . GALLERY_PHOTO_ORDER,
    [$albumId]
);

$category = one('SELECT cname, iurltitle, cgroup FROM ' . tbl('category') . ' WHERE catid = ?', [$catid]);
$group = $category
    ? one('SELECT name, iurltitle FROM ' . tbl('subcategory') . ' WHERE groupid = ?', [(int) $category['cgroup']])
    : null;

$ogImage = null;
if ($fotky) {
    $first = $fotky[0];
    $ogImage = cfg('canonical_base') . foto_medium_url((int) $first['oid'], $first['onahled']);
}

$content = view('gallery-album', [
    'album'    => $album,
    'fotky'    => $fotky,
    'catid'    => $catid,
    'category' => $category,
    'group'    => $group,
]);

$metaOpts = [
    'title'       => $album['fnazev'],
    'description' => truncate_text((string) $album['fpopis'], 200) ?: ('Fotoalbum ' . $album['fnazev']),
    'canonical'   => url_album($albumId, $catid),
    'og_type'     => 'article',
];
if ($ogImage !== null) {
    $metaOpts['og_image'] = $ogImage;
}

echo view('layout', [
    'meta'    => build_meta($metaOpts),
    'content' => $content,
]);
