<?php
declare(strict_types=1);

/*
 * Mini-apps ("steps"): something people see between scanning a code and reaching its target.
 *
 *   form  – fields (text, email, tel, number, textarea, select, checkbox, photo, file) → newsletter, registration, survey, photo upload
 *   poll  – one question, 2–8 options, optional live results
 *   info  – text + a button to any link (ticket shop, Stripe payment link, …), then continue to the target
 *   page  – a small hosted page / flyer (image, text, up to 5 link buttons). Works without any target.
 *
 * Public page: /step.php?c=CODE. Owner editor + responses: /app/step.php?id=LINK.
 * Only codes in an account can have a step (they collect personal data, so there must be an accountable owner).
 */

const STEP_TYPES = ['page', 'form', 'poll', 'info'];
const STEP_MAX_LINKS = 5;
const STEP_FIELD_TYPES = ['text', 'email', 'tel', 'number', 'textarea', 'select', 'checkbox', 'photo', 'file'];
const STEP_MAX_FIELDS = 12;
const STEP_UPLOAD_DIR = __DIR__ . '/uploads';
const STEP_MAX_UPLOAD = 10 * 1024 * 1024;
const STEP_MAX_PER_HOUR = 20; // submissions per visitor and step

function step_for_link(int $linkId): ?array
{
    $s = q('SELECT * FROM steps WHERE link_id = ?', [$linkId])->fetch();
    if (!$s) return null;
    $s['config'] = json_decode((string) $s['config'], true) ?: [];
    return $s;
}

