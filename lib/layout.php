<?php
declare(strict_types=1);

const ICONS = [
    'logo' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 20h1M20 14h1"/></svg>',
    'codes' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h7M14 17.5h7M14 21h7"/></svg>',
    'campaigns' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7l9-4 9 4-9 4z"/><path d="M3 12l9 4 9-4M3 17l9 4 9-4"/></svg>',
    'stats' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
    'guests' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><circle cx="17.5" cy="17.5" r="3.5" stroke-dasharray="2 2"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M16 4a4 4 0 010 8M19 15c2 1 3 3 3 6"/></svg>',
];

function nav_items(array $user): array
{
    $items = [
        'codes' => ['/app/', t('Codes'), t('All your QR links')],
        'campaigns' => ['/app/campaigns.php', t('Campaigns'), t('Groups & bulk codes')],
        'stats' => ['/app/stats.php', t('Stats'), t('Scans over time')],
    ];
    if ($user['role'] === 'admin') {
        $items['users'] = ['/app/users.php', t('Users'), t('Invite, block, resend')];
        $items['guests'] = ['/app/guests.php', t('Guest codes'), t('Made without account')];
    }
    return $items;
}

/** Menu icon: the dancing figures for the main sections, line icons for admin pages. */
function nav_icon(string $k): string
{
    $fig = ['codes' => ['jump', 'var(--red)'], 'campaigns' => ['run', 'var(--blue)'], 'stats' => ['hold', 'var(--green)']];
    if (isset($fig[$k])) {
        return '<svg class="fig" viewBox="0 0 200 250" aria-hidden="true" style="color:' . $fig[$k][1] . '"><use href="#pose-' . $fig[$k][0] . '"/></svg>';
    }
    return ICONS[$k] ?? '';
}

function page_head(string $title, ?string $active = null): void
{
    $user = current_user();
    $f = flash();
    ?><!doctype html>
<html lang="<?= lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · qr</title>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode(str_replace('currentColor', '%23252321', ICONS['logo'])) ?>">
<link rel="stylesheet" href="/app/assets/app.css?v=19">
<link rel="stylesheet" href="/app/assets/street.css?v=19">
<link rel="stylesheet" href="/app/assets/street-app.css?v=11">
</head>
<body class="street app">
<?php street_defs(); ?>
<?php if ($user && !empty($user['impersonator_id'])): ?>
<div class="impersonating">
  <span><?= t("You're logged in as {who} ({email}). Changes you make affect their account.", ['who' => '<strong>' . e($user['name'] ?: $user['email']) . '</strong>', 'email' => e($user['email'])]) ?></span>
  <form class="inline-form" method="post" action="/app/impersonate.php"><?= csrf_field() ?><input type="hidden" name="action" value="stop"><button class="button small"><?= te('Back to my account') ?></button></form>
</div>
<?php endif; ?>
<?php if ($user): ?>
<header class="topbar">
  <a class="st-logo" href="/app/">q<span>r</span></a>
  <div class="actions">
    <?= lang_switch('lang-switch st') ?>
    <a class="nav-button" href="/app/account.php" title="<?= e($user['email']) ?>"><?= e($user['name'] ?: $user['email']) ?><?= $user['role'] === 'admin' ? ' <span class="badge admin">admin</span>' : '' ?></a>
    <form class="inline-form" method="post" action="/app/logout.php"><?= csrf_field() ?><button class="nav-button"><?= te('Sign out') ?></button></form>
  </div>
</header>
<nav class="mobile-nav">
<?php foreach (nav_items($user) as $k => [$href, $label]): ?>
  <a class="nav-button" href="<?= $href ?>"<?= $k === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
</nav>
<div class="shell">
  <aside class="sidebar"><nav><ul>
<?php foreach (nav_items($user) as $k => [$href, $label, $hint]): ?>
<?php if ($k === 'users'): ?><li class="group"><?= te('Admin') ?></li><?php endif; ?>
    <li><a class="menu-item" href="<?= $href ?>"<?= $k === $active ? ' aria-current="page"' : '' ?>><span class="icon"><?= nav_icon($k) ?></span><div><h4><?= e($label) ?></h4><p><?= e($hint) ?></p></div></a></li>
<?php endforeach; ?>
  </ul></nav></aside>
  <main class="content">
<?php else: ?>
<div class="center-lang"><?= lang_switch('lang-switch st') ?></div>
<main class="center-card">
<?php endif; ?>
<?php if ($f): ?><div class="notice <?= $f[0] === 'err' ? 'err' : ($f[0] === 'info' ? 'info' : 'ok') ?>"><?= e($f[1]) ?></div><?php endif;
}

