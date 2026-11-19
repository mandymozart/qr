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
    fputcsv($out, ['time', 'code', 'title', 'campaign', 'device', 'os', 'referrer']);
    $st = q("SELECT h.created_at, l.code, l.title, c.name, h.device, h.os, h.referer FROM hits h
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

$byCampaign = q("SELECT COALESCE(c.name, '— no campaign —') name, c.id, COUNT(*) n
                 FROM hits h JOIN links l ON l.id = h.link_id LEFT JOIN campaigns c ON c.id = l.campaign_id
                 WHERE l.user_id = ? AND h.device <> 'bot' $since GROUP BY c.id, c.name ORDER BY n DESC", [$uid])->fetchAll();
$topCodes = q("SELECT l.id, l.title, l.code, COUNT(*) n $base $since GROUP BY l.id, l.title, l.code ORDER BY n DESC LIMIT 10", [$uid])->fetchAll();
$devices = q("SELECT CONCAT(h.device, IF(h.os <> '', CONCAT(' · ', h.os), '')) d, COUNT(*) n $base $since GROUP BY d ORDER BY n DESC LIMIT 8", [$uid])->fetchAll();
$hours = q("SELECT HOUR(h.created_at) hr, COUNT(*) n $base $since GROUP BY hr", [$uid])->fetchAll(PDO::FETCH_KEY_PAIR);
$recent = q("SELECT h.created_at, h.device, h.os, l.id, l.title, l.code, c.name campaign
             FROM hits h JOIN links l ON l.id = h.link_id LEFT JOIN campaigns c ON c.id = l.campaign_id
             WHERE l.user_id = ? AND h.device <> 'bot' ORDER BY h.id DESC LIMIT 30", [$uid])->fetchAll();

function share_table(array $rows, string $label, ?callable $link = null): string
{
    if (!$rows) return '<div class="empty">No scans in this period.</div>';
    $max = max(array_column($rows, 'n'));
    $sum = array_sum(array_column($rows, 'n'));
    $h = '<table class="data"><thead><tr><th>' . e($label) . '</th><th style="width:40%"></th><th class="num">Scans</th><th class="num">%</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $name = $link ? $link($r) : e($r['name'] ?? $r['d']);
        $h .= '<tr><td>' . $name . '</td><td><div class="hbar" style="width:' . round($r['n'] / $max * 100) . '%"></div></td><td class="num">' . (int) $r['n'] . '</td><td class="num">' . round($r['n'] / $sum * 100) . '</td></tr>';
    }
    return $h . '</tbody></table>';
}

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
  <div class="tile"><div class="label">All time</div><div class="value"><?= number_format($total) ?></div></div>
</div>
<?= bar_chart($daily) ?>

<section class="block" style="margin-top:2rem">
  <h3>By campaign</h3><p class="hint">Last <?= $days ?> days.</p>
  <?= share_table($byCampaign, 'Campaign', fn($r) => $r['id'] ? '<a href="/app/campaign.php?id=' . (int) $r['id'] . '">' . e($r['name']) . '</a>' : e($r['name'])) ?>
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
  <h3>Recent scans</h3><p class="hint">Newest first, which code and which campaign.</p>
  <?php if (!$recent): ?><div class="empty">No scans yet.</div><?php else: ?>
  <table class="data"><thead><tr><th>When</th><th>Code</th><th>Campaign</th><th>Device</th></tr></thead><tbody>
  <?php foreach ($recent as $r): ?>
    <tr><td><?= e(fmt_date($r['created_at'], 'M j, H:i')) ?></td>
      <td><a href="/app/code.php?id=<?= $r['id'] ?>"><?= e($r['title']) ?></a></td>
      <td><?= $r['campaign'] ? '<span class="badge campaign">' . e($r['campaign']) . '</span>' : '—' ?></td>
      <td><?= e($r['device'] . ($r['os'] ? ' · ' . $r['os'] : '')) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</section>
<?php page_foot();
