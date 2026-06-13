<?php
declare(strict_types=1);

$cat = one(
    'SELECT catid, cname, cdesc, iurltitle, cblog, cgroup FROM ' . tbl('category') . ' WHERE iurltitle = ?',
    [$slug]
);
if (!$cat) {
    not_found();
}

if ((int) $cat['cblog'] === FOTO_BLOG) {
    require_once DIR_ROOT . '/lib/gallery.php';
    gallery_albums_listing_page(
        [(int) $cat['catid']],
        $cat['cname'],
        trim((string) ($cat['cdesc'] ?? '')) ?: null,
        url_category($cat['iurltitle']),
        $offset,
        [
            'title'       => $cat['cname'],
            'description' => 'Fotoalba v kategorii ' . $cat['cname'],
            'canonical'   => url_category($cat['iurltitle']),
        ]
    );
}

$crumbs = [['Úvod', '/']];
$group = one(
    'SELECT name, iurltitle FROM ' . tbl('subcategory') . ' WHERE groupid = ?',
    [(int) ($cat['cgroup'] ?? 0)]
);
if ($group && trim((string) $group['iurltitle']) !== '') {
    $crumbs[] = [$group['name'], url_group($group['iurltitle'])];
}
$crumbs[] = [$cat['cname'], null];

$desc = trim((string) ($cat['cdesc'] ?? ''));
if ($desc === trim((string) $cat['cname'])) {
    $desc = '';        // popis shodný s názvem nemá smysl opakovat
}

articles_listing_page([
    'heading'  => $cat['cname'],
    'subtitle' => $desc ?: null,
    'base'     => url_category($cat['iurltitle']),
    'offset'   => $offset,
    'where'    => 'i.icat = ?',
    'params'   => [(int) $cat['catid']],
    'crumbs'   => $crumbs,
    'meta'     => [
        'title'       => $cat['cname'],
        'description' => trim((string) ($cat['cdesc'] ?? '')) ?: ('Články v rubrice ' . $cat['cname']),
        'canonical'   => url_category($cat['iurltitle']),
    ],
]);
