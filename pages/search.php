<?php
declare(strict_types=1);

/* Fulltextové hledání — /hledani?q=…&offset=N. */

$qRaw = trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));
$offset = max(0, (int) ($_GET['offset'] ?? 0));

$meta = build_meta([
    'title'       => $qRaw !== '' ? 'Hledání: ' . $qRaw : 'Hledání',
    'description' => 'Fulltextové hledání v článcích ' . cfg('name'),
    'canonical'   => '/hledani',
    'robots'      => 'noindex,follow',
]);

if ($qRaw === '' || mb_strlen($qRaw) < 2) {
    echo view('layout', [
        'meta'    => $meta,
        'content' => view('search-form', ['q' => $qRaw, 'message' => $qRaw === '' ? null : 'Zadejte alespoň 2 znaky.']),
    ]);
    exit;
}

$terms = preg_split('/\s+/u', $qRaw, -1, PREG_SPLIT_NO_EMPTY);
$terms = array_slice($terms, 0, 8);

// boolean mode: +slovo* pro slova od 3 znaků
$ftTerms = array_filter($terms, fn($t) => mb_strlen($t) >= 3);
$rows = [];
$total = 0;

if ($ftTerms) {
    $boolean = implode(' ', array_map(fn($t) => '+' . preg_replace('/[+\-<>()~*"@]/u', '', $t) . '*', $ftTerms));
    $where = 'MATCH(i.ititle, i.ibody, i.imore) AGAINST (? IN BOOLEAN MODE)';
    $total = articles_count($where, [$boolean]);
    if ($total > 0) {
        $rows = all(
            'SELECT ' . ARTICLE_FIELDS . ',
                    MATCH(i.ititle, i.ibody, i.imore) AGAINST (?) AS relevance
             FROM ' . tbl('item') . ' i
             LEFT JOIN ' . tbl('category') . ' c ON c.catid = i.icat
             LEFT JOIN ' . tbl('member') . ' m ON m.mnumber = i.iauthor
             WHERE i.idraft = 0 AND i.itime <= NOW() AND ' . $where . '
             ORDER BY relevance DESC, i.itime DESC
             LIMIT ' . $offset . ', ' . PER_PAGE,
            [$boolean, $boolean]
        );
    }
}

if ($total === 0) {
    // fallback LIKE (krátká slova, žádné fulltext výsledky)
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $qRaw) . '%';
    $where = '(i.ititle LIKE ? OR i.ibody LIKE ?)';
    $total = articles_count($where, [$like, $like]);
    $rows = $total > 0 ? articles_fetch($where, [$like, $like], $offset) : [];
}

$base = '/hledani';
$qs = '?q=' . rawurlencode($qRaw);

$content = view('search-form', ['q' => $qRaw, 'message' => null])
    . view('article-list', [
        'heading'   => 'Výsledky hledání',
        'subtitle'  => $total > 0
            ? 'Nalezeno ' . $total . ' článků pro „' . $qRaw . '“'
            : 'Pro „' . $qRaw . '“ nebylo nic nalezeno. Zkuste jiná slova.',
        'items'     => $rows,
        'total'     => $total,
        'highlight' => $terms,
        'pager'     => [
            'prev'  => $offset > 0 ? $base . $qs . '&offset=' . max(0, $offset - PER_PAGE) : null,
            'next'  => ($offset + PER_PAGE) < $total ? $base . $qs . '&offset=' . ($offset + PER_PAGE) : null,
            'page'  => intdiv($offset, PER_PAGE) + 1,
            'pages' => (int) ceil($total / PER_PAGE),
        ],
    ]);

echo view('layout', ['meta' => $meta, 'content' => $content]);
