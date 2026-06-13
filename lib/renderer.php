<?php
declare(strict_types=1);

/*
 * Renderer těl článků — věrná reimplementace starých Nucleus tagů a pluginů:
 *   <%image(soubor|W|H|alt)%>  <%popup(soubor|W|H|text)%>  <%media(soubor|text)%>
 *   %video(typ,id)%  %pdf(soubor)%  %flash(...)%  %album(fid[,nVýpis[,nDetail]])%
 * Těla v DB už obsahují <p> (autop probíhal při ukládání) a normalizované iframy.
 */

function render_body(?string $html, array $ctx = []): string
{
    if ($html === null || trim($html) === '') {
        return '';
    }
    $authorId = (int) ($ctx['authorid'] ?? 0);
    $isDetail = (bool) ($ctx['detail'] ?? false);

    // 1. core tagy <%image%> / <%popup%> / <%media%>
    $html = preg_replace_callback(
        '/<\%(image|popup|media)\((.*?)\)\%>/s',
        fn(array $m) => render_core_tag($m[1], $m[2], $authorId),
        $html
    );

    // 2. %video(typ,id)%
    $html = preg_replace_callback('/\%video\((.*?)\)\%/i', function (array $m): string {
        $a = array_map('trim', explode(',', $m[1]));
        return render_video($a[0] ?? '', $a[1] ?? '');
    }, $html);

    // 3. %pdf(soubor)%
    $html = preg_replace_callback('/\%pdf\((.*?)\)\%/i', function (array $m): string {
        $pdf = e(trim($m[1]));
        return '<div class="embed embed-pdf"><object data="' . $pdf . '" type="application/pdf"></object>'
            . '<p class="embed-fallback"><a href="' . $pdf . '">Otevřít PDF</a></p></div>';
    }, $html);

    // 4. %flash(...)% — mrtvá technologie, jen odkaz
    $html = preg_replace_callback('/\%flash\((.*?)\)\%/i', function (array $m): string {
        $a = array_map('trim', explode(',', $m[1]));
        $file = e($a[0] ?? '');
        return '<p class="legacy-flash"><a href="' . $file . '">Flash obsah (' . $file . ') — již nepodporováno</a></p>';
    }, $html);

    // 5. %album(fid[,nVýpis[,nDetail]])%
    $html = preg_replace_callback('/\%album\((.*?)\)\%/i', function (array $m) use ($isDetail): string {
        $a = array_map('trim', explode(',', $m[1]));
        $fid = (int) ($a[0] ?? 0);
        $num = (int) ($a[1] ?? 0);
        if ($isDetail && isset($a[2])) {
            $num = (int) $a[2];
        }
        return $fid ? render_album_embed($fid, $num ?: 6) : '';
    }, $html);

    // 6. <pre> → <pre><code class="language-…"> pro highlighting
    $html = render_pre_blocks($html);

    // 7. smajlíci → unicode emoji a <3 → ❤️ (mimo kód a HTML značky)
    $html = replace_emoticons($html);

    return $html;
}

/** Mapa textových smajlíků na unicode (port NP_FancyText). Klíče s první alfanum. znakem
 *  vyžadují levou hranici, aby se nepletly s běžným textem (např. „bod 8)“). */
function emoticon_map(): array
{
    return [
        ':-)' => '🙂', ':)' => '🙂', '(-:' => '🙂',
        ':-(' => '🙁', ':(' => '🙁',
        ';-)' => '😉', ';)' => '😉',
        ':-D' => '😄', ':D' => '😄',
        ':-P' => '😛', ':P' => '😛', ':-p' => '😛', ':p' => '😛',
        ':oops:' => '😳', ':wink:' => '😉', ':lol:' => '😂', ':cry:' => '😢',
        ':evil:' => '👿', ':twisted:' => '😈', ':roll:' => '🙄',
        ':idea:' => '💡', ':arrow:' => '➡️', ':mrgreen:' => '😁',
        ':!:' => '❗', ':?:' => '❓',
        ':o' => '😮', ':?' => '😕', ':x' => '😡', ':|' => '😐',
        '<3' => '❤️', '&lt;3' => '❤️',
    ];
}

