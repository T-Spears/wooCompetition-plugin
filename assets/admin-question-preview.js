(function () {
  if (typeof window === 'undefined') return;
  document.addEventListener('DOMContentLoaded', function () {
    var previewRoot = document.getElementById('raffall-question-preview-inner');
    if (!previewRoot) return;

    // Helper to read current inputs (fallback to empty)
    function getField(id) {
      var el = document.getElementById(id);
      return el ? el.value.trim() : '';
    }

    function readOptions() {
      return [
        getField('_raff_validation_option_1'),
        getField('_raff_validation_option_2'),
        getField('_raff_validation_option_3')
      ].filter(Boolean);
    }

    function getStyle() {
      // prefer per-product meta select, then fall back to global setting (select id raffall_question_style)
      var p = document.getElementById('_raff_question_style');
      if (p && p.value) return p.value;
      var g = document.getElementById('raffall_question_style');
      if (g && g.value) return g.value;
      return (window.raffAllPreviewData && window.raffAllPreviewData.style) || 'radios';
    }

    // Apply CSS variables from localized preview data to preview root's parent so styles pick them up
    var previewContainer = document.getElementById('raffall-question-preview');
    if (previewContainer && window.raffAllPreviewData) {
      previewContainer.style.setProperty('--raff-question-btn-bg', window.raffAllPreviewData.btn_bg || '#fff');
      previewContainer.style.setProperty('--raff-question-btn-text', window.raffAllPreviewData.btn_text || '#222');
      previewContainer.style.setProperty('--raff-question-btn-bg-active', window.raffAllPreviewData.btn_bg_active || '#7b3cff');
    }

    function renderPreview() {
      var qtext = getField('_raff_validation_question') || 'Sample question: Which option is correct?';
      var options = readOptions();
      var style = getStyle();

      // Build markup according to style
      var html = '<div class="raff-preview-question" style="margin-bottom:8px;font-weight:600;">' + escapeHtml(qtext) + '</div>';

      if (options.length === 0) {
        html += '<div style="color:#666;font-size:13px;">No options configured yet.</div>';
      } else if (style === 'dropdown') {
        html += '<select disabled style="padding:8px;border-radius:6px;border:1px solid #ddd;">';
        html += '<option>' + (window.raffAllPreviewData ? window.raffAllPreviewData.text_strings.select_placeholder : 'Select') + '</option>';
        options.forEach(function (o) { html += '<option>' + escapeHtml(o) + '</option>'; });
        html += '</select>';
      } else if (style === 'buttons') {
        html += '<div class="raff-question-buttons" role="radiogroup" aria-disabled="true" style="display:flex;gap:8px;flex-wrap:wrap;">';
        options.forEach(function (o, i) {
          html += '<button type="button" class="raff-question-btn" aria-pressed="false" tabindex="-1" style="pointer-events:none;">' + escapeHtml(o) + '</button>';
        });
        html += '</div>';
      } else {
        // radios
        html += '<div>';
        options.forEach(function (o, i) {
          html += '<label style="display:block;margin-bottom:6px;"><input type="radio" disabled> ' + escapeHtml(o) + '</label>';
        });
        html += '</div>';
      }

      previewRoot.innerHTML = html;
    }

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; });
    }

    // Wire events: update preview when admin edits question/options/style
    var inputs = ['_raff_validation_question','_raff_validation_option_1','_raff_validation_option_2','_raff_validation_option_3','_raff_question_style'];
    inputs.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', renderPreview);
    });
    var styleEl = document.querySelector('#raffall_question_style');
    if (styleEl) styleEl.addEventListener('change', function () {
      // when style changed in settings page, also update global preview CSS vars if needed
      renderPreview();
    });

    // initial render
    renderPreview();
  });
})();
