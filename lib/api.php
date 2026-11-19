<?php
declare(strict_types=1);

/*
 * Shared core for the REST API (/api/v1/...) and the MCP server (/mcp).
 *
 * Auth: "Authorization: Bearer qr_…" or "X-API-Key: qr_…". Without a key, calls are anonymous:
 * agents can still create single codes (they get an edit link to hand to the human), with small quotas.
 * Quotas per plan live in api_limits() (lib/plan.php). This is where Pro can be charged later.
 */

final class ApiError extends Exception
{
    public function __construct(string $message, public int $status = 400, public string $errorCode = 'bad_request')
    {
        parent::__construct($message);
    }
}

/* ---------------------------------------------------------------- keys */

/** Creates a key and returns it in plain text (shown once; only the hash is stored). */
function api_key_create(array $user, string $name): string
{
    $key = 'qr_' . bin2hex(random_bytes(20));
    q('INSERT INTO api_keys (user_id, name, prefix, key_hash) VALUES (?, ?, ?, ?)',
        [$user['id'], mb_substr($name, 0, 80), substr($key, 0, 12), hash('sha256', $key)]);
    return $key;
}

function api_keys_of(array $user): array
{
    return q('SELECT id, name, prefix, created_at, last_used_at FROM api_keys WHERE user_id = ? ORDER BY id DESC', [$user['id']])->fetchAll();
}

