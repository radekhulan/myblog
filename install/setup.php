<?php
declare(strict_types=1);

/*
 * MyBlog — CLI instalátor (idempotentní).
 * Spuštění: & c:\inetpub\php\php.exe c:\inetpub\wwwroot\myblog\install\setup.php
 * Záměrně NEpoužívá db()/tbl() — pro každou doménu z MYBLOG_SITES otevírá vlastní spojení.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Instalátor lze spustit pouze z příkazové řádky.\n");
}

require __DIR__ . '/../cfg.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$errors   = 0;
$warnings = 0;

function out(string $status, string $msg): void
{
    echo '[' . $status . '] ' . $msg . "\n";
}

echo "MyBlog instalátor\n";
echo str_repeat('=', 64) . "\n";

/* ---- společné kontroly ---- */
echo "\n--- Společné ---\n";
$logTest = DIR_LOG . DIRECTORY_SEPARATOR . '.setup-write-test';
if (is_dir(DIR_LOG) && @file_put_contents($logTest, 'test') !== false) {
    @unlink($logTest);
    out('OK', 'Adresář log/ je zapisovatelný (' . DIR_LOG . ')');
} else {
    out('CHYBÍ', 'Adresář log/ NENÍ zapisovatelný (' . DIR_LOG . ')');
    $errors++;
}

/* ---- jednotlivé domény ---- */
foreach (MYBLOG_SITES as $domain => $site) {
    echo "\n=== {$domain} (db={$site['db']}, prefix={$site['prefix']}) ===\n";
    $prefix = $site['prefix'];

    try {
        $db = new mysqli(SQL_HOST, $site['db_user'], $site['db_pass'], $site['db']);
        $db->set_charset('utf8mb4');
        out('OK', 'Připojení k databázi ' . $site['db']);
    } catch (mysqli_sql_exception $e) {
        out('CHYBÍ', 'Připojení k databázi ' . $site['db'] . ' selhalo: ' . $e->getMessage());
        $errors++;
        continue;
    }

    /* 1) tabulka {prefix}myblog_user */
    $userTable = $prefix . 'myblog_user';
    try {
        $db->query(
            'CREATE TABLE IF NOT EXISTS `' . $userTable . '` ('
            . ' id INT AUTO_INCREMENT PRIMARY KEY,'
            . ' email VARCHAR(190) NOT NULL UNIQUE,'
            . ' password_hash VARCHAR(255) NOT NULL,'
            . ' reset_token_hash VARCHAR(64) NULL,'
            . ' reset_expires DATETIME NULL,'
            . ' last_login DATETIME NULL,'
            . ' created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        out('OK', 'Tabulka ' . $userTable);
    } catch (mysqli_sql_exception $e) {
        out('CHYBÍ', 'Tabulku ' . $userTable . ' se nepodařilo vytvořit: ' . $e->getMessage());
        $errors++;
        $db->close();
        continue;
    }

    /* 2) seed admin účtu */
    try {
        $stmt = $db->prepare('SELECT id FROM `' . $userTable . '` WHERE email = ?');
        $stmt->execute([ADMIN_EMAIL]);
        $exists = $stmt->get_result()->fetch_row();
        if ($exists) {
            out('OK', 'Admin účet ' . ADMIN_EMAIL . ' existuje');
        } else {
            $hash = password_hash('CHANGEME', PASSWORD_DEFAULT);
            $ins  = $db->prepare('INSERT INTO `' . $userTable . '` (email, password_hash) VALUES (?, ?)');
            $ins->execute([ADMIN_EMAIL, $hash]);
            out('OK', 'Admin účet ' . ADMIN_EMAIL . ' vytvořen (heslo: CHANGEME — po přihlášení ihned změňte!)');
        }
    } catch (mysqli_sql_exception $e) {
        out('CHYBÍ', 'Seed admin účtu selhal: ' . $e->getMessage());
        $errors++;
    }

    /* 3) FULLTEXT index na item(ititle, ibody, imore) */
    $itemTable = $prefix . 'item';
    try {
        $stmt = $db->prepare(
            'SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols'
            . ' FROM information_schema.STATISTICS'
            . " WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_TYPE = 'FULLTEXT'"
            . ' GROUP BY INDEX_NAME'
        );
        $stmt->execute([$site['db'], $itemTable]);
        $ftName = null;
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $idx) {
            $cols = explode(',', (string) $idx['cols']);
            sort($cols);
            if ($cols === ['ibody', 'imore', 'ititle']) {
                $ftName = $idx['INDEX_NAME'];
                break;
            }
        }
        if ($ftName !== null) {
            out('OK', 'FULLTEXT index na ' . $itemTable . ' (ititle, ibody, imore) — `' . $ftName . '`');
        } else {
            echo "      ... vytvářím FULLTEXT index, může chvíli trvat\n";
            $db->query('ALTER TABLE `' . $itemTable . '` ADD FULLTEXT `myblog_search` (ititle, ibody, imore)');
            out('OK', 'FULLTEXT index `myblog_search` na ' . $itemTable . ' vytvořen');
        }
    } catch (mysqli_sql_exception $e) {
        out('CHYBÍ', 'Kontrola/vytvoření FULLTEXT indexu selhalo: ' . $e->getMessage());
        $errors++;
    }

    /* 4) obrázkové adresáře images/{doména}/{media,img,tmp} (fyzické úložiště, ručně nakopírované) */
    $imgRoot = DIR_IMAGES . DIRECTORY_SEPARATOR . $domain;
    foreach (['media', 'img', 'tmp'] as $dir) {
        $path = $imgRoot . DIRECTORY_SEPARATOR . $dir;
        if (is_dir($path)) {
            out('OK', 'images/' . $domain . '/' . $dir);
        } else {
            out('--', 'images/' . $domain . '/' . $dir . ' — adresář chybí, nakopírujte sem obsah webu');
            $warnings++;
        }
    }

    $db->close();
}

echo "\n" . str_repeat('=', 64) . "\n";
if ($errors === 0) {
    echo 'Hotovo, vše v pořádku' . ($warnings > 0 ? " ({$warnings} upozornění, viz výše)" : '') . ".\n";
} else {
    echo "Dokončeno s problémy: {$errors} (viz [CHYBÍ] výše).\n";
}
exit($errors > 0 ? 1 : 0);
