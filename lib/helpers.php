<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function cfg(string $key): mixed
{
    return $GLOBALS['CFG'][$key] ?? null;
}

/** Vyrenderuje šablonu z /templates do stringu. */
function view(string $template, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include DIR_ROOT . '/templates/' . $template . '.php';
    return ob_get_clean();
}

/* ---------- URL buildery (gramatika starého webu: páry klíč/hodnota) ---------- */

function url_item(string $slug): string
{
    return '/item/' . rawurlencode($slug);
}

function url_offset(string $base, int $offset): string
{
    if ($offset <= 0) {
        return $base === '' ? '/' : $base;
    }
    return ($base === '/' ? '' : rtrim($base, '/')) . '/offset/' . $offset;
}

function url_category(string $slug): string
{
    return '/category/' . rawurlencode($slug);
}

function url_group(string $slug): string
{
    return '/group/' . rawurlencode($slug);
}

function url_section(string $short): string
{
    return '/section/' . rawurlencode($short);
}

function url_tag(int $id, string $slug): string
{
    return '/tag/' . $id . '-' . rawurlencode($slug);
}

function url_album(int $fid, ?int $catid = null): string
{
    $slug = $catid ? cat_slug((int) $catid) : null;
    return '/album/' . $fid . ($slug ? '/category/' . rawurlencode($slug) : '');
}

function url_fotka(int $oid, ?int $catid = null): string
{
    $slug = $catid ? cat_slug((int) $catid) : null;
    return '/fotka/' . $oid . ($slug ? '/category/' . rawurlencode($slug) : '');
}

function cat_slug(int $catid): ?string
{
    static $cache = [];
    if (!array_key_exists($catid, $cache)) {
        $cache[$catid] = scalar('SELECT iurltitle FROM ' . tbl('category') . ' WHERE catid = ?', [$catid]);
    }
    return $cache[$catid];
}

/* ---------- fotogalerie: cesty k souborům (struktura starého webu) ---------- */

function foto_dir(int $id): int
{
    return intdiv($id, 1000);
}

function foto_thumb_url(int $oid, string $nahled): string
{
    return '/media/thumb/' . foto_dir($oid) . '/' . rawurlencode($nahled);
}

function foto_medium_url(int $oid, string $nahled): string
{
    return '/media/thumb/' . foto_dir($oid) . '/m' . rawurlencode($nahled);
}

function foto_full_url(int $oid, string $soubor): string
{
    return '/media/foto/' . foto_dir($oid) . '/' . rawurlencode($soubor);
}

/* ---------- texty a data ---------- */

function cz_date(string $datetime, bool $withTime = false): string
{
    static $months = [1 => 'ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];
    $ts = strtotime($datetime);
    if (!$ts) {
        return '';
    }
    $out = date('j', $ts) . '. ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    if ($withTime) {
        $out .= ' v ' . date('G:i', $ts);
    }
    return $out;
}

/** Český plurál: cz_plural(5, ['komentář', 'komentáře', 'komentářů']). */
function cz_plural(int $n, array $forms): string
{
    if ($n === 1) {
        return $forms[0];
    }
    if ($n >= 2 && $n <= 4) {
        return $forms[1];
    }
    return $forms[2];
}

function truncate_text(string $html, int $len = 200): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    $text = mb_substr($text, 0, $len);
    $cut = mb_strrpos($text, ' ');
    return ($cut !== false ? mb_substr($text, 0, $cut) : $text) . '…';
}

/** Port makeFancyUrl() ze starého webu — SEO slug z titulku. */
function slugify(string $title): string
{
    $title = strip_tags(trim(preg_replace('/&(.*?);/', '', $title)));
    static $map = [
        'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'ë' => 'e',
        'í' => 'i', 'ï' => 'i', 'ľ' => 'l', 'ĺ' => 'l', 'ň' => 'n', 'ó' => 'o', 'ö' => 'o',
        'ô' => 'o', 'ř' => 'r', 'ŕ' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u',
        'ü' => 'u', 'ý' => 'y', 'ž' => 'z', 'ß' => 'ss',
    ];
    $title = strtr(mb_strtolower($title, 'UTF-8'), $map);
    preg_match_all('/[a-z0-9]+/', $title, $m);
    return implode('-', $m[0]);
}

