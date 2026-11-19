<?php
declare(strict_types=1);

/*
 * Plans and throttles.
 *
 * Codes per account are unlimited. What is limited is the *rate*, to protect the server:
 *   - bulk in the app: max BULK_MAX_PER_RUN per run and BULK_MAX_PER_DAY per account and day
 *   - API / MCP: per-plan quotas (see api_limits()); this is where Pro can be charged later
 * "Request Pro" mails the master admin; admins switch users to Pro on the Users page.
 */
const BULK_MAX_PER_RUN = 500;
const BULK_MAX_PER_DAY = 2000;

function user_is_pro(array $user): bool
{
    return $user['role'] === 'admin' || ($user['plan'] ?? 'free') === 'pro';
}

function user_code_count(array $user): int
{
    return (int) q('SELECT COUNT(*) FROM links WHERE user_id = ?', [$user['id']])->fetchColumn();
}

/** How many more codes this user may create in bulk today (protects the server, not a plan limit). */
function bulk_left_today(array $user): int
{
    $today = (int) q('SELECT COUNT(*) FROM links WHERE user_id = ? AND created_at >= NOW() - INTERVAL 1 DAY', [$user['id']])->fetchColumn();
    return max(0, BULK_MAX_PER_DAY - $today);
}

/** API / MCP quotas per plan. Anonymous = no API key (agents without an account). */
function api_limits(string $plan): array
{
    return match ($plan) {
        'pro' => ['requests_per_hour' => 2000, 'codes_per_day' => 5000, 'bulk_per_call' => 500],
        'free' => ['requests_per_hour' => 120, 'codes_per_day' => 100, 'bulk_per_call' => 20],
        default => ['requests_per_hour' => 30, 'codes_per_day' => 10, 'bulk_per_call' => 1], // anonymous
    };
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
          . 'Codes: ' . user_code_count($user) . "\n"
          . 'API calls (24h): ' . (int) q('SELECT COUNT(*) FROM api_calls WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY', [$user['id']])->fetchColumn() . "\n"
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
