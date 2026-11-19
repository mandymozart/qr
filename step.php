<?php
declare(strict_types=1);

/*
 * Public mini-app page shown between scan and target: https://qr.viennastruggle.at/step.php?c=CODE
 * (the scan itself was already counted by index.php).
 */
require_once __DIR__ . '/lib/app.php';

$code = (string) ($_GET['c'] ?? $_POST['c'] ?? '');
$link = $code !== '' ? q('SELECT l.*, c.name AS campaign, u.name AS owner_name FROM links l
                          LEFT JOIN campaigns c ON c.id = l.campaign_id LEFT JOIN users u ON u.id = l.user_id
                          WHERE l.code = ? AND l.active = 1', [$code])->fetch() : null;
if (!$link) redirect('/?c=' . rawurlencode($code)); // shows the friendly "not found" page

$step = step_for_link((int) $link['id']);
if ($step && !$step['active']) $step = null;
if ($step && !$link['user_id']) $step = null; // mini-apps only for codes in an account
$target = step_target($link);
$self = '/step.php?c=' . rawurlencode($link['code']);

// Flyer image of a page step (stored outside the web root)
if (isset($_GET['img'])) {
    $f = $step['config']['image'] ?? '';
    $path = STEP_UPLOAD_DIR . '/' . ($step['id'] ?? 0) . '/' . basename((string) $f);
    if (!$step || $f === '' || !is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}

// No step and no target: nothing to show yet
if (!$step && $target === '') {
    public_head(t('Coming soon') . ' · qr');
    ?>
    <section class="hero">
      <span class="entity-type"><?= te('Coming soon') ?></span>
      <h1><?= te('Nothing here yet.') ?></h1>
      <p class="entity-description"><?= te('The owner of this code has not set up its page yet. Check back later.') ?></p>
    </section>
    <?php
    public_foot();
    exit;
}
if (!$step) redirect($target);

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$visitor = visitor_id($ip, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
unset($ip);
$errors = [];
$old = [];
$showResults = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'skip' && !$step['required'] && $target !== '') redirect($target);
    if (post('website') !== '') redirect($target ?: $self); // honeypot
    if (step_rate_limited($step, $visitor)) {
        $errors['_'] = t('That was a lot in a short time. Please try again later.');
    } elseif ($step['type'] === 'form') {
        [$ok, $errors, $old] = step_submit_form($step, $visitor);
        if ($ok) {
            if ($target !== '') redirect($target);
            flash(t('Thanks! Your answers were sent.'));
            redirect($self . '&done=1');
        }
    } elseif ($step['type'] === 'poll') {
        $choice = (int) post('choice', '-1');
        if (!isset($step['config']['options'][$choice])) {
            $errors['_'] = t('Please choose one of the options.');
        } else {
            $already = (int) q('SELECT COUNT(*) FROM responses WHERE step_id = ? AND visitor = ? AND created_at >= CURDATE()', [$step['id'], $visitor])->fetchColumn();
            if (!$already) q('INSERT INTO responses (step_id, visitor, data) VALUES (?, ?, ?)', [$step['id'], $visitor, json_encode(['choice' => $choice])]);
            if (empty($step['config']['show_results']) && $target !== '') redirect($target);
            $showResults = true;
        }
    } elseif ($step['type'] === 'info') {
        redirect($target ?: $self);
    }
}

$cfg = $step['config'];
public_head($step['title'] . ' · qr');
$intro = trim((string) $step['intro']);
?>
<section class="step-page">
  <?php if ($step['type'] === 'page' && !empty($cfg['image'])): ?>
    <img class="step-flyer" src="<?= e($self) ?>&amp;img=1" alt="<?= e($step['title']) ?>">
  <?php endif; ?>
  <h1 class="step-title"><?= e($step['title']) ?></h1>
  <?php if ($intro !== ''): ?><div class="step-intro"><?= nl2br(e($intro)) ?></div><?php endif; ?>
  <?php if (!empty($errors['_'])): ?><div class="notice err"><?= e($errors['_']) ?></div><?php endif; ?>

  <?php if ($step['type'] === 'page'): ?>
    <div class="step-links">
      <?php foreach ($cfg['links'] ?? [] as $i => $l): ?>
        <a class="btn <?= ['r', 'y', 'b', '', 'y'][$i % 5] ?>" href="<?= e($l['url']) ?>" rel="noopener"><?= e($l['label']) ?></a>
      <?php endforeach; ?>
      <?php if ($target !== ''): ?><a class="btn" href="<?= e($target) ?>"><?= te('Continue') ?> →</a><?php endif; ?>
    </div>

  <?php elseif ($step['type'] === 'info'): ?>
    <div class="step-links">
      <a class="btn r" href="<?= e($cfg['button_url'] ?? '#') ?>" rel="noopener"><?= e($cfg['button_label'] ?? t('Open')) ?></a>
      <?php if ($target !== ''): ?><a class="btn" href="<?= e($target) ?>"><?= te('Continue') ?> →</a><?php endif; ?>
    </div>

  <?php elseif ($step['type'] === 'poll'): ?>
    <?php if ($showResults): [$counts, $total] = step_poll_results($step); ?>
      <div class="poll-results">
        <?php foreach ($cfg['options'] as $i => $o): $pct = $total ? round($counts[$i] / $total * 100) : 0; ?>
          <div class="poll-row<?= (int) post('choice') === $i ? ' mine' : '' ?>"><span class="poll-label"><?= e($o) ?></span><span class="poll-bar"><i style="width:<?= $pct ?>%"></i></span><b><?= $pct ?>%</b></div>
        <?php endforeach; ?>
        <p class="hint"><?= te('{n} votes so far. Thanks for voting!', ['n' => $total]) ?></p>
      </div>
      <?php if ($target !== ''): ?><div class="step-links"><a class="btn r" href="<?= e($target) ?>"><?= te('Continue') ?> →</a></div><?php endif; ?>
    <?php else: ?>
      <form method="post" class="poll-form">
        <input type="hidden" name="c" value="<?= e($link['code']) ?>"><input type="hidden" name="action" value="vote">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
        <div class="poll-options">
          <?php foreach ($cfg['options'] as $i => $o): ?>
            <button class="btn <?= ['r', 'y', 'b', '', 'y', 'r', 'b', ''][$i % 8] ?>" name="choice" value="<?= $i ?>"><?= e($o) ?></button>
          <?php endforeach; ?>
        </div>
      </form>
    <?php endif; ?>

  <?php else: /* form */ ?>
    <?php if (isset($_GET['done'])): ?>
      <div class="notice ok"><?= te('Thanks! Your answers were sent.') ?></div>
    <?php else: ?>
    <form method="post" enctype="multipart/form-data" class="step-form">
      <input type="hidden" name="c" value="<?= e($link['code']) ?>"><input type="hidden" name="action" value="submit">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
      <?php foreach ($cfg['fields'] ?? [] as $i => $f):
          $key = step_field_key($i); $req = $f['required'] ? ' required' : ''; $val = is_string($old[$key] ?? null) ? $old[$key] : ''; ?>
        <div class="field<?= isset($errors[$key]) ? ' has-error' : '' ?><?= $f['type'] === 'checkbox' ? ' check' : '' ?>">
          <?php if ($f['type'] === 'checkbox'): ?>
            <label><input type="checkbox" name="<?= $key ?>" value="1"<?= $val ? ' checked' : '' ?><?= $req ?>> <?= e($f['label']) ?><?= $f['required'] ? ' *' : '' ?></label>
          <?php else: ?>
            <label for="<?= $key ?>"><?= e($f['label']) ?><?= $f['required'] ? ' *' : '' ?></label>
            <?php if ($f['type'] === 'textarea'): ?>
              <textarea id="<?= $key ?>" name="<?= $key ?>" rows="4"<?= $req ?>><?= e($val) ?></textarea>
            <?php elseif ($f['type'] === 'select'): ?>
              <select id="<?= $key ?>" name="<?= $key ?>"<?= $req ?>><option value=""><?= te('Please choose') ?></option>
                <?php foreach ($f['options'] ?? [] as $o): ?><option<?= $val === $o ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select>
            <?php elseif ($f['type'] === 'photo'): ?>
              <label class="dropzone step-drop"><input type="file" id="<?= $key ?>" name="<?= $key ?>" accept="image/*" capture="environment"<?= $req ?>>
                <span class="dz-thumb"></span><span class="dz-text"><b data-default="<?= te('Take or choose a photo') ?>"><?= te('Take or choose a photo') ?></b><small><?= te('Opens your camera on the phone') ?></small></span></label>
            <?php elseif ($f['type'] === 'file'): ?>
              <label class="dropzone step-drop"><input type="file" id="<?= $key ?>" name="<?= $key ?>" accept="image/*,application/pdf"<?= $req ?>>
                <span class="dz-thumb"></span><span class="dz-text"><b data-default="<?= te('Choose a file') ?>"><?= te('Choose a file') ?></b><small><?= te('Image or PDF, max. 10 MB') ?></small></span></label>
            <?php else: ?>
              <input id="<?= $key ?>" name="<?= $key ?>" type="<?= e($f['type']) ?>" value="<?= e($val) ?>"<?= $req ?><?= $f['type'] === 'email' ? ' autocomplete="email"' : ($f['type'] === 'tel' ? ' autocomplete="tel"' : '') ?>>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (isset($errors[$key])): ?><div class="field-hint field-error"><?= e($errors[$key]) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="field check consent<?= isset($errors['consent']) ? ' has-error' : '' ?>">
        <label><input type="checkbox" name="consent" value="1" required>
          <span><?= te('I agree that {who} receives my answers to this form.', ['who' => $link['owner_name'] ?: t('the organisers of this code')]) ?>
          <?php if (!empty($cfg['privacy_url'])): ?><a href="<?= e($cfg['privacy_url']) ?>" rel="noopener" target="_blank"><?= te('Privacy policy') ?></a><?php endif; ?></span></label>
        <?php if (isset($errors['consent'])): ?><div class="field-hint field-error"><?= e($errors['consent']) ?></div><?php endif; ?>
      </div>
      <button class="btn r"><?= e($cfg['button'] ?? t('Continue')) ?></button>
    </form>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!$step['required'] && $target !== '' && !in_array($step['type'], ['page', 'info'], true) && !$showResults): ?>
    <form method="post" class="step-skip"><input type="hidden" name="c" value="<?= e($link['code']) ?>"><input type="hidden" name="action" value="skip">
      <button class="linklike"><?= te('Skip and continue') ?> →</button></form>
  <?php endif; ?>
</section>
<?php public_foot();
