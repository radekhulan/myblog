<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';
require_login();

/** Normalizace <iframe> tagů (logika starého NP_Item). */
function normalize_iframes(string $t): string
{
    $t = preg_replace('/<\/iframe>/i', '', $t);
    $t = preg_replace('/<iframe(.*?)\/>/i', '<iframe$1>', $t);
    $t = preg_replace('/<iframe(.*?)>/i', '<iframe$1></iframe>', $t);
    return $t;
}

$id   = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    $item = one('SELECT * FROM ' . tbl('item') . ' WHERE inumber = ?', [$id]);
    if (!$item) {
        flash_set('err', 'Článek č. ' . $id . ' nebyl nalezen.');
        header('Location: /admin/');
        exit;
    }
}

$error  = null;
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

if ($isPost && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    if (!$item) {
        flash_set('err', 'Článek neexistuje.');
        header('Location: /admin/');
        exit;
    }
    $deletedComments = exec_q('DELETE FROM ' . tbl('comment') . ' WHERE citem = ?', [$id]);
    exec_q('DELETE FROM ' . tbl('tags_item') . ' WHERE titemid = ?', [$id]);
    exec_q('DELETE FROM ' . tbl('item') . ' WHERE inumber = ?', [$id]);
    blog_log('info', 'admin: článek smazán', [
        'id' => $id, 'title' => $item['ititle'], 'komentaru' => $deletedComments, 'admin' => current_admin(),
    ]);
    flash_set('ok', 'Článek „' . title_text($item['ititle']) . '“ byl smazán'
        . ($deletedComments > 0 ? ' včetně ' . $deletedComments . ' komentářů' : '') . '.');
    header('Location: /admin/');
    exit;
}

if ($isPost) {
    csrf_check();
    $ititle = trim((string) ($_POST['ititle'] ?? ''));
    $catid  = (int) ($_POST['icat'] ?? 0);
    $cat    = $catid > 0 ? one('SELECT catid, cblog FROM ' . tbl('category') . ' WHERE catid = ?', [$catid]) : null;

    if ($ititle === '' || $catid <= 0) {
        $error = 'Vyplňte prosím titulek a vyberte kategorii.';
    } elseif (!$cat) {
        $error = 'Vybraná kategorie neexistuje.';
    } else {
        // slug + unikátnost
        $slug = trim((string) ($_POST['iurltitle'] ?? ''));
        $slug = slugify($slug !== '' ? $slug : $ititle);
        if ($slug === '') {
            $slug = 'clanek';
        }
        $base = $slug;
        $n    = 2;
        while (scalar('SELECT inumber FROM ' . tbl('item') . ' WHERE iurltitle = ? AND inumber <> ?', [$slug, $id]) !== null) {
            $slug = $base . '-' . $n++;
        }

        // datum a čas (datetime-local → Y-m-d H:i:s)
        $rawTime = trim((string) ($_POST['itime'] ?? ''));
        $dt      = $rawTime !== '' ? date_create($rawTime) : false;
        $itime   = $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

        $data = [
            'ititle'    => $ititle,
            'iurltitle' => $slug,
            'ibody'     => normalize_iframes((string) ($_POST['ibody'] ?? '')),
            'imore'     => normalize_iframes((string) ($_POST['imore'] ?? '')),
            'iblog'     => (int) $cat['cblog'],
            'icat'      => $catid,
            'itime'     => $itime,
            'idraft'    => isset($_POST['idraft']) ? 1 : 0,
            'iclosed'   => isset($_POST['iclosed']) ? 1 : 0,
        ];

        if ($item) {
            $set  = [];
            $vals = [];
            foreach ($data as $col => $val) {
                if ((string) ($item[$col] ?? '') !== (string) $val) {
                    $set[]  = $col . ' = ?';
                    $vals[] = $val;
                }
            }
            if ($set) {
                $vals[] = $id;
                exec_q('UPDATE ' . tbl('item') . ' SET ' . implode(', ', $set) . ' WHERE inumber = ?', $vals);
                flash_set('ok', 'Článek byl uložen.');
            } else {
                flash_set('ok', 'Článek je beze změn.');
            }
        } else {
            $author = (int) (scalar(
                'SELECT mnumber FROM ' . tbl('member') . ' WHERE madmin = 1 ORDER BY mnumber LIMIT 1'
            ) ?? 0) ?: 1;
            exec_q(
                'INSERT INTO ' . tbl('item')
                . ' (ititle, iurltitle, ibody, imore, iblog, iauthor, itime, iclosed, idraft, icat, inumcomments)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)',
                [
                    $data['ititle'], $data['iurltitle'], $data['ibody'], $data['imore'],
                    $data['iblog'], $author, $data['itime'], $data['iclosed'], $data['idraft'], $data['icat'],
                ]
            );
            flash_set('ok', 'Článek byl vytvořen.');
        }
        header('Location: /admin/');
        exit;
    }
}

