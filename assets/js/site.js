/* MyBlog front-end: přepínač témat, lightbox, AJAX komentáře, zvýraznění kódu */
(function () {
  'use strict';

  /* ---------- téma (auto / light / dark) ---------- */
  var mq = matchMedia('(prefers-color-scheme: dark)');

  function applyTheme(pref) {
    var dark = pref === 'dark' || (pref === 'auto' && mq.matches);
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme-pref', pref);
    document.querySelectorAll('.theme-switch button').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-theme-set') === pref);
    });
  }

  function themePref() {
    try { return localStorage.getItem('myblog-theme') || 'auto'; } catch (e) { return 'auto'; }
  }

  document.addEventListener('DOMContentLoaded', function () {
    applyTheme(themePref());
    document.querySelectorAll('[data-theme-set]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var pref = btn.getAttribute('data-theme-set');
        try { localStorage.setItem('myblog-theme', pref); } catch (e) {}
        applyTheme(pref);
      });
    });
    mq.addEventListener('change', function () {
      if (themePref() === 'auto') applyTheme('auto');
    });

    initLightbox();
    initComments();
    highlightCode();
    initShare();
    initContactForm();
  });

  /* ---------- kontaktní formulář (AJAX odeslání) ---------- */
  function initContactForm() {
    var form = document.getElementById('contactForm');
    if (!form) return;
    var btn = form.querySelector('.contact-btn');
    var msg = document.getElementById('contactMsg');

    function showMsg(text, ok) {
      if (!msg) return;
      msg.textContent = text;
      msg.className = 'contact-msg ' + (ok ? 'ok' : 'err');
      msg.hidden = false;
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (btn.disabled) return;

      var missing = Array.prototype.slice.call(form.querySelectorAll('[required]')).filter(function (f) {
        return !f.value.trim();
      });
      if (missing.length) { missing[0].focus(); showMsg('Vyplňte prosím všechna povinná pole.', false); return; }

      btn.disabled = true;
      btn.classList.add('is-loading');
      if (msg) msg.hidden = true;

      var body = new URLSearchParams();
      new FormData(form).forEach(function (v, k) { body.append(k, v); });

      fetch(form.getAttribute('action') || '/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: body.toString()
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          showMsg(data.message || (data.ok ? 'Odesláno.' : 'Zprávu se nepodařilo odeslat.'), !!data.ok);
          if (data.ok) form.reset();
          if (window.turnstile) { try { window.turnstile.reset(); } catch (e) {} }
        })
        .catch(function () {
          showMsg('Omlouvám se, zprávu se nepodařilo odeslat. Zkuste to prosím znovu.', false);
          if (window.turnstile) { try { window.turnstile.reset(); } catch (e) {} }
        })
        .finally(function () {
          btn.disabled = false;
          btn.classList.remove('is-loading');
        });
    });
  }

  /* ---------- kopírování odkazu ze sdílení ---------- */
  function initShare() {
    document.querySelectorAll('.share-copy').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-copy');
        function done() {
          btn.classList.add('copied');
          btn.title = 'Zkopírováno!';
          setTimeout(function () { btn.classList.remove('copied'); }, 1600);
        }
        function fallbackCopy() {
          var ta = document.createElement('textarea');
          ta.value = url;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); done(); } catch (e) {}
          ta.remove();
        }
        (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject())
          .then(done)
          .catch(fallbackCopy);
      });
    });
  }

  /* ---------- lightbox (a.lightbox + legacy a.colorbox / a.colorshow) ---------- */
  var lbItems = [];
  var lbIndex = 0;
  var overlay = null;

  function imageSrcFor(a) {
    var src = a.getAttribute('data-full') || a.getAttribute('data-src');
    if (src) return src;
    var href = a.getAttribute('href') || '';
    if (/\.(jpe?g|png|gif|webp|avif)(\?.*)?$/i.test(href)) return href;
    return null;
  }

  function initLightbox() {
    var anchors = Array.prototype.slice.call(
      document.querySelectorAll('a.lightbox, a.colorbox, a.colorshow')
    );
    lbItems = [];
    anchors.forEach(function (a) {
      var src = imageSrcFor(a);
      if (!src) return;
      var idx = lbItems.length;
      lbItems.push({
        src: src,
        caption: a.getAttribute('title') || (a.querySelector('img') && a.querySelector('img').alt) || ''
      });
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        openLb(idx);
      });
    });
  }

  function openLb(idx) {
    lbIndex = idx;
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'lb-overlay';
      overlay.innerHTML =
        '<button class="lb-btn lb-close" aria-label="Zavřít">×</button>' +
        '<button class="lb-btn lb-prev" aria-label="Předchozí">‹</button>' +
        '<img alt="">' +
        '<div class="lb-caption"></div>' +
        '<button class="lb-btn lb-next" aria-label="Další">›</button>';
      document.body.appendChild(overlay);
      overlay.querySelector('.lb-close').addEventListener('click', closeLb);
      overlay.querySelector('.lb-prev').addEventListener('click', function () { stepLb(-1); });
      overlay.querySelector('.lb-next').addEventListener('click', function () { stepLb(1); });
      overlay.addEventListener('click', function (ev) {
        if (ev.target === overlay) closeLb();
      });
      document.addEventListener('keydown', function (ev) {
        if (!overlay || !overlay.classList.contains('open')) return;
        if (ev.key === 'Escape') closeLb();
        if (ev.key === 'ArrowLeft') stepLb(-1);
        if (ev.key === 'ArrowRight') stepLb(1);
      });
    }
    renderLb();
    overlay.style.display = 'flex';
    requestAnimationFrame(function () { overlay.classList.add('open'); });
    document.body.style.overflow = 'hidden';
  }

  function renderLb() {
    var it = lbItems[lbIndex];
    if (!it) return;
    overlay.querySelector('img').src = it.src;
    overlay.querySelector('.lb-caption').textContent = it.caption;
    overlay.querySelector('.lb-prev').style.visibility = lbItems.length > 1 ? 'visible' : 'hidden';
    overlay.querySelector('.lb-next').style.visibility = lbItems.length > 1 ? 'visible' : 'hidden';
  }

  function stepLb(dir) {
    lbIndex = (lbIndex + dir + lbItems.length) % lbItems.length;
    renderLb();
  }

  function closeLb() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(function () { if (overlay) overlay.style.display = 'none'; }, 180);
  }

  /* ---------- AJAX donačítání komentářů ---------- */
  function loadMoreComments() {
    var btn = document.getElementById('load-comments');
    if (!btn) return Promise.resolve(false);
    var item = btn.getAttribute('data-item');
    var offset = btn.getAttribute('data-offset');
    btn.disabled = true;
    return fetch('/ajax/comments?item=' + encodeURIComponent(item) + '&offset=' + encodeURIComponent(offset), {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        document.getElementById('comment-list').insertAdjacentHTML('beforeend', data.html);
        btn.setAttribute('data-offset', data.offset);
        if (data.remaining > 0) {
          btn.disabled = false;
          var rest = btn.querySelector('.load-rest');
          if (rest) rest.textContent = '(zbývá ' + data.remaining + ')';
          return true;
        }
        btn.closest('.comments-more').remove();
        return false;
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Načíst další komentáře (zkusit znovu)';
        return false;
      });
  }

  function initComments() {
    var btn = document.getElementById('load-comments');
    if (btn) {
      btn.addEventListener('click', function () { loadMoreComments(); });
    }

    // prokliky [12] referencí — když kotva ještě není načtená, donačíst komentáře
    document.addEventListener('click', function (ev) {
      var a = ev.target.closest('a.comment-ref');
      if (!a) return;
      var id = (a.getAttribute('href') || '').slice(1);
      if (!id || document.getElementById(id)) return;   // kotva existuje → výchozí chování
      ev.preventDefault();
      (function loadUntilFound() {
        loadMoreComments().then(function (more) {
          var el = document.getElementById(id);
          if (el) {
            el.closest('.comment') && el.closest('.comment').scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState(null, '', '#' + id);
          } else if (more) {
            loadUntilFound();
          }
        });
      })();
    });
  }

  /* ---------- jednoduché zvýraznění kódu v <pre class="codeblock"> ---------- */
  var KEYWORDS = 'function|var|let|const|return|if|else|elseif|for|foreach|while|do|switch|case|break|continue|class|extends|implements|new|echo|print|public|private|protected|static|final|try|catch|finally|throw|use|namespace|require|require_once|include|include_once|true|false|null|this|self|int|string|bool|float|void|array|select|insert|update|delete|from|where|join|left|right|inner|order|by|group|having|limit|and|or|not|as|in|is|def|import|html|head|body|div|span';

  function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function highlightCode() {
    document.querySelectorAll('pre.codeblock code').forEach(function (code) {
      var text = code.textContent;
      if (!text || text.length > 60000) return;
      var re = new RegExp(
        '(\\/\\*[\\s\\S]*?\\*\\/|<!--[\\s\\S]*?-->|(?:^|\\n)\\s*(?:\\/\\/|#)[^\\n]*)' +
        '|(\'(?:[^\'\\\\\\n]|\\\\.)*\'|"(?:[^"\\\\\\n]|\\\\.)*")' +
        '|(<\\/?[a-zA-Z][^<>\\n]*>)' +
        '|\\b(\\d+(?:\\.\\d+)?)\\b' +
        '|\\b(' + KEYWORDS + ')\\b',
        'gi'
      );
      var out = '';
      var last = 0;
      var m;
      while ((m = re.exec(text)) !== null) {
        out += escapeHtml(text.slice(last, m.index));
        var cls = m[1] ? 'tok-com' : m[2] ? 'tok-str' : m[3] ? 'tok-tag' : m[4] ? 'tok-num' : 'tok-kw';
        out += '<span class="' + cls + '">' + escapeHtml(m[0]) + '</span>';
        last = m.index + m[0].length;
      }
      out += escapeHtml(text.slice(last));
      code.innerHTML = out;
    });
  }
})();
