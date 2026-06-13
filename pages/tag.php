<?php
declare(strict_types=1);

/* Výpis článků s tagem — /tag/{id}-{slug} (staré URL, bez editace v adminu). */

$tagId = (int) $tagRaw;
if ($tagId <= 0) {
    not_found();
}

$tag = one('SELECT tagid, tagname, tagurl FROM ' . tbl('tags') . ' WHERE tagid = ?', [$tagId]);
if (!$tag) {
    not_found();
}

// kanonický tvar /tag/{id}-{tagurl}
$canonical = url_tag((int) $tag['tagid'], (string) $tag['tagurl']);
if ($tagRaw !== $tag['tagid'] . '-' . $tag['tagurl'] && $offset === 0) {
    redirect301($canonical);
}

articles_listing_page([
    'heading'  => 'Tag: ' . $tag['tagname'],
    'base'     => $canonical,
    'offset'   => $offset,
    'where'    => 'i.inumber IN (SELECT titemid FROM ' . tbl('tags_item') . ' WHERE ttagid = ?)',
    'params'   => [(int) $tag['tagid']],
    'crumbs'   => [['Úvod', '/'], ['Tag: ' . $tag['tagname'], null]],
    'meta'     => [
        'title'       => 'Tag: ' . $tag['tagname'],
        'description' => 'Články označené tagem ' . $tag['tagname'],
        'canonical'   => $canonical,
    ],
]);
