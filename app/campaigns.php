<?php
declare(strict_types=1);
require __DIR__ . '/../lib/app.php';

$user = require_user();
$uid = $user['id'];

/** Creates $n codes in campaign $cid. {n} and {code} in title/url are replaced. */
function bulk_create(array $user, int $cid, int $n, string $title, string $url): int
{
    $start = (int) q('SELECT COUNT(*) FROM links WHERE campaign_id = ?', [$cid])->fetchColumn();
    $pad = strlen((string) ($start + $n));
    db()->beginTransaction();
    for ($i = 1; $i <= $n; $i++) {
        $code = unique_code();
        $num = str_pad((string) ($start + $i), max(2, $pad), '0', STR_PAD_LEFT);
        $t = str_contains($title, '{n}') ? str_replace('{n}', $num, $title) : ($n === 1 && $start === 0 ? $title : "$title #$num");
        $u = str_replace(['{n}', '{code}'], [$num, $code], $url);
        q('INSERT INTO links (user_id, campaign_id, code, title, target_url) VALUES (?, ?, ?, ?, ?)', [$user['id'], $cid, $code, $t, $u]);
    }
    db()->commit();
    return $n;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = post('name');
    $count = (int) post('count', '0');
    $url = post('target_url');
    $cid = (int) post('campaign_id');

    if (!$cid && $name === '') $errors[] = t('Name the campaign (or pick an existing one).');
    if ($count < 0 || $count > 500) $errors[] = t('Between 0 and 500 codes at once.');
    if ($count > 0 && ($left = user_codes_left($user)) !== null && $count > $left) { $errors[] = limit_message($left); $limitHit = true; }
    if ($count > 0 && !valid_url(str_replace(['{n}', '{code}'], ['1', 'x'], $url))) $errors[] = t('Target URL needs to be a full http(s):// URL.');
    if ($cid && !find_campaign($cid, $user)) $errors[] = t('Unknown campaign.');

    if (!$errors) {
        if (!$cid) {
            q('INSERT INTO campaigns (user_id, name) VALUES (?, ?)', [$uid, $name]);
            $cid = (int) db()->lastInsertId();
        }
        if ($count > 0) bulk_create($user, $cid, $count, post('title') ?: ($name ?: 'Code'), $url);
        flash($count ? t('Created {n} codes.', ['n' => $count]) : t('Campaign created.'));
        redirect('/app/campaign.php?id=' . $cid);
    }
}

$campaigns = q("SELECT c.*, (SELECT COUNT(*) FROM links l WHERE l.campaign_id = c.id) codes,
                  (SELECT COUNT(*) FROM hits h JOIN links l2 ON l2.id = h.link_id WHERE l2.campaign_id = c.id AND h.device <> 'bot') scans,
                  (SELECT COUNT(*) FROM hits h JOIN links l2 ON l2.id = h.link_id WHERE l2.campaign_id = c.id AND h.device <> 'bot' AND h.created_at >= CURDATE() - INTERVAL 29 DAY) scans30
                FROM campaigns c
                WHERE c.user_id = ? ORDER BY c.created_at DESC", [$uid])->fetchAll();

page_head(t('Campaigns'), 'campaigns');
?>
<div class="page-head">
  <div>
    <span class="entity-type"><?= te('Campaigns') ?></span>
    <h2 class="entity-title"><?= te('Group your codes') ?></h2>
    <p class="entity-description"><?= te('A campaign is a set of codes, e.g. one poster run. Create many at once and download them as a zip.') ?></p>
  </div>
</div>

<?php if ($errors): ?><div class="notice err"><?= e(implode(' ', $errors)) ?><?= !empty($limitHit) ? ' ' . pro_button() : '' ?></div><?php endif; ?>

<?php if ($campaigns): ?>
<div class="list">
<?php foreach ($campaigns as $c): ?>
  <a class="row" href="/app/campaign.php?id=<?= $c['id'] ?>">
    <div class="main"><h4><?= e($c['name']) ?></h4><div class="meta"><?= te('{n} codes · created {date}', ['n' => (int) $c['codes'], 'date' => fmt_date($c['created_at'])]) ?></div></div>
    <div class="stat"><strong><?= (int) $c['scans30'] ?></strong><span><?= te('30 days') ?></span></div>
    <div class="stat"><strong><?= (int) $c['scans'] ?></strong><span><?= te('total') ?></span></div>
  </a>
<?php endforeach; ?>
</div>
<?php else: ?>
  <div class="empty"><?= te('No campaigns yet.') ?></div>
<?php endif; ?>

<section class="block" id="bulk" style="margin-top:2rem;max-width:40rem">
  <h3><?= te('New campaign / bulk codes') ?></h3>
  <p class="hint"><?= t('Make a campaign and optionally fill it with codes right away. Use {num} for the running number and {short} for the short code, e.g. {example}.', ['num' => '<code>{n}</code>', 'short' => '<code>{code}</code>', 'example' => '<code>https://viennastruggle.at/?utm_campaign=posters&amp;utm_content={code}</code>']) ?></p>
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label for="name"><?= te('New campaign name') ?></label><input id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="<?= te('Poster run November') ?>"></div>
      <div class="field"><label for="campaign_id"><?= te('…or add to existing') ?></label><?= campaign_select($user, (int) ($_POST['campaign_id'] ?? 0) ?: null) ?></div>
      <div class="field"><label for="count"><?= te('Number of codes') ?></label><input id="count" name="count" type="number" min="0" max="500" value="<?= e($_POST['count'] ?? '1') ?>"></div>
      <div class="field"><label for="title"><?= te('Code names') ?></label><input id="title" name="title" value="<?= e($_POST['title'] ?? '') ?>" placeholder="<?= te('Poster #{n}') ?>"></div>
    </div>
    <div class="field"><label for="target_url"><?= te('Target URL (same for all)') ?></label><input id="target_url" name="target_url" value="<?= e($_POST['target_url'] ?? 'https://') ?>"></div>
    <div class="form-actions" style="justify-content:flex-start"><button class="button"><?= te('Create') ?></button></div>
  </form>
</section>
<?php page_foot();
