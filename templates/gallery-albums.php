<?php
declare(strict_types=1);
/** @var string $heading */
/** @var ?string $subtitle */
/** @var array $albums */
/** @var int $total */
/** @var array $pager */
/** @var ?array $crumbs */
?>
<?php if (!empty($crumbs) && count($crumbs) > 1): ?>
<nav class="crumbs" aria-label="Drobečková navigace">
  <?php foreach ($crumbs as $ci => [$cLabel, $cHref]): ?>
    <?php if ($ci > 0): ?><span aria-hidden="true">›</span><?php endif; ?>
    <?php if ($cHref !== null): ?><a href="<?= e($cHref) ?>"><?= e($cLabel) ?></a><?php else: ?><span class="crumb-current"><?= e($cLabel) ?></span><?php endif; ?>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<header class="page-head">
    <h1><?= e($heading) ?></h1>
    <?php if ($subtitle !== null && $subtitle !== ''): ?>
        <p class="page-sub"><?= e($subtitle) ?></p>
    <?php endif; ?>
</header>

<?php if (!$albums): ?>
    <p>V této sekci zatím nejsou žádná alba.</p>
<?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($albums as $album): ?>
            <?php
            $fid = (int) $album['fid'];
            $catid = (int) $album['fkategorie'];
            $count = (int) $album['ffotek'];
            $meta = $count . ' ' . cz_plural($count, ['fotka', 'fotky', 'fotek'])
                . ' · ' . cz_date((string) $album['fdatum']);
            ?>
            <a class="gallery-card" href="<?= e(url_album($fid, $catid)) ?>">
                <?php if (!empty($album['thumb_nahled'])): ?>
                    <img class="gallery-card-img"
                         src="<?= e(foto_thumb_url((int) $album['thumb_oid'], $album['thumb_nahled'])) ?>"
                         alt="<?= e($album['fnazev']) ?>" loading="lazy">
                <?php endif; ?>
                <span class="gallery-card-body">
                    <strong class="gallery-card-title"><?= e($album['fnazev']) ?></strong>
                    <span class="gallery-card-meta"><?= e($meta) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($pager['prev'] || $pager['next']): ?>
        <nav class="pager">
            <?php if ($pager['prev']): ?>
                <a class="pager-prev" href="<?= e($pager['prev']) ?>">‹ Novější</a>
            <?php endif; ?>
            <span class="pager-info"><?= (int) $pager['page'] ?> / <?= (int) $pager['pages'] ?></span>
            <?php if ($pager['next']): ?>
                <a class="pager-next" href="<?= e($pager['next']) ?>">Starší ›</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
