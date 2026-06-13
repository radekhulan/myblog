<?php
declare(strict_types=1);

/* Detail článku + komentáře (jen zobrazení; prvních 20, další AJAXem). */

if ($slug === '') {
    not_found();
}

$item = one(
    'SELECT ' . ARTICLE_FIELDS . '
     FROM ' . tbl('item') . ' i
     LEFT JOIN ' . tbl('category') . ' c ON c.catid = i.icat
     LEFT JOIN ' . tbl('member') . ' m ON m.mnumber = i.iauthor
     WHERE i.iurltitle = ? AND i.idraft = 0 AND i.itime <= NOW()',
    [$slug]
);

if (!$item) {
    // starší slug článku → 301 na aktuální URL (tabulka plugin_fancierurl)
    $inumber = scalar('SELECT inumber FROM ' . tbl('plugin_fancierurl') . ' WHERE iurltitle = ?', [$slug]);
    if ($inumber) {
        $current = scalar(
            'SELECT iurltitle FROM ' . tbl('item') . ' WHERE inumber = ? AND idraft = 0',
            [(int) $inumber]
        );
        if ($current) {
            redirect301(url_item((string) $current));
        }
    }
    not_found();
}

$itemId = (int) $item['inumber'];
$renderCtx = ['authorid' => (int) $item['iauthor'], 'detail' => true, 'itemid' => $itemId];

$totalComments = comments_count($itemId);
$comments = $totalComments > 0 ? comments_for_item($itemId, 0, COMMENTS_INITIAL) : [];

$tags = all(
    'SELECT t.tagid, t.tagname, t.tagurl FROM ' . tbl('tags') . ' t
     JOIN ' . tbl('tags_item') . ' ti ON ti.ttagid = t.tagid
     WHERE ti.titemid = ? ORDER BY t.tagname',
    [$itemId]
);

// drobečky: Úvod › skupina (subcategory) › kategorie
$crumbs = [['Úvod', '/']];
if ((int) $item['icat'] > 0) {
    $group = one(
        'SELECT s.name, s.iurltitle FROM ' . tbl('subcategory') . ' s
         JOIN ' . tbl('category') . ' c ON c.cgroup = s.groupid
         WHERE c.catid = ?',
        [(int) $item['icat']]
    );
    if ($group && trim((string) $group['iurltitle']) !== '') {
        $crumbs[] = [$group['name'], url_group($group['iurltitle'])];
    }
    if (!empty($item['catslug'])) {
        $crumbs[] = [$item['catname'], url_category($item['catslug'])];
    }
}

$content = view('article-detail', [
    'item'     => $item,
    'body'     => render_body($item['ibody'], $renderCtx),
    'more'     => render_body($item['imore'], $renderCtx),
    'tags'     => $tags,
    'crumbs'   => $crumbs,
    'comments' => view('comments', [
        'itemId'   => $itemId,
        'comments' => $comments,
        'total'    => $totalComments,
        'shown'    => count($comments),
    ]),
]);

echo view('layout', [
    'meta' => build_meta([
        'title'       => title_text($item['ititle']),
        'description' => truncate_text($item['ibody'], 200),
        'canonical'   => url_item($item['iurltitle']),
        'og_type'     => 'article',
        'og_image'    => og_image_for_item($item),
        'published'   => date('c', strtotime($item['itime'])),
    ]),
    'content' => $content,
]);
