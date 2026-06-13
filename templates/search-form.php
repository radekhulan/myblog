<?php
/** @var string $q  @var ?string $message */
?>
<form class="search-big" action="/hledani" role="search">
  <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Co hledáte?" aria-label="Hledaný výraz" autofocus>
  <button type="submit" class="btn">Hledat</button>
</form>
<?php if (!empty($message)): ?><p class="search-note"><?= e($message) ?></p><?php endif; ?>