/* ---------- hodnoty formuláře (POST při chybě, jinak DB / výchozí) ---------- */
$vTitle  = $isPost ? trim((string) ($_POST['ititle'] ?? '')) : title_text($item['ititle'] ?? '');
$vSlug   = $isPost ? trim((string) ($_POST['iurltitle'] ?? '')) : (string) ($item['iurltitle'] ?? '');
$vCat    = $isPost ? (int) ($_POST['icat'] ?? 0) : (int) ($item['icat'] ?? 0);
$vTime   = $isPost
    ? (string) ($_POST['itime'] ?? '')
    : date('Y-m-d\TH:i', $item ? (strtotime((string) $item['itime']) ?: time()) : time());
$vDraft  = $isPost ? isset($_POST['idraft']) : (int) ($item['idraft'] ?? 0) === 1;
$vClosed = $isPost ? isset($_POST['iclosed']) : (int) ($item['iclosed'] ?? 0) === 1;
$vBody   = $isPost ? (string) ($_POST['ibody'] ?? '') : (string) ($item['ibody'] ?? '');
$vMore   = $isPost ? (string) ($_POST['imore'] ?? '') : (string) ($item['imore'] ?? '');

/* ---------- kategorie pro select (optgroup = skupiny, seskupeno dle blogu) ---------- */
$catRows = all(
    'SELECT c.catid, c.cname, c.cblog, c.cgroup, s.name AS gname, b.bname'
    . ' FROM ' . tbl('category') . ' c'
    . ' LEFT JOIN ' . tbl('subcategory') . ' s ON s.groupid = c.cgroup'
    . ' LEFT JOIN ' . tbl('blog') . ' b ON b.bnumber = c.cblog'
    . ' ORDER BY c.cblog, COALESCE(s.subsort, 9999), s.name, c.csort, c.cname'
);
$optgroups = [];
foreach ($catRows as $r) {
    $key = $r['cblog'] . ':' . (int) ($r['cgroup'] ?? 0);
    if (!isset($optgroups[$key])) {
        $blogName = title_text($r['bname'] ?? ('Blog ' . $r['cblog']));
        $optgroups[$key] = [
            'label' => $blogName . ' — ' . title_text($r['gname'] ?? 'Bez skupiny'),
            'cats'  => [],
        ];
    }
    $optgroups[$key]['cats'][] = $r;
}

