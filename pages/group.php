<?php
declare(strict_types=1);

/* Výpis článků celé skupiny kategorií (subcategory) — /group/{slug}. */

$group = one(
    'SELECT groupid, blogid, name, iurltitle FROM ' . tbl('subcategory') . ' WHERE iurltitle = ?',
    [$slug]
);
if (!$group) {
    not_found();
}

$catIds = array_column(
    all('SELECT catid FROM ' . tbl('category') . ' WHERE cgroup = ?', [(int) $group['groupid']]),
    'catid'
);
if (!$catIds) {
    not_found();
}

if ((int) $group['blogid'] === FOTO_BLOG) {
    require_once DIR_ROOT . '/lib/gallery.php';
    gallery_albums_listing_page(
        $catIds,
        $group['name'],
        null,
        url_group($group['iurltitle']),
        $offset,
        [
            'title'       => $group['name'],
            'description' => 'Fotoalba v sekci ' . $group['name'],
            'canonical'   => url_group($group['iurltitle']),
        ]
    );
}

$in = implode(',', array_map('intval', $catIds));

// kategorie skupiny jako rozcestník (jen ty s články)
$chips = [];
$groupCats = all(
    'SELECT c.cname, c.iurltitle, COUNT(i.inumber) AS cnt
     FROM ' . tbl('category') . ' c
     LEFT JOIN ' . tbl('item') . ' i ON i.icat = c.catid AND i.idraft = 0 AND i.itime <= NOW()
     WHERE c.cgroup = ?
     GROUP BY c.catid, c.cname, c.iurltitle
     HAVING cnt > 0
     ORDER BY c.cname',
    [(int) $group['groupid']]
);
if (count($groupCats) > 1) {
    foreach ($groupCats as $gc) {
        $chips[] = ['label' => $gc['cname'], 'href' => url_category($gc['iurltitle']), 'count' => (int) $gc['cnt']];
    }
}

articles_listing_page([
    'heading' => $group['name'],
    'base'    => url_group($group['iurltitle']),
    'offset'  => $offset,
    'where'   => 'i.icat IN (' . $in . ')',
    'params'  => [],
    'crumbs'  => [['Úvod', '/'], [$group['name'], null]],
    'chips'   => $chips,
    'meta'    => [
        'title'       => $group['name'],
        'description' => 'Články v sekci ' . $group['name'],
        'canonical'   => url_group($group['iurltitle']),
    ],
]);
