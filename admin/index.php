<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';
require_login();

$p    = max(1, (int) ($_GET['p'] ?? 1));
$s    = trim((string) ($_GET['s'] ?? ''));
$blog = (int) ($_GET['blog'] ?? 0);
$cat  = (int) ($_GET['cat'] ?? 0);

$where  = [];
$params = [];
if ($s !== '') {
    $where[]  = 'i.ititle LIKE ?';
    $params[] = '%' . addcslashes($s, '\\%_') . '%';
}
if ($blog > 0) {
    $where[]  = 'i.iblog = ?';
    $params[] = $blog;
}
if ($cat > 0) {
    $where[]  = 'i.icat = ?';
    $params[] = $cat;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total  = (int) scalar('SELECT COUNT(*) FROM ' . tbl('item') . ' i' . $whereSql, $params);
$pages  = max(1, (int) ceil($total / ADMIN_PER_PAGE));
$p      = min($p, $pages);
$offset = ($p - 1) * ADMIN_PER_PAGE;

$rows = all(
    'SELECT i.inumber, i.ititle, i.iurltitle, i.itime, i.idraft, i.iclosed, i.inumcomments, b.bshortname, c.cname'
    . ' FROM ' . tbl('item') . ' i'
    . ' LEFT JOIN ' . tbl('category') . ' c ON c.catid = i.icat'
    . ' LEFT JOIN ' . tbl('blog') . ' b ON b.bnumber = i.iblog'
    . $whereSql
    . ' ORDER BY i.itime DESC'
    . ' LIMIT ' . (int) ADMIN_PER_PAGE . ' OFFSET ' . (int) $offset,
    $params
);

$blogs = all('SELECT bnumber, bname, bshortname FROM ' . tbl('blog') . ' ORDER BY bnumber');

/* Kategorie pro filtr — seskupené do optgroup dle blogu. */
$catRows = all(
    'SELECT c.catid, c.cname, c.cblog, b.bname'
    . ' FROM ' . tbl('category') . ' c'
    . ' LEFT JOIN ' . tbl('blog') . ' b ON b.bnumber = c.cblog'
    . ' ORDER BY c.cblog, c.csort, c.cname'
);
$catGroups = [];
foreach ($catRows as $r) {
    $bid = (int) $r['cblog'];
    if (!isset($catGroups[$bid])) {
        $catGroups[$bid] = ['label' => title_text($r['bname'] ?? ('Blog ' . $bid)), 'cats' => []];
    }
    $catGroups[$bid]['cats'][] = $r;
}

function index_url(int $page, string $s, int $blog, int $cat): string
{
    $qs = http_build_query(array_filter([
        'p'    => $page > 1 ? $page : null,
        's'    => $s !== '' ? $s : null,
        'blog' => $blog > 0 ? $blog : null,
        'cat'  => $cat > 0 ? $cat : null,
    ]));
    return '/admin/' . ($qs !== '' ? '?' . $qs : '');
}

ob_start();
?>
<div class="panel">
  <form method="get" action="/admin/" class="panel-tools">
    <div class="field" style="flex:2;min-width:200px;margin:0">
      <label for="s">Hledat v titulku</label>
      <input type="search" id="s" name="s" value="<?= e($s) ?>" placeholder="Hledaný výraz…">
    </div>
    <div class="field" style="flex:1;min-width:150px;margin:0">
      <label for="blog">Blog</label>
      <select id="blog" name="blog">
        <option value="0">— všechny blogy —</option>
        <?php foreach ($blogs as $b): ?>
          <option value="<?= (int) $b['bnumber'] ?>"<?= $blog === (int) $b['bnumber'] ? ' selected' : '' ?>>
            <?= e(title_text($b['bname'])) ?> (<?= e($b['bshortname']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="flex:1;min-width:160px;margin:0">
      <label for="cat">Kategorie</label>
      <select id="cat" name="cat">
        <option value="0">— všechny kategorie —</option>
        <?php foreach ($catGroups as $g): ?>
          <optgroup label="<?= e($g['label']) ?>">
            <?php foreach ($g['cats'] as $c): ?>
              <option value="<?= (int) $c['catid'] ?>"<?= $cat === (int) $c['catid'] ? ' selected' : '' ?>><?= e(title_text($c['cname'])) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-ghost">Filtrovat</button>
    <a href="/admin/article.php" class="btn">+ Nový článek</a>
  </form>
</div>

<div class="panel">
  <h2>Články <span class="muted small">(celkem <?= $total ?>)</span></h2>
  <?php if (!$rows): ?>
    <p class="muted">Žádné články nenalezeny.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th class="num">#</th>
        <th>Titulek</th>
        <th>Blog</th>
        <th>Kategorie</th>
        <th>Datum</th>
        <th class="num">Komentáře</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="num"><?= (int) $r['inumber'] ?></td>
        <td>
          <a href="/admin/article.php?id=<?= (int) $r['inumber'] ?>"><?= e(title_text($r['ititle'])) ?></a>
          <?php if ((int) $r['idraft'] === 1): ?> <span class="badge badge-draft">koncept</span><?php endif; ?>
          <?php if ((int) $r['iclosed'] === 1): ?> <span class="badge badge-closed">kom. uzavřeny</span><?php endif; ?>
        </td>
        <td><?= e($r['bshortname'] ?? '—') ?></td>
        <td><?= e(title_text($r['cname'] ?? '—')) ?></td>
        <td style="white-space:nowrap"><?= e(cz_date($r['itime'], true)) ?></td>
        <td class="num"><a href="/admin/comments.php?item=<?= (int) $r['inumber'] ?>"><?= (int) $r['inumcomments'] ?></a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<div class="pager">
  <?php if ($p > 1): ?><a href="<?= e(index_url($p - 1, $s, $blog, $cat)) ?>">&laquo; Předchozí</a><?php endif; ?>
  <span class="pager-info">strana <?= $p ?> z <?= $pages ?></span>
  <?php if ($p < $pages): ?><a href="<?= e(index_url($p + 1, $s, $blog, $cat)) ?>">Další &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php
admin_page('Články', ob_get_clean());
