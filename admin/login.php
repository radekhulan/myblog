<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';

if (is_logged()) {
    header('Location: /admin/');
    exit;
}

$error = null;
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $ip       = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if (bf_blocked($ip)) {
        http_response_code(429);
        blog_log('warn', 'admin login blocked (brute-force)', ['email' => $email, 'ip' => $ip]);
        $error = 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za ' . BF_WINDOW_MIN . ' minut.';
    } elseif (!turnstile_verify($_POST['cf-turnstile-response'] ?? null, $ip)) {
        $error = 'Ověření se nezdařilo. Zkuste to prosím znovu.';
    } else {
        $user = one('SELECT id, email, password_hash FROM ' . tbl('myblog_user') . ' WHERE email = ?', [$email]);
        if ($user && password_verify($password, $user['password_hash'])) {
            bf_clear($ip);
            login_user($user['email']);
            header('Location: /admin/');
            exit;
        }
        bf_fail($ip);
        blog_log('warn', 'admin login failed', ['email' => $email, 'ip' => $ip]);
        sleep(1);
        $error = 'Neplatný e-mail nebo heslo.';
    }
}

ob_start();
?>
<div class="auth-card">
  <div class="panel">
    <h2>Přihlášení do administrace</h2>
    <?php if ($error !== null): ?>
      <div class="flash flash-err" style="margin:0 0 16px"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/admin/login.php">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
      </div>
      <div class="field">
        <label for="password">Heslo</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <div class="cf-turnstile" data-sitekey="<?= e(TURNSTILE_SITE_KEY) ?>"></div>
      <button type="submit" class="btn" style="width:100%">Přihlásit se</button>
    </form>
    <p class="auth-links"><a href="/admin/forgot.php">Zapomenuté heslo</a></p>
  </div>
</div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php
admin_page('Přihlášení', ob_get_clean());
