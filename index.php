<?php
declare(strict_types=1);

require __DIR__ . '/cfg.php';
require __DIR__ . '/lib/log.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/renderer.php';
require __DIR__ . '/lib/seo.php';
require __DIR__ . '/lib/articles.php';

/* ---- parsování URL (gramatika starého webu: /typ/hodnota/klíč/hodnota/…) ---- */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = '/' . ltrim(rawurldecode($path), '/');

// CV-only doména (radekhulan.cz): vše vede na CV, žádné menu ani články
if (!empty($GLOBALS['CFG']['cv_only'])) {
    require DIR_ROOT . '/pages/cv.php';
    exit;
}

$segments = array_values(array_filter(explode('/', trim($path, '/')), fn($s) => $s !== ''));
$type = strtolower($segments[0] ?? '');

$params = [];
if (count($segments) >= 2) {
    $params[$type] = $segments[1];
    for ($i = 2; $i + 1 < count($segments); $i += 2) {
        $params[strtolower($segments[$i])] = $segments[$i + 1];
    }
}
$offset = max(0, (int) ($params['offset'] ?? 0));

/* ---- routing ---- */

switch ($type) {
    case '':
        require DIR_ROOT . '/pages/home.php';
        break;

    case 'offset':                                  // stránkování homepage: /offset/10
        $offset = max(0, (int) ($segments[1] ?? 0));
        require DIR_ROOT . '/pages/home.php';
        break;

    case 'item':
        $slug = $params['item'] ?? '';
        require DIR_ROOT . '/pages/item.php';
        break;

    case 'category':
        $slug = $params['category'] ?? '';
        require DIR_ROOT . '/pages/category.php';
        break;

    case 'group':
        $slug = $params['group'] ?? '';
        if ($slug === 'galerie') {
            require DIR_ROOT . '/pages/gallery.php';
        } else {
            require DIR_ROOT . '/pages/group.php';
        }
        break;

    case 'blog':                                    // staré synonymum pro section
        redirect301('/section/' . rawurlencode($params['blog'] ?? ''));

    case 'section':
        $short = $params['section'] ?? '';
        require DIR_ROOT . '/pages/section.php';
        break;

    case 'album':
        $albumId = (int) ($params['album'] ?? 0);
        require DIR_ROOT . '/pages/album.php';
        break;

    case 'fotka':
        $fotkaId = (int) ($params['fotka'] ?? 0);
        require DIR_ROOT . '/pages/fotka.php';
        break;

    case 'tag':
        $tagRaw = $params['tag'] ?? '';
        require DIR_ROOT . '/pages/tag.php';
        break;

    case 'feed':
        $feed = strtolower($params['feed'] ?? '');
        if ($feed === 'rss2' || $feed === 'rss2.xml') {
            require DIR_ROOT . '/pages/feed.php';
        } else {
            redirect301('/feed/rss2');
        }
        break;

    case 'extra':
        if (strtolower($segments[1] ?? '') === 'cv') {
            // CV žije na vlastní doméně — přesměruj tam (zabrání duplicitě obsahu)
            $cvDom = defined('CV_ONLY_DOMAINS') ? (CV_ONLY_DOMAINS[0] ?? null) : null;
            if ($cvDom) {
                redirect301((cfg('is_dev') ? 'https://dev.' : 'https://') . $cvDom . '/');
            }
            require DIR_ROOT . '/pages/cv.php';
        } else {
            redirect301('/');
        }
        break;

    case 'hledani':
        require DIR_ROOT . '/pages/search.php';
        break;

    case 'ajax':
        if (strtolower($segments[1] ?? '') === 'comments') {
            require DIR_ROOT . '/pages/ajax-comments.php';
        } else {
            not_found();
        }
        break;

    case 'archive':
    case 'archives':
    case 'member':
        redirect301('/');

    default:
        not_found();
}
