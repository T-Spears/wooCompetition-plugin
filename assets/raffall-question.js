document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.raff-question-buttons').forEach(function (group) {
    var hidden = group.closest('.raff-question-buttons-wrapper') ? group.closest('.raff-question-buttons-wrapper').querySelector('.raff-choice-hidden') : group.querySelector('.raff-choice-hidden');
    var buttons = Array.prototype.slice.call(group.querySelectorAll('.raff-question-btn'));

    function setSelected(btn) {
      buttons.forEach(function (b) {
        b.classList.remove('selected');
        b.setAttribute('aria-pressed', 'false');
      });
      if (btn) {
        btn.classList.add('selected');
        btn.setAttribute('aria-pressed', 'true');
        var value = btn.getAttribute('data-value') || '';
        if (hidden) hidden.value = value;
        // also check the matching hidden radio (if present)
        var radio = document.querySelector('input[name="raff_choice"][value="' + value + '"]');
        if (radio) {
          radio.checked = true;
          // trigger change for any listeners
          var evt = new Event('change', { bubbles: true });
          radio.dispatchEvent(evt);
        }
      }
    }

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        setSelected(btn);
      });
      btn.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          setSelected(btn);
          btn.focus();
        }
        // left/right arrows navigate
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
          e.preventDefault();
          var idx = buttons.indexOf(btn);
          var next = buttons[(idx + 1) % buttons.length];
          next.focus();
        }
        if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
          e.preventDefault();
          var idx = buttons.indexOf(btn);
          var prev = buttons[(idx - 1 + buttons.length) % buttons.length];
          prev.focus();
        }
      });
    });

    // ensure form submit visual cue if nothing selected
    var form = group.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        if (hidden && !hidden.value) {
          group.classList.add('raff-question-missing');
          setTimeout(function () { group.classList.remove('raff-question-missing'); }, 900);
        }
      });
    }
  });
});
