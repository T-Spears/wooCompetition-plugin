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

// Minimal DOM ready (works without jQuery guarantee)
function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
}

function pad(n) { return String(n).padStart(2, '0'); }

function calcRemaining(drawIso) {
    var now = new Date();
    var draw = new Date(drawIso);
    var diff = Math.max(0, draw - now); // ms
    var secs = Math.floor(diff / 1000);
    var days = Math.floor(secs / 86400); secs -= days * 86400;
    var hours = Math.floor(secs / 3600); secs -= hours * 3600;
    var minutes = Math.floor(secs / 60); secs -= minutes * 60;
    var seconds = secs;
    return { days: days, hours: hours, minutes: minutes, seconds: seconds, totalMs: diff };
}

function flipUnit(unitEl, value) {
    var num = unitEl.querySelector('.number');
    if (!num) return;
    var cur = num.querySelector('.current');
    var next = num.querySelector('.next');
    if (!cur || !next) return;
    var newVal = pad(value);
    if (cur.textContent === newVal) return; // no change
    next.textContent = newVal;
    // trigger flip
    num.classList.remove('flip'); // reset in case
    // force reflow
    void num.offsetWidth;
    num.classList.add('flip');
    // after animation, set current to new value and remove flip
    setTimeout(function () {
        cur.textContent = newVal;
        num.classList.remove('flip');
    }, 600); // matches CSS animation duration
}

function updateCountdown(container) {
    var drawIso = container.getAttribute('data-draw');
    if (!drawIso) return;
    var rem = calcRemaining(drawIso);
    flipUnit(container.querySelector('[data-unit="days"]'), rem.days);
    flipUnit(container.querySelector('[data-unit="hours"]'), rem.hours);
    flipUnit(container.querySelector('[data-unit="minutes"]'), rem.minutes);
    flipUnit(container.querySelector('[data-unit="seconds"]'), rem.seconds);
    // Optionally hide seconds when over
    if (rem.totalMs <= 0) {
        container.classList.add('raff-countdown-ended');
    }
}

ready(function () {
    var containers = Array.prototype.slice.call(document.querySelectorAll('.raff-countdown[data-draw]'));
    if (!containers.length) return;
    // initial update
    containers.forEach(function (c) { updateCountdown(c); });
    // tick every second
    setInterval(function () {
        containers.forEach(function (c) { updateCountdown(c); });
    }, 1000);
});

