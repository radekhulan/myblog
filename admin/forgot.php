<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';

if (is_logged()) {
    header('Location: /admin/');
    exit;
}

$error = null;
$done  = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $email = trim((string) ($_POST['email'] ?? ''));
    $ip    = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if (!turnstile_verify($_POST['cf-turnstile-response'] ?? null, $ip)) {
        $error = 'Ověření se nezdařilo. Zkuste to prosím znovu.';
    } else {
        $user = one('SELECT id, email FROM ' . tbl('myblog_user') . ' WHERE email = ?', [$email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            exec_q(
                'UPDATE ' . tbl('myblog_user')
                . ' SET reset_token_hash = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?',
                [hash('sha256', $token), (int) $user['id']]
            );
            $link = cfg('base_url') . '/admin/reset.php?token=' . $token;

            $html = '<div style="font-family:\'Segoe UI\',system-ui,sans-serif;max-width:520px;margin:0 auto;'
                . 'padding:28px 24px;border:1px solid #e6e2f0;border-radius:12px;color:#1f2937">'
                . '<h2 style="margin:0 0 16px;color:#3B2D83;font-size:20px">Obnova hesla — '
                . e((string) cfg('name')) . '</h2>'
                . '<p>Dobrý den,</p>'
                . '<p>obdrželi jsme žádost o obnovu hesla do administrace. Pro nastavení nového hesla '
                . 'klikněte na tlačítko níže. Odkaz je platný 30 minut.</p>'
                . '<p style="text-align:center;margin:28px 0"><a href="' . e($link) . '" '
                . 'style="background:#6753AE;color:#ffffff;padding:13px 30px;border-radius:8px;'
                . 'text-decoration:none;display:inline-block;font-weight:600">Nastavit nové heslo</a></p>'
                . '<p>Pokud jste o obnovu hesla nežádali, tento e-mail ignorujte — heslo zůstává beze změny.</p>'
                . '<p style="color:#6b7280;font-size:13px;word-break:break-all">Odkaz: ' . e($link) . '</p>'
                . '</div>';

            if (!send_mail($user['email'], 'Obnova hesla do administrace ' . cfg('name'), $html)) {
                blog_log('warn', 'Reset odkaz (mail selhal): ' . $link, ['email' => $user['email'], 'ip' => $ip]);
            }
        }
        $done = true; // neutrální odpověď bez ohledu na existenci účtu
    }
}

ob_start();
?>
<div class="auth-card">
  <div class="panel">
    <h2>Zapomenuté heslo</h2>
    <?php if ($done): ?>
      <div class="flash flash-ok" style="margin:0 0 16px">Pokud účet existuje, poslali jsme na zadaný e-mail odkaz pro obnovu hesla.</div>
      <p class="auth-links"><a href="/admin/login.php">Zpět na přihlášení</a></p>
    <?php else: ?>
      <?php if ($error !== null): ?>
        <div class="flash flash-err" style="margin:0 0 16px"><?= e($error) ?></div>
      <?php endif; ?>
      <p class="small muted">Zadejte e-mail svého účtu. Pošleme vám odkaz pro nastavení nového hesla.</p>
      <form method="post" action="/admin/forgot.php">
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" required autofocus autocomplete="username">
        </div>
        <div class="cf-turnstile" data-sitekey="<?= e(TURNSTILE_SITE_KEY) ?>"></div>
        <button type="submit" class="btn" style="width:100%">Odeslat odkaz</button>
      </form>
      <p class="auth-links"><a href="/admin/login.php">Zpět na přihlášení</a></p>
    <?php endif; ?>
  </div>
</div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php
admin_page('Zapomenuté heslo', ob_get_clean());
