(function () {
  if (typeof window === 'undefined') return;

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$ (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  function getValById(id) {
    var el = document.getElementById(id);
    if (!el) return '';
    return el.value || el.textContent || '';
  }

  function getPrice() {
    // WooCommerce regular price field common selectors
    var el = document.querySelector('input[name="_regular_price"], #_regular_price, input[name="_price"]');
    return el ? el.value : '';
  }

  function getFeaturedImageSrc() {
    // Try to find featured image preview
    var img = document.querySelector('#postimagediv img, .attachment-thumbnail img');
    if (img && img.src) return img.src;
    return '';
  }

  function calcPercent(cap, next) {
    cap = parseInt(cap,10) || 0;
    next = parseInt(next,10) || 1;
    if (cap < 1) {
      // approximate cap using next if cap unset
      return 0;
    }
    var sold = Math.max(0, (next - 1));
    var pct = Math.round((sold / cap) * 100);
    if (!isFinite(pct) || pct < 0) pct = 0;
    if (pct > 100) pct = 100;
    return pct;
  }

  function renderCardPreview() {
    var container = document.getElementById('raffall-card-preview-inner');
    if (!container) return;

    var title = document.getElementById('title') ? document.getElementById('title').value : (document.querySelector('#post-title-0') ? document.querySelector('#post-title-0').textContent : 'Product title');
    var price = getPrice() || '—';
    var cap = document.getElementById('_raff_ticket_cap') ? document.getElementById('_raff_ticket_cap').value : '';
    var next = document.getElementById('_raff_next_ticket') ? document.getElementById('_raff_next_ticket').value : '';
    var image = getFeaturedImageSrc() || '';
    var instant = document.getElementById('_raff_is_instant_win') && document.getElementById('_raff_is_instant_win').checked;
    var freeinfo = document.getElementById('_raff_is_free_entry') && document.getElementById('_raff_is_free_entry').checked;

    var pct = calcPercent(cap, next);

    // Build HTML
    var imgHtml = image ? '<div class="raff-card-thumb"><img src="' + image + '" alt=""></div>' : '<div class="raff-card-thumb"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="24" rx="4" fill="#eee"/></svg></div>';
    var meta = (cap ? pct + '% sold (' + Math.max(0,next-1) + '/' + cap + ')' : 'Tickets: —');
    var instBadge = instant ? '<span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:999px;background:rgba(123,60,255,0.12);color:var(--raff-fill,#7b3cff);font-weight:600;font-size:12px;">Instant</span>' : '';
    var freeBadge = freeinfo ? '<span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:999px;background:#f1f1f1;color:#333;font-weight:600;font-size:12px;">Free entry</span>' : '';

    var html = '<div class="raff-card">';
    html += imgHtml;
    html += '<div class="raff-card-body">';
    html += '<div class="raff-card-title">' + escapeHtml(title) + '</div>';
    html += '<div class="raff-card-meta">' + escapeHtml(meta) + instBadge + freeBadge + '</div>';
    html += '<div style="display:flex;align-items:center;gap:12px;">';
    html += '<div style="font-weight:700;font-size:16px;">' + escapeHtml(price) + '</div>';
    html += '<div style="flex:1 1 auto;"><div class="raff-progress" aria-hidden="true"><span class="raff-progress-inner" style="width:' + pct + '%;"></span></div></div>';
    html += '</div>';
    html += '</div></div>';

    container.innerHTML = html;

    // Apply colors from localized data (if present)
    if (window.raffAllCardPreviewData) {
      var root = container.closest('#raffall-card-preview');
      if (root) {
        root.style.setProperty('--raff-fill', window.raffAllCardPreviewData.fill_color || '#7b3cff');
        root.style.setProperty('--raff-bg', window.raffAllCardPreviewData.bg_color || '#f1f1f1');
      }
    }
  }

  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; }); }

  document.addEventListener('DOMContentLoaded', function () {
    // watch for common fields: title, price, draw, cap, next, checkboxes
    var ids = ['title','_regular_price','_price','_raff_ticket_cap','_raff_next_ticket','_raff_is_instant_win','_raff_is_free_entry'];
    ids.forEach(function (id) {
      var el = document.getElementById(id) || document.querySelector('input[name="' + id + '"]');
      if (el) {
        el.addEventListener('input', renderCardPreview);
        el.addEventListener('change', renderCardPreview);
      }
    });

    // also update when featured image changes (watch the featured image container)
    var postImageDiv = document.getElementById('postimagediv');
    if (postImageDiv) {
      // mutation observer to detect image changes
      var mo = new MutationObserver(function () { renderCardPreview(); });
      mo.observe(postImageDiv, { childList: true, subtree: true, attributes: true });
    }

    // initial render
    renderCardPreview();
    // update every 2s to catch changes from WP UI that may not fire input events
    setInterval(renderCardPreview, 2000);
  });
})();
