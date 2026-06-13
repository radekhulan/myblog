<?php
declare(strict_types=1);

function db(): mysqli
{
    static $db = null;
    if ($db === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $db = new mysqli(SQL_HOST, $GLOBALS['CFG']['db_user'], $GLOBALS['CFG']['db_pass'], $GLOBALS['CFG']['db']);
            $db->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            blog_log('error', 'DB connect failed: ' . $e->getMessage());
            blog_fail('Databáze není dostupná.');
        }
    }
    return $db;
}

function tbl(string $name): string
{
    return $GLOBALS['CFG']['prefix'] . $name;
}

function q(string $sql, array $params = []): mysqli_result|bool
{
    try {
        if (!$params) {
            return db()->query($sql);
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->get_result();
    } catch (mysqli_sql_exception $e) {
        blog_log('error', 'SQL: ' . $e->getMessage(), ['sql' => $sql, 'url' => $_SERVER['REQUEST_URI'] ?? '']);
        blog_fail('Chyba databáze.');
    }
}

function all(string $sql, array $params = []): array
{
    $res = q($sql, $params);
    return $res instanceof mysqli_result ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function one(string $sql, array $params = []): ?array
{
    $res = q($sql, $params);
    $row = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
    return $row ?: null;
}

function scalar(string $sql, array $params = []): mixed
{
    $row = one($sql, $params);
    return $row ? array_values($row)[0] : null;
}

function exec_q(string $sql, array $params = []): int
{
    q($sql, $params);
    return (int) db()->affected_rows;
}
