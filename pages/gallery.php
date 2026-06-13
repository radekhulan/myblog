<?php
declare(strict_types=1);

require_once DIR_ROOT . '/lib/gallery.php';

if (!has_gallery()) {
    not_found();
}

/* Hlavní rozcestník fotogalerie — /group/galerie: sekce dle kategorií blogu FOTO_BLOG.
   Každá kategorie = nadpis s odkazem na /category/{slug}, náhled alb a „Všechna alba". */

$cats = all(
    'SELECT catid, cname, iurltitle FROM ' . tbl('category') . '
     WHERE cblog = ?
     ORDER BY csort, cname',
    [FOTO_BLOG]
);

$sections = [];
foreach ($cats as $cat) {
    $catId = (int) $cat['catid'];
    $total = gallery_albums_count([$catId]);
    if (!$total) {
        continue;
    }
    $sections[] = [
        'name'   => $cat['cname'],
        'url'    => url_category($cat['iurltitle']),
        'albums' => gallery_albums([$catId], 0, 8),
        'total'  => $total,
    ];
}

$content = view('gallery-home', [
    'heading'  => 'Fotogalerie',
    'sections' => $sections,
]);

echo view('layout', [
    'meta' => build_meta([
        'title'       => 'Fotogalerie',
        'description' => 'Fotogalerie webu ' . cfg('name'),
        'canonical'   => url_group('galerie'),
    ]),
    'content' => $content,
]);
