<?php
declare(strict_types=1);
/** @var array $fotka */
/** @var ?array $album */
/** @var ?int $catid */
/** @var ?int $prev */
/** @var ?int $next */
$oid = (int) $fotka['oid'];
$views = (int) $fotka['oviews'];
$w = (int) $fotka['ow'];
$h = (int) $fotka['oh'];
?>
<nav class="crumbs">
    <a href="<?= e(url_group('galerie')) ?>">Fotogalerie</a>
    <?php if ($album): ?>
        <span>›</span>
        <a href="<?= e(url_album((int) $album['fid'], $catid)) ?>"><?= e($album['fnazev']) ?></a>
    <?php endif; ?>
    <span>›</span>
    <span><?= e($fotka['onazev']) ?></span>
</nav>

<figure class="foto-stage">
    <a class="lightbox" href="<?= e(foto_full_url($oid, $fotka['osoubor'])) ?>" title="<?= e($fotka['onazev']) ?>">
        <img src="<?= e(foto_medium_url($oid, $fotka['onahled'])) ?>" alt="<?= e($fotka['onazev']) ?>" loading="lazy">
    </a>
    <figcaption class="foto-caption">
        <?php if (trim((string) $fotka['onazev']) !== ''): ?>
            <strong><?= e($fotka['onazev']) ?></strong>
        <?php endif; ?>
        <?php if (trim((string) $fotka['opopis']) !== ''): ?>
            <span class="foto-desc"><?= e($fotka['opopis']) ?></span>
        <?php endif; ?>
        <span class="foto-meta">
            <?= e(cz_date((string) $fotka['odatum'])) ?>
            · <?= $views ?> <?= e(cz_plural($views, ['zhlédnutí', 'zhlédnutí', 'zhlédnutí'])) ?>
            <?php if ($w > 0 && $h > 0): ?>
                · <?= $w ?>×<?= $h ?> px
            <?php endif; ?>
        </span>
    </figcaption>
</figure>

<nav class="foto-nav">
    <?php if ($prev !== null): ?>
        <a class="foto-prev" href="<?= e(url_fotka($prev, $catid)) ?>" title="Předchozí fotka">‹ Předchozí</a>
    <?php endif; ?>
    <?php if ($album): ?>
        <a class="foto-back" href="<?= e(url_album((int) $album['fid'], $catid)) ?>">Album</a>
    <?php endif; ?>
    <?php if ($next !== null): ?>
        <a class="foto-next" href="<?= e(url_fotka($next, $catid)) ?>" title="Další fotka">Další ›</a>
    <?php endif; ?>
</nav>