/** Titulky ze staré DB mohou obsahovat HTML entity — dekódovat před escapováním. */
function title_text(?string $s): string
{
    return html_entity_decode($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Escapuje text a zvýrazní hledané výrazy přes <mark>. */
function mark_text(string $text, ?array $terms): string
{
    $out = e($text);
    if (!$terms) {
        return $out;
    }
    $quoted = array_map(fn($t) => preg_quote(e($t), '/'), array_filter($terms));
    if (!$quoted) {
        return $out;
    }
    return preg_replace('/(' . implode('|', $quoted) . ')/iu', '<mark>$1</mark>', $out);
}

/** Je návštěvník přihlášený admin? (čte admin session jen pokud existuje cookie) */
function fe_is_admin(): bool
{
    static $is = null;
    if ($is !== null) {
        return $is;
    }
    $is = false;
    if (!empty($_COOKIE['myblogadm']) && session_status() === PHP_SESSION_NONE) {
        session_name('myblogadm');
        session_start();
        $is = !empty($_SESSION['myblog_admin']);
        session_write_close();
    }
    return $is;
}

/** SVG ikona pro CV (sociální sítě a projekty). Neznámý název → generická tečka. */
function cv_icon(string $name): string
{
    static $icons = [
        'github'    => '<path fill="currentColor" d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.9 1.52 2.34 1.08 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.56-1.11-4.56-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.64 0 0 .84-.27 2.75 1.02a9.58 9.58 0 0 1 5 0c1.91-1.3 2.75-1.02 2.75-1.02.55 1.37.2 2.39.1 2.64.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.68-4.57 4.93.36.31.68.92.68 1.85V21c0 .27.18.58.69.48A10 10 0 0 0 12 2z"/>',
        'threads'   => '<path fill="currentColor" d="M12.7 11.1c.8.04 4.6.5 4.6 4.06 0 2.69-2.06 4.84-5.27 4.84-3.66 0-6.53-2.6-6.53-8S8.37 4 12.03 4c2.93 0 4.88 1.46 5.84 3.5l-1.86.77c-.66-1.4-1.97-2.42-3.98-2.42-2.6 0-4.6 2.06-4.6 6.15s2 6.15 4.6 6.15c2.1 0 3.4-1.18 3.4-2.93 0-1.86-1.78-2.36-2.84-2.4-.5-.02-1 .02-1.45.13-.3-.6-.43-1.27-.36-1.96.62-.13 1.28-.16 1.92-.13.04-.7-.3-1.4-1.27-1.4-.84 0-1.34.4-1.6.9l-1.8-.76c.62-1.2 1.83-2 3.43-2 2.2 0 3.4 1.4 3.34 3.5z"/>',
        'twitter'   => '<path fill="currentColor" d="M17.6 3h3l-6.6 7.6L21.8 21h-6.1l-4.8-6.2L5.4 21h-3l7-8.1L2.2 3h6.3l4.3 5.7L17.6 3zm-1.1 16.2h1.7L7.6 4.7H5.8l10.7 14.5z"/>',
        'facebook'  => '<path fill="currentColor" d="M13.5 21v-7h2.4l.4-3h-2.8V9.1c0-.87.24-1.46 1.49-1.46h1.39V5a18 18 0 0 0-2.06-.11c-2.16 0-3.65 1.32-3.65 3.75V11H8.3v3h2.37v7h2.83z"/>',
        'linkedin'  => '<path fill="currentColor" d="M6.94 8.5H3.86V21h3.08V8.5zM5.4 3a1.85 1.85 0 1 0 0 3.7 1.85 1.85 0 0 0 0-3.7zM21 13.39c0-3.42-1.83-5.15-4.27-5.15-1.97 0-2.85 1.08-3.34 1.84V8.5H10.3V21h3.08v-6.95c0-1.4.95-2.23 2.1-2.23 1.12 0 2.43.6 2.43 2.7V21H21v-7.61z"/>',
        'instagram' => '<path fill="currentColor" d="M12 2.2c-2.67 0-3 .01-4.05.06-1.05.05-1.77.21-2.4.46a4.84 4.84 0 0 0-1.75 1.14A4.84 4.84 0 0 0 2.66 5.6c-.25.63-.41 1.35-.46 2.4C2.15 9.05 2.14 9.38 2.14 12s.01 2.95.06 4c.05 1.05.21 1.77.46 2.4.26.65.6 1.2 1.14 1.75.55.54 1.1.88 1.75 1.14.63.25 1.35.41 2.4.46 1.05.05 1.38.06 4.05.06s3-.01 4.05-.06c1.05-.05 1.77-.21 2.4-.46a4.84 4.84 0 0 0 1.75-1.14c.54-.55.88-1.1 1.14-1.75.25-.63.41-1.35.46-2.4.05-1.05.06-1.38.06-4s-.01-2.95-.06-4c-.05-1.05-.21-1.77-.46-2.4a4.84 4.84 0 0 0-1.14-1.75 4.84 4.84 0 0 0-1.75-1.14c-.63-.25-1.35-.41-2.4-.46-1.05-.05-1.38-.06-4.05-.06zm0 1.8c2.62 0 2.93.01 3.96.06.96.04 1.48.2 1.82.34.46.18.79.39 1.13.73.34.34.55.67.73 1.13.13.34.3.86.34 1.82.05 1.03.06 1.34.06 3.92s-.01 2.89-.06 3.92c-.04.96-.2 1.48-.34 1.82-.18.46-.39.79-.73 1.13-.34.34-.67.55-1.13.73-.34.13-.86.3-1.82.34-1.03.05-1.34.06-3.96.06s-2.93-.01-3.96-.06c-.96-.04-1.48-.2-1.82-.34a3.04 3.04 0 0 1-1.13-.73 3.04 3.04 0 0 1-.73-1.13c-.13-.34-.3-.86-.34-1.82-.05-1.03-.06-1.34-.06-3.92s.01-2.89.06-3.92c.04-.96.2-1.48.34-1.82.18-.46.39-.79.73-1.13.34-.34.67-.55 1.13-.73.34-.13.86-.3 1.82-.34 1.03-.05 1.34-.06 3.96-.06zm0 3.06a4.94 4.94 0 1 0 0 9.88 4.94 4.94 0 0 0 0-9.88zm0 8.14a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4zm6.28-8.34a1.15 1.15 0 1 1-2.3 0 1.15 1.15 0 0 1 2.3 0z"/>',
        'web'       => '<path fill="currentColor" d="M3 4h18a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-7v2h3v2H7v-2h3v-2H3a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zm1 2v9h16V6H4z"/>',
        'invoice'   => '<path fill="currentColor" d="M6 2h12a1 1 0 0 1 1 1v18l-3-2-2 2-2-2-2 2-2-2-3 2V3a1 1 0 0 1 1-1zm2 5v2h8V7H8zm0 4v2h8v-2H8zm0 4v2h5v-2H8z"/>',
    ];
    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="6" fill="currentColor"/>';
    return '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">' . $path . '</svg>';
}

/** Má databáze fotogalerii? (existuje tabulka {prefix}foto) — výsledek se cachuje. */
function has_gallery(): bool
{
    static $has = null;
    if ($has === null) {
        $has = (int) scalar(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [tbl('foto')]
        ) > 0;
    }
    return $has;
}

/* ---------- HTTP ---------- */

function redirect301(string $to): never
{
    header('Location: ' . $to, true, 301);
    exit;
}

function not_found(): never
{
    http_response_code(404);
    echo view('layout', [
        'title'   => 'Stránka nenalezena',
        'content' => view('404'),
        'robots'  => 'noindex',
    ]);
    exit;
}

/** Data pro šablonu stránkování. */
function pager_data(string $base, int $offset, int $perPage, int $total): array
{
    return [
        'prev'  => $offset > 0 ? url_offset($base, max(0, $offset - $perPage)) : null,
        'next'  => ($offset + $perPage) < $total ? url_offset($base, $offset + $perPage) : null,
        'page'  => intdiv($offset, max(1, $perPage)) + 1,
        'pages' => (int) ceil($total / max(1, $perPage)),
    ];
}