ob_start();
?>
<?php if ($error !== null): ?>
  <div class="flash flash-err" style="margin:0 0 16px"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/admin/article.php<?= $id > 0 ? '?id=' . $id : '' ?>">
  <?= csrf_field() ?>
  <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

  <div class="panel">
    <div class="field">
      <label for="ititle">Titulek</label>
      <input type="text" id="ititle" name="ititle" value="<?= e($vTitle) ?>" maxlength="160" required>
    </div>
    <div class="field">
      <label for="iurltitle">SEO slug (adresa článku)</label>
      <div class="field-inline">
        <input type="text" id="iurltitle" name="iurltitle" value="<?= e($vSlug) ?>" maxlength="160" placeholder="ponechte prázdné pro automatické vygenerování">
        <button type="button" class="btn btn-ghost btn-sm" onclick="myblogSlug()">Vygenerovat</button>
      </div>
    </div>
    <div class="field-row">
      <div class="field">
        <label for="icat">Kategorie</label>
        <select id="icat" name="icat" required>
          <option value="">— vyberte kategorii —</option>
          <?php foreach ($optgroups as $og): ?>
            <optgroup label="<?= e($og['label']) ?>">
              <?php foreach ($og['cats'] as $c): ?>
                <option value="<?= (int) $c['catid'] ?>"<?= $vCat === (int) $c['catid'] ? ' selected' : '' ?>><?= e(title_text($c['cname'])) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="itime">Datum a čas publikace</label>
        <input type="datetime-local" id="itime" name="itime" value="<?= e($vTime) ?>" required>
      </div>
    </div>
    <div class="checks">
      <label class="check"><input type="checkbox" name="idraft" value="1"<?= $vDraft ? ' checked' : '' ?>> Koncept</label>
      <label class="check"><input type="checkbox" name="iclosed" value="1"<?= $vClosed ? ' checked' : '' ?>> Komentáře uzavřeny</label>
    </div>
  </div>

  <div class="panel">
    <h2>Text článku</h2>
    <textarea id="ibody" name="ibody" class="wysiwyg"><?= e($vBody) ?></textarea>
  </div>

  <div class="panel">
    <h2>Pokračování článku <span class="muted small">(nepovinné, zobrazí se až v detailu)</span></h2>
    <textarea id="imore" name="imore" class="wysiwyg"><?= e($vMore) ?></textarea>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn">Uložit článek</button>
    <a href="/admin/" class="btn btn-ghost">Zpět na přehled</a>
    <?php if ($item): ?>
      <a href="<?= e('/item/' . rawurlencode((string) $item['iurltitle'])) ?>" target="_blank" rel="noopener" class="btn btn-ghost">Náhled na webu ↗</a>
      <button type="submit" name="action" value="delete" formnovalidate class="btn btn-danger"
              onclick="return confirm('Opravdu smazat článek včetně všech jeho komentářů? Akce je nevratná.');"
              style="margin-left:auto">Smazat článek</button>
    <?php endif; ?>
  </div>
</form>

<script>
function myblogSlug() {
  var map = {'á':'a','ä':'a','č':'c','ď':'d','é':'e','ě':'e','ë':'e','í':'i','ï':'i','ľ':'l','ĺ':'l','ň':'n','ó':'o','ö':'o','ô':'o','ř':'r','ŕ':'r','š':'s','ť':'t','ú':'u','ů':'u','ü':'u','ý':'y','ž':'z','ß':'ss'};
  var t = (document.getElementById('ititle').value || '').replace(/&[^;\s]{1,30};/g, '').toLowerCase();
  t = Array.from(t).map(function (ch) { return Object.prototype.hasOwnProperty.call(map, ch) ? map[ch] : ch; }).join('');
  var m = t.match(/[a-z0-9]+/g);
  document.getElementById('iurltitle').value = m ? m.join('-') : '';
}
</script>
<script src="/admin/wysiwyg/tinymce8/tinymce.min.js"></script>
<script>
tinymce.init({selector:'textarea.wysiwyg',width:'100%',branding:false,license_key:'gpl',skin:'oxide',icons:'lucide',toolbar_mode:'floating',
external_plugins:{fileimagemanager:'/admin/wysiwyg/fileimagemanager/public/tinymce/plugin.js'},
plugins:'quickbars autoresize anchor advlist autolink table code link lists image media fileimagemanager fullscreen visualblocks searchreplace',
toolbar1:'blocks | bold italic underline strikethrough removeformat | bullist numlist | alignleft aligncenter alignright | link unlink | blockquote',
toolbar2:'undo redo | table hr | image media fileimagemanager | searchreplace visualblocks | fullscreen code',
fileimagemanager_url:'/admin/wysiwyg/fileimagemanager/public/',
content_css:'/assets/css/site.css',body_class:'itembody',
content_style:'body{max-width:760px;margin:0 auto;padding:14px 18px;background:var(--panel);}body::before{display:none;}',
entity_encoding:'raw',image_advtab:true,extended_valid_elements:'*[*]',relative_urls:false,menubar:false,cleanup:false,verify_html:false,autoresize_min_height:380,promotion:false,
block_formats:'Odstavec=p;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote;Kód=pre'});
</script>
<?php
admin_page($item ? 'Úprava článku #' . $id : 'Nový článek', ob_get_clean());
