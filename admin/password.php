<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';
require_login();

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $old = (string) ($_POST['old_password'] ?? '');
    $pw1 = (string) ($_POST['password'] ?? '');
    $pw2 = (string) ($_POST['password2'] ?? '');

    $user = one('SELECT id, password_hash FROM ' . tbl('myblog_user') . ' WHERE email = ?', [current_admin()]);

    if (!$user || !password_verify($old, $user['password_hash'])) {
        $error = 'Stávající heslo není správné.';
    } elseif (mb_strlen($pw1) < 8) {
        $error = 'Nové heslo musí mít alespoň 8 znaků.';
    } elseif ($pw1 !== $pw2) {
        $error = 'Nová hesla se neshodují.';
    } else {
        exec_q(
            'UPDATE ' . tbl('myblog_user') . ' SET password_hash = ? WHERE id = ?',
            [password_hash($pw1, PASSWORD_DEFAULT), (int) $user['id']]
        );
        blog_log('info', 'admin password changed', ['email' => current_admin()]);
        flash_set('ok', 'Heslo bylo změněno.');
        header('Location: /admin/password.php');
        exit;
    }
}

ob_start();
?>
<div class="panel" style="max-width:480px">
  <h2>Změna hesla</h2>
  <?php if ($error !== null): ?>
    <div class="flash flash-err" style="margin:0 0 16px"><?= e($error) ?></div>
  <?php endif; ?>
  <p class="small muted">Účet: <strong><?= e((string) current_admin()) ?></strong></p>
  <form method="post" action="/admin/password.php">
    <?= csrf_field() ?>
    <div class="field">
      <label for="old_password">Stávající heslo</label>
      <input type="password" id="old_password" name="old_password" required autocomplete="current-password">
    </div>
    <div class="field">
      <label for="password">Nové heslo (min. 8 znaků)</label>
      <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
    </div>
    <div class="field">
      <label for="password2">Nové heslo znovu</label>
      <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn">Změnit heslo</button>
  </form>
</div>
<?php
admin_page('Heslo', ob_get_clean());
