<?php
/** @var array $comments  @var int $seq */
$i = $seq ?? 1;
foreach ($comments as $c):
    $isMember = (int) $c['cmember'] > 0 && !empty($c['membername']);
    $name = $isMember ? $c['membername'] : (trim((string) $c['cuser']) !== '' ? $c['cuser'] : 'Anonym');
    $isAdmin = $isMember && (int) ($c['madmin'] ?? 0) === 1;
    $up = (int) ($c['cup'] ?? 0);
    $down = (int) ($c['cdown'] ?? 0);
?>
<li class="comment<?= $isAdmin ? ' comment-admin' : '' ?>" id="comment<?= (int) $c['cnumber'] ?>">
  <span class="comment-anchor" id="cmmnt<?= $i ?>"></span>
  <div class="comment-head">
    <a class="comment-num" href="#comment<?= (int) $c['cnumber'] ?>">#<?= $i ?></a>
    <strong class="comment-author"><?= e(title_text($name)) ?></strong>
    <?php if ($isAdmin): ?><span class="comment-badge">autor webu</span><?php endif; ?>
    <time class="comment-time" datetime="<?= e(date('c', strtotime($c['ctime']))) ?>"><?= e(cz_date($c['ctime'], true)) ?></time>
    <?php if ($up > 0 || $down > 0): ?>
      <span class="comment-karma" title="Hodnocení komentáře">▲<?= $up ?> ▼<?= $down ?></span>
    <?php endif; ?>
  </div>
  <div class="comment-body"><?= render_comment($c['cbody']) ?></div>
</li>
<?php $i++; endforeach; ?>
