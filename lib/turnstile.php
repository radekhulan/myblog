<?php
declare(strict_types=1);

/** Ověření Cloudflare Turnstile tokenu. Při jakékoli chybě vrací false. */
function turnstile_verify(?string $token, string $ip): bool
{
    if ($token === null || trim($token) === '') {
        return false;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => TURNSTILE_SECRET_KEY,
            'response' => $token,
            'remoteip' => $ip,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
        blog_log('warn', 'Turnstile: curl selhal: ' . curl_error($ch), ['ip' => $ip]);
        return false;
    }

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        blog_log('warn', 'Turnstile: neplatná odpověď', ['raw' => substr((string) $raw, 0, 200), 'ip' => $ip]);
        return false;
    }

    return (bool) ($data['success'] ?? false);
}
