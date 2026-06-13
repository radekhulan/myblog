<?php
/** @var ?string $heading  @var ?string $subtitle  @var array $items  @var array $pager  @var ?array $highlight  @var ?array $crumbs */
$highlight = $highlight ?? null;
?>
<?php if (!empty($crumbs) && count($crumbs) > 1): ?>
<nav class="crumbs" aria-label="Drobečková navigace">
  <?php foreach ($crumbs as $ci => [$cLabel, $cHref]): ?>
    <?php if ($ci > 0): ?><span aria-hidden="true">›</span><?php endif; ?>
    <?php if ($cHref !== null): ?>
      <a href="<?= e($cHref) ?>"><?= e(title_text($cLabel)) ?></a>
    <?php else: ?>
      <span class="crumb-current"><?= e(title_text($cLabel)) ?></span>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if (!empty($heading)): ?>
<header class="page-head">
  <h1><?= mark_text(title_text($heading), $highlight) ?></h1>
  <?php if (!empty($subtitle)): ?><p class="page-sub"><?= e($subtitle) ?></p><?php endif; ?>
</header>
<?php elseif (!empty($subtitle)): ?>
<header class="page-head"><p class="page-sub"><?= e($subtitle) ?></p></header>
<?php endif; ?>

<?php if (!empty($chips)): ?>
<div class="cat-strip" aria-label="Kategorie v sekci">
  <?php foreach ($chips as $ch): ?>
    <a class="chip" href="<?= e($ch['href']) ?>"><?= e(title_text($ch['label'])) ?><?php if (isset($ch['count'])): ?> <span class="chip-count"><?= (int) $ch['count'] ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$items): ?>
  <?php if (empty($subtitle)): ?><p class="empty-note">Zatím tu nejsou žádné články.</p><?php endif; ?>
<?php else: ?>
<div class="post-list">
  <?php foreach ($items as $it):
      $url = url_item($it['iurltitle']);
      $nc = (int) $it['inumcomments']; ?>
  <article class="post-card">
    <div class="post-meta">
      <time datetime="<?= e(date('c', strtotime($it['itime']))) ?>"><?= e(cz_date($it['itime'])) ?></time>
      <?php if (!empty($it['catslug'])): ?>
        <a class="chip" href="<?= e(url_category($it['catslug'])) ?>"><?= e($it['catname']) ?></a>
      <?php endif; ?>
    </div>
    <h2 class="post-title"><a href="<?= e($url) ?>"><?= mark_text(title_text($it['ititle']), $highlight) ?></a></h2>
    <?php if ($highlight): ?>
      <p class="post-perex"><?= mark_text(truncate_text($it['ibody'], 260), $highlight) ?></p>
    <?php else: ?>
      <div class="post-body itembody">
        <?= render_body($it['ibody'], ['authorid' => (int) $it['iauthor'], 'detail' => false]) ?>
      </div>
    <?php endif; ?>
    <div class="post-foot">
      <?php if (trim((string) $it['imore']) !== ''): ?>
        <a class="readmore" href="<?= e($url) ?>">Celý článek<span aria-hidden="true"> →</span></a>
      <?php endif; ?>
      <a class="post-comments" href="<?= e($url) ?>#komentare">
        <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M21 12a8 8 0 0 1-8 8H4l2.5-3A8 8 0 1 1 21 12z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
        <?= $nc ?> <?= e(cz_plural($nc, ['komentář', 'komentáře', 'komentářů'])) ?>
      </a>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<?= view('pager', ['pager' => $pager]) ?>
<?php endif; ?>
