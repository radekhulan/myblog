<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/phpmailer/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/** Odešle HTML e-mail přes SMTP. Nikdy nevyhazuje výjimku, při selhání loguje a vrací false. */
function send_mail(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;

        if (SMTP_USER !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
        } else {
            $mail->SMTPAuth = false;
        }

        if (SMTP_SECURE === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM, (string) cfg('name'));

        $fromSeparator = strrpos(MAIL_FROM, '@');
        if (
            defined('MAIL_DKIM_SELECTOR')
            && MAIL_DKIM_SELECTOR !== ''
            && defined('MAIL_DKIM_PRIVATE_KEY')
            && $fromSeparator !== false
            && is_readable(MAIL_DKIM_PRIVATE_KEY)
        ) {
            $mail->DKIM_domain = substr(MAIL_FROM, $fromSeparator + 1);
            $mail->DKIM_selector = MAIL_DKIM_SELECTOR;
            $mail->DKIM_private = MAIL_DKIM_PRIVATE_KEY;
            $mail->DKIM_identity = MAIL_FROM;
        }

        $mail->addAddress($to);
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = trim(strip_tags($htmlBody));
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        blog_log('warn', 'Mail se nepodařilo odeslat: ' . $e->getMessage(), [
            'to'      => $to,
            'subject' => $subject,
        ]);
        return false;
    }
}
