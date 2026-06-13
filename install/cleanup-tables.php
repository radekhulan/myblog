<?php
declare(strict_types=1);

/*
 * cleanup-tables.php — CLI nástroj na úklid nepoužívaných tabulek staré DB.
 *
 * Projde všechny tabulky s prefixem dané domény a ty, které nový CMS NEpoužívá,
 * označí jako nepotřebné. NIC nemaže bez explicitního potvrzení.
 *
 * Použití:
 *   php install/cleanup-tables.php                 # jen vypíše (dry-run, NIC nemaže)
 *   php install/cleanup-tables.php --site=myego.cz # jen jedna doména
 *   php install/cleanup-tables.php --backup        # vygeneruje SQL zálohu (CREATE+INSERT) do install/_backup/
 *   php install/cleanup-tables.php --drop          # ZÁLOHUJE a poté DROPNE (ptá se na potvrzení)
 *   php install/cleanup-tables.php --drop --yes    # bez interaktivního potvrzení (pro skript)
 *
 * Doporučený postup: nejdřív bez parametrů (kontrola), pak --backup (záloha),
 * teprve potom --drop. Záloha umožní tabulky kdykoli obnovit importem.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Pouze z příkazové řádky.\n");
}

require __DIR__ . '/../cfg.php';

// tabulky (bez prefixu), které nový CMS používá — TY se NIKDY nesmažou
const KEEP_TABLES = [
    'item', 'comment', 'category', 'subcategory', 'blog', 'member',
    'foto', 'foto_fotka', 'tags', 'tags_item', 'plugin_fancierurl',
    'myblog_user', 'myblog_loginfail',
];

$opts    = getopt('', ['site::', 'backup', 'drop', 'yes']);
$doDrop  = isset($opts['drop']);
$doBackup = isset($opts['backup']) || $doDrop;     // drop vždy nejdřív zálohuje
$autoYes = isset($opts['yes']);
$onlySite = $opts['site'] ?? null;

$backupDir = __DIR__ . DIRECTORY_SEPARATOR . '_backup';

echo str_repeat('=', 70) . "\n";
echo "MyBlog — úklid nepoužívaných tabulek" . ($doDrop ? ' (REŽIM MAZÁNÍ)' : ($doBackup ? ' (ZÁLOHA)' : ' (jen výpis)')) . "\n";
echo str_repeat('=', 70) . "\n";

$grandUnused = 0;

foreach (MYBLOG_SITES as $domain => $site) {
    if ($onlySite !== null && $domain !== $onlySite) {
        continue;
    }
    echo "\n### {$domain} — databáze {$site['db']}, prefix {$site['prefix']}\n";

    try {
        $db = new mysqli(SQL_HOST, $site['db_user'], $site['db_pass'], $site['db']);
        $db->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        echo "  [CHYBA] připojení selhalo: {$e->getMessage()}\n";
        continue;
    }

    $prefix = $site['db'] === '' ? '' : $site['prefix'];
    $keep   = array_map(fn($t) => $prefix . $t, KEEP_TABLES);

    // všechny objekty s prefixem (tabulky i views)
    $stmt = $db->prepare(
        'SELECT table_name, table_type, table_rows, ROUND((data_length + index_length)/1024/1024, 2) AS mb
         FROM information_schema.tables
         WHERE table_schema = ? AND table_name LIKE ?
         ORDER BY table_name'
    );
    // LIKE potřebuje escapnout _ a % v prefixu
    $likePattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';
    $stmt->bind_param('ss', $site['db'], $likePattern);
    $stmt->execute();
    $tables = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $unused = [];   // [název => 'VIEW' | 'BASE TABLE']
    foreach ($tables as $t) {
        $name = $t['table_name'];
        $isView = $t['table_type'] === 'VIEW';
        $kept = in_array($name, $keep, true);
        $mark = $kept ? 'PONECHAT' : 'nepoužívá se';
        printf("  %-12s %-32s %8s řádků  %6s MB  %s\n", $mark, $name,
            number_format((float) ($t['table_rows'] ?? 0), 0, '', ' '),
            $t['mb'] ?? '-', $isView ? 'VIEW' : '');
        if (!$kept) {
            $unused[$name] = $isView ? 'VIEW' : 'BASE TABLE';
        }
    }

    echo "  ── celkem " . count($tables) . " objektů, " . count($unused) . " nepoužívaných\n";
    $grandUnused += count($unused);

    if (!$unused) {
        $db->close();
        continue;
    }

    // záloha
    if ($doBackup) {
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        $file = $backupDir . DIRECTORY_SEPARATOR . $site['db'] . '-unused-' . date('Ymd-His') . '.sql';
        $sql = "-- MyBlog záloha nepoužívaných objektů z DB {$site['db']}\n"
             . "-- " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach ($unused as $name => $type) {
            if ($type === 'VIEW') {
                $row = $db->query('SHOW CREATE VIEW `' . $name . '`')->fetch_assoc();
                $def = preg_replace('/DEFINER=`[^`]*`@`[^`]*` ?/', '', (string) $row['Create View']);
                $sql .= "DROP VIEW IF EXISTS `{$name}`;\n{$def};\n\n";
                continue;
            }
            $create = $db->query('SHOW CREATE TABLE `' . $name . '`')->fetch_assoc();
            $sql .= "DROP TABLE IF EXISTS `{$name}`;\n" . $create['Create Table'] . ";\n\n";
            $rows = $db->query('SELECT * FROM `' . $name . '`');
            while ($r = $rows->fetch_assoc()) {
                $cols = '`' . implode('`,`', array_keys($r)) . '`';
                $vals = implode(',', array_map(
                    fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string((string) $v) . "'",
                    array_values($r)
                ));
                $sql .= "INSERT INTO `{$name}` ({$cols}) VALUES ({$vals});\n";
            }
            $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($file, $sql);
        echo "  [ZÁLOHA] " . count($unused) . " objektů → " . $file . " (" . number_format(filesize($file) / 1024, 0) . " kB)\n";
    }

    // drop (každý objekt zvlášť v try/catch — chyba jednoho nezastaví zbytek)
    if ($doDrop) {
        if (!$autoYes) {
            echo "\n  Opravdu DROPNOUT " . count($unused) . " objektů z databáze '{$site['db']}'? Záloha je výše.\n";
            echo "  Napište přesně název databáze '{$site['db']}' pro potvrzení: ";
            $answer = trim((string) fgets(STDIN));
            if ($answer !== $site['db']) {
                echo "  [PŘESKOČENO] potvrzení nesouhlasí.\n";
                $db->close();
                continue;
            }
        }
        try { $db->query('SET FOREIGN_KEY_CHECKS=0'); } catch (mysqli_sql_exception $e) {}
        $dropped = 0;
        $failed = 0;
        foreach ($unused as $name => $type) {
            try {
                $db->query(($type === 'VIEW' ? 'DROP VIEW IF EXISTS `' : 'DROP TABLE IF EXISTS `') . $name . '`');
                echo "  [DROP] {$name}" . ($type === 'VIEW' ? ' (view)' : '') . "\n";
                $dropped++;
            } catch (mysqli_sql_exception $e) {
                echo "  [CHYBA] {$name}: {$e->getMessage()}\n";
                $failed++;
            }
        }
        try { $db->query('SET FOREIGN_KEY_CHECKS=1'); } catch (mysqli_sql_exception $e) {}
        echo "  [HOTOVO] smazáno {$dropped}" . ($failed ? ", selhalo {$failed}" : '') . ".\n";
    }

    $db->close();
}

echo "\n" . str_repeat('=', 70) . "\n";
if (!$doBackup && !$doDrop) {
    echo "Toto byl jen výpis — nic se nezměnilo. Celkem {$grandUnused} nepoužívaných tabulek.\n";
    echo "Záloha:  php install/cleanup-tables.php --backup\n";
    echo "Smazání: php install/cleanup-tables.php --drop   (nejdřív zálohuje, pak se zeptá)\n";
} elseif ($doDrop) {
    echo "Hotovo. Zálohy jsou v install/_backup/ — obnovení: mysql DB < soubor.sql\n";
} else {
    echo "Zálohy vytvořeny v install/_backup/. Pro smazání spusť s --drop.\n";
}
