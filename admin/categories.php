<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';
require_login();

$blogs = all('SELECT bnumber, bname, bshortname FROM ' . tbl('blog') . ' ORDER BY bnumber');
if (!$blogs) {
    flash_set('err', 'V databázi nejsou žádné blogy.');
    header('Location: /admin/');
    exit;
}
$blogIds = array_map(fn(array $b): int => (int) $b['bnumber'], $blogs);

$blogId = (int) ($_POST['blog'] ?? $_GET['blog'] ?? 1);
if (!in_array($blogId, $blogIds, true)) {
    $blogId = $blogIds[0];
}

/* ---------- zpracování akcí ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        case 'group_save': {
            $groupId = (int) ($_POST['groupid'] ?? 0);
            $group   = one('SELECT groupid FROM ' . tbl('subcategory') . ' WHERE groupid = ? AND blogid = ?', [$groupId, $blogId]);
            $name    = trim((string) ($_POST['name'] ?? ''));
            if (!$group) {
                flash_set('err', 'Skupina nebyla nalezena.');
            } elseif ($name === '') {
                flash_set('err', 'Název skupiny nesmí být prázdný.');
            } else {
                $slug = trim((string) ($_POST['iurltitle'] ?? ''));
                $slug = slugify($slug !== '' ? $slug : $name);
                exec_q(
                    'UPDATE ' . tbl('subcategory') . ' SET name = ?, shortname = ?, iurltitle = ?, subsort = ? WHERE groupid = ?',
                    [$name, trim((string) ($_POST['shortname'] ?? '')), $slug, (int) ($_POST['subsort'] ?? 0), $groupId]
                );
                flash_set('ok', 'Skupina „' . $name . '" byla uložena.');
            }
            break;
        }

        case 'group_delete': {
            $groupId = (int) ($_POST['groupid'] ?? 0);
            $group   = one('SELECT groupid, name FROM ' . tbl('subcategory') . ' WHERE groupid = ? AND blogid = ?', [$groupId, $blogId]);
            if (!$group) {
                flash_set('err', 'Skupina nebyla nalezena.');
            } elseif ((int) scalar('SELECT COUNT(*) FROM ' . tbl('category') . ' WHERE cgroup = ?', [$groupId]) > 0) {
                flash_set('err', 'Skupinu nelze smazat — obsahuje kategorie.');
            } else {
                exec_q('DELETE FROM ' . tbl('subcategory') . ' WHERE groupid = ?', [$groupId]);
                flash_set('ok', 'Skupina „' . title_text($group['name']) . '" byla smazána.');
            }
            break;
        }

        case 'group_new': {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                flash_set('err', 'Název nové skupiny nesmí být prázdný.');
            } else {
                $slug = trim((string) ($_POST['iurltitle'] ?? ''));
                $slug = slugify($slug !== '' ? $slug : $name);
                exec_q(
                    'INSERT INTO ' . tbl('subcategory') . ' (blogid, name, shortname, iurltitle, subsort) VALUES (?, ?, ?, ?, ?)',
                    [$blogId, $name, trim((string) ($_POST['shortname'] ?? '')), $slug, (int) ($_POST['subsort'] ?? 0)]
                );
                flash_set('ok', 'Skupina „' . $name . '" byla vytvořena.');
            }
            break;
        }

        case 'cat_save': {
            $catId = (int) ($_POST['catid'] ?? 0);
            $cat   = one('SELECT catid, cblog FROM ' . tbl('category') . ' WHERE catid = ? AND cblog = ?', [$catId, $blogId]);
            $name  = trim((string) ($_POST['cname'] ?? ''));
            $group = (int) ($_POST['cgroup'] ?? 0);
            if (!$cat) {
                flash_set('err', 'Kategorie nebyla nalezena.');
            } elseif ($name === '') {
                flash_set('err', 'Název kategorie nesmí být prázdný.');
            } elseif (!one('SELECT groupid FROM ' . tbl('subcategory') . ' WHERE groupid = ? AND blogid = ?', [$group, (int) $cat['cblog']])) {
                // skupina musí patřit témuž blogu jako kategorie (konzistence cblog/cgroup)
                flash_set('err', 'Cílová skupina nepatří do stejného blogu.');
            } else {
                $slug = trim((string) ($_POST['iurltitle'] ?? ''));
                $slug = slugify($slug !== '' ? $slug : $name);
                exec_q(
                    'UPDATE ' . tbl('category') . ' SET cname = ?, cdesc = ?, iurltitle = ?, cgroup = ? WHERE catid = ?',
                    [$name, trim((string) ($_POST['cdesc'] ?? '')), $slug, $group, $catId]
                );
                flash_set('ok', 'Kategorie „' . $name . '" byla uložena.');
            }
            break;
        }

        case 'cat_delete': {
            $catId = (int) ($_POST['catid'] ?? 0);
            $cat   = one('SELECT catid, cname FROM ' . tbl('category') . ' WHERE catid = ? AND cblog = ?', [$catId, $blogId]);
            if (!$cat) {
                flash_set('err', 'Kategorie nebyla nalezena.');
            } elseif ((int) scalar('SELECT COUNT(*) FROM ' . tbl('item') . ' WHERE icat = ?', [$catId]) > 0) {
                flash_set('err', 'Kategorii nelze smazat — obsahuje články.');
            } elseif (has_gallery() && (int) scalar('SELECT COUNT(*) FROM ' . tbl('foto') . ' WHERE fkategorie = ?', [$catId]) > 0) {
                flash_set('err', 'Kategorii nelze smazat — obsahuje fotoalba.');
            } else {
                exec_q('DELETE FROM ' . tbl('category') . ' WHERE catid = ?', [$catId]);
                flash_set('ok', 'Kategorie „' . title_text($cat['cname']) . '" byla smazána.');
            }
            break;
        }

        case 'cat_new': {
            $name  = trim((string) ($_POST['cname'] ?? ''));
            $group = (int) ($_POST['cgroup'] ?? 0);
            if ($name === '') {
                flash_set('err', 'Název nové kategorie nesmí být prázdný.');
            } elseif (!one('SELECT groupid FROM ' . tbl('subcategory') . ' WHERE groupid = ? AND blogid = ?', [$group, $blogId])) {
                flash_set('err', 'Vyberte skupinu patřící do tohoto blogu.');
            } else {
                $slug = trim((string) ($_POST['iurltitle'] ?? ''));
                $slug = slugify($slug !== '' ? $slug : $name);
                exec_q(
                    'INSERT INTO ' . tbl('category') . ' (cblog, cname, cdesc, iurltitle, cgroup) VALUES (?, ?, ?, ?, ?)',
                    [$blogId, $name, trim((string) ($_POST['cdesc'] ?? '')), $slug, $group]
                );
                flash_set('ok', 'Kategorie „' . $name . '" byla vytvořena.');
            }
            break;
        }

        default:
            flash_set('err', 'Neznámá akce.');
    }

    header('Location: /admin/categories.php?blog=' . $blogId);
    exit;
}

/* ---------- data pro výpis ---------- */
$groups = all('SELECT * FROM ' . tbl('subcategory') . ' WHERE blogid = ? ORDER BY subsort, name', [$blogId]);
$albCntExpr = has_gallery()
    ? '(SELECT COUNT(*) FROM ' . tbl('foto') . ' f WHERE f.fkategorie = c.catid)'
    : '0';
