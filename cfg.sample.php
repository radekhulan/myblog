<?php
declare(strict_types=1);

/*
 * MyBlog — VZOR konfigurace. Zkopíruj na produkci jako `cfg.php` a vyplň reálné hodnoty.
 * `cfg.php` je v .gitignore (obsahuje hesla, Turnstile secret, SMTP) — NIKDY ho necommituj.
 *
 * Systém zvládá jeden i více webů z jednoho codebase. Web se podle HTTP_HOST
 * (po odstranění www. / dev.) rozhodne, kterou databázi a branding použít.
 * Pro každý web přidej položku do MYBLOG_SITES; klíč = produkční doména (bez www).
 */

const MYBLOG_SITES = [
    'example.com' => [
        'db'      => 'example_db',
        'db_user' => 'example_user',
        'db_pass' => 'change-me-strong-password',
        'prefix'  => 'blog_',                       // prefix tabulek v databázi
        'name'    => 'Example Blog',
        'claim'   => 'Krátký popis webu do hlavičky',
        'accent'  => 'example',                      // název SVG loga v assets/logo/{accent}.svg
    ],
    'second-site.com' => [
        'db'      => 'second_db',
        'db_user' => 'second_user',
        'db_pass' => 'change-me-strong-password',
        'prefix'  => 'blog_',
        'name'    => 'Second Site',
        'claim'   => 'Another short tagline',
        'accent'  => 'second',
    ],
];

// Domény zobrazující jen CV (/extra/cv obsah), bez menu a článků. Prázdné = funkce vypnutá.
const CV_ONLY_DOMAINS = [];                 // např. ['mojejmeno.cz']

// Staré domény přesměrované (301) na cílový web. Klíč = alias (bez www.), hodnota = cílová doména.
const DOMAIN_ALIASES = [];                  // např. ['stara-domena.cz' => 'example.com']

const SQL_HOST = 'localhost';

const MAIN_BLOG = 1;   // hlavní blog (bnumber)
const FOTO_BLOG = 2;   // fotogalerie (bnumber)

const PER_PAGE          = 10;  // článků na stránku (FE)
const ADMIN_PER_PAGE    = 25;  // článků na stránku (admin)
const COMMENTS_INITIAL  = 20;  // komentářů zobrazených bez AJAXu
const COMMENTS_CHUNK    = 30;  // kolik komentářů donačte jeden AJAX požadavek
const GALLERY_PER_PAGE  = 15;  // alb na stránku v galerii

// Cloudflare Turnstile (ochrana administrace) — klíče z dashboardu Cloudflare
const TURNSTILE_SITE_KEY   = '1x00000000000000000000AA';   // testovací site key (vždy projde)
const TURNSTILE_SECRET_KEY = '1x0000000000000000000000000000000AA';

// SMTP pro PHPMailer (obnova hesla); když odeslání selže, na dev se odkaz zaloguje
const SMTP_HOST   = 'localhost';
const SMTP_PORT   = 25;
const SMTP_USER   = '';
const SMTP_PASS   = '';
const SMTP_SECURE = '';                  // '', 'tls' nebo 'ssl'
const MAIL_FROM   = 'admin@example.com';
const ADMIN_EMAIL = 'admin@example.com';

// Autor/vývojář zobrazený v horní liště (volitelné; null = nezobrazí se nic)
const SITE_AUTHOR = [
    'name'  => 'Your Name',
    'photo' => '/assets/author.webp',      // necommitovaný osobní soubor (viz .gitignore)
    'cv'    => '/extra/cv',                 // odkaz na rozcestník; null = bez odkazu
    'links' => [
        ['label' => 'Your Studio',  'url' => 'https://example.com/',     'title' => 'Web development'],
        ['label' => 'Your Product', 'url' => 'https://example-app.com/',  'title' => 'A product you build'],
    ],
];

// Rozcestník na /extra/cv (volitelné; null = stránka i odkaz v hlavičce se nezobrazí).
// Pole socials/projects: 'icon' = github|threads|twitter|facebook|linkedin|instagram|web|invoice
const CV_PROFILE = [
    'name'  => 'Your Name',
    'photo' => '/assets/author.webp',
    // Volitelná interaktivní SVG animace v hero sekci CV. Cesta relativní k images/{doména}/
    // (soubor mimo repo); inlinuje se do stránky kvůli JS a dědění tématu. null = vypnuto.
    'hero_svg' => null,
    'role'  => 'Developer &amp; blogger',
    'bio'   => 'Short bio with <strong>highlights</strong> about you and your work.',
    'stats' => [
        ['20+', 'years of experience'],
        ['50+', 'projects delivered'],
    ],
    'github_user' => 'your-github',
    'socials' => [
        ['icon' => 'github',  'label' => 'GitHub',   'sub' => '@your-github', 'url' => 'https://github.com/your-github'],
        ['icon' => 'twitter', 'label' => 'X',        'sub' => '@you',         'url' => 'https://twitter.com/you'],
    ],
    'repos' => [
        ['name' => 'your-repo', 'desc' => 'Description of your project', 'stars' => 0],
    ],
    'projects' => [
        ['icon' => 'web', 'title' => 'Your Studio', 'url' => 'https://example.com/', 'desc' => 'What your company does'],
    ],
];

define('DIR_ROOT', __DIR__);
define('DIR_LOG', __DIR__ . DIRECTORY_SEPARATOR . 'log');
define('DIR_IMAGES', __DIR__ . DIRECTORY_SEPARATOR . 'images');

/* ---- výběr webu dle domény ---- */
$myblogHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$myblogHost = preg_replace('/:\d+$/', '', $myblogHost);
$myblogBare = preg_replace('/^(?:www\.|dev\.)/', '', $myblogHost);

if (PHP_SAPI === 'cli' && !isset(MYBLOG_SITES[$myblogBare])) {
    $myblogBare = getenv('MYBLOG_SITE') ?: array_key_first(MYBLOG_SITES);
    $myblogHost = 'dev.' . $myblogBare;
}

if (!isset(MYBLOG_SITES[$myblogBare])) {
    header('Location: https://' . array_key_first(MYBLOG_SITES) . '/', true, 301);
    exit;
}

$GLOBALS['CFG'] = MYBLOG_SITES[$myblogBare] + [
    'domain'         => $myblogBare,                // produkční doména (bez www)
    'host'           => $myblogHost,                // aktuální host (dev.* při vývoji)
    'is_dev'         => str_starts_with($myblogHost, 'dev.'),
    'base_url'       => 'https://' . $myblogHost,   // dev i produkce běží na HTTPS
    'canonical_base' => 'https://' . $myblogBare,   // canonical/OG vždy produkční doména
    'media_dir'      => DIR_IMAGES . DIRECTORY_SEPARATOR . $myblogBare,
];
unset($myblogHost, $myblogBare);
