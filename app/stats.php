<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

$user = require_user();
$uid = $user['id'];

// CSV export of all scans
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="qr-scans-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['time', 'code', 'title', 'placement', 'campaign', 'country', 'city', 'language', 'device', 'os', 'browser', 'referrer', 'daily_visitor']);
    $st = q("SELECT h.created_at, l.code, l.title, l.placement, c.name, h.country, h.city, h.lang, h.device, h.os, h.browser, h.referer, h.visitor FROM hits h
             JOIN links l ON l.id = h.link_id LEFT JOIN campaigns c ON c.id = l.campaign_id
             WHERE l.user_id = ? AND h.device <> 'bot' ORDER BY h.id DESC", [$uid]);
    while ($r = $st->fetch(PDO::FETCH_NUM)) fputcsv($out, $r);
    exit;
}

$days = max(7, min(365, int_param('days') ?: 30));
$daily = daily_counts('l.user_id = ?', [$uid], $days);
$base = "FROM hits h JOIN links l ON l.id = h.link_id WHERE l.user_id = ? AND h.device <> 'bot'";
$total = (int) q("SELECT COUNT(*) $base", [$uid])->fetchColumn();
$today = (int) q("SELECT COUNT(*) $base AND h.created_at >= CURDATE()", [$uid])->fetchColumn();
$week = (int) q("SELECT COUNT(*) $base AND h.created_at >= CURDATE() - INTERVAL 6 DAY", [$uid])->fetchColumn();
$since = "AND h.created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY";

// Admin: refresh the GeoIP database by hand
if (($_POST['action'] ?? '') === 'geo-update' && $user['role'] === 'admin') {
    check_csrf();
    $err = geo_update();
    flash($err ? "GeoIP update failed: $err" : 'GeoIP database updated.', $err ? 'err' : 'ok');
    redirect('/app/stats.php');
}

$uniques = (int) q('SELECT ' . UNIQUES_SQL . " $base $since", [$uid])->fetchColumn();
$places = q('SELECT ' . PLACE_SQL . " d, COUNT(*) n $base $since GROUP BY d ORDER BY n DESC LIMIT 15", [$uid])->fetchAll();
$countries = q("SELECT CASE WHEN h.country <> '' THEN h.country ELSE '— unknown —' END d, COUNT(*) n $base $since GROUP BY d ORDER BY n DESC LIMIT 10", [$uid])->fetchAll();
$placements = q("SELECT CASE WHEN l.placement <> '' THEN l.placement ELSE '— not set —' END d, COUNT(*) n, " . UNIQUES_SQL . " u $base $since GROUP BY d ORDER BY n DESC LIMIT 15", [$uid])->fetchAll();
$langs = q("SELECT h.lang d, COUNT(*) n $base $since GROUP BY h.lang ORDER BY n DESC LIMIT 10", [$uid])->fetchAll();
$browsers = q("SELECT CASE WHEN h.browser <> '' THEN h.browser ELSE '— unknown —' END d, COUNT(*) n $base $since GROUP BY d ORDER BY n DESC LIMIT 10", [$uid])->fetchAll();

