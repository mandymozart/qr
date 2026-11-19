<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends a mail via PHPMailer. Uses SMTP when smtp_host is configured,
 * otherwise PHP's mail() (works out of the box on all-inkl / kasserver).
 * Returns null on success or an error string.
 */
function send_mail(string $to, string $subject, string $text): ?string
{
    $m = new PHPMailer(true);
    try {
        $m->CharSet = 'UTF-8';
        if ($host = config('smtp_host')) {
            $m->isSMTP();
            $m->Host = $host;
            $m->Port = (int) config('smtp_port', 587);
            $m->SMTPAuth = (bool) config('smtp_user');
            $m->Username = (string) config('smtp_user', '');
            $m->Password = (string) config('smtp_pass', '');
            $m->SMTPSecure = config('smtp_port', 587) == 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $m->isMail();
        }
        $from = config('mail_from', 'noreply@' . parse_url(base_url(), PHP_URL_HOST));
        $m->setFrom($from, config('mail_from_name', 'qr'));
        $m->addAddress($to);
        $m->Subject = $subject;
        $m->Body = $text;
        $m->send();
        return null;
    } catch (Throwable $ex) {
        error_log('qr mail error: ' . $m->ErrorInfo . ' ' . $ex->getMessage());
        return $m->ErrorInfo ?: $ex->getMessage();
    }
}

function send_login_mail(array $user, string $link, bool $invite = false, ?string $valid = null): ?string
{
    $valid = t($valid ?? ($invite ? '7 days' : '30 minutes')); // '7 days' / '30 minutes' / '24 hours'
    $host = parse_url(base_url(), PHP_URL_HOST);
    $hi = $user['name'] ? t('Hi {name},', ['name' => $user['name']]) : t('Hi,');
    $body = $hi . "\n\n" . ($invite
        ? t("welcome to {host}, the QR code and link tracker.\n\nSign in with this secret link (valid {valid}, works once):\n{link}\n\nLater on, request a fresh link any time at {url}\n", ['host' => $host, 'valid' => $valid, 'link' => $link, 'url' => base_url() . '/app/'])
        : t("here is your secret sign-in link for {host} (valid {valid}, works once):\n{link}\n\nIf you didn't ask for this, ignore this mail.\n", ['host' => $host, 'valid' => $valid, 'link' => $link]));
    return send_mail($user['email'], $invite ? t('Your access to {host}', ['host' => $host]) : t('Sign in to {host}', ['host' => $host]), $body);
}
