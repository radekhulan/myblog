<?php
declare(strict_types=1);

// CV je volitelné — bez vyplněného CV_PROFILE v cfg.php se /extra/cv nezobrazuje
$cv = defined('CV_PROFILE') ? CV_PROFILE : null;
if (!is_array($cv) || empty($cv['name'])) {
    not_found();
}

// ---- odeslání kontaktního formuláře (AJAX, vrací JSON) ----
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['contact_send'])) {
    require_once DIR_ROOT . '/lib/turnstile.php';
    require_once DIR_ROOT . '/lib/mailer.php';

    ini_set('display_errors', '0');   // AJAX/JSON endpoint — žádné PHP hlášky do těla odpovědi
    header('Content-Type: application/json; charset=utf-8');

    $name    = trim((string) ($_POST['name'] ?? ''));
    $email   = trim((string) ($_POST['email'] ?? ''));
    $phone   = trim((string) ($_POST['phone'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $ip      = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    $reply = static function (bool $ok, string $msg): void {
        echo json_encode(['ok' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (!empty($_POST['website'])) {                               // honeypot — vyplní jen bot
        $reply(false, 'Zprávu se nepodařilo odeslat.');
    }
    if ($name === '' || $email === '' || $message === '') {
        $reply(false, 'Vyplňte prosím jméno, e-mail i zprávu.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reply(false, 'Zadejte prosím platnou e-mailovou adresu.');
    }
    if (!turnstile_verify($_POST['cf-turnstile-response'] ?? null, $ip)) {
        $reply(false, 'Ověření proti spamu se nezdařilo. Zkuste to prosím znovu.');
    }

    // Ověření existence e-mailu přes SMTP (best-effort; odmítne JEN když MX příjemce schránku
    // výslovně odmítne — při nedostupnosti probe/odchozího portu 25 se NEblokuje, viz fail-open níže)
    require_once DIR_ROOT . '/vendor/autoload.php';
    try {
        $sender    = 'noreply@' . cfg('domain');   // MAIL FROM pro ověřovací probe (vlastní doména)
        $validator = new \SMTPValidateEmail\Validator($email, $sender);
        $validator->no_conn_is_valid = true;   // nelze se připojit (blokovaný port 25) → neodmítat
        $validator->no_comm_is_valid = true;   // timeout / divná odpověď serveru → neodmítat
        $validator->setConnectTimeout(8);
        $validator->setCatchAllValidity(true); // catch-all doménu nelze ověřit → brát jako platnou
        $results = $validator->validate();
        if (array_key_exists($email, $results) && $results[$email] === false) {
            $reply(false, 'Zdá se, že tato e-mailová adresa neexistuje nebo nepřijímá poštu. Zkontrolujte ji prosím.');
        }
    } catch (\Throwable $e) {
        blog_log('info', 'SMTP validace e-mailu přeskočena: ' . $e->getMessage(), ['email' => $email]);
    }

    $sentAt    = function_exists('cz_date') ? cz_date(date('Y-m-d H:i:s'), true) : date('j. n. Y H:i');
    $replyHref = 'mailto:' . e($email) . '?subject=' . rawurlencode('Re: Vaše zpráva z ' . cfg('domain'));
    $td        = 'padding:7px 18px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top';
    $tdv       = 'padding:7px 0;font-size:15px;color:#1f2937';

    $rows = '<tr><td style="' . $td . '">Jméno</td><td style="' . $tdv . '"><strong>' . e($name) . '</strong></td></tr>'
          . '<tr><td style="' . $td . '">E-mail</td><td style="' . $tdv . '">'
          . '<a href="mailto:' . e($email) . '" style="color:#5C45A0;text-decoration:none">' . e($email) . '</a></td></tr>';
    if ($phone !== '') {
        $rows .= '<tr><td style="' . $td . '">Telefon</td><td style="' . $tdv . '">'
               . '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $phone)) . '" style="color:#5C45A0;text-decoration:none">'
               . e($phone) . '</a></td></tr>';
    }

    $html =
        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2fb;margin:0;padding:0">'
      . '<tr><td align="center" style="padding:28px 16px">'
      . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(59,45,131,.12)">'
      . '<tr><td style="background:#3B2D83;background:linear-gradient(115deg,#6753AE,#3B2D83);padding:26px 32px">'
      .   '<div style="color:#ffffff;font-family:Segoe UI,Arial,sans-serif;font-size:19px;font-weight:700">Nová zpráva z kontaktního formuláře</div>'
      .   '<div style="color:#cdbff7;font-family:Segoe UI,Arial,sans-serif;font-size:13.5px;margin-top:4px">' . e((string) cfg('domain')) . '</div>'
      . '</td></tr>'
      . '<tr><td style="padding:28px 32px;font-family:Segoe UI,Arial,sans-serif">'
      .   '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse">' . $rows . '</table>'
      .   '<div style="margin:22px 0 8px;color:#6b7280;font-size:14px">Zpráva</div>'
      .   '<div style="background:#faf8fe;border:1px solid #e6e2f0;border-left:4px solid #b07d33;border-radius:10px;'
      .     'padding:16px 18px;color:#1f2937;font-size:15px;line-height:1.65">' . nl2br(e($message)) . '</div>'
      .   '<div style="margin-top:22px">'
      .     '<a href="' . $replyHref . '" style="display:inline-block;background:#b07d33;color:#2a1f06;'
      .       'font-family:Segoe UI,Arial,sans-serif;font-size:15px;font-weight:700;padding:12px 26px;'
      .       'border-radius:10px;text-decoration:none;box-shadow:0 4px 14px rgba(176,125,51,.30)">&#8618; Odpovědět ' . e($name) . '</a>'
      .   '</div>'
      . '</td></tr>'
      . '<tr><td style="padding:16px 32px 24px;border-top:1px solid #efedf6;color:#9ca3af;'
      .   'font-family:Segoe UI,Arial,sans-serif;font-size:12.5px;line-height:1.6">'
      .   'Odesláno ' . e($sentAt) . ' z ' . e((string) cfg('host')) . ' · IP ' . e($ip)
      . '</td></tr>'
      . '</table></td></tr></table>';

    $subject = 'Nová zpráva z webu ' . cfg('domain') . ' — ' . $name;

    if (send_mail(ADMIN_EMAIL, $subject, $html, $email)) {
        $reply(true, 'Děkuji za zprávu, co nejdříve se vám ozvu.');
    }
    blog_log('warn', 'Kontaktní formulář: e-mail se nepodařilo odeslat', ['from' => $email, 'ip' => $ip]);
    $reply(false, 'Zprávu se teď nepodařilo odeslat. Napište mi prosím přímo na ' . ADMIN_EMAIL . '.');
}

require_once DIR_ROOT . '/lib/github.php';
$ghLive = !empty($cv['github_user']) ? github_stars((string) $cv['github_user']) : [];

echo view('layout', [
    'meta' => build_meta([
        'title'       => cfg('cv_only')
            ? $cv['name'] . ' – ' . trim(strip_tags(explode('·', (string) ($cv['role'] ?? ''))[0]))
            : $cv['name'],
        'description' => trim(strip_tags((string) ($cv['bio'] ?? $cv['name']))),
        'canonical'   => cfg('cv_only') ? '/' : '/extra/cv',
        'og_type'     => 'profile',
        'og_image'    => cfg('canonical_base') . ($cv['og'] ?? $cv['photo'] ?? '/assets/og/' . cfg('accent') . '.png'),
    ]),
    'content' => view('cv', ['cv' => $cv, 'ghLive' => $ghLive]),
]);
