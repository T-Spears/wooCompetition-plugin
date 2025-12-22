(function () {
    if (typeof window === 'undefined') return;
    var conf = window.raffAllCartSidebar || {};
    var storageKey = 'raffall_cart_sidebar_settings_v1';

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    function loadSettings() {
        try {
            var s = localStorage.getItem(storageKey);
            if (!s) return null;
            return JSON.parse(s);
        } catch (e) { return null; }
    }
    function saveSettings(obj) {
        try { localStorage.setItem(storageKey, JSON.stringify(obj)); } catch (e) {}
    }

    function applySettings(s) {
        var root = document.documentElement;
        if (!s) return;
        if (s.bg) root.style.setProperty('--raff-cart-bg', s.bg);
        if (s.accent) root.style.setProperty('--raff-cart-accent', s.accent);
        if (s.text) root.style.setProperty('--raff-cart-text', s.text);
        if (s.width) {
            var px = parseInt(s.width, 10);
            document.querySelectorAll('.raffall-cart-panel').forEach(function (el) {
                el.style.width = px + 'px';
            });
        }
        if (s.position === 'left') {
            document.querySelectorAll('.raffall-cart-panel').forEach(function (el) {
                el.style.right = 'auto'; el.style.left = '0';
            });
        } else {
            document.querySelectorAll('.raffall-cart-panel').forEach(function (el) {
                el.style.left = 'auto'; el.style.right = '0';
            });
        }
    }

    function initCustomiser(root) {
        var toggle = $('.raffall-cart-customiser-toggle', root);
        var panel = $('.raffall-cart-customiser-panel', root);
        if (!toggle || !panel) return;
        toggle.addEventListener('click', function () {
            var open = panel.getAttribute('aria-hidden') === 'false';
            panel.setAttribute('aria-hidden', open ? 'true' : 'false');
        });

        $all('[data-custom]', panel).forEach(function (input) {
            input.addEventListener('input', function () {
                var s = loadSettings() || {};
                var key = input.getAttribute('data-custom');
                s[key] = input.value;
                saveSettings(s);
                applySettings(s);
            });
        });

        var reset = $('.raffall-cart-customiser-reset', panel);
        if (reset) reset.addEventListener('click', function () {
            localStorage.removeItem(storageKey);
            var defaults = { bg: '#ffffff', accent: '#7b3cff', text: '#222222', width: 360, position: 'right' };
            saveSettings(defaults);
            // reflect inputs
            $all('[data-custom]', panel).forEach(function (i) {
                var k = i.getAttribute('data-custom');
                if (k && defaults[k] !== undefined) i.value = defaults[k];
            });
            applySettings(defaults);
        });
    }

    function initSidebar() {
        var root = document.getElementById('raffall-cart-sidebar');
        if (!root) return;
        var overlay = root.querySelector('.raffall-cart-overlay');
        var panel = root.querySelector('.raffall-cart-panel');
        var openBtn = root.querySelector('.raffall-cart-toggle');
        var closeBtn = root.querySelector('.raffall-cart-close');

        function open() { root.setAttribute('data-open','true'); root.setAttribute('aria-hidden','false'); document.body.classList.add('raffall-cart-open'); }
        function close(){ root.setAttribute('data-open','false'); root.setAttribute('aria-hidden','true'); document.body.classList.remove('raffall-cart-open'); }

        overlay && overlay.addEventListener('click', close);
        closeBtn && closeBtn.addEventListener('click', close);
        openBtn && openBtn.addEventListener('click', function () {
            open();
        });

        // customiser
        initCustomiser(root);

        // apply saved settings
        var s = loadSettings();
        if (!s) {
            s = { bg: getComputedStyle(document.documentElement).getPropertyValue('--raff-cart-bg') || '#ffffff',
                  accent: getComputedStyle(document.documentElement).getPropertyValue('--raff-cart-accent') || '#7b3cff',
                  text: getComputedStyle(document.documentElement).getPropertyValue('--raff-cart-text') || '#222222',
                  width: 360,
                  position: 'right' };
            saveSettings(s);
        }
        // sync control values if present
        var panelControls = root.querySelector('.raffall-cart-customiser-panel');
        if (panelControls) {
            $all('[data-custom]', panelControls).forEach(function (i) {
                var k = i.getAttribute('data-custom');
                if (s[k] !== undefined) i.value = s[k];
            });
        }
        applySettings(s);

        // listen for WC cart changes (if fragments are used)
        document.body.addEventListener('wc_fragments_refreshed', function () {
            // a simple, non-invasive approach: reload page fragments are handled by WC; we leave server rendering intact.
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // if sidebar enabled (server-side flag), initialize
        if (conf.enabled === false) return;
        initSidebar();
    });

    // expose small API
    window.RaffAllCartSidebar = {
        open: function () { var r = document.getElementById('raffall-cart-sidebar'); if (r) r.querySelector('.raffall-cart-toggle').click(); },
        close: function () { var r = document.getElementById('raffall-cart-sidebar'); if (r) r.querySelector('.raffall-cart-close').click(); },
        getSettings: function () { return loadSettings(); },
        applySettings: function (s) { saveSettings(s); applySettings(s); }
    };

    (function () {
        if (typeof window === 'undefined') return;

        function qs(sel, ctx){ return (ctx || document).querySelector(sel); }
        function qsa(sel, ctx){ return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

        document.addEventListener('DOMContentLoaded', function () {
            var root = qs('#raffall-cart-sidebar') || qs('.raffall-cart-sidebar');
            if (!root) return;

            var toggle = qs('.raffall-cart-toggle', root) || document.querySelector('.raffall-cart-toggle');
            var overlay = qs('.raffall-cart-overlay', root);
            var closeBtn = qs('.raffall-cart-close', root);
            var panel = qs('.raffall-cart-panel', root);

            function isOpen(){ return root && root.getAttribute('data-open') === 'true'; }
            function open() {
              if (!root) return;
              root.setAttribute('data-open','true');
              root.setAttribute('aria-hidden','false');
              // trap focus on panel
              if (panel) panel.focus();
              document.body.classList.add('raffall-cart-open');
            }
            function close() {
              if (!root) return;
              root.setAttribute('data-open','false');
              root.setAttribute('aria-hidden','true');
              document.body.classList.remove('raffall-cart-open');
              if (toggle) toggle.focus();
            }
            function toggleOpen() { if (isOpen()) close(); else open(); }

            if (toggle) {
              toggle.addEventListener('click', function (e) { e.preventDefault(); toggleOpen(); });
            }
            if (overlay) {
              overlay.addEventListener('click', function (e) { e.preventDefault(); close(); });
            }
            if (closeBtn) {
              closeBtn.addEventListener('click', function (e) { e.preventDefault(); close(); });
            }

            // close on Esc
            document.addEventListener('keydown', function (e) {
              if (e.key === 'Escape' && isOpen()) {
                close();
              }
            });

            // If cart items change (e.g. via AJAX) update count badge when possible
            function refreshCount() {
              try {
                var countElem = qs('.raffall-cart-toggle-count', toggle || document);
                if (!countElem) return;
                // attempt to read WooCommerce cart count global if present
                var cnt = 0;
                if (window.wc_cart_fragments_params && wc_cart_fragments_params) {
                  // fall back to existing DOM cart count in header if present
                }
                // if there is a cart count element in DOM with class .raffall-cart-count use that
                var globalCount = qs('.raffall-cart-count');
                if (globalCount) cnt = parseInt(globalCount.textContent.replace(/\D/g,'')) || 0;
                countElem.textContent = cnt;
              } catch (e) { /* silent */ }
            }

            // initial refresh
            setTimeout(refreshCount, 50);

            // expose for other scripts
            window.raffallCartSidebar = {
              open: open,
              close: close,
              toggle: toggleOpen,
              refreshCount: refreshCount
            };
        });
    })();
})();