/** Ready-made setups the editor can load with one click (texts in the editor's language). */
function step_presets(): array
{
    return [
        'newsletter' => ['label' => t('Newsletter'), 'hint' => t('Collect emails for your news.'), 'type' => 'form', 'title' => t('Stay in the loop'), 'intro' => t('Get news about upcoming shows and releases. No spam, unsubscribe any time.'), 'required' => 0,
            'config' => ['button' => t('Sign up'), 'fields' => [
                ['label' => t('Name'), 'type' => 'text', 'required' => false],
                ['label' => t('Email'), 'type' => 'email', 'required' => true],
            ]]],
        'registration' => ['label' => t('Registration'), 'hint' => t('Sign-ups for events, races, workshops.'), 'type' => 'form', 'title' => t('Register now'), 'intro' => t('Fill in your details to take part.'), 'required' => 1,
            'config' => ['button' => t('Register'), 'fields' => [
                ['label' => t('Name'), 'type' => 'text', 'required' => true],
                ['label' => t('Email'), 'type' => 'email', 'required' => true],
                ['label' => t('Phone'), 'type' => 'tel', 'required' => false],
                ['label' => t('Team or club'), 'type' => 'text', 'required' => false],
                ['label' => t('T-shirt size'), 'type' => 'select', 'required' => false, 'options' => ['S', 'M', 'L', 'XL']],
            ]]],
        'survey' => ['label' => t('Survey'), 'hint' => t('Ask a few quick questions.'), 'type' => 'form', 'title' => t('Quick question'), 'intro' => t('Takes 20 seconds and helps us a lot.'), 'required' => 0,
            'config' => ['button' => t('Send'), 'fields' => [
                ['label' => t('How did you hear about us?'), 'type' => 'select', 'required' => false, 'options' => [t('Poster'), 'Instagram', t('Friends'), t('Other')]],
                ['label' => t('How do you like it? (1–5)'), 'type' => 'select', 'required' => false, 'options' => ['1', '2', '3', '4', '5']],
                ['label' => t('Anything else?'), 'type' => 'textarea', 'required' => false],
            ]]],
        'photo' => ['label' => t('Photo upload'), 'hint' => t('People snap and send a picture.'), 'type' => 'form', 'title' => t('Take a photo'), 'intro' => t('Snap a picture and send it to us.'), 'required' => 1,
            'config' => ['button' => t('Send photo'), 'fields' => [
                ['label' => t('Photo'), 'type' => 'photo', 'required' => true],
                ['label' => t('Note'), 'type' => 'textarea', 'required' => false],
            ]]],
        'poll' => ['label' => t('Poll'), 'hint' => t('One question, live results.'), 'type' => 'poll', 'title' => t('Which one should we play as an encore?'), 'intro' => '', 'required' => 0,
            'config' => ['options' => ['A', 'B', 'C'], 'show_results' => true]],
        'flyer' => ['label' => t('Flyer page'), 'hint' => t('A hosted page, even without a link.'), 'type' => 'page', 'title' => t('Tonight: our show'), 'intro' => t("Doors 20:00, live 21:00. Bring friends.

All infos and tickets below."), 'required' => 0,
            'config' => ['links' => [['label' => t('Tickets'), 'url' => 'https://'], ['label' => 'Instagram', 'url' => 'https://instagram.com/']]]],
        'tickets' => ['label' => t('Tickets'), 'hint' => t('Send people to your ticket shop.'), 'type' => 'info', 'title' => t('Get your ticket'), 'intro' => t('Tickets are limited. Grab yours before the show sells out.'), 'required' => 0,
            'config' => ['button_label' => t('Buy ticket'), 'button_url' => 'https://']],
    ];
}

/** Builds a clean config from the editor POST. Returns [step row, errors]. */
function step_from_post(): array
{
    $type = in_array(post('type'), STEP_TYPES, true) ? post('type') : 'form';
    $errors = [];
    $config = [];
    if ($type === 'form') {
        $fields = [];
        foreach (array_slice((array) ($_POST['fields'] ?? []), 0, STEP_MAX_FIELDS) as $f) {
            $label = mb_substr(trim((string) ($f['label'] ?? '')), 0, 120);
            if ($label === '') continue;
            $ftype = in_array($f['type'] ?? '', STEP_FIELD_TYPES, true) ? $f['type'] : 'text';
            $field = ['label' => $label, 'type' => $ftype, 'required' => !empty($f['required'])];
            if ($ftype === 'select') {
                $opts = array_values(array_filter(array_map(fn($o) => mb_substr(trim($o), 0, 80), preg_split('~\R~', (string) ($f['options'] ?? '')))));
                if (count($opts) < 2) $errors[] = t('The choice field "{label}" needs at least two options (one per line).', ['label' => $label]);
                $field['options'] = array_slice($opts, 0, 20);
            }
            $fields[] = $field;
        }
        if (!$fields) $errors[] = t('Add at least one field.');
        $config = ['fields' => $fields, 'button' => mb_substr(post('button') ?: t('Continue'), 0, 40), 'privacy_url' => valid_url(post('privacy_url')) ? post('privacy_url') : ''];
    } elseif ($type === 'poll') {
        $opts = array_values(array_filter(array_map(fn($o) => mb_substr(trim($o), 0, 80), preg_split('~\R~', post('poll_options')))));
        if (count($opts) < 2) $errors[] = t('A poll needs at least two options (one per line).');
        $config = ['options' => array_slice($opts, 0, 8), 'show_results' => isset($_POST['show_results'])];
    } elseif ($type === 'page') {
        $links = [];
        foreach (array_slice((array) ($_POST['links'] ?? []), 0, STEP_MAX_LINKS) as $l) {
            $label = mb_substr(trim((string) ($l['label'] ?? '')), 0, 60);
            $url = trim((string) ($l['url'] ?? ''));
            if ($label === '' && ($url === '' || $url === 'https://')) continue;
            if (!valid_url($url)) { $errors[] = t('The button "{label}" needs a full web address, starting with https://', ['label' => $label ?: '?']); continue; }
            $links[] = ['label' => $label ?: parse_url($url, PHP_URL_HOST), 'url' => $url];
        }
        $config = ['links' => $links]; // the image is handled by the editor (needs the step id)
    } else {
        if (!valid_url(post('button_url'))) $errors[] = t('The button needs a full web address, starting with https://');
        $config = ['button_label' => mb_substr(post('button_label') ?: t('Open'), 0, 40), 'button_url' => post('button_url')];
    }
    $preset = post('preset');
    $config['preset'] = isset(step_presets()[$preset]) ? $preset : '';
    if (post('title') === '') $errors[] = t('Give the step a title.');
    return [[
        'type' => $type,
        'title' => mb_substr(post('title'), 0, 190),
        'intro' => mb_substr(post('intro'), 0, 2000),
        'required' => isset($_POST['required']) ? 1 : 0,
        'active' => isset($_POST['active']) ? 1 : 0,
        'config' => $config,
    ], $errors];
}

function step_save(int $linkId, array $step): void
{
    $cfg = json_encode($step['config'], JSON_UNESCAPED_UNICODE);
    if (q('SELECT 1 FROM steps WHERE link_id = ?', [$linkId])->fetch()) {
        q('UPDATE steps SET type = ?, title = ?, intro = ?, config = ?, required = ?, active = ? WHERE link_id = ?',
            [$step['type'], $step['title'], $step['intro'], $cfg, $step['required'], $step['active'], $linkId]);
    } else {
        q('INSERT INTO steps (link_id, type, title, intro, config, required, active) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$linkId, $step['type'], $step['title'], $step['intro'], $cfg, $step['required'], $step['active']]);
    }
}

function step_delete(array $step): void
{
    step_delete_files((int) $step['id']);
    q('DELETE FROM steps WHERE id = ?', [$step['id']]);
}

function step_delete_files(int $stepId): void
{
    foreach (glob(STEP_UPLOAD_DIR . '/' . $stepId . '/*') ?: [] as $f) @unlink($f);
    @rmdir(STEP_UPLOAD_DIR . '/' . $stepId);
}

/** Where the visitor goes after the step ('' when the code has no target, e.g. a pure flyer page). */
function step_target(array $link): string
{
    if (trim((string) $link['target_url']) === '') return '';
    return $link['utm'] ? with_utm($link['target_url'], $link['code'], $link['campaign'] ?? null) : $link['target_url'];
}

/** Field id used in forms and stored data. */
function step_field_key(int $i): string
{
    return 'f' . $i;
}

/**
 * Validates and stores a form submission. Returns [ok, errors, old input].
 * Files: images are re-encoded (removes hidden data like GPS location), PDFs are kept as is.
 */
function step_submit_form(array $step, string $visitor): array
{
    $fields = $step['config']['fields'] ?? [];
    $data = [];
    $errors = [];
    $files = [];
    foreach ($fields as $i => $f) {
        $key = step_field_key($i);
        if (in_array($f['type'], ['photo', 'file'], true)) {
            $up = $_FILES[$key] ?? null;
            if (!$up || ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                if ($f['required']) $errors[$key] = t('Please add a file.');
                continue;
            }
            $files[$key] = [$up, $f['type']];
            continue;
        }
        $v = trim((string) ($_POST[$key] ?? ''));
        if ($f['type'] === 'checkbox') $v = isset($_POST[$key]) ? '1' : '';
        if ($v === '') {
            if ($f['required']) $errors[$key] = t('This field is required.');
            continue;
        }
        $v = mb_substr($v, 0, $f['type'] === 'textarea' ? 4000 : 300);
        if ($f['type'] === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $errors[$key] = t('Please enter a valid email address.');
        if ($f['type'] === 'number' && !is_numeric($v)) $errors[$key] = t('Please enter a number.');
        if ($f['type'] === 'select' && !in_array($v, $f['options'] ?? [], true)) $errors[$key] = t('Please choose one of the options.');
        $data[$key] = $v;
    }
    if (!isset($_POST['consent'])) $errors['consent'] = t('Please agree so we can send your answers.');
    if ($errors) return [false, $errors, $data];

    // store files only once everything else is valid
    $dir = STEP_UPLOAD_DIR . '/' . (int) $step['id'];
    foreach ($files as $key => [$up, $kind]) {
        $saved = step_store_upload($up, $kind, $dir);
        if (is_string($saved) && str_starts_with($saved, 'error:')) return [false, [$key => substr($saved, 6)], $data];
        $data[$key] = ['file' => $saved, 'name' => mb_substr(basename((string) $up['name']), 0, 120)];
    }
    q('INSERT INTO responses (step_id, visitor, data) VALUES (?, ?, ?)', [$step['id'], $visitor, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    return [true, [], $data];
}

/** Saves an upload; returns the stored file name or "error:<message>". */
function step_store_upload(array $up, string $kind, string $dir): string
{
    if (($up['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return 'error:' . (in_array($up['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? t('That file is too big.') : t('The upload did not work. Please try again.'));
    }
    if ($up['size'] > STEP_MAX_UPLOAD) return 'error:' . t('That file is too big.');
    $raw = (string) file_get_contents($up['tmp_name']);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return 'error:' . t('The upload did not work. Please try again.');
    $name = bin2hex(random_bytes(12));

    $img = @imagecreatefromstring($raw);
    if ($img) {
        // apply the phone's rotation, then re-encode: strips EXIF (incl. GPS) and anything hidden in the file
        if (function_exists('exif_read_data') && ($ex = @exif_read_data($up['tmp_name'])) && !empty($ex['Orientation'])) {
            $img = match ((int) $ex['Orientation']) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        }
        $w = imagesx($img); $h = imagesy($img); $max = 2000;
        if (max($w, $h) > $max) {
            $r = $max / max($w, $h);
            $img = imagescale($img, (int) round($w * $r), (int) round($h * $r));
        }
        imagejpeg($img, "$dir/$name.jpg", 85);
        return "$name.jpg";
    }
    if ($kind === 'file' && str_starts_with($raw, '%PDF')) {
        file_put_contents("$dir/$name.pdf", $raw);
        return "$name.pdf";
    }
    return 'error:' . ($kind === 'photo' ? t('Please upload a photo (JPG, PNG, WebP, GIF).') : t('Please upload an image or a PDF.'));
}

/** Poll: [counts per option, total]. */
function step_poll_results(array $step): array
{
    $counts = array_fill(0, count($step['config']['options'] ?? []), 0);
    foreach (q('SELECT data FROM responses WHERE step_id = ?', [$step['id']])->fetchAll(PDO::FETCH_COLUMN) as $d) {
        $i = (int) (json_decode((string) $d, true)['choice'] ?? -1);
        if (isset($counts[$i])) $counts[$i]++;
    }
    return [$counts, array_sum($counts)];
}

function step_rate_limited(array $step, string $visitor): bool
{
    return $visitor !== '' && (int) q('SELECT COUNT(*) FROM responses WHERE step_id = ? AND visitor = ? AND created_at > NOW() - INTERVAL 1 HOUR',
        [$step['id'], $visitor])->fetchColumn() >= STEP_MAX_PER_HOUR;
}

/** Human-readable label of a step type for owners. */
function step_type_label(string $type): string
{
    return match ($type) { 'page' => t('Page / flyer'), 'form' => t('Form'), 'poll' => t('Poll'), default => t('Info + button') };
}