/** [user|null]: the account behind the request's API key, null for anonymous. Throws on a bad key. */
function api_user(): ?array
{
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $key = preg_match('~^Bearer\s+(\S+)~i', $auth, $m) ? $m[1] : (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($key === '') return null;
    $row = q("SELECT u.*, k.id AS api_key_id FROM api_keys k JOIN users u ON u.id = k.user_id WHERE k.key_hash = ?", [hash('sha256', $key)])->fetch();
    if (!$row) throw new ApiError('Invalid API key.', 401, 'unauthorized');
    if ($row['status'] !== 'active') throw new ApiError('This account is blocked.', 403, 'forbidden');
    q('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?', [$row['api_key_id']]);
    return $row;
}

function api_visitor(): string
{
    return visitor_id((string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function api_plan(?array $user): string
{
    return $user ? (user_is_pro($user) ? 'pro' : 'free') : 'anonymous';
}

/* ---------------------------------------------------------------- quotas */

/** Throws 429 when the request would exceed the plan's quotas; otherwise logs it. */
function api_meter(?array $user, string $action, int $codes, string $via): void
{
    $plan = api_plan($user);
    $lim = api_limits($plan);
    [$col, $val] = $user ? ['user_id', $user['id']] : ['visitor', api_visitor()];
    $hour = (int) q("SELECT COUNT(*) FROM api_calls WHERE $col = ? AND created_at > NOW() - INTERVAL 1 HOUR" . ($user ? '' : ' AND user_id IS NULL'), [$val])->fetchColumn();
    if ($hour >= $lim['requests_per_hour']) {
        throw new ApiError(api_limit_message($plan, "Rate limit reached: {$lim['requests_per_hour']} requests per hour."), 429, 'rate_limited');
    }
    if ($codes > 0) {
        $day = (int) q("SELECT COALESCE(SUM(units), 0) FROM api_calls WHERE $col = ? AND created_at > NOW() - INTERVAL 1 DAY" . ($user ? '' : ' AND user_id IS NULL'), [$val])->fetchColumn();
        if ($day + $codes > $lim['codes_per_day']) {
            throw new ApiError(api_limit_message($plan, "Daily limit reached: {$lim['codes_per_day']} new codes per day (" . max(0, $lim['codes_per_day'] - $day) . ' left).'), 429, 'quota_exceeded');
        }
    }
    q('INSERT INTO api_calls (user_id, visitor, action, units, via) VALUES (?, ?, ?, ?, ?)',
        [$user['id'] ?? null, $user ? '' : api_visitor(), $action, $codes, $via]);
}

function api_limit_message(string $plan, string $msg): string
{
    return match ($plan) {
        'anonymous' => "$msg Create a free account and an API key for higher limits: " . base_url() . '/app/register.php',
        'free' => "$msg Request Pro for higher limits: " . base_url() . '/app/account.php#api',
        default => $msg,
    };
}

/* ---------------------------------------------------------------- codes */

/** Public JSON shape of a code. $editUrl only for anonymous codes (the human's way to manage it). */
function api_code(array $link, ?string $editUrl = null): array
{
    $out = [
        'code' => $link['code'],
        'title' => $link['title'],
        'target_url' => $link['target_url'],
        'short_url' => short_url($link['code']),
        'active' => (bool) $link['active'],
        'created_at' => $link['created_at'] ?? null,
        'image_png' => base_url() . '/qr/' . rawurlencode($link['code']) . '.png',
        'image_svg' => base_url() . '/qr/' . rawurlencode($link['code']) . '.svg',
        'style' => qr_style($link['style'] ?? null),
    ];
    if ($link['user_id']) $out['manage_url'] = base_url() . '/app/code.php?id=' . (int) $link['id'];
    if ($editUrl) $out['edit_url'] = $editUrl;
    return $out;
}

/** Validates an optional style from API input; logos are limited to none/emoji (uploads only in the app). */
function api_style(mixed $in): ?array
{
    if (!is_array($in) || !$in) return null;
    $s = qr_style([
        'shape' => $in['shape'] ?? '', 'color' => $in['color'] ?? '', 'eye' => $in['corners'] ?? ($in['eye'] ?? ''),
        'logo' => ($in['emoji'] ?? '') !== '' ? 'emoji' : 'none', 'emoji' => $in['emoji'] ?? '',
        'c1' => $in['dots_color'] ?? '', 'c2' => $in['corners_color'] ?? '',
    ]);
    if (isset($in['dots_color']) || isset($in['corners_color'])) {
        $s = qr_style(['color' => 'custom', 'c1' => $in['dots_color'] ?? '#111111', 'c2' => $in['corners_color'] ?? ($in['dots_color'] ?? '#111111')] + $s);
        foreach (['c1', 'c2'] as $k) {
            if (qr_contrast_with_white($s[$k]) < 3.0) throw new ApiError('Colors must be dark enough to scan (contrast at least 3:1 against white).', 422, 'invalid_style');
        }
    }
    return $s;
}

/** Creates one code. Anonymous codes become guest codes with an edit link. Returns the API shape. */
function api_create(?array $user, array $in, string $via): array
{
    $target = trim((string) ($in['target_url'] ?? ''));
    if (!valid_url($target)) throw new ApiError('target_url must be a full http(s):// URL.', 422, 'invalid_target');
    if (strtolower((string) parse_url($target, PHP_URL_HOST)) === strtolower((string) parse_url(base_url(), PHP_URL_HOST))) {
        throw new ApiError('target_url cannot point to this QR service itself.', 422, 'invalid_target');
    }
    $title = mb_substr(trim((string) ($in['title'] ?? '')) ?: preg_replace('~^www\.~', '', (string) parse_url($target, PHP_URL_HOST)), 0, 160);
    $style = api_style($in['style'] ?? null);
    api_meter($user, 'create', 1, $via);

    $code = unique_code();
    if ($user) {
        q('INSERT INTO links (user_id, code, title, target_url) VALUES (?, ?, ?, ?)', [$user['id'], $code, $title, $target]);
    } else {
        q('INSERT INTO links (user_id, code, title, target_url, creator) VALUES (NULL, ?, ?, ?, ?)', [$code, $title, $target, api_visitor()]);
    }
    $id = (int) db()->lastInsertId();
    $warning = null;
    if ($style && !qr_is_default($style)) {
        if (qr_scans(short_url($code), $style, qr_logo_file($style, $id))) {
            q('UPDATE links SET style = ? WHERE id = ?', [json_encode($style), $id]);
        } else {
            $warning = 'The requested style did not scan reliably and was not applied.';
        }
    }
    $editUrl = $user ? null : new_edit_link($id);
    $link = q('SELECT * FROM links WHERE id = ?', [$id])->fetch();
    return api_code($link, $editUrl) + ($warning ? ['warning' => $warning] : []);
}

/** Bulk create for accounts. */
function api_bulk(array $user, array $items, string $campaign, string $via): array
{
    $lim = api_limits(api_plan($user));
    if (!$items) throw new ApiError('items must contain at least one entry.', 422, 'invalid_items');
    if (count($items) > $lim['bulk_per_call']) throw new ApiError(api_limit_message(api_plan($user), "At most {$lim['bulk_per_call']} codes per bulk call."), 429, 'bulk_too_large');
    foreach ($items as $i => $it) {
        if (!is_array($it) || !valid_url(trim((string) ($it['target_url'] ?? '')))) throw new ApiError("items[$i].target_url must be a full http(s):// URL.", 422, 'invalid_target');
    }
    api_meter($user, 'bulk', count($items), $via);
    $cid = null;
    if ($campaign !== '') {
        q('INSERT INTO campaigns (user_id, name) VALUES (?, ?)', [$user['id'], mb_substr($campaign, 0, 160)]);
        $cid = (int) db()->lastInsertId();
    }
    $out = [];
    db()->beginTransaction();
    foreach ($items as $it) {
        $code = unique_code();
        $target = trim((string) $it['target_url']);
        $title = mb_substr(trim((string) ($it['title'] ?? '')) ?: preg_replace('~^www\.~', '', (string) parse_url($target, PHP_URL_HOST)), 0, 160);
        q('INSERT INTO links (user_id, campaign_id, code, title, target_url) VALUES (?, ?, ?, ?, ?)', [$user['id'], $cid, $code, $title, $target]);
        $out[] = (int) db()->lastInsertId();
    }
    db()->commit();
    return array_map(fn($id) => api_code(q('SELECT * FROM links WHERE id = ?', [$id])->fetch()), $out);
}

/** A code the caller may manage: owned by the API key's account, or (anonymous) via its edit key. */
function api_owned(?array $user, string $code, string $editKey = ''): array
{
    $link = q('SELECT * FROM links WHERE code = ?', [$code])->fetch();
    if ($link && $user && (int) $link['user_id'] === (int) $user['id']) return $link;
    if ($link && $editKey !== '' && $link['edit_key_hash'] && hash_equals($link['edit_key_hash'], hash('sha256', $editKey))) return $link;
    throw new ApiError('Code not found (or not yours).', 404, 'not_found');
}

/** Pulls the edit key out of an edit URL or takes it as is. */
function api_edit_key(string $v): string
{
    return preg_match('~[?&]k=([a-f0-9]+)~', $v, $m) ? $m[1] : trim($v);
}

function api_update(array $link, array $in): array
{
    $sets = [];
    $vals = [];
    if (array_key_exists('target_url', $in)) {
        if (!valid_url((string) $in['target_url'])) throw new ApiError('target_url must be a full http(s):// URL.', 422, 'invalid_target');
        $sets[] = 'target_url = ?'; $vals[] = (string) $in['target_url'];
    }
    if (array_key_exists('title', $in) && trim((string) $in['title']) !== '') { $sets[] = 'title = ?'; $vals[] = mb_substr(trim((string) $in['title']), 0, 160); }
    if (array_key_exists('active', $in)) { $sets[] = 'active = ?'; $vals[] = $in['active'] ? 1 : 0; }
    if (!$sets) throw new ApiError('Nothing to update. Send target_url, title or active.', 422, 'nothing_to_update');
    $vals[] = $link['id'];
    q('UPDATE links SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
    return api_code(q('SELECT * FROM links WHERE id = ?', [$link['id']])->fetch());
}

function api_list(array $user, int $limit, int $offset): array
{
    $limit = max(1, min(100, $limit));
    $rows = q("SELECT l.*, (SELECT COUNT(*) FROM hits h WHERE h.link_id = l.id AND h.device <> 'bot') scans
               FROM links l WHERE l.user_id = ? ORDER BY l.id DESC LIMIT $limit OFFSET " . max(0, $offset), [$user['id']])->fetchAll();
    return [
        'total' => user_code_count($user),
        'codes' => array_map(fn($l) => api_code($l) + ['scans' => (int) $l['scans']], $rows),
    ];
}

function api_stats(array $link, int $days): array
{
    $days = max(1, min(365, $days));
    $id = (int) $link['id'];
    $since = 'AND h.created_at >= CURDATE() - INTERVAL ' . ($days - 1) . ' DAY';
    $base = "FROM hits h WHERE h.link_id = ? AND h.device <> 'bot'";
    return [
        'code' => $link['code'],
        'days' => $days,
        'scans_total' => (int) q("SELECT COUNT(*) $base", [$id])->fetchColumn(),
        'scans_in_period' => (int) q("SELECT COUNT(*) $base $since", [$id])->fetchColumn(),
        'unique_visitors_in_period' => (int) q('SELECT ' . UNIQUES_SQL . " $base $since", [$id])->fetchColumn(),
        'daily' => daily_counts('l.id = ?', [$id], min($days, 90)),
        'top_cities' => q('SELECT ' . PLACE_SQL . " place, COUNT(*) scans $base $since GROUP BY place ORDER BY scans DESC LIMIT 10", [$id])->fetchAll(),
        'devices' => q("SELECT h.device, COUNT(*) scans $base $since GROUP BY h.device ORDER BY scans DESC", [$id])->fetchAll(),
        'last_scan' => q("SELECT MAX(h.created_at) $base", [$id])->fetchColumn() ?: null,
    ];
}

function api_delete(array $link): void
{
    if ($mini = step_for_link((int) $link['id'])) step_delete_files((int) $mini['id']);
    q('DELETE FROM links WHERE id = ?', [$link['id']]);
    qr_delete_logo((int) $link['id']);
}

/** Small PNG of a code for agents to show inline (base64). */
function api_png_base64(array $link): string
{
    $style = qr_style($link['style'] ?? null);
    return base64_encode(qr_png(short_url($link['code']), $style, qr_logo_file($style, (int) $link['id']), 6));
}
