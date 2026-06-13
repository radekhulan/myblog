<?php
declare(strict_types=1);

/*
 * Router pro PHP built-in server — lokální smoke testy bez IIS.
 * Použití:  set MYBLOG_HOST=dev.myego.cz && php -S 127.0.0.1:8123 install/dev-router.php
 * Simuluje rewrite pravidla z web.config (per-doména media, sitemap, robots).
 */

$_SERVER['HTTP_HOST'] = getenv('MYBLOG_HOST') ?: 'dev.myego.cz';
$_SERVER['HTTPS'] = 'on';

$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$bare = preg_replace('/^(?:www\.|dev\.)/', '', preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']));

$serveFile = function (string $file): bool {
    if (!is_file($file)) {
        return false;
    }
    $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'ttf' => 'font/ttf',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($file));
    readfile($file);
    return true;
};

// per-doména media/img/tmp + favicon (web.config pravidla)
if (preg_match('~^/(media|img|tmp)/(.+)$~', $path, $m)) {
    $rel = str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($m[2]));
    if (str_contains($rel, '..')) {
        http_response_code(403);
        exit('forbidden');
    }
    $file = $root . '/images/' . $bare . '/' . $m[1] . '/' . $rel;
    if ($serveFile($file)) {
        exit;
    }
    http_response_code(404);
    exit('media not found');
}
if ($path === '/favicon.ico') {
    $accent = explode('.', $bare)[0];
    if ($serveFile($root . '/assets/logo/' . $accent . '.ico')) {
        exit;
    }
    http_response_code(404);
    exit;
}
if ($path === '/sitemap.xml') {
    require $root . '/sitemap.php';
    exit;
}
if ($path === '/robots.txt') {
    require $root . '/robots.php';
    exit;
}

// /admin adresář → defaultDocument index.php (jako na IIS)
if (preg_match('~^/admin(?:/|$)~', $path)) {
    $file = $root . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($path));
    if (is_dir($file)) {
        $file = rtrim($file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
        $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    }
    if (is_file($file) && str_ends_with($file, '.php')) {
        chdir(dirname($file));
        require $file;
        exit;
    }
}

// statické soubory (assets, images) servíruj přímo
$physical = $root . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($path));
if ($path !== '/' && is_file($physical)) {
    return false; // built-in server soubor obslouží sám
}

require $root . '/index.php';
