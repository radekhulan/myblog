<?php
declare(strict_types=1);
/** @var array $album */
/** @var array $fotky */
/** @var int $catid */
/** @var ?array $category */
/** @var ?array $group */
$fid = (int) $album['fid'];
$count = (int) $album['ffotek'];
$views = (int) $album['fviews'];
?>
<nav class="crumbs">
    <a href="<?= e(url_group('galerie')) ?>">Fotogalerie</a>
    <?php if ($group): ?>
        <span>›</span>
        <a href="<?= e(url_group($group['iurltitle'])) ?>"><?= e($group['name']) ?></a>
    <?php endif; ?>
    <?php if ($category): ?>
        <span>›</span>
        <a href="<?= e(url_category($category['iurltitle'])) ?>"><?= e($category['cname']) ?></a>
    <?php endif; ?>
    <span>›</span>
    <span><?= e($album['fnazev']) ?></span>
</nav>

<header class="page-head">
    <h1><?= e($album['fnazev']) ?></h1>
    <p class="page-sub">
        <?= e(cz_date((string) $album['fdatum'])) ?>
        · <?= $count ?> <?= e(cz_plural($count, ['fotka', 'fotky', 'fotek'])) ?>
        · <?= $views ?> <?= e(cz_plural($views, ['zhlédnutí', 'zhlédnutí', 'zhlédnutí'])) ?>
    </p>
</header>

<?php if (trim((string) $album['fpopis']) !== ''): ?>
    <div class="album-desc itembody"><?= $album['fpopis'] ?></div>
<?php endif; ?>

<?php if ($fotky): ?>
    <div class="album-grid album-grid-page">
        <?php foreach ($fotky as $f): ?>
            <?php $oid = (int) $f['oid']; ?>
            <a href="<?= e(url_fotka($oid, $catid)) ?>" class="colorshow" rel="album<?= $fid ?>"
               data-full="<?= e(foto_full_url($oid, $f['osoubor'])) ?>" title="<?= e($f['onazev']) ?>">
                <img src="<?= e(foto_thumb_url($oid, $f['onahled'])) ?>" alt="<?= e($f['onazev']) ?>" loading="lazy">
                <span class="foto-label"><?= e($f['onazev']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>V tomto albu zatím nejsou žádné fotografie.</p>
<?php endif; ?>
