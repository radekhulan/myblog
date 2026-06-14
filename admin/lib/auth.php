<?php
declare(strict_types=1);

/* MyBlog — bootstrap administrace: konfigurace, knihovny, session, CSRF, layout. */

require_once dirname(__DIR__, 2) . '/cfg.php';
require_once dirname(__DIR__, 2) . '/lib/log.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/lib/helpers.php';
require_once dirname(__DIR__, 2) . '/lib/renderer.php';
require_once dirname(__DIR__, 2) . '/lib/turnstile.php';
require_once dirname(__DIR__, 2) . '/lib/mailer.php';

session_name('myblogadm');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => true,
    'path'     => '/',
]);
session_start();

/* ---------- přihlášení ---------- */

function is_logged(): bool
{
    return ($_SESSION['myblog_admin'] ?? null) !== null;
}

function require_login(): void
{
    if (!is_logged()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function current_admin(): ?string
{
    return $_SESSION['myblog_admin'] ?? null;
}

function login_user(string $email): void
{
    session_regenerate_id(true);
    $_SESSION['myblog_admin'] = $email;
    $_SESSION['ImageEditorAllowed'] = 1; // POVINNÉ — gate file manageru
    exec_q('UPDATE ' . tbl('myblog_user') . ' SET last_login = NOW() WHERE email = ?', [$email]);
}

function logout_user(): void
{
    unset($_SESSION['ImageEditorAllowed']);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}

/* ---------- brute-force ochrana přihlášení (MEMORY tabulka, per IP) ---------- */

const BF_MAX_ATTEMPTS = 8;     // pokusů v okně
const BF_WINDOW_MIN   = 15;    // délka okna v minutách

function bf_ensure_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    q('CREATE TABLE IF NOT EXISTS ' . tbl('myblog_loginfail') . ' (
        ip VARCHAR(45) NOT NULL PRIMARY KEY,
        fails INT NOT NULL DEFAULT 0,
        last_fail DATETIME NOT NULL
    ) ENGINE=MEMORY');
    $done = true;
}

function bf_blocked(string $ip): bool
{
    bf_ensure_table();
    $row = one('SELECT fails, last_fail FROM ' . tbl('myblog_loginfail') . ' WHERE ip = ?', [$ip]);
    if (!$row) {
        return false;
    }
    if (strtotime((string) $row['last_fail']) < time() - BF_WINDOW_MIN * 60) {
        exec_q('DELETE FROM ' . tbl('myblog_loginfail') . ' WHERE ip = ?', [$ip]);
        return false;
    }
    return (int) $row['fails'] >= BF_MAX_ATTEMPTS;
}

function bf_fail(string $ip): void
{
    bf_ensure_table();
    q('INSERT INTO ' . tbl('myblog_loginfail') . ' (ip, fails, last_fail) VALUES (?, 1, NOW())
       ON DUPLICATE KEY UPDATE fails = fails + 1, last_fail = NOW()', [$ip]);
}

function bf_clear(string $ip): void
{
    bf_ensure_table();
    exec_q('DELETE FROM ' . tbl('myblog_loginfail') . ' WHERE ip = ?', [$ip]);
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['myblog_csrf'])) {
        $_SESSION['myblog_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['myblog_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $sent = (string) ($_POST['csrf'] ?? '');
    if ($sent === '' || empty($_SESSION['myblog_csrf']) || !hash_equals($_SESSION['myblog_csrf'], $sent)) {
        http_response_code(403);
        die('Neplatný bezpečnostní token (CSRF). Obnovte stránku a zkuste to znovu.');
    }
}

/* ---------- flash zprávy ---------- */

function flash_set(string $type, string $msg): void
{
    $_SESSION['myblog_flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    $f = $_SESSION['myblog_flash'] ?? null;
    unset($_SESSION['myblog_flash']);
    return $f;
}

/* ---------- layout ---------- */

function admin_page(string $title, string $contentHtml): void
{
    $siteName = (string) cfg('name');
    $script   = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    $nav = '';
    if (is_logged()) {
        $tabs = [
            ['/admin/',                'Články',    in_array($script, ['index.php', 'article.php', 'comments.php'], true)],
            ['/admin/categories.php',  'Kategorie', $script === 'categories.php'],
        ];
        if (has_gallery()) {
            $tabs[] = ['/admin/gallery.php', 'Fotogalerie', in_array($script, ['gallery.php', 'album.php'], true)];
        }
        $tabs[] = ['/admin/password.php',    'Heslo',     $script === 'password.php'];
        $tabs[] = ['/',                      'Zobrazit web ↗', false];
        $tabs[] = ['/admin/logout.php',      'Odhlásit',  false];
        $nav = '<nav class="tabs">';
        foreach ($tabs as [$href, $label, $active]) {
            $nav .= '<a href="' . e($href) . '"' . ($active ? ' class="active"' : '') . '>' . e($label) . '</a>';
        }
        $nav .= '</nav>';
    }

    $flashHtml = '';
    if ($flash = flash_get()) {
        $cls = $flash['type'] === 'ok' ? 'flash-ok' : 'flash-err';
        $flashHtml = '<div class="flash ' . $cls . '">' . e($flash['msg']) . '</div>';
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . e($title) . ' — Administrace ' . e($siteName) . '</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-in">
    <a class="brand" href="/admin/"><strong>' . e($siteName) . '</strong><span>Administrace</span></a>
    ' . $nav . '
  </div>
</header>
<main class="wrap">
' . $flashHtml . '
<h1 class="page-title">' . e($title) . '</h1>
' . $contentHtml . '
</main>
<script>
/* Spolehlivé potvrzení akcí: prvek s atributem data-confirm se před kliknutím zeptá.
   Hodnotu čte getAttribute (ne JS string), takže ji nerozbije žádný znak v textu. */
document.addEventListener("click", function (e) {
  var el = e.target.closest("[data-confirm]");
  if (el && !window.confirm(el.getAttribute("data-confirm"))) {
    e.preventDefault();
    e.stopImmediatePropagation();
  }
}, true);
</script>
</body>
</html>';
    exit;
}
