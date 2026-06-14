<?php
declare(strict_types=1);

require __DIR__ . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/gallery_admin.php';
require_login();

/** Pošle JSON odpověď a ukončí běh (pro AJAX akce). */
function album_json(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$fid    = (int) ($_GET['id'] ?? $_POST['fid'] ?? 0);
$album  = $fid > 0 ? one('SELECT * FROM ' . tbl('foto') . ' WHERE fid = ?', [$fid]) : null;
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$action = (string) ($_POST['action'] ?? '');

/* ---------- AJAX akce (JSON): nahrání, mazání a přejmenování fotky ---------- */
if ($isPost && in_array($action, ['upload_photo', 'delete_photo', 'rename_photo', 'reorder'], true)) {
    if (!hash_equals($_SESSION['myblog_csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        album_json(['ok' => false, 'error' => 'Neplatný bezpečnostní token. Obnovte stránku.']);
    }
    if (!$album || (int) $album['fblog'] !== FOTO_BLOG) {
        album_json(['ok' => false, 'error' => 'Album nebylo nalezeno.']);
    }

    if ($action === 'upload_photo') {
        $file = $_FILES['photo'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            $msg  = in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Soubor je příliš velký (max 32 MB).'
                : 'Soubor se nepodařilo nahrát.';
            album_json(['ok' => false, 'error' => $msg]);
        }
        $res = gallery_add_photo($fid, $file['tmp_name'], (string) $file['name'], (string) $album['fnazev']);
        if (is_string($res)) {
            album_json(['ok' => false, 'error' => $res]);
        }
        $res['count'] = (int) scalar('SELECT COUNT(*) FROM ' . tbl('foto_fotka') . ' WHERE fid = ? AND otyp = 0', [$fid]);
        album_json(['ok' => true] + $res);
    }

    if ($action === 'delete_photo') {
        $oid = (int) ($_POST['oid'] ?? 0);
        $own = (int) (scalar('SELECT fid FROM ' . tbl('foto_fotka') . ' WHERE oid = ?', [$oid]) ?? 0);
        if ($own !== $fid) {
            album_json(['ok' => false, 'error' => 'Fotka do tohoto alba nepatří.']);
        }
        gallery_delete_photo($oid);
        $cnt = (int) scalar('SELECT COUNT(*) FROM ' . tbl('foto_fotka') . ' WHERE fid = ? AND otyp = 0', [$fid]);
        album_json(['ok' => true, 'count' => $cnt]);
    }

    if ($action === 'rename_photo') {
        $oid  = (int) ($_POST['oid'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if (mb_strlen($name) > GALLERY_CAPTION_MAXLEN) {
            $name = mb_substr($name, 0, GALLERY_CAPTION_MAXLEN);
        }
        exec_q('UPDATE ' . tbl('foto_fotka') . ' SET onazev = ? WHERE oid = ? AND fid = ?', [$name, $oid, $fid]);
        album_json(['ok' => true, 'name' => $name]);
    }

    if ($action === 'reorder') {
        // Přiřadí oporadi = 1..N podle nového pořadí oid (jen fotky daného alba).
        $oids = $_POST['oids'] ?? [];
        if (!is_array($oids) || !$oids) {
            album_json(['ok' => false, 'error' => 'Chybí pořadí fotek.']);
        }
        $valid = array_map(
            static fn(array $r): int => (int) $r['oid'],
            all('SELECT oid FROM ' . tbl('foto_fotka') . ' WHERE fid = ? AND otyp = 0', [$fid])
        );
        $pos = 0;
        foreach ($oids as $oidStr) {
            $oid = (int) $oidStr;
            if (!in_array($oid, $valid, true)) {
                continue;
            }
            exec_q('UPDATE ' . tbl('foto_fotka') . ' SET oporadi = ? WHERE oid = ? AND fid = ?', [++$pos, $oid, $fid]);
        }
        gallery_recount($fid);
        album_json(['ok' => true, 'count' => $pos]);
    }
}

/* ---------- klasické POST akce (redirect): uložení metadat, smazání alba ---------- */
if ($isPost && in_array($action, ['save_album', 'delete_album'], true)) {
    csrf_check();
    if (!$album) {
        flash_set('err', 'Album nebylo nalezeno.');
        header('Location: /admin/gallery.php');
        exit;
    }

    if ($action === 'delete_album') {
        gallery_delete_album($fid);
        blog_log('info', 'admin: album smazáno', ['fid' => $fid, 'name' => $album['fnazev'], 'admin' => current_admin()]);
        flash_set('ok', 'Album „' . title_text($album['fnazev']) . '" bylo smazáno včetně fotek.');
        header('Location: /admin/gallery.php');
        exit;
    }

    // save_album
    $name  = trim((string) ($_POST['fnazev'] ?? ''));
    $catid = (int) ($_POST['fkategorie'] ?? 0);
    $popis = trim((string) ($_POST['fpopis'] ?? ''));
    $catIds = array_map(fn(array $c): int => (int) $c['catid'], gallery_foto_categories());
    if ($name === '') {
        flash_set('err', 'Název alba nesmí být prázdný.');
    } elseif (!in_array($catid, $catIds, true)) {
        flash_set('err', 'Vyberte platnou kategorii fotogalerie.');
    } else {
        exec_q(
            'UPDATE ' . tbl('foto') . ' SET fnazev = ?, fpopis = ?, fkategorie = ?, fzmena = NOW() WHERE fid = ?',
            [$name, $popis, $catid, $fid]
        );
        flash_set('ok', 'Album bylo uloženo.');
    }
    header('Location: /admin/album.php?id=' . $fid);
    exit;
}

if (!$album) {
    flash_set('err', 'Album nebylo nalezeno.');
    header('Location: /admin/gallery.php');
    exit;
}

/* ---------- data pro editor ---------- */
$cats  = gallery_foto_categories();
$fotky = all('SELECT oid, onazev, onahled, osoubor FROM ' . tbl('foto_fotka') . ' WHERE fid = ? AND otyp = 0 ORDER BY ' . GALLERY_PHOTO_ORDER, [$fid]);
$count = count($fotky);

ob_start();
?>
<div class="panel">
  <form method="post" action="/admin/album.php?id=<?= $fid ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_album">
    <input type="hidden" name="fid" value="<?= $fid ?>">
    <div class="field-row">
      <div class="field" style="flex:2;min-width:240px">
        <label for="fnazev">Název alba</label>
        <input type="text" id="fnazev" name="fnazev" value="<?= e(title_text($album['fnazev'])) ?>" maxlength="255" required>
      </div>
      <div class="field" style="flex:1;min-width:180px">
        <label for="fkategorie">Kategorie</label>
        <select id="fkategorie" name="fkategorie" required>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int) $c['catid'] ?>"<?= (int) $album['fkategorie'] === (int) $c['catid'] ? ' selected' : '' ?>><?= e(title_text($c['cname'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label for="fpopis">Popis alba <span class="muted small">(nepovinné)</span></label>
      <input type="text" id="fpopis" name="fpopis" value="<?= e(title_text($album['fpopis'] ?? '')) ?>" maxlength="255">
    </div>
    <div class="form-actions">
      <button type="submit" class="btn">Uložit album</button>
      <a href="/admin/gallery.php" class="btn btn-ghost">Zpět na alba</a>
      <a href="<?= e(url_album($fid, (int) $album['fkategorie'])) ?>" target="_blank" rel="noopener" class="btn btn-ghost">Náhled na webu ↗</a>
      <button type="submit" name="action" value="delete_album" formnovalidate class="btn btn-danger"
              data-confirm="Opravdu smazat album „<?= e(title_text($album['fnazev'])) ?>“ včetně všech <?= $count ?> fotek? Akce je nevratná."
              style="margin-left:auto">Smazat album</button>
    </div>
  </form>
</div>

<div class="panel">
  <h2>Fotky <span class="muted small">(<span id="photoCount"><?= $count ?></span>)</span></h2>

  <div id="dropzone" class="dropzone" tabindex="0" role="button" aria-label="Nahrát fotky">
    <input type="file" id="fileInput" accept="image/jpeg,image/png,image/webp" multiple hidden>
    <div class="dz-inner">
      <strong>Přetáhněte sem fotky</strong>
      <span class="muted small">nebo klikněte pro výběr · JPEG, PNG, WEBP · max 32 MB</span>
    </div>
    <div id="uploadStatus" class="dz-status" hidden></div>
  </div>

  <div id="photoGrid" class="ph-grid">
    <?php foreach ($fotky as $f): ?>
      <?php $oid = (int) $f['oid']; ?>
      <div class="ph-card" data-oid="<?= $oid ?>" draggable="true">
        <span class="ph-grip" title="Přetažením změníte pořadí" aria-hidden="true">⠿</span>
        <a class="ph-thumb" draggable="false" href="<?= e(foto_full_url($oid, (string) $f['osoubor'])) ?>" target="_blank" rel="noopener" title="Otevřít plnou velikost">
          <img src="<?= e(foto_thumb_url($oid, (string) $f['onahled'])) ?>" alt="" loading="lazy" draggable="false">
        </a>
        <input class="ph-cap" type="text" value="<?= e(title_text($f['onazev'] ?? '')) ?>" maxlength="240" title="Popisek fotky" aria-label="Popisek fotky">
        <button type="button" class="ph-del" title="Smazat fotku" aria-label="Smazat fotku">✕</button>
      </div>
    <?php endforeach; ?>
  </div>
  <p id="emptyHint" class="muted small"<?= $count ? ' hidden' : '' ?>>Album zatím nemá žádné fotky. Přetáhněte je do plochy výše.</p>
</div>

<script>
(function () {
  var FID = <?= $fid ?>;
  var CSRF = <?= json_encode(csrf_token()) ?>;
  var ENDPOINT = '/admin/album.php?id=' + FID;
  var MAX_PARALLEL = 1; // sekvenčně → oid = pořadí přetažení (nová fotka se zařadí na konec)

  var zone = document.getElementById('dropzone');
  var input = document.getElementById('fileInput');
  var grid = document.getElementById('photoGrid');
  var statusEl = document.getElementById('uploadStatus');
  var countEl = document.getElementById('photoCount');
  var emptyHint = document.getElementById('emptyHint');

  function setCount(n) {
    countEl.textContent = n;
    emptyHint.hidden = n > 0;
  }

  function escAttr(s) { return (s == null ? '' : String(s)).replace(/"/g, '&quot;'); }

  function buildCard(d) {
    var card = document.createElement('div');
    card.className = 'ph-card';
    card.setAttribute('data-oid', d.oid);
    card.setAttribute('draggable', 'true');
    card.innerHTML =
      '<span class="ph-grip" title="Přetažením změníte pořadí" aria-hidden="true">⠿</span>' +
      '<a class="ph-thumb" draggable="false" href="' + escAttr(d.full_url) + '" target="_blank" rel="noopener" title="Otevřít plnou velikost">' +
        '<img src="' + escAttr(d.thumb_url) + '" alt="" loading="lazy" draggable="false">' +
      '</a>' +
      '<input class="ph-cap" type="text" value="' + escAttr(d.caption) + '" maxlength="240" title="Popisek fotky" aria-label="Popisek fotky">' +
      '<button type="button" class="ph-del" title="Smazat fotku" aria-label="Smazat fotku">✕</button>';
    return card;
  }

  function isImage(file) {
    return /^image\/(jpeg|png|webp)$/.test(file.type) || /\.(jpe?g|png|webp)$/i.test(file.name);
  }

  function uploadOne(file) {
    var fd = new FormData();
    fd.append('action', 'upload_photo');
    fd.append('csrf', CSRF);
    fd.append('fid', FID);
    fd.append('photo', file);
    return fetch(ENDPOINT, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          grid.appendChild(buildCard(d));
          if (typeof d.count === 'number') setCount(d.count);
          return true;
        }
        console.warn('Upload selhal:', file.name, d && d.error);
        return false;
      })
      .catch(function (err) { console.warn('Upload chyba:', file.name, err); return false; });
  }

  function uploadFiles(fileList) {
    var files = Array.prototype.slice.call(fileList).filter(isImage);
    if (!files.length) return;
    var total = files.length, done = 0, ok = 0, idx = 0;
    statusEl.hidden = false;
    statusEl.classList.remove('dz-status-done');
    statusEl.textContent = 'Nahrávám 0 / ' + total + '…';

    function pump() {
      if (idx >= files.length) return Promise.resolve();
      var file = files[idx++];
      return uploadOne(file).then(function (success) {
        done++; if (success) ok++;
        statusEl.textContent = 'Nahrávám ' + done + ' / ' + total + '…';
        return pump();
      });
    }

    var workers = [];
    for (var w = 0; w < Math.min(MAX_PARALLEL, files.length); w++) workers.push(pump());
    Promise.all(workers).then(function () {
      var failed = total - ok;
      statusEl.classList.add('dz-status-done');
      statusEl.textContent = 'Hotovo: nahráno ' + ok + ' z ' + total + (failed ? ' (' + failed + ' selhalo)' : '') + '.';
      setTimeout(function () { statusEl.hidden = true; }, 4000);
    });
  }

  // Drag & drop
  ['dragenter', 'dragover'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('dz-over'); });
  });
  ['dragleave', 'dragend', 'drop'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.remove('dz-over'); });
  });
  zone.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files) uploadFiles(e.dataTransfer.files);
  });

  // Klik / klávesa → výběr souborů
  zone.addEventListener('click', function () { input.click(); });
  zone.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  input.addEventListener('change', function () { uploadFiles(input.files); input.value = ''; });

  // Mazání + přejmenování (delegace na mřížku)
  grid.addEventListener('click', function (e) {
    var btn = e.target.closest('.ph-del');
    if (!btn) return;
    var card = btn.closest('.ph-card');
    var oid = card.getAttribute('data-oid');
    if (!confirm('Opravdu smazat tuto fotku? Akce je nevratná.')) return;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('action', 'delete_photo');
    fd.append('csrf', CSRF);
    fd.append('fid', FID);
    fd.append('oid', oid);
    fetch(ENDPOINT, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
      if (d && d.ok) { card.remove(); if (typeof d.count === 'number') setCount(d.count); }
      else { btn.disabled = false; alert((d && d.error) || 'Smazání se nezdařilo.'); }
    }).catch(function () { btn.disabled = false; alert('Smazání se nezdařilo.'); });
  });

  grid.addEventListener('change', function (e) {
    var inp = e.target.closest('.ph-cap');
    if (!inp) return;
    var card = inp.closest('.ph-card');
    var oid = card.getAttribute('data-oid');
    var fd = new FormData();
    fd.append('action', 'rename_photo');
    fd.append('csrf', CSRF);
    fd.append('fid', FID);
    fd.append('oid', oid);
    fd.append('name', inp.value);
    inp.classList.add('ph-cap-saving');
    fetch(ENDPOINT, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
      inp.classList.remove('ph-cap-saving');
      if (d && d.ok) { inp.classList.add('ph-cap-ok'); setTimeout(function () { inp.classList.remove('ph-cap-ok'); }, 900); }
    }).catch(function () { inp.classList.remove('ph-cap-saving'); });
  });

  // Změna pořadí přetažením (HTML5 drag & drop)
  var dragEl = null, saveTimer = null;

  grid.addEventListener('dragstart', function (e) {
    var card = e.target.closest('.ph-card');
    if (!card) return;
    if (e.target.closest('.ph-cap, .ph-del')) { e.preventDefault(); return; } // netáhnout přes popisek/mazání
    dragEl = card;
    setTimeout(function () { card.classList.add('dragging'); }, 0);
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', card.getAttribute('data-oid')); } catch (err) {}
  });

  grid.addEventListener('dragover', function (e) {
    if (!dragEl) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    var after = getDragAfter(grid, e.clientX, e.clientY);
    if (after == null) grid.appendChild(dragEl);
    else if (after !== dragEl) grid.insertBefore(dragEl, after);
  });

  grid.addEventListener('drop', function (e) { if (dragEl) e.preventDefault(); });

  grid.addEventListener('dragend', function () {
    if (!dragEl) return;
    dragEl.classList.remove('dragging');
    dragEl = null;
    saveOrder();
  });

  function getDragAfter(container, x, y) {
    var cards = Array.prototype.slice.call(container.querySelectorAll('.ph-card:not(.dragging)'));
    var best = null, bestDist = Infinity;
    for (var i = 0; i < cards.length; i++) {
      var r = cards[i].getBoundingClientRect();
      var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
      var afterPointer = (y < cy - r.height / 2) || (Math.abs(y - cy) <= r.height / 2 && x < cx);
      if (afterPointer) {
        var dist = Math.hypot(x - cx, y - cy);
        if (dist < bestDist) { bestDist = dist; best = cards[i]; }
      }
    }
    return best;
  }

  function saveOrder() {
    var oids = Array.prototype.slice.call(grid.querySelectorAll('.ph-card')).map(function (c) { return c.getAttribute('data-oid'); });
    if (!oids.length) return;
    var fd = new FormData();
    fd.append('action', 'reorder');
    fd.append('csrf', CSRF);
    fd.append('fid', FID);
    oids.forEach(function (oid) { fd.append('oids[]', oid); });
    statusEl.hidden = false;
    statusEl.classList.remove('dz-status-done');
    statusEl.textContent = 'Ukládám pořadí…';
    fetch(ENDPOINT, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
      statusEl.classList.add('dz-status-done');
      statusEl.textContent = (d && d.ok) ? 'Pořadí uloženo.' : ((d && d.error) || 'Pořadí se nepodařilo uložit.');
      clearTimeout(saveTimer);
      saveTimer = setTimeout(function () { statusEl.hidden = true; }, 2500);
    }).catch(function () {
      statusEl.classList.add('dz-status-done');
      statusEl.textContent = 'Pořadí se nepodařilo uložit.';
    });
  }
})();
</script>
<?php
admin_page('Album: ' . title_text($album['fnazev']), ob_get_clean());
