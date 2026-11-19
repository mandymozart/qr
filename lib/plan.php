<?php
declare(strict_types=1);

/*
 * Free vs. Pro accounts. Free accounts can hold up to FREE_MAX_CODES codes in total
 * (single + bulk). Admins and Pro accounts are unlimited. "Request Pro" mails the master admin.
 */
const FREE_MAX_CODES = 200;

function user_is_unlimited(array $user): bool
{
    return $user['role'] === 'admin' || ($user['plan'] ?? 'free') === 'pro';
}

function user_code_count(array $user): int
{
    return (int) q('SELECT COUNT(*) FROM links WHERE user_id = ?', [$user['id']])->fetchColumn();
}

/** How many more codes this user may create (null = unlimited). */
function user_codes_left(array $user): ?int
{
    return user_is_unlimited($user) ? null : max(0, FREE_MAX_CODES - user_code_count($user));
}

function limit_message(int $left): string
{
    return $left > 0
        ? t('A free account holds up to {max} codes. You can create {n} more.', ['max' => FREE_MAX_CODES, 'n' => $left])
        : t('You have reached the {max} codes of a free account. Request Pro to create more.', ['max' => FREE_MAX_CODES]);
}

/** Sends the Pro request to the master admin. Returns [flashType, message]. */
function request_pro(array $user): array
{
    if (!empty($user['pro_requested_at']) && strtotime($user['pro_requested_at']) > time() - 86400) {
        return ['info', t('Your request is already on its way. We will get back to you.')];
    }
    $admin = (string) config('admin_user');
    if (!filter_var($admin, FILTER_VALIDATE_EMAIL)) return ['err', t('Requests are not set up yet. Please try again later.')];
    $host = parse_url(base_url(), PHP_URL_HOST);
    $body = "Pro request on $host\n\n"
          . 'Name: ' . ($user['name'] ?: '—') . "\n"
          . 'Email: ' . $user['email'] . "\n"
          . 'Codes: ' . user_code_count($user) . ' / ' . FREE_MAX_CODES . "\n"
          . 'Member since: ' . $user['created_at'] . "\n\n"
          . 'Make them Pro: ' . base_url() . "/app/users.php\n";
    $err = send_mail($admin, "Pro request: {$user['email']}", $body);
    if ($err) return ['err', t('The request could not be sent. Please try again later.')];
    q('UPDATE users SET pro_requested_at = NOW() WHERE id = ?', [$user['id']]);
    return ['ok', t('Request sent. We will get back to you by email.')];
}

/** The little "Request Pro" button (POST to /app/pro.php). */
function pro_button(string $class = 'button small'): string
{
    return '<form class="inline-form" method="post" action="/app/pro.php">' . csrf_field()
        . '<input type="hidden" name="back" value="' . e($_SERVER['REQUEST_URI'] ?? '/app/') . '">'
        . '<button class="' . e($class) . '">⭐ ' . te('Request Pro') . '</button></form>';
}
