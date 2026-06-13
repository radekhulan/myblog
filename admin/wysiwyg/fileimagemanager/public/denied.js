(function () {
    function findOurIframeIn(win) {
        try {
            if (win === window.parent && window.frameElement) return window.frameElement;
            var iframes = win.document.getElementsByTagName('iframe');
            for (var i = 0; i < iframes.length; i++) {
                try { if (iframes[i].contentWindow === window) return iframes[i]; } catch (e) {}
            }
        } catch (e) {}
        return null;
    }

    function closeViaDom(win) {
        var iframe = findOurIframeIn(win);
        if (!iframe) return false;
        var el = iframe;
        while (el && el.parentNode) {
            if (el.classList && el.classList.contains('tox-dialog')) break;
            el = el.parentNode;
        }
        if (!el || !el.classList || !el.classList.contains('tox-dialog')) return false;
        var btn = el.querySelector(
            'button[aria-label="Close"], button[title="Close"], .tox-button[aria-label="Close"], .tox-button--icon[aria-label="Close"]'
        );
        if (btn) { btn.click(); return true; }
        return false;
    }

    function closeViaApi(win) {
        try {
            if (!win.tinymce) return false;
            var editors = win.tinymce.editors || [];
            for (var i = 0; i < editors.length; i++) {
                var ed = editors[i];
                try {
                    if (ed && ed.windowManager && ed.windowManager.getWindows && ed.windowManager.getWindows().length) {
                        ed.windowManager.close();
                        return true;
                    }
                } catch (e) {}
            }
            if (win.tinymce.activeEditor && win.tinymce.activeEditor.windowManager) {
                win.tinymce.activeEditor.windowManager.close();
                return true;
            }
        } catch (e) {}
        return false;
    }

    function closeDialog() {
        var w = window;
        for (var i = 0; i < 6; i++) {
            if (!w) break;
            try {
                if (closeViaDom(w)) return;
                if (closeViaApi(w)) return;
            } catch (e) {}
            if (w === w.parent) break;
            w = w.parent;
        }

        try {
            if (window.parent && window.parent.CKEDITOR && window.parent.CKEDITOR.dialog) {
                var d = window.parent.CKEDITOR.dialog.getCurrent();
                if (d) { d.hide(); return; }
            }
        } catch (e) {}

        try { if (window.opener && !window.opener.closed) { window.close(); return; } } catch (e) {}

        try { history.back(); } catch (e) {}
        setTimeout(function () { try { window.close(); } catch (e) {} }, 100);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('rfmCloseBtn');
        if (btn) btn.addEventListener('click', closeDialog);
    });
})();