$cats   = all(
    'SELECT c.*, (SELECT COUNT(*) FROM ' . tbl('item') . ' i WHERE i.icat = c.catid) AS cnt,'
    . ' ' . $albCntExpr . ' AS acnt'
    . ' FROM ' . tbl('category') . ' c WHERE c.cblog = ? ORDER BY c.csort, c.cname',
    [$blogId]
);

$groupIdsHere = array_map(fn(array $g): int => (int) $g['groupid'], $groups);
$catsByGroup  = [];
$orphans      = [];
foreach ($cats as $c) {
    $g = (int) ($c['cgroup'] ?? 0);
    if (in_array($g, $groupIdsHere, true)) {
        $catsByGroup[$g][] = $c;
    } else {
        $orphans[] = $c;
    }
}

/** Select skupin daného blogu (pro přesun kategorie / novou kategorii). */
function group_select(string $name, array $groups, int $selected): string
{
    $out = '<select name="' . e($name) . '">';
    foreach ($groups as $g) {
        $out .= '<option value="' . (int) $g['groupid'] . '"'
            . ((int) $g['groupid'] === $selected ? ' selected' : '') . '>'
            . e(title_text($g['name'])) . '</option>';
    }
    return $out . '</select>';
}

/** Hlavička sloupců nad výpisem kategorií. */
function cat_head(): string
{
    return '<div class="cat-head">'
        . '<span class="c-name">Název</span>'
        . '<span class="c-slug">Slug</span>'
        . '<span class="c-desc">Popis</span>'
        . '<span class="c-group">Skupina</span>'
        . '<span class="c-count">Obsah</span>'
        . '<span class="c-act">Akce</span>'
        . '</div>';
}