function page_foot(): void
{
    $user = current_user();
    ?>
  </main>
<?php if ($user): ?></div><div class="fade-bottom"></div><?php endif; ?>
<script src="/app/assets/app.js?v=11"></script>
</body>
</html>
<?php
}

/** Tiny inline SVG bar sparkline. */
function sparkline(array $counts): string
{
    $max = max(1, max($counts ?: [0]));
    $w = 6;
    $out = '<svg class="spark" viewBox="0 0 ' . (count($counts) * $w) . ' 24" aria-hidden="true">';
    $i = 0;
    foreach ($counts as $n) {
        $h = $n ? max(2, (int) round($n / $max * 22)) : 1;
        $out .= '<rect x="' . ($i++ * $w) . '" y="' . (24 - $h) . '" width="4" height="' . $h . '" rx="1"' . ($n ? '' : ' opacity=".3"') . '/>';
    }
    return $out . '</svg>';
}

/** Daily bar chart from [Y-m-d => n]. */
function bar_chart(array $daily): string
{
    $max = max(1, max($daily ?: [0]));
    $out = '<div class="chart"><div class="bars">';
    foreach ($daily as $d => $n) {
        $out .= '<div class="bar' . ($n ? '' : ' zero') . '" style="height:' . max(1, round($n / $max * 100, 1)) . '%" title="' . e(fmt_date($d, 'D, M j') . ': ' . t('{n} scans', ['n' => $n])) . '"></div>';
    }
    $keys = array_keys($daily);
    $out .= '</div><div class="axis"><span>' . e(fmt_date($keys[0] ?? null, 'M j')) . '</span><span>' . e(t('max {n}/day', ['n' => $max])) . '</span><span>' . e(fmt_date(end($keys) ?: null, 'M j')) . '</span></div></div>';
    return $out;
}

function campaign_select(array $user, ?int $selected, string $name = 'campaign_id'): string
{
    $out = '<select name="' . $name . '" id="' . $name . '"><option value="">' . e(t('— none —')) . '</option>';
    foreach (user_campaigns($user) as $c) {
        $out .= '<option value="' . $c['id'] . '"' . ((int) $selected === (int) $c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>';
    }
    return $out . '</select>';
}

function share_table(array $rows, string $label, ?callable $link = null): string
{
    if (!$rows) return '<div class="empty">' . e(t('No scans in this period.')) . '</div>';
    $max = max(array_column($rows, 'n'));
    $sum = array_sum(array_column($rows, 'n'));
    $h = '<table class="data"><thead><tr><th>' . e($label) . '</th><th style="width:40%"></th><th class="num">' . e(t('Scans')) . '</th><th class="num">%</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $name = $link ? $link($r) : e(t((string) ($r['name'] ?? $r['d'] ?? '—')));
        $h .= '<tr><td>' . $name . '</td><td><div class="hbar" style="width:' . round($r['n'] / $max * 100) . '%"></div></td><td class="num">' . (int) $r['n'] . '</td><td class="num">' . round($r['n'] / $sum * 100) . '</td></tr>';
    }
    return $h . '</tbody></table>';
}
