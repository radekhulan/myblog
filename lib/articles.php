<?php
declare(strict_types=1);

/* Společné dotazy a výpis článků (homepage, kategorie, skupiny, sekce, tagy, hledání). */

const ARTICLE_FIELDS = 'i.inumber, i.ititle, i.iurltitle, i.ibody, i.imore, i.itime, i.inumcomments,
    i.iauthor, i.icat, i.iclosed, c.cname AS catname, c.iurltitle AS catslug,
    COALESCE(NULLIF(m.mrealname, \'\'), m.mname) AS author';

function articles_fetch(string $where, array $params, int $offset, int $limit = PER_PAGE): array
{
    return all(
        'SELECT ' . ARTICLE_FIELDS . '
         FROM ' . tbl('item') . ' i
         LEFT JOIN ' . tbl('category') . ' c ON c.catid = i.icat
         LEFT JOIN ' . tbl('member') . ' m ON m.mnumber = i.iauthor
         WHERE i.idraft = 0 AND i.itime <= NOW() AND ' . $where . '
         ORDER BY i.itime DESC
         LIMIT ' . max(0, $offset) . ', ' . max(1, $limit),
        $params
    );
}

function articles_count(string $where, array $params): int
{
    return (int) scalar(
        'SELECT COUNT(*) FROM ' . tbl('item') . ' i WHERE i.idraft = 0 AND i.itime <= NOW() AND ' . $where,
        $params
    );
}

/**
 * Vyrenderuje stránku s výpisem článků a stránkováním.
 * $opts: heading, subtitle?, base (URL pro stránkování), offset, where, params, meta (pole pro build_meta)
 */
function articles_listing_page(array $opts): never
{
    $offset = max(0, (int) $opts['offset']);
    $total  = articles_count($opts['where'], $opts['params']);
    $rows   = articles_fetch($opts['where'], $opts['params'], $offset);

    if ($offset > 0 && !$rows) {
        not_found();
    }

    $meta = $opts['meta'];
    if ($offset > 0) {
        $meta['canonical'] = url_offset($opts['base'], $offset);
        $meta['robots'] = $meta['robots'] ?? 'noindex,follow';   // stránkované výpisy neindexovat
    }

    $content = view('article-list', [
        'heading'  => $opts['heading'],
        'subtitle' => $opts['subtitle'] ?? null,
        'items'    => $rows,
        'total'    => $total,
        'pager'    => pager_data($opts['base'], $offset, PER_PAGE, $total),
        'highlight'=> $opts['highlight'] ?? null,
        'crumbs'   => $opts['crumbs'] ?? null,
        'chips'    => $opts['chips'] ?? null,
    ]);

    echo view('layout', ['meta' => build_meta($meta), 'content' => $content]);
    exit;
}

/* ---------- komentáře ---------- */

function comments_for_item(int $itemId, int $offset, int $limit): array
{
    return all(
        'SELECT co.cnumber, co.cbody, co.cuser, co.cmember, co.ctime, co.cup, co.cdown,
                COALESCE(NULLIF(m.mrealname, \'\'), m.mname) AS membername,
                COALESCE(m.madmin, 0) AS madmin
         FROM ' . tbl('comment') . ' co
         LEFT JOIN ' . tbl('member') . ' m ON m.mnumber = co.cmember
         WHERE co.citem = ?
         ORDER BY co.ctime ASC, co.cnumber ASC
         LIMIT ' . max(0, $offset) . ', ' . max(1, $limit),
        [$itemId]
    );
}

function comments_count(int $itemId): int
{
    return (int) scalar('SELECT COUNT(*) FROM ' . tbl('comment') . ' WHERE citem = ?', [$itemId]);
}
