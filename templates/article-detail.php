<?php
/** @var array $item  @var string $body  @var string $more  @var array $tags  @var string $comments */
$nc = (int) $item['inumcomments'];
?>
<?php if (!empty($crumbs) && count($crumbs) > 1): ?>
<nav class="crumbs" aria-label="Drobečková navigace">
  <?php foreach ($crumbs as $ci => [$label, $href]): ?>
    <?php if ($ci > 0): ?><span aria-hidden="true">›</span><?php endif; ?>
    <a href="<?= e($href) ?>"><?= e(title_text($label)) ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<article class="post-detail">
  <header class="post-head">
    <div class="post-meta">
      <?php if (!empty($item['catslug'])): ?>
        <a class="chip" href="<?= e(url_category($item['catslug'])) ?>"><?= e($item['catname']) ?></a>
      <?php endif; ?>
      <time datetime="<?= e(date('c', strtotime($item['itime']))) ?>"><?= e(cz_date($item['itime'], true)) ?></time>
      <?php if (!empty($item['author'])): ?><span class="post-author"><?= e($item['author']) ?></span><?php endif; ?>
      <?php if ($nc > 0): ?>
      <a class="post-comments" href="#komentare"><?= $nc ?> <?= e(cz_plural($nc, ['komentář', 'komentáře', 'komentářů'])) ?></a>
      <?php endif; ?>
      <?php if (fe_is_admin()): ?>
      <a class="chip chip-edit" href="/admin/article.php?id=<?= (int) $item['inumber'] ?>">✎ Upravit</a>
      <?php endif; ?>
    </div>
    <h1 class="post-detail-title"><?= e(title_text($item['ititle'])) ?></h1>
  </header>

  <div class="itembody post-body-detail"><?= $body ?></div>
  <?php if (trim($more) !== ''): ?>
  <div class="itembody post-body-detail post-more"><?= $more ?></div>
  <?php endif; ?>

  <?php if ($tags): ?>
  <div class="tag-list" aria-label="Tagy článku">
    <?php foreach ($tags as $t): ?>
      <a class="chip chip-tag" href="<?= e(url_tag((int) $t['tagid'], $t['tagurl'])) ?>">#<?= e($t['tagname']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
  $shareUrl = cfg('canonical_base') . url_item($item['iurltitle']);
  $shareU = rawurlencode($shareUrl);
  $shareT = rawurlencode(title_text($item['ititle']));
  ?>
  <div class="share-row" aria-label="Sdílení článku">
    <span class="share-label">Sdílet</span>
    <a class="share-btn share-fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareU ?>" target="_blank" rel="noopener" title="Sdílet na Facebooku">
      <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><path fill="currentColor" d="M13.5 21v-7h2.4l.4-3h-2.8V9.1c0-.87.24-1.46 1.49-1.46h1.39V5a18 18 0 0 0-2.06-.11c-2.16 0-3.65 1.32-3.65 3.75V11H8.3v3h2.37v7h2.83z"/></svg>
    </a>
    <a class="share-btn share-x" href="https://twitter.com/intent/tweet?url=<?= $shareU ?>&amp;text=<?= $shareT ?>" target="_blank" rel="noopener" title="Sdílet na X">
      <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M17.6 3h3l-6.6 7.6L21.8 21h-6.1l-4.8-6.2L5.4 21h-3l7-8.1L2.2 3h6.3l4.3 5.7L17.6 3zm-1.1 16.2h1.7L7.6 4.7H5.8l10.7 14.5z"/></svg>
    </a>
    <a class="share-btn share-li" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareU ?>" target="_blank" rel="noopener" title="Sdílet na LinkedIn">
      <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M6.94 8.5H3.86V21h3.08V8.5zM5.4 3a1.85 1.85 0 1 0 0 3.7 1.85 1.85 0 0 0 0-3.7zM21 13.39c0-3.42-1.83-5.15-4.27-5.15-1.97 0-2.85 1.08-3.34 1.84V8.5H10.3V21h3.08v-6.95c0-1.4.95-2.23 2.1-2.23 1.12 0 2.43.6 2.43 2.7V21H21v-7.61z"/></svg>
    </a>
    <a class="share-btn share-wa" href="https://wa.me/?text=<?= $shareT ?>%20<?= $shareU ?>" target="_blank" rel="noopener" title="Sdílet přes WhatsApp">
      <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 1.8a8.2 8.2 0 1 1-4.2 15.3l-.3-.18-2.96.77.8-2.88-.2-.31A8.2 8.2 0 0 1 12 3.8zm-3.1 4.1c-.2 0-.5.07-.76.36-.26.28-1 .97-1 2.37 0 1.4 1.02 2.76 1.16 2.95.14.19 2 3.05 4.85 4.15 2.4.94 2.88.76 3.4.71.52-.05 1.68-.68 1.92-1.34.24-.66.24-1.23.17-1.35-.07-.12-.26-.19-.55-.33-.28-.14-1.68-.83-1.94-.92-.26-.1-.45-.14-.64.14-.19.28-.74.92-.9 1.11-.17.19-.34.21-.62.07a7.8 7.8 0 0 1-2.28-1.41 8.6 8.6 0 0 1-1.58-1.97c-.17-.28-.02-.44.12-.58.13-.13.29-.33.43-.5.14-.16.19-.28.28-.47.1-.19.05-.35-.02-.5-.07-.14-.62-1.54-.88-2.1-.23-.5-.47-.5-.65-.5z"/></svg>
    </a>
    <a class="share-btn share-mail" href="mailto:?subject=<?= $shareT ?>&amp;body=<?= $shareU ?>" title="Poslat e-mailem">
      <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><path fill="currentColor" d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm1 2.4V17h16V7.4l-8 5.3-8-5.3zM5.6 7l6.4 4.2L18.4 7H5.6z"/></svg>
    </a>
    <button type="button" class="share-btn share-copy" data-copy="<?= e($shareUrl) ?>" title="Zkopírovat odkaz">
      <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M10.5 13.5a4 4 0 0 0 5.7 0l3.3-3.3a4 4 0 1 0-5.7-5.7l-1.2 1.2M13.5 10.5a4 4 0 0 0-5.7 0l-3.3 3.3a4 4 0 1 0 5.7 5.7l1.2-1.2"/></svg>
    </button>
  </div>
</article>

<?= $comments ?>
