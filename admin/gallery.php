<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/gallery_admin.php';
require_login();

if (!has_gallery()) {
    flash_set('err', 'Tento web nemá tabulku fotogalerie.');
    header('Location: /admin/');
    exit;
}

$cats   = gallery_foto_categories();
$catIds = array_map(fn(array $c): int => (int) $c['catid'], $cats);

/* ---------- akce ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        case 'create_album': {
            $name  = trim((string) ($_POST['fnazev'] ?? ''));
            $catid = (int) ($_POST['fkategorie'] ?? 0);
            $popis = trim((string) ($_POST['fpopis'] ?? ''));
            if ($name === '') {
                flash_set('err', 'Název alba nesmí být prázdný.');
            } elseif (!in_array($catid, $catIds, true)) {
                flash_set('err', 'Vyberte platnou kategorii fotogalerie.');
            } else {
                exec_q(
                    'INSERT INTO ' . tbl('foto')
                    . ' (fnazev, fpopis, fdatum, fzmena, fkategorie, fblog, ffotek, fviews, oid, fhodnoceni, fitemid)'
                    . ' VALUES (?, ?, NOW(), NOW(), ?, ?, 0, 0, 0, 0, 0)',
                    [$name, $popis, $catid, FOTO_BLOG]
                );
                $newId = (int) db()->insert_id;
                flash_set('ok', 'Album „' . $name . '" bylo vytvořeno — nahrajte fotky.');
                header('Location: /admin/album.php?id=' . $newId);
                exit;
            }
            break;
        }

        case 'delete_album': {
            $fid   = (int) ($_POST['fid'] ?? 0);
            $album = $fid > 0 ? one('SELECT fid, fnazev FROM ' . tbl('foto') . ' WHERE fid = ?', [$fid]) : null;
            if (!$album) {
                flash_set('err', 'Album nebylo nalezeno.');
            } else {
                gallery_delete_album($fid);
                blog_log('info', 'admin: album smazáno', ['fid' => $fid, 'name' => $album['fnazev'], 'admin' => current_admin()]);
                flash_set('ok', 'Album „' . title_text($album['fnazev']) . '" bylo smazáno včetně fotek.');
            }
            break;
        }

        default:
            flash_set('err', 'Neznámá akce.');
    }

    header('Location: /admin/gallery.php');
    exit;
}

/* ---------- data pro výpis: alba seskupená dle kategorie ---------- */
function gallery_admin_albums(int $catid): array
{
    return all(
        'SELECT f.fid, f.fnazev, f.ffotek, f.fviews, f.fdatum,
                fo.oid AS thumb_oid, fo.onahled AS thumb_nahled
         FROM ' . tbl('foto') . ' f
         LEFT JOIN ' . tbl('foto_fotka') . ' fo ON fo.oid = (
             SELECT oid FROM ' . tbl('foto_fotka') . '
             WHERE fid = f.fid AND otyp = 0
             ORDER BY ohodnoceni DESC, ' . GALLERY_PHOTO_ORDER . ' LIMIT 1
         )
         WHERE f.fkategorie = ?
         ORDER BY f.fid DESC',
        [$catid]
    );
}

ob_start();
?>
<div class="panel">
  <h2>Nové album</h2>
  <form method="post" action="/admin/gallery.php" class="group-head" style="margin:0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_album">
    <div class="field f-name"><label>Název alba</label><input type="text" name="fnazev" maxlength="255" required></div>
    <div class="field f-short"><label>Kategorie</label>
      <select name="fkategorie" required>
        <option value="">— vyberte —</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int) $c['catid'] ?>"><?= e(title_text($c['cname'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field f-name"><label>Popis <span class="muted small">(nepovinné)</span></label><input type="text" name="fpopis" maxlength="255"></div>
    <button type="submit" class="btn">Vytvořit album</button>
  </form>
</div>

<?php if (!$cats): ?>
  <div class="panel"><p class="muted" style="margin:0">Blog fotogalerie nemá žádné kategorie. Vytvořte je v sekci Kategorie.</p></div>
<?php endif; ?>

<?php foreach ($cats as $c): ?>
  <?php
    $catid  = (int) $c['catid'];
    $albums = gallery_admin_albums($catid);
  ?>
  <div class="panel">
    <h2><?= e(title_text($c['cname'])) ?> <span class="muted small">(<?= count($albums) ?> alb)</span></h2>
    <?php if (!$albums): ?>
      <p class="muted small" style="margin:0">V této kategorii zatím nejsou žádná alba.</p>
    <?php else: ?>
      <div class="alb-grid">
        <?php foreach ($albums as $a): ?>
          <?php $fid = (int) $a['fid']; $cnt = (int) $a['ffotek']; ?>
          <div class="alb-card">
            <a class="alb-thumb" href="/admin/album.php?id=<?= $fid ?>">
              <?php if ($a['thumb_oid']): ?>
                <img src="<?= e(foto_thumb_url((int) $a['thumb_oid'], (string) $a['thumb_nahled'])) ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="alb-empty">bez fotek</span>
              <?php endif; ?>
            </a>
            <div class="alb-meta">
              <a class="alb-name" href="/admin/album.php?id=<?= $fid ?>"><?= e(title_text($a['fnazev'])) ?></a>
              <span class="muted small"><?= $cnt ?> <?= e(cz_plural($cnt, ['fotka', 'fotky', 'fotek'])) ?> · <?= e(cz_date((string) $a['fdatum'])) ?></span>
            </div>
            <div class="alb-actions">
              <a class="btn btn-ghost btn-sm" href="/admin/album.php?id=<?= $fid ?>">Upravit</a>
              <form method="post" action="/admin/gallery.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_album">
                <input type="hidden" name="fid" value="<?= $fid ?>">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Opravdu smazat album „<?= e(title_text($a['fnazev'])) ?>“ včetně všech <?= $cnt ?> fotek? Akce je nevratná.">Smazat</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php
admin_page('Fotogalerie', ob_get_clean());
