<?php
/** @var array|null $meta  @var string $content */
$meta = $meta ?? build_meta(['title' => $title ?? '', 'robots' => $robots ?? null]);
if (isset($robots) && empty($meta['robots'])) {
    $meta['robots'] = $robots;
}
if (cfg('is_dev')) {
    $meta['robots'] = 'noindex,nofollow';
}

$brand = cfg('name');                                   // např. MyEgo.cz
$brandCore = preg_replace('/^My|\.cz$/i', '', $brand);  // Ego
$cvOnly = (bool) cfg('cv_only');                        // doména jen s CV (radekhulan.cz)
$cvDomain = defined('CV_ONLY_DOMAINS') ? (CV_ONLY_DOMAINS[0] ?? null) : null;
$cvUrl = $cvDomain ? ((cfg('is_dev') ? 'https://dev.' : 'https://') . $cvDomain . '/') : null;

static $navGroups = null;
if ($navGroups === null && !$cvOnly) {
    $navGroups = all(
        'SELECT s.groupid, s.name, s.iurltitle FROM ' . tbl('subcategory') . ' s
         WHERE s.blogid = ? AND EXISTS (SELECT 1 FROM ' . tbl('category') . ' c WHERE c.cgroup = s.groupid)
         ORDER BY s.subsort, s.name',
        [MAIN_BLOG]
    );
}
$navGroups = $navGroups ?? [];
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta['title']) ?></title>
<meta name="description" content="<?= e($meta['description']) ?>">
<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<?php if (!empty($meta['robots'])): ?><meta name="robots" content="<?= e($meta['robots']) ?>"><?php endif; ?>
<meta property="og:title" content="<?= e($meta['title']) ?>">
<meta property="og:description" content="<?= e($meta['description']) ?>">
<meta property="og:url" content="<?= e($meta['canonical']) ?>">
<meta property="og:site_name" content="<?= e($brand) ?>">
<meta property="og:type" content="<?= e($meta['og_type']) ?>">
<meta property="og:image" content="<?= e($meta['og_image']) ?>">
<meta property="og:locale" content="cs_CZ">
<meta name="twitter:card" content="summary_large_image">
<?php if (!empty($meta['published'])): ?><meta property="article:published_time" content="<?= e($meta['published']) ?>"><?php endif; ?>
<?php if (!$cvOnly): ?><link rel="alternate" type="application/rss+xml" title="<?= e($brand) ?> — RSS" href="/feed/rss2"><?php endif; ?>
<?php $favBase = $cvOnly ? 'radekhulan' : (string) cfg('accent'); ?>
<link rel="icon" href="/assets/logo/<?= e($favBase) ?>.svg" type="image/svg+xml">
<?php if (!$cvOnly): ?><link rel="alternate icon" href="/assets/logo/<?= e($favBase) ?>.ico" sizes="48x48 32x32 16x16"><?php endif; ?>
<link rel="apple-touch-icon" href="/assets/logo/<?= e($favBase) ?>.svg">
<script>
(function () {
  try {
    var p = localStorage.getItem('myblog-theme') || 'auto';
    var d = p === 'dark' || (p === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.setAttribute('data-theme', d ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme-pref', p);
  } catch (e) {}
})();
</script>
<link rel="stylesheet" href="/assets/css/site.css?v=22">
</head>
<body>
<div class="topline">
  <div class="container topline-row">
    <nav class="site-switch" aria-label="Rodina webů">
      <?php foreach (MYBLOG_SITES as $dom => $s):
          $href = (cfg('is_dev') ? 'https://dev.' : 'https://') . $dom;
          $isCur = $dom === cfg('domain');
          $parts = preg_match('/^My(.+)\.cz$/i', $s['name'], $pm) ? $pm[1] : $s['name']; ?>
        <?php if ($isCur): ?>
          <strong class="site-link current">My<b><?= e($parts) ?></b>.cz</strong>
        <?php else: ?>
          <a class="site-link" href="<?= e($href) ?>">My<b><?= e($parts) ?></b>.cz</a>
        <?php endif; ?>
      <?php endforeach; ?>
      <span class="site-sep" aria-hidden="true"></span>
      <a class="site-link site-link-ai" href="https://rozumimeai.cz/" title="Školení a kurzy umělé inteligence">Rozumíme<b>AI</b>.cz</a>
    </nav>
    <?php $author = defined('SITE_AUTHOR') ? SITE_AUTHOR : null; ?>
    <?php if (is_array($author) && !empty($author['name'])): ?>
    <nav class="topline-links">
      <?php foreach ($author['links'] ?? [] as $lnk): ?>
        <a class="tl-link" href="<?= e($lnk['url']) ?>" title="<?= e($lnk['title'] ?? '') ?>"><?= e($lnk['label']) ?></a>
        <span class="tl-sep" aria-hidden="true"></span>
      <?php endforeach; ?>
      <?php $cvLink = $cvUrl ?? ($author['cv'] ?? null); ?>
      <?php if (!empty($cvLink) && !$cvOnly): ?>
        <a href="<?= e($cvLink) ?>" class="tl-cv"><?php if (!empty($author['photo'])): ?><img src="<?= e($author['photo']) ?>" alt="" width="24" height="24"><?php endif; ?><span><?= e($author['name']) ?></span></a>
      <?php else: ?>
        <span class="tl-cv tl-cv-static<?= $cvOnly ? ' tl-cv-current' : '' ?>"><?php if (!empty($author['photo'])): ?><img src="<?= e($author['photo']) ?>" alt="" width="24" height="24"><?php endif; ?><span><?= e($author['name']) ?></span></span>
      <?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
</div>

<header class="site-header<?= $cvOnly ? ' site-header-cv' : '' ?>">
  <div class="container header-row">
    <a class="brand" href="/">
      <?php if ($cvOnly && !empty(CV_PROFILE['photo'])): ?>
        <img class="brand-logo brand-photo" src="<?= e(CV_PROFILE['photo']) ?>" alt="" width="46" height="46">
        <span class="brand-text">
          <span class="brand-name"><?= e($brand) ?></span>
          <span class="brand-claim"><?= e(CV_PROFILE['tagline'] ?? html_entity_decode((string) cfg('claim'), ENT_QUOTES, 'UTF-8')) ?></span>
        </span>
      <?php else: ?>
        <img class="brand-logo" src="/assets/logo/<?= e(cfg('accent')) ?>.svg" alt="" width="46" height="46">
        <span class="brand-text">
          <span class="brand-name">My<b><?= e($brandCore) ?></b><i>.cz</i></span>
          <span class="brand-claim"><?= e(cfg('claim')) ?></span>
        </span>
      <?php endif; ?>
    </a>
    <div class="header-tools">
      <?php if (!$cvOnly): ?>
      <form class="search-mini" action="/hledani" role="search">
        <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input type="search" name="q" placeholder="Hledat…" aria-label="Hledat na webu">
      </form>
      <?php endif; ?>
      <div class="theme-switch" role="group" aria-label="Barevný režim">
        <button type="button" data-theme-set="auto" title="Podle systému"><svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm0 2v14a7 7 0 0 1 0-14z" fill="currentColor"/></svg></button>
        <button type="button" data-theme-set="light" title="Světlý"><svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="2" x2="12" y2="4.5"/><line x1="12" y1="19.5" x2="12" y2="22"/><line x1="2" y1="12" x2="4.5" y2="12"/><line x1="19.5" y1="12" x2="22" y2="12"/><line x1="4.6" y1="4.6" x2="6.4" y2="6.4"/><line x1="17.6" y1="17.6" x2="19.4" y2="19.4"/><line x1="4.6" y1="19.4" x2="6.4" y2="17.6"/><line x1="17.6" y1="6.4" x2="19.4" y2="4.6"/></g></svg></button>
        <button type="button" data-theme-set="dark" title="Tmavý"><svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5 8.5 8.5 0 1 0 20.5 14.5z" fill="currentColor"/></svg></button>
      </div>
    </div>
  </div>
  <?php if (!$cvOnly): ?>
  <div class="container nav-row">
    <nav class="main-nav" aria-label="Hlavní navigace">
      <a href="/"<?= $reqPath === '/' ? ' class="active"' : '' ?>>Úvod</a>
      <?php foreach ($navGroups as $g): $gu = url_group($g['iurltitle']); ?>
        <a href="<?= e($gu) ?>"<?= str_starts_with($reqPath, $gu) ? ' class="active"' : '' ?>><?= e($g['name']) ?></a>
      <?php endforeach; ?>
      <?php if (has_gallery()): ?>
      <a href="/group/galerie"<?= str_starts_with($reqPath, '/group/galerie') || str_starts_with($reqPath, '/album') || str_starts_with($reqPath, '/fotka') ? ' class="active"' : '' ?>>Fotogalerie</a>
      <?php endif; ?>
      <a href="/feed/rss2" class="nav-rss" title="RSS kanál" aria-label="RSS kanál"><svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><circle cx="5.2" cy="18.8" r="1.8" fill="currentColor"/></svg></a>
    </nav>
  </div>
  <?php endif; ?>
</header>

<main class="container main" id="obsah">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="container">
    <p><strong><?= e($brand) ?></strong> · Vyvíjí <a href="https://mywebdesign.cz/">MyWebdesign.cz s.r.o.</a> · © 2003–<?= date('Y') ?></p>
  </div>
</footer>
<script src="/assets/js/site.js?v=3" defer></script>
</body>
</html>