$byCampaign = q("SELECT COALESCE(c.name, '— no campaign —') name, c.id, COUNT(*) n
                 FROM hits h JOIN links l ON l.id = h.link_id LEFT JOIN campaigns c ON c.id = l.campaign_id
                 WHERE l.user_id = ? AND h.device <> 'bot' $since GROUP BY c.id, c.name ORDER BY n DESC", [$uid])->fetchAll();
$topCodes = q("SELECT l.id, l.title, l.code, COUNT(*) n $base $since GROUP BY l.id, l.title, l.code ORDER BY n DESC LIMIT 10", [$uid])->fetchAll();
$devices = q("SELECT CONCAT(h.device, IF(h.os <> '', CONCAT(' · ', h.os), '')) d, COUNT(*) n $base $since GROUP BY d ORDER BY n DESC LIMIT 8", [$uid])->fetchAll();
$hours = q("SELECT HOUR(h.created_at) hr, COUNT(*) n $base $since GROUP BY hr", [$uid])->fetchAll(PDO::FETCH_KEY_PAIR);
$recent = q("SELECT h.created_at, h.device, h.os, h.city, h.country, h.browser, l.placement, l.id, l.title, l.code, c.name campaign
             FROM hits h JOIN links l ON l.id = h.link_id LEFT JOIN campaigns c ON c.id = l.campaign_id
             WHERE l.user_id = ? AND h.device <> 'bot' ORDER BY h.id DESC LIMIT 30", [$uid])->fetchAll();


page_head('Stats', 'stats');
?>
<div class="page-head">
  <div>
    <span class="entity-type">Stats</span>
    <h2 class="entity-title">Scans across all codes</h2>
    <p class="entity-description">Bots and link previews are filtered out.</p>
  </div>
  <div>
    <form method="get" class="inline-form"><select name="days" onchange="this.form.submit()" class="button ghost" aria-label="Period">
      <?php foreach ([7, 30, 90, 365] as $d): ?><option value="<?= $d ?>"<?= $d === $days ? ' selected' : '' ?>>Last <?= $d ?> days</option><?php endforeach; ?>
    </select></form>
    <a class="button ghost" href="/app/stats.php?export=csv">Export CSV</a>
  </div>
</div>

<div class="tiles">
  <div class="tile"><div class="label">Today</div><div class="value"><?= $today ?></div></div>
  <div class="tile"><div class="label">7 days</div><div class="value"><?= number_format($week) ?></div></div>
  <div class="tile"><div class="label"><?= $days ?> days</div><div class="value"><?= number_format(array_sum($daily)) ?></div></div>
  <div class="tile"><div class="label">Unique visitors</div><div class="value"><?= number_format($uniques) ?></div><div class="sub"><?= $days ?> days, counted per day</div></div>
  <div class="tile"><div class="label">All time</div><div class="value"><?= number_format($total) ?></div></div>
</div>
<?= bar_chart($daily) ?>

<section class="block" style="margin-top:2rem">
  <h3>By campaign</h3><p class="hint">Last <?= $days ?> days.</p>
  <?= share_table($byCampaign, 'Campaign', fn($r) => $r['id'] ? '<a href="/app/campaign.php?id=' . (int) $r['id'] . '">' . e($r['name']) . '</a>' : e($r['name'])) ?>
</section>

<section class="block">
  <h3>By placement</h3><p class="hint">Where the codes hang, as set on each code. Last <?= $days ?> days.</p>
  <?= share_table($placements, 'Placement') ?>
</section>

<section class="block">
  <h3>Where visitors are</h3><p class="hint">Approximate location from the IP address, which is never stored. Last <?= $days ?> days.</p>
  <?= share_table($places, 'City') ?>
  <div style="height:1rem"></div>
  <?= share_table($countries, 'Country') ?>
</section>

<section class="block">
  <h3>Top codes</h3><p class="hint">Last <?= $days ?> days.</p>
  <?= share_table($topCodes, 'Code', fn($r) => '<a href="/app/code.php?id=' . (int) $r['id'] . '">' . e($r['title']) . '</a> <span class="url">' . e($r['code']) . '</span>') ?>
</section>

<section class="block">
  <h3>When people scan</h3><p class="hint">Hour of day, last <?= $days ?> days.</p>
  <?php $hourly = []; for ($i = 0; $i < 24; $i++) $hourly[sprintf('%02d:00', $i)] = (int) ($hours[$i] ?? 0); $hm = max(1, max($hourly)); ?>
  <div class="chart"><div class="bars" style="height:5rem">
    <?php foreach ($hourly as $hr => $n): ?><div class="bar<?= $n ? '' : ' zero' ?>" style="height:<?= max(1, round($n / $hm * 100)) ?>%" title="<?= $hr ?>: <?= $n ?> scans"></div><?php endforeach; ?>
  </div><div class="axis"><span>00:00</span><span>12:00</span><span>23:00</span></div></div>
</section>

<section class="block">
  <h3>Devices</h3><p class="hint">Last <?= $days ?> days.</p>
  <?= share_table($devices, 'Device') ?>
</section>

<section class="block">
  <h3>Language &amp; browser</h3><p class="hint">Phone language setting and the app or browser that opened the link. Last <?= $days ?> days.</p>
  <?= share_table(array_map(fn($r) => ['d' => lang_name($r['d']), 'n' => $r['n']], $langs), 'Language') ?>
  <div style="height:1rem"></div>
  <?= share_table($browsers, 'Browser') ?>
</section>

<section class="block">
  <h3>Recent scans</h3><p class="hint">Newest first, which code and which campaign.</p>
  <?php if (!$recent): ?><div class="empty">No scans yet.</div><?php else: ?>
  <table class="data"><thead><tr><th>When</th><th>Code</th><th>Campaign</th><th>Where</th><th>Device</th></tr></thead><tbody>
  <?php foreach ($recent as $r): ?>
    <tr><td><?= e(fmt_date($r['created_at'], 'M j, H:i')) ?></td>
      <td><a href="/app/code.php?id=<?= $r['id'] ?>"><?= e($r['title']) ?></a><?= $r['placement'] !== '' ? '<br><small style="color:var(--neutral-20)">' . e($r['placement']) . '</small>' : '' ?></td>
      <td><?= $r['campaign'] ? '<span class="badge campaign">' . e($r['campaign']) . '</span>' : '—' ?></td>
      <td><?= e($r['city'] ? $r['city'] . ', ' . $r['country'] : ($r['country'] ?: '—')) ?></td>
      <td><?= e($r['device'] . ($r['os'] ? ' · ' . $r['os'] : '') . ($r['browser'] ? ' · ' . $r['browser'] : '')) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</section>

<section class="block" style="font-size:.8rem;color:var(--neutral-30)">
  <p>Privacy: no cookies, no stored IP addresses. Unique visitors use a hash with a random salt that is deleted daily. Individual scans are deleted after <?= RETENTION_MONTHS ?> months.
  <a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a> (CC BY 4.0).</p>
  <?php if ($user['role'] === 'admin'): ?>
  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="geo-update">
    GeoIP database: <?= is_file(GEO_DB) ? 'from ' . e(date('M j, Y', filemtime(GEO_DB))) : '<strong>not installed yet</strong> (downloads automatically after the next scan)' ?>
    <button class="button small ghost">Update now</button></form>
  <?php endif; ?>
</section>
<?php page_foot();
