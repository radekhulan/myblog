<?php
/** @var int $itemId  @var array $comments  @var int $total  @var int $shown */
if ($total === 0) {
    return;     // bez komentářů se sekce vůbec nezobrazuje
}
?>
<section class="comments" id="komentare">
  <h2 class="comments-title">Komentáře <span class="comments-count"><?= $total ?></span></h2>
  <ol class="comment-list" id="comment-list">
    <?= view('comments-items', ['comments' => $comments, 'seq' => 1]) ?>
  </ol>
  <?php if ($total > $shown): ?>
  <div class="comments-more">
    <button type="button" class="btn-load-comments" id="load-comments"
            data-item="<?= $itemId ?>" data-offset="<?= $shown ?>">
      Načíst další komentáře <span class="load-rest">(zbývá <?= $total - $shown ?>)</span>
    </button>
  </div>
  <?php endif; ?>
</section>
