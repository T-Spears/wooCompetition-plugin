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

/**
 * Parse a variety of draw string formats into a Date object.
 * Accepts:
 * - ISO with timezone (2025-12-13T15:00:00Z or 2025-12-13T15:00:00+01:00)
 * - ISO without timezone (2025-12-13T15:00:00) -> treated as UTC
 * - "YYYY-MM-DD HH:MM" -> treated as UTC
 * - Date only "YYYY-MM-DD" -> treated as end of day UTC
 * Returns a Date or null.
 */
function parseDrawToDate(draw) {
    if (!draw) return null;
    var s = String(draw).trim();

    // Quick native parse attempt (handles ISO with timezone)
    var d = new Date(s);
    if (!isNaN(d)) return d;

    // "YYYY-MM-DD HH:MM(:SS)?" -> convert to YYYY-MM-DDTHH:MM(:SS)Z
    if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/.test(s)) {
        try {
            return new Date(s.replace(/\s+/, 'T') + 'Z');
        } catch (e) { /* fallthrough */ }
    }

    // ISO without timezone: "YYYY-MM-DDTHH:MM(:SS)?" -> append Z to treat as UTC
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(s)) {
        try {
            return new Date(s + 'Z');
        } catch (e) { /* fallthrough */ }
    }

    // Date only "YYYY-MM-DD" -> use end of day UTC
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        try {
            return new Date(s + 'T23:59:59Z');
        } catch (e) { /* fallthrough */ }
    }

    // Last attempt: let Date try again; if invalid return null
    d = new Date(s);
    return isNaN(d) ? null : d;
}

function calcRemaining(drawIso) {
    var now = new Date();
    var draw = parseDrawToDate(drawIso);
    if (!draw || isNaN(draw)) {
        return { days: 0, hours: 0, minutes: 0, seconds: 0, totalMs: 0 };
    }
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

