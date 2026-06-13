<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';
require_login();

const COMMENTS_ADMIN_PER_PAGE = 50;

$itemId = (int) ($_POST['item'] ?? $_GET['item'] ?? 0);
$item   = $itemId > 0
    ? one('SELECT inumber, ititle, iurltitle FROM ' . tbl('item') . ' WHERE inumber = ?', [$itemId])
    : null;
if (!$item) {
    flash_set('err', 'Článek nebyl nalezen.');
    header('Location: /admin/');
    exit;
}

$p = max(1, (int) ($_POST['p'] ?? $_GET['p'] ?? 1));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $cnumber = (int) ($_POST['cnumber'] ?? 0);
    $deleted = exec_q('DELETE FROM ' . tbl('comment') . ' WHERE cnumber = ? AND citem = ?', [$cnumber, $itemId]);
    if ($deleted > 0) {
        exec_q(
            'UPDATE ' . tbl('item') . ' SET inumcomments = GREATEST(inumcomments - 1, 0) WHERE inumber = ?',
            [$itemId]
        );
        flash_set('ok', 'Komentář č. ' . $cnumber . ' byl smazán.');
    } else {
        flash_set('err', 'Komentář nebyl nalezen.');
    }
    header('Location: /admin/comments.php?item=' . $itemId . ($p > 1 ? '&p=' . $p : ''));
    exit;
}

$total  = (int) scalar('SELECT COUNT(*) FROM ' . tbl('comment') . ' WHERE citem = ?', [$itemId]);
$pages  = max(1, (int) ceil($total / COMMENTS_ADMIN_PER_PAGE));
$p      = min($p, $pages);
$offset = ($p - 1) * COMMENTS_ADMIN_PER_PAGE;

$rows = all(
    'SELECT c.cnumber, c.cbody, c.cuser, c.cmail, c.cmember, c.ctime, c.chost, m.mname'
    . ' FROM ' . tbl('comment') . ' c'
    . ' LEFT JOIN ' . tbl('member') . ' m ON m.mnumber = c.cmember'
    . ' WHERE c.citem = ?'
    . ' ORDER BY c.ctime ASC'
    . ' LIMIT ' . (int) COMMENTS_ADMIN_PER_PAGE . ' OFFSET ' . (int) $offset,
    [$itemId]
);

ob_start();
?>
<div class="panel">
  <h2><?= e(title_text($item['ititle'])) ?></h2>
  <p class="small muted" style="margin:0">
    <a href="<?= e('/item/' . rawurlencode((string) $item['iurltitle'])) ?>" target="_blank" rel="noopener">Zobrazit článek na webu ↗</a>
    &nbsp;·&nbsp; <a href="/admin/article.php?id=<?= (int) $item['inumber'] ?>">Upravit článek</a>
    &nbsp;·&nbsp; komentářů celkem: <?= $total ?>
  </p>
</div>

<?php if (!$rows): ?>
  <div class="panel"><p class="muted" style="margin:0">Článek zatím nemá žádné komentáře.</p></div>
<?php else: ?>
  <?php foreach ($rows as $c): ?>
    <?php $author = ((int) ($c['cmember'] ?? 0) > 0 && ($c['mname'] ?? '') !== '') ? $c['mname'] : ($c['cuser'] ?? ''); ?>
    <article class="comment">
      <div class="comment-head">
        <span class="author"><?= e($author !== '' ? $author : 'anonym') ?></span>
        <?php if (($c['cmail'] ?? '') !== ''): ?><span class="muted"><?= e($c['cmail']) ?></span><?php endif; ?>
        <?php if (($c['chost'] ?? '') !== ''): ?><span class="muted"><?= e($c['chost']) ?></span><?php endif; ?>
        <span class="muted"><?= e(cz_date($c['ctime'], true)) ?></span>
        <span class="muted">#<?= (int) $c['cnumber'] ?></span>
        <form method="post" action="/admin/comments.php" onsubmit="return confirm('Opravdu smazat komentář č. <?= (int) $c['cnumber'] ?>?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="item" value="<?= $itemId ?>">
          <input type="hidden" name="p" value="<?= $p ?>">
          <input type="hidden" name="cnumber" value="<?= (int) $c['cnumber'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">Smazat</button>
        </form>
      </div>
      <div class="comment-body"><?= render_comment($c['cbody']) ?></div>
    </article>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($pages > 1): ?>
<div class="pager">
  <?php if ($p > 1): ?><a href="/admin/comments.php?item=<?= $itemId ?>&amp;p=<?= $p - 1 ?>">&laquo; Předchozí</a><?php endif; ?>
  <span class="pager-info">strana <?= $p ?> z <?= $pages ?></span>
  <?php if ($p < $pages): ?><a href="/admin/comments.php?item=<?= $itemId ?>&amp;p=<?= $p + 1 ?>">Další &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php
admin_page('Komentáře k článku #' . $itemId, ob_get_clean());
