<?php
declare(strict_types=1);

/** Najde první obrázek v těle článku (core tag, <img>, odkaz do /media|/img). */
function first_image_url(array $item): ?string
{
    $html = ($item['ibody'] ?? '') . ' ' . ($item['imore'] ?? '');
    if (preg_match('/<\%image\((.*?)\)\%>/s', $html, $m)) {
        $file = trim(explode('|', $m[1])[0]);
        if ($file !== '') {
            if (!str_contains($file, '/')) {
                $file = (int) ($item['iauthor'] ?? 0) . '/' . $file;
            }
            return '/img/' . $file;
        }
    }
    if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('~["\'](/(?:media|img)/[^"\']+\.(?:jpe?g|png|gif|webp))["\']~i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    // článek s vloženým albem → první (nejlépe hodnocená) fotka alba
    if (preg_match('/\%album\((\d+)/i', $html, $m)) {
        $foto = one(
            'SELECT oid, onahled FROM ' . tbl('foto_fotka')
            . ' WHERE fid = ? ORDER BY ohodnoceni DESC, ' . GALLERY_PHOTO_ORDER . ' LIMIT 1',
            [(int) $m[1]]
        );
        if ($foto) {
            return foto_medium_url((int) $foto['oid'], $foto['onahled']);
        }
    }
    return null;
}

/** og:image článku — přímý odkaz na první obrázek z textu, jinak default webu. */
function og_image_for_item(array $item): string
{
    $src = first_image_url($item);
    if (!$src) {
        return cfg('canonical_base') . '/assets/og/' . cfg('accent') . '.png';
    }
    if (preg_match('~^https?://~i', $src)) {
        return preg_replace('~^http://~i', 'https://', $src);
    }
    return cfg('canonical_base') . $src;
}

/**
 * Sestaví meta data pro layout.
 * Klíče: title, description, canonical (path), og_image, og_type, published, robots.
 */
function build_meta(array $opts): array
{
    $siteName = cfg('name');
    return [
        'title'       => isset($opts['title']) && $opts['title'] !== ''
            ? $opts['title'] . ' — ' . $siteName : $siteName . ' — ' . cfg('claim'),
        'description' => $opts['description'] ?? cfg('claim'),
        'canonical'   => cfg('canonical_base') . ($opts['canonical'] ?? '/'),
        'og_image'    => $opts['og_image'] ?? cfg('canonical_base') . '/assets/og/' . cfg('accent') . '.png',
        'og_type'     => $opts['og_type'] ?? 'website',
        'published'   => $opts['published'] ?? null,
        'robots'      => $opts['robots'] ?? null,
    ];
}
