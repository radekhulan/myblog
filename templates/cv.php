<?php
/** @var array $cv  CV_PROFILE z konfigurace  @var array $ghLive  živé GH stars */
?>
<?php
  // Volitelná interaktivní SVG animace (mimo repo, inlinuje se kvůli JS + dědění tématu)
  $cvHeroSvg = '';
  if (!empty($cv['hero_svg'])) {
      $rel = ltrim((string) $cv['hero_svg'], '/\\');
      if (strpos($rel, '..') === false) {
          $f = cfg('media_dir') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
          if (is_file($f)) {
              $cvHeroSvg = preg_replace('/^\xEF\xBB\xBF|^\s*<\?xml.*?\?>\s*/s', '', (string) file_get_contents($f));
          }
      }
  }
?>
<section class="cv">
  <div class="cv-hero<?= $cvHeroSvg !== '' ? ' cv-hero-anim' : '' ?>">
    <?php if ($cvHeroSvg !== ''): ?><div class="cv-hero-bg" aria-hidden="true"><?= $cvHeroSvg ?></div><?php endif; ?>
    <?php if (!empty($cv['photo'])): ?>
    <div class="cv-photo-ring">
      <img src="<?= e($cv['photo']) ?>" alt="<?= e($cv['name']) ?>" width="148" height="148">
    </div>
    <?php endif; ?>
    <h1 class="cv-name"><?= e($cv['name']) ?></h1>
    <?php if ($cvHeroSvg !== ''):
        $cvW = preg_split('/\s+/', trim((string) $cv['name'])) ?: [];
        $cvInit = mb_strtoupper(mb_substr($cvW[0] ?? '', 0, 1));
        if (count($cvW) > 1) $cvInit .= mb_strtoupper(mb_substr(end($cvW), 0, 1));
    ?>
    <span class="cv-sign" aria-hidden="true"><?= e($cvInit) ?></span>
    <?php endif; ?>
    <?php if (!empty($cv['role'])): ?><p class="cv-role"><?= $cv['role'] ?></p><?php endif; ?>
    <?php if (!empty($cv['bio'])): ?><p class="cv-bio"><?= $cv['bio'] ?></p><?php endif; ?>
    <?php if (!empty($cv['stats'])): ?>
    <div class="cv-stats">
      <?php foreach ($cv['stats'] as [$num, $label]): ?>
        <div class="cv-stat"><strong><?= e($num) ?></strong><span><?= e($label) ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="cv-cta">
    <a href="#kontakt" class="cv-contact-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
      Kontakt
    </a>
  </div>

  <?php if (!empty($cv['projects'])): ?>
  <h2 class="cv-section-title">Firma a projekty</h2>
  <?php if (!empty($cv['projects_motto'])): ?><p class="cv-section-motto"><?= $cv['projects_motto'] ?></p><?php endif; ?>
  <div class="cv-grid cv-grid-projects">
    <?php foreach ($cv['projects'] as $p): ?>
    <a class="cv-card cv-card-wide" href="<?= e($p['url']) ?>" rel="noopener">
      <span class="cv-icon <?= empty($p['logo']) ? 'cv-icon-brand' : 'cv-icon-logo' ?>">
        <?php if (!empty($p['logo'])): ?><img src="<?= e($p['logo']) ?>" alt="" width="30" height="30"><?php else: ?><?= cv_icon($p['icon'] ?? '') ?><?php endif; ?>
      </span>
      <span class="cv-card-text"><strong><?= e($p['title']) ?></strong><span><?= e($p['desc']) ?></span></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($cv['projects_intro'])): ?>
  <p class="cv-section-intro"><?= $cv['projects_intro'] ?></p>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($cv['repos'])): $ghUser = $cv['github_user'] ?? '';
      $cvRepos = $cv['repos'];
      usort($cvRepos, function ($a, $b) use ($ghLive) {
          $sa = $ghLive[strtolower($a['name'])] ?? $a['stars'];
          $sb = $ghLive[strtolower($b['name'])] ?? $b['stars'];
          return (int) $sb <=> (int) $sa;
      }); ?>
  <h2 class="cv-section-title">Open source na GitHubu</h2>
  <div class="cv-grid">
    <?php foreach ($cvRepos as $repo):
        $stars = $ghLive[strtolower($repo['name'])] ?? $repo['stars']; ?>
    <a class="cv-card cv-repo" href="https://github.com/<?= e($ghUser) ?>/<?= e($repo['name']) ?>" rel="noopener">
      <span class="cv-icon cv-icon-gh"><?= cv_icon('github') ?></span>
      <span class="cv-card-text"><strong><?= e($repo['name']) ?></strong><span><?= e($repo['desc']) ?></span></span>
      <span class="cv-stars" title="<?= (int) $stars ?> hvězdiček na GitHubu">★ <?= (int) $stars ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($cv['socials'])): ?>
  <h2 class="cv-section-title">Sociální sítě</h2>
  <div class="cv-grid cv-grid-socials">
    <?php foreach ($cv['socials'] as $s): ?>
    <a class="cv-card cv-soc cv-soc-<?= e($s['icon']) ?>" href="<?= e($s['url']) ?>" rel="me noopener">
      <span class="cv-icon"><?= cv_icon($s['icon']) ?></span>
      <span class="cv-card-text"><strong><?= e($s['label']) ?></strong><span><?= e($s['sub']) ?></span></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2 class="cv-section-title">Weby</h2>
  <div class="cv-grid">
    <?php foreach (MYBLOG_SITES as $dom => $site): ?>
    <a class="cv-card" href="https://<?= e($dom) ?>/">
      <span class="cv-icon cv-icon-logo"><img src="/assets/logo/<?= e($site['accent']) ?>.svg" alt="" width="26" height="26"></span>
      <span class="cv-card-text"><strong><?= e($site['name']) ?></strong><span><?= e($site['claim']) ?></span></span>
    </a>
    <?php endforeach; ?>
  </div>

  <h2 class="cv-section-title" id="kontakt">Napište mi</h2>
  <form class="contact" id="contactForm" method="post" action="/" novalidate>
    <p class="contact-intro">Máte dotaz nebo nabídku spolupráce? Napište mi, brzy se ozvu.</p>
    <input type="hidden" name="contact_send" value="1">
    <div class="contact-hp" aria-hidden="true">
      <label>Nevyplňujte <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>
    <div class="contact-row">
      <div class="form-group">
        <label for="cf-name">Jméno a příjmení <sup>*</sup></label>
        <input type="text" id="cf-name" class="form-control" name="name" required autocomplete="name">
      </div>
      <div class="form-group">
        <label for="cf-email">E-mail <sup>*</sup></label>
        <input type="email" id="cf-email" class="form-control" name="email" required autocomplete="email">
      </div>
    </div>
    <div class="form-group">
      <label for="cf-phone">Telefon</label>
      <input type="tel" id="cf-phone" class="form-control" name="phone" autocomplete="tel">
    </div>
    <div class="form-group">
      <label for="cf-message">Zpráva <sup>*</sup></label>
      <textarea id="cf-message" class="form-control" name="message" required rows="6"></textarea>
    </div>
    <div class="cf-turnstile" data-sitekey="<?= e(TURNSTILE_SITE_KEY) ?>"></div>
    <div class="contact-actions">
      <button type="submit" class="contact-btn"><strong>Odeslat zprávu</strong></button>
      <p class="contact-msg" id="contactMsg" role="status" aria-live="polite" hidden></p>
    </div>
  </form>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</section>
