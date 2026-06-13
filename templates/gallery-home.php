<?php
declare(strict_types=1);
/** @var string $heading */
/** @var array $sections */
?>
<header class="page-head">
    <h1><?= e($heading) ?></h1>
</header>

<?php if (!$sections): ?>
    <p>Zatím tu nejsou žádná alba.</p>
<?php else: ?>
    <?php foreach ($sections as $section): ?>
        <section class="gallery-section">
            <h2 class="gallery-section-title">
                <a href="<?= e($section['url']) ?>"><?= e($section['name']) ?></a>
                <span class="gallery-section-count"><?= (int) $section['total'] ?> <?= e(cz_plural((int) $section['total'], ['album', 'alba', 'alb'])) ?></span>
            </h2>
            <div class="gallery-grid">
                <?php foreach ($section['albums'] as $album): ?>
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
            <?php if ($section['total'] > count($section['albums'])): ?>
            <p class="gallery-section-more">
                <a href="<?= e($section['url']) ?>">Všechna alba (<?= (int) $section['total'] ?>) →</a>
            </p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
