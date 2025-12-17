document.addEventListener('DOMContentLoaded', function () {
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

