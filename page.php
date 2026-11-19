<?php
declare(strict_types=1);

/*
 * Public content pages: /de/<slug> and /en/<slug> (rewritten to page.php?lang=..&slug=.. by .htaccess).
 */
require_once __DIR__ . '/lib/app.php';
require_once __DIR__ . '/lib/pages.php';

$lang = in_array($_GET['lang'] ?? '', LANGS, true) ? $_GET['lang'] : 'en';
$key = content_page_by_slug($lang, (string) ($_GET['slug'] ?? ''));
if (!$key) {
    http_response_code(404);
    redirect('/?lang=' . $lang);
}
$all = content_pages()[$key];
$page = $all[$lang];
$canonical = seo_page_url($lang, $page['slug']);
$alternates = [];
foreach (LANGS as $l) $alternates[$l] = seo_page_url($l, $all[$l]['slug']);
$alternates['x-default'] = $alternates['en'];

$jsonld = [];
if (!empty($page['faq'])) {
    $jsonld[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'inLanguage' => $lang,
        'mainEntity' => array_map(fn($qa) => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $page['faq'])];
}

public_head($page['title'] . ' · qr', 'narrow', [
    'index' => true, 'description' => $page['description'], 'canonical' => $canonical,
    'alternates' => $alternates, 'jsonld' => $jsonld,
]);
?>
<article class="content-page">
  <span class="tag"><?= e($page['nav']) ?></span>
  <h1><?= e($page['h1']) ?></h1>
  <?php if (!empty($page['faq'])): ?>
    <div class="faq">
      <?php foreach ($page['faq'] as [$q, $a]): ?>
        <details><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <?= str_replace('{base}', e(base_url()), $page['html']) ?>
  <?php endif; ?>
  <div class="content-cta">
    <a class="btn r" href="/?lang=<?= $lang ?>"><?= te('Make your own QR code.') ?></a>
  </div>
</article>
<?php public_foot();