/** Jeden řádek kategorie (inline edit + mazání). */
function cat_row(array $c, array $groups, int $blogId): string
{
    $catId   = (int) $c['catid'];
    $cnt     = (int) $c['cnt'];
    $acnt    = (int) ($c['acnt'] ?? 0);
    $formId  = 'catsave-' . $catId;
    ob_start();
    ?>
    <div class="cat-row">
      <form id="<?= $formId ?>" method="post" action="/admin/categories.php" style="display:contents">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cat_save">
        <input type="hidden" name="blog" value="<?= $blogId ?>">
        <input type="hidden" name="catid" value="<?= $catId ?>">
        <span class="c-name"><input type="text" name="cname" value="<?= e(title_text($c['cname'])) ?>" maxlength="40" required title="Název"></span>
        <span class="c-slug"><input type="text" name="iurltitle" value="<?= e($c['iurltitle'] ?? '') ?>" maxlength="40" title="Slug" placeholder="slug (auto)"></span>
        <span class="c-desc"><input type="text" name="cdesc" value="<?= e(title_text($c['cdesc'] ?? '')) ?>" maxlength="200" title="Popis" placeholder="popis"></span>
        <span class="c-group"><?= group_select('cgroup', $groups, (int) ($c['cgroup'] ?? 0)) ?></span>
        <span class="c-count"><?= $cnt ?>&nbsp;čl.<?php if ($acnt > 0): ?> · <?= $acnt ?>&nbsp;alb<?php endif; ?></span>
      </form>
      <span class="c-act">
        <button type="submit" form="<?= $formId ?>" class="btn btn-ghost btn-sm">Uložit</button>
        <?php if ($cnt === 0 && $acnt === 0): ?>
        <form method="post" action="/admin/categories.php" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cat_delete">
          <input type="hidden" name="blog" value="<?= $blogId ?>">
          <input type="hidden" name="catid" value="<?= $catId ?>">
          <button type="submit" class="btn btn-danger btn-sm" data-confirm="Opravdu smazat kategorii „<?= e(title_text($c['cname'])) ?>“?">Smazat</button>
        </form>
        <?php endif; ?>
      </span>
    </div>
    <?php
    return ob_get_clean();
}

