<?php
declare(strict_types=1);

function blog_log(string $level, string $message, array $context = []): void
{
    $line = sprintf(
        "%s | %s | %s | %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $GLOBALS['CFG']['domain'] ?? '-',
        $message,
        $context ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents(DIR_LOG . DIRECTORY_SEPARATOR . 'myblog-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

function blog_fail(string $publicMessage = 'Omlouváme se, na serveru došlo k chybě.'): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $publicMessage . "\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Chyba serveru</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;padding:0 20px">'
        . '<h1 style="font-size:26px">Něco se pokazilo</h1><p>' . htmlspecialchars($publicMessage, ENT_QUOTES)
        . '</p><p><a href="/">Zpět na úvodní stránku</a></p></body></html>';
    exit;
}

set_exception_handler(function (Throwable $e): void {
    blog_log('error', get_class($e) . ': ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'url'  => $_SERVER['REQUEST_URI'] ?? '',
    ]);
    blog_fail();
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        blog_log('fatal', $err['message'], [
            'file' => $err['file'] . ':' . $err['line'],
            'url'  => $_SERVER['REQUEST_URI'] ?? '',
        ]);
    }
});
