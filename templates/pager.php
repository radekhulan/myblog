<?php
/** @var array $pager */
if (empty($pager) || ($pager['prev'] === null && $pager['next'] === null)) {
    return;
}
?>
<nav class="pager" aria-label="Stránkování">
  <?php if ($pager['prev'] !== null): ?>
    <a class="pager-link pager-prev" rel="prev" href="<?= e($pager['prev']) ?>">← Novější</a>
  <?php else: ?>
    <span class="pager-link disabled">← Novější</span>
  <?php endif; ?>
  <span class="pager-info">Strana <?= (int) $pager['page'] ?> z <?= (int) $pager['pages'] ?></span>
  <?php if ($pager['next'] !== null): ?>
    <a class="pager-link pager-next" rel="next" href="<?= e($pager['next']) ?>">Starší →</a>
  <?php else: ?>
    <span class="pager-link disabled">Starší →</span>
  <?php endif; ?>
</nav>
