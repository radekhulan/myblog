<?php
declare(strict_types=1);

/* Sdílené dotazy a výpis fotogalerie (alba, detail alba, detail fotky). */

function gallery_albums(array $catIds, int $offset, int $limit): array
{
    $catIds = array_values(array_filter(array_map('intval', $catIds)));
    if (!$catIds) {
        return [];
    }
    $in = implode(',', $catIds);

    return all(
        'SELECT f.fid, f.fnazev, f.fdatum, f.ffotek, f.fviews, f.fkategorie,
                fo.oid AS thumb_oid, fo.onahled AS thumb_nahled
         FROM ' . tbl('foto') . ' f
         LEFT JOIN ' . tbl('foto_fotka') . ' fo ON fo.oid = (
             SELECT oid FROM ' . tbl('foto_fotka') . '
             WHERE fid = f.fid AND otyp = 0
             ORDER BY ohodnoceni DESC, oid ASC
             LIMIT 1
         )
         WHERE f.fkategorie IN (' . $in . ') AND f.ffotek > 0
         ORDER BY f.fid DESC
         LIMIT ' . max(0, $offset) . ', ' . max(1, $limit)
    );
}

function gallery_albums_count(array $catIds): int
{
    $catIds = array_values(array_filter(array_map('intval', $catIds)));
    if (!$catIds) {
        return 0;
    }
    $in = implode(',', $catIds);

    return (int) scalar(
        'SELECT COUNT(*) FROM ' . tbl('foto') . ' WHERE fkategorie IN (' . $in . ') AND ffotek > 0'
    );
}

/**
 * Vyrenderuje stránku se sítí alb a stránkováním po GALLERY_PER_PAGE.
 * $metaOpts: pole pro build_meta (title, description, canonical, …).
 */
function gallery_albums_listing_page(array $catIds, string $heading, ?string $subtitle, string $base, int $offset, array $metaOpts): never
{
    $offset = max(0, $offset);
    $total  = gallery_albums_count($catIds);
    $albums = gallery_albums($catIds, $offset, GALLERY_PER_PAGE);

    if ($offset > 0 && !$albums) {
        not_found();
    }

    $meta = $metaOpts;
    if ($offset > 0) {
        $meta['canonical'] = url_offset($base, $offset);
        $meta['robots'] = $meta['robots'] ?? 'noindex,follow';
    }

    $content = view('gallery-albums', [
        'heading'  => $heading,
        'subtitle' => $subtitle,
        'albums'   => $albums,
        'total'    => $total,
        'pager'    => pager_data($base, $offset, GALLERY_PER_PAGE, $total),
        'crumbs'   => [['Fotogalerie', url_group('galerie')], [$heading, null]],
    ]);

    echo view('layout', ['meta' => build_meta($meta), 'content' => $content]);
    exit;
}