ob_start();
?>
<div class="panel">
  <form method="get" action="/admin/categories.php" class="panel-tools">
    <div class="field" style="flex:1;min-width:220px;margin:0">
      <label for="blog">Blog</label>
      <select id="blog" name="blog" onchange="this.form.submit()">
        <?php foreach ($blogs as $b): ?>
          <option value="<?= (int) $b['bnumber'] ?>"<?= $blogId === (int) $b['bnumber'] ? ' selected' : '' ?>>
            <?= e(title_text($b['bname'])) ?> (<?= e($b['bshortname']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <noscript><button type="submit" class="btn btn-ghost">Zobrazit</button></noscript>
  </form>
</div>

<?php if (!$groups && !$orphans): ?>
  <div class="panel"><p class="muted" style="margin:0">Tento blog zatím nemá žádné skupiny kategorií. Vytvořte první skupinu níže.</p></div>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
  <?php
    $groupId   = (int) $g['groupid'];
    $groupCats = $catsByGroup[$groupId] ?? [];
  ?>
  <div class="panel">
    <div class="group-head">
      <form method="post" action="/admin/categories.php" style="display:contents">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="group_save">
        <input type="hidden" name="blog" value="<?= $blogId ?>">
        <input type="hidden" name="groupid" value="<?= $groupId ?>">
        <div class="field f-name"><label>Skupina</label><input type="text" name="name" value="<?= e(title_text($g['name'])) ?>" maxlength="255" required></div>
        <div class="field f-short"><label>Krátký název</label><input type="text" name="shortname" value="<?= e($g['shortname'] ?? '') ?>" maxlength="60"></div>
        <div class="field f-slug"><label>Slug</label><input type="text" name="iurltitle" value="<?= e($g['iurltitle'] ?? '') ?>" maxlength="255" placeholder="auto"></div>
        <div class="field f-sort"><label>Pořadí</label><input type="number" name="subsort" value="<?= (int) ($g['subsort'] ?? 0) ?>"></div>
        <button type="submit" class="btn btn-ghost btn-sm">Uložit</button>
      </form>
      <?php if (!$groupCats): ?>
      <form method="post" action="/admin/categories.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="group_delete">
        <input type="hidden" name="blog" value="<?= $blogId ?>">
        <input type="hidden" name="groupid" value="<?= $groupId ?>">
        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Opravdu smazat skupinu „<?= e(title_text($g['name'])) ?>“?">Smazat</button>
      </form>
      <?php endif; ?>
    </div>

    <?php if ($groupCats): ?>
      <div class="cat-grid">
        <?= cat_head() ?>
        <?php foreach ($groupCats as $c) { echo cat_row($c, $groups, $blogId); } ?>
      </div>
    <?php else: ?>
      <p class="muted small" style="margin:0">Skupina neobsahuje žádné kategorie.</p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if ($orphans): ?>
  <div class="panel">
    <h2>Nezařazené kategorie <span class="muted small">(skupina neexistuje — přesuňte je do platné skupiny)</span></h2>
    <div class="cat-grid">
      <?= cat_head() ?>
      <?php foreach ($orphans as $c) { echo cat_row($c, $groups, $blogId); } ?>
    </div>
  </div>
<?php endif; ?>

<div class="panel">
  <h2>Nová skupina</h2>
  <form method="post" action="/admin/categories.php" class="group-head" style="margin:0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="group_new">
    <input type="hidden" name="blog" value="<?= $blogId ?>">
    <div class="field f-name"><label>Název</label><input type="text" name="name" maxlength="255" required></div>
    <div class="field f-short"><label>Krátký název</label><input type="text" name="shortname" maxlength="60"></div>
    <div class="field f-slug"><label>Slug</label><input type="text" name="iurltitle" maxlength="255" placeholder="auto"></div>
    <div class="field f-sort"><label>Pořadí</label><input type="number" name="subsort" value="0"></div>
    <button type="submit" class="btn">Vytvořit skupinu</button>
  </form>
</div>

<?php if ($groups): ?>
<div class="panel">
  <h2>Nová kategorie</h2>
  <form method="post" action="/admin/categories.php" class="group-head" style="margin:0">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="cat_new">
    <input type="hidden" name="blog" value="<?= $blogId ?>">
    <div class="field f-name"><label>Název</label><input type="text" name="cname" maxlength="40" required></div>
    <div class="field f-slug"><label>Slug</label><input type="text" name="iurltitle" maxlength="40" placeholder="auto"></div>
    <div class="field f-name"><label>Popis</label><input type="text" name="cdesc" maxlength="200"></div>
    <div class="field f-short"><label>Skupina</label><?= group_select('cgroup', $groups, 0) ?></div>
    <button type="submit" class="btn">Vytvořit kategorii</button>
  </form>
</div>
<?php endif; ?>
<?php
admin_page('Kategorie', ob_get_clean());