/** Aplikuje callback na textové uzly HTML — mimo <pre>/<code> bloky a mimo značky (atributy). */
function apply_to_text_nodes(string $html, callable $fn): string
{
    $blocks = preg_split('/(<(?:pre|code)\b.*?<\/(?:pre|code)>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($blocks as $bi => $block) {
        if ($bi % 2 === 1) {
            continue;   // obsah <pre>/<code> nechat být
        }
        $nodes = preg_split('/(<[^>]+>)/s', $block, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($nodes as $ni => $node) {
            if ($ni % 2 === 0 && $node !== '') {
                $nodes[$ni] = $fn($node);
            }
        }
        $blocks[$bi] = implode('', $nodes);
    }
    return implode('', $blocks);
}

function replace_emoticons(string $html): string
{
    static $pattern = null, $map = null;
    if ($pattern === null) {
        $map  = emoticon_map();
        $bare = [];     // potřebují levou hranici (začínají alfanum. znakem)
        $rest = [];
        foreach (array_keys($map) as $k) {
            if (ctype_alnum($k[0])) {
                $bare[] = $k;
            } else {
                $rest[] = $k;
            }
        }
        usort($rest, fn($a, $b) => strlen($b) <=> strlen($a));   // delší vzory dřív
        $alt = array_map(fn($k) => preg_quote($k, '~'), $rest);
        $parts = [implode('|', $alt)];
        if ($bare) {
            $parts[] = '(?<![0-9A-Za-z])(?:' . implode('|', array_map(fn($k) => preg_quote($k, '~'), $bare)) . ')';
        }
        $pattern = '~' . implode('|', $parts) . '~u';
    }

    // rychlý filtr: bez : ; 8 ani <3 není co nahrazovat
    if (!preg_match('~[:;8]|<3|&lt;3~', $html)) {
        return $html;
    }
    return apply_to_text_nodes($html, fn(string $t): string =>
        preg_replace_callback($pattern, fn(array $m) => $map[$m[0]] ?? $m[0], $t)
    );
}

function render_core_tag(string $tag, string $rawArgs, int $authorId): string
{
    $args = explode('|', $rawArgs);
    $file = trim($args[0] ?? '');
    if ($file === '') {
        return '';
    }
    if (!str_contains($file, '/')) {
        $file = $authorId . '/' . $file;       // soukromá kolekce autora
    }
    $url = '/img/' . str_replace('%2F', '/', rawurlencode($file));

    switch ($tag) {
        case 'image':
            $w   = (int) ($args[1] ?? 0);
            $h   = (int) ($args[2] ?? 0);
            $alt = e(html_entity_decode(implode('|', array_slice($args, 3)), ENT_QUOTES, 'UTF-8'));
            $dim = ($w > 0 ? ' width="' . $w . '"' : '') . ($h > 0 ? ' height="' . $h . '"' : '');
            return '<img src="' . e($url) . '" alt="' . $alt . '"' . $dim . ' loading="lazy" class="ct-img">';

        case 'popup':
            $w    = (int) ($args[1] ?? 0);
            $h    = (int) ($args[2] ?? 0);
            $text = e(html_entity_decode(implode('|', array_slice($args, 3)), ENT_QUOTES, 'UTF-8'));
            return '<a href="' . e($url) . '" class="lightbox" data-width="' . $w . '" data-height="' . $h . '">'
                . ($text !== '' ? $text : 'obrázek') . '</a>';

        case 'media':
            $text = e(html_entity_decode(implode('|', array_slice($args, 1)), ENT_QUOTES, 'UTF-8'));
            return '<a href="' . e($url) . '">' . ($text !== '' ? $text : basename($file)) . '</a>';
    }
    return '';
}

function render_video(string $typ, string $video): string
{
    $typ = strtolower($typ);
    if ($typ === '' || $video === '') {
        return '';
    }
    switch ($typ) {
        case 'y':
        case 'youtube':
            $id = e(preg_replace('/[^\w\-]/', '', $video));
            return '<div class="embed embed-video"><iframe src="https://www.youtube-nocookie.com/embed/' . $id . '"'
                . ' title="Video (YouTube)" loading="lazy" allow="accelerometer; clipboard-write; encrypted-media;'
                . ' gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>';

        case 'v':
        case 'vimeo':
            $id = e(preg_replace('/[^\d]/', '', $video));
            return '<div class="embed embed-video"><iframe src="https://player.vimeo.com/video/' . $id
                . '?title=0&byline=0&portrait=0" title="Video (Vimeo)" loading="lazy" allowfullscreen></iframe></div>';

        case 'l':
        case 'local':
            $file = e($video);
            return '<p class="embed-fallback"><a href="/media/video/' . $file . '">Video: ' . $file . '</a></p>';

        case 'f':
        case 'facebook':
            $id = e($video);
            return '<p class="embed-fallback"><a href="https://www.facebook.com/v/' . $id
                . '" rel="noopener">Video na Facebooku</a></p>';
    }
    return '';
}

/** Mřížka fotek alba vloženého do článku — náhrada NP_Album (event_PreItem). */
function render_album_embed(int $fid, int $num): string
{
    $album = one('SELECT fid, fnazev, fkategorie FROM ' . tbl('foto') . ' WHERE fid = ?', [$fid]);
    if (!$album) {
        return '';
    }
    $catid = (int) $album['fkategorie'];
    $fotky = all(
        'SELECT oid, onazev, onahled, osoubor FROM ' . tbl('foto_fotka')
        . ' WHERE fid = ? ORDER BY ohodnoceni DESC, oid ASC LIMIT ' . max(1, $num),
        [$fid]
    );
    if (!$fotky) {
        return '';
    }
    $out = '<figure class="album-embed">';
    $out .= '<figcaption class="album-embed-title">Album: <a href="' . e(url_album($fid, $catid)) . '">'
        . e($album['fnazev']) . '</a></figcaption>';
    $out .= '<div class="album-grid">';
    foreach ($fotky as $f) {
        $oid = (int) $f['oid'];
        $out .= '<a href="' . e(url_fotka($oid, $catid)) . '" class="colorshow" rel="album' . $fid . '"'
            . ' data-full="' . e(foto_full_url($oid, $f['osoubor'])) . '"'
            . ' title="' . e($f['onazev']) . '">'
            . '<img src="' . e(foto_thumb_url($oid, $f['onahled'])) . '" alt="' . e($f['onazev']) . '" loading="lazy">'
            . '</a>';
    }
    $out .= '</div></figure>';
    return $out;
}

/** <pre> bloky → <pre><code class="language-…"> (jazyk ze staré class, např. javascript/css/html). */
function render_pre_blocks(string $html): string
{
    if (stripos($html, '<pre') === false) {
        return $html;
    }
    return preg_replace_callback('/<pre([^>]*)>(.*?)<\/pre>/is', function (array $m): string {
        $attrs = $m[1];
        $code  = $m[2];
        if (stripos($code, '<code') !== false) {
            return '<pre class="codeblock"' . $attrs . '>' . $code . '</pre>';
        }
        $lang = '';
        if (preg_match('/class\s*=\s*["\']([^"\']+)["\']/i', $attrs, $cm)) {
            foreach (preg_split('/\s+/', strtolower(trim($cm[1]))) as $cls) {
                if (in_array($cls, ['javascript', 'js', 'css', 'html', 'html5', 'php', 'sql', 'xml', 'csharp', 'cpp', 'c', 'bash', 'ini', 'json'], true)) {
                    $lang = $cls === 'html5' ? 'html' : ($cls === 'js' ? 'javascript' : $cls);
                    break;
                }
            }
        }
        return '<pre class="codeblock"' . ($lang ? ' data-lang="' . e($lang) . '"' : '')
            . '><code' . ($lang ? ' class="language-' . e($lang) . '"' : '') . '>' . $code . '</code></pre>';
    }, $html);
}

/** Komentář: tělo je v DB už zformátované; staré komentáře bez <p> obalit. */
function render_comment(?string $body): string
{
    $body = trim($body ?? '');
    if ($body === '') {
        return '';
    }
    if (!str_contains($body, '<p>')) {
        $body = '<p>' . $body . '</p>';
    }
    // odkazy na jiné komentáře: [12] → proklik na kotvu #cmmnt12
    $body = preg_replace(
        '/(?<![\w\/])\[(\d{1,3})\](?!\()/',
        '<a href="#cmmnt$1" class="comment-ref">[$1]</a>',
        $body
    );
    return replace_emoticons($body);
}
