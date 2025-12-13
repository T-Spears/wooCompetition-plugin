document.addEventListener('DOMContentLoaded', function () {
  // Countdown
  document.querySelectorAll('.raff-countdown').forEach(function (el) {
    var draw = el.getAttribute('data-draw');
    if (!draw) return;

    var target;
    // If draw ends with Z or contains timezone, Date will parse as UTC
    if (draw.match(/Z$/) || draw.indexOf('+') !== -1 || (draw.indexOf('-') !== -1 && draw.indexOf('T') !== -1)) {
      target = new Date(draw);
    } else if (draw.indexOf('T') !== -1) {
      // ISO without timezone, treat as UTC
      target = new Date(draw + 'Z');
    } else if (draw.indexOf(' ') !== -1) {
      // "YYYY-MM-DD HH:MM" treat as UTC
      target = new Date(draw.replace(' ', 'T') + 'Z');
    } else {
      // date only
      target = new Date(draw + 'T23:59:59Z');
    }

    var timerEl = el.querySelector('.raff-countdown-timer');
    function tick() {
      var now = new Date();
      var diff = target - now;
      if (diff <= 0) {
        timerEl.textContent = 'Draw time reached';
        return;
      }
      var days = Math.floor(diff / (1000 * 60 * 60 * 24));
      var hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
      var mins = Math.floor((diff / (1000 * 60)) % 60);
      var secs = Math.floor((diff / 1000) % 60);
      timerEl.textContent = (days ? days + 'd ' : '') + hours + 'h ' + mins + 'm ' + secs + 's';
    }
    tick();
    setInterval(tick, 1000);
  });

  // Animate progress bars on load
  document.querySelectorAll('.raff-progress-inner').forEach(function (el) {
    var w = el.style.width || '0%';
    el.style.width = '0%';
    setTimeout(function () {
      el.style.width = w;
    }, 80);
  });
});

