<?php
declare(strict_types=1);

/* Výpis vedlejšího blogu — /section/{bshortname}. */

$blog = one(
    'SELECT bnumber, bname, bdesc, bshortname FROM ' . tbl('blog') . ' WHERE bshortname = ?',
    [$short]
);
if (!$blog) {
    not_found();
}

articles_listing_page([
    'heading'  => $blog['bname'],
    'subtitle' => trim((string) ($blog['bdesc'] ?? '')) ?: null,
    'base'     => url_section($blog['bshortname']),
    'offset'   => $offset,
    'where'    => 'i.iblog = ?',
    'params'   => [(int) $blog['bnumber']],
    'crumbs'   => [['Úvod', '/'], [$blog['bname'], null]],
    'meta'     => [
        'title'       => $blog['bname'],
        'description' => trim((string) ($blog['bdesc'] ?? '')) ?: ('Články blogu ' . $blog['bname']),
        'canonical'   => url_section($blog['bshortname']),
    ],
]);
