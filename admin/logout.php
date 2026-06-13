<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';

logout_user();
header('Location: /admin/login.php');
exit;
