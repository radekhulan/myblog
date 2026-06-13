<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$user  = null;

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $user = one(
        'SELECT id, email FROM ' . tbl('myblog_user')
        . ' WHERE reset_token_hash = ? AND reset_expires > NOW()',
        [hash('sha256', $token)]
    );
}

if (!$user) {
    ob_start();
    ?>
    <div class="auth-card">
      <div class="panel">
        <h2>Neplatný odkaz</h2>
        <div class="flash flash-err" style="margin:0 0 16px">Odkaz pro obnovu hesla je neplatný nebo jeho platnost vypršela.</div>
        <p class="auth-links"><a href="/admin/forgot.php">Vyžádat nový odkaz</a> · <a href="/admin/login.php">Přihlášení</a></p>
      </div>
    </div>
    <?php
    admin_page('Obnova hesla', ob_get_clean());
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $pw1 = (string) ($_POST['password'] ?? '');
    $pw2 = (string) ($_POST['password2'] ?? '');

    if (mb_strlen($pw1) < 8) {
        $error = 'Heslo musí mít alespoň 8 znaků.';
    } elseif ($pw1 !== $pw2) {
        $error = 'Hesla se neshodují.';
    } else {
        exec_q(
            'UPDATE ' . tbl('myblog_user')
            . ' SET password_hash = ?, reset_token_hash = NULL, reset_expires = NULL WHERE id = ?',
            [password_hash($pw1, PASSWORD_DEFAULT), (int) $user['id']]
        );
        blog_log('info', 'admin password reset', ['email' => $user['email']]);
        flash_set('ok', 'Heslo bylo změněno. Nyní se můžete přihlásit.');
        header('Location: /admin/login.php');
        exit;
    }
}

ob_start();
?>
<div class="auth-card">
  <div class="panel">
    <h2>Nastavení nového hesla</h2>
    <?php if ($error !== null): ?>
      <div class="flash flash-err" style="margin:0 0 16px"><?= e($error) ?></div>
    <?php endif; ?>
    <p class="small muted">Účet: <strong><?= e($user['email']) ?></strong></p>
    <form method="post" action="/admin/reset.php">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field">
        <label for="password">Nové heslo (min. 8 znaků)</label>
        <input type="password" id="password" name="password" required minlength="8" autofocus autocomplete="new-password">
      </div>
      <div class="field">
        <label for="password2">Nové heslo znovu</label>
        <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
      </div>
      <button type="submit" class="btn" style="width:100%">Uložit nové heslo</button>
    </form>
  </div>
</div>
<?php
admin_page('Obnova hesla', ob_get_clean());
