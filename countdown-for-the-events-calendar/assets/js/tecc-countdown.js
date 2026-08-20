/**
 * tecc-countdown.js — cache-safe tri-state countdown runtime.
 *
 * Contract: the server emits ABSOLUTE UTC epoch seconds on each instance root
 * (data-tecc-start / data-tecc-end); the client computes all remaining time
 * from Date.now(). Nothing time-relative is baked into the HTML, so the timer
 * survives full-page caching — the server's data-tecc-state is only a first
 * guess and is always recomputed from the epochs at init.
 *
 * States: now < start = "before" (ticking box); start <= now < end = "during"
 * ("Happening now" message); now >= end = "after" (roll over to the next
 * occurrence from data-tecc-next, else "Ended" message).
 *
 * Pure math/state helpers are exported for the Node harness; the DOM layer
 * only runs in browsers.
 * ES5 only: no jQuery, no arrow functions, no let/const.
 */
(function () {
  'use strict';

  /* ---------- Pure helpers (Node-testable, no DOM) ---------- */

  /**
   * Zero-pad a number to at least 2 digits. Never truncates (123 -> "123").
   */
  function pad2(n) {
    n = parseInt(n, 10);
    if (isNaN(n) || n < 0) {
      n = 0;
    }
    return (n < 10 ? '0' : '') + n;
  }

  /**
   * Raw (numeric) days/hours/minutes/seconds remaining until `target`.
   * Both arguments are UTC epoch seconds; remaining is floored at 0.
   */
  function computeUnits(target, now) {
    var remaining = Math.floor(target - now);
    if (remaining < 0) {
      remaining = 0;
    }
    return {
      days: Math.floor(remaining / 86400),
      hours: Math.floor((remaining % 86400) / 3600),
      minutes: Math.floor((remaining % 3600) / 60),
      seconds: Math.floor(remaining % 60)
    };
  }

  /**
   * Zero-padded display digits until `target`.
   */
  function computeDigits(target, now) {
    var u = computeUnits(target, now);
    return {
      days: pad2(u.days),
      hours: pad2(u.hours),
      minutes: pad2(u.minutes),
      seconds: pad2(u.seconds)
    };
  }

  /**
   * Ring fill fraction (0..1) for one unit. Seconds/minutes over 60, hours
   * over 24, days over the baked total (fallback 30).
   */
  function ringFraction(unit, value, totalDays) {
    value = parseInt(value, 10) || 0;
    if ('seconds' === unit || 'minutes' === unit) {
      return Math.max(0, Math.min(1, value / 60));
    }
    if ('hours' === unit) {
      return Math.max(0, Math.min(1, value / 24));
    }
    if ('days' === unit) {
      var total = parseInt(totalDays, 10);
      if (!total || total < 1) {
        total = 30;
      }
      return Math.max(0, Math.min(1, value / total));
    }
    return 0;
  }

  /**
   * Resolve the tri-state from absolute epochs. now==start -> "during",
   * now==end -> "after".
   */
  function resolveState(start, end, now) {
    if (now < start) {
      return 'before';
    }
    if (now < end) {
      return 'during';
    }
    return 'after';
  }

  /**
   * Pop the next still-relevant occurrence off the queue (mutates it).
   * Skips entries that already ended or are malformed. Returns
   * {s, e, title, link, allDay} or null when nothing usable remains.
   */
  function advanceQueue(queue, now) {
    while (queue && queue.length) {
      var item = queue.shift();
      var s = item ? parseInt(item.s, 10) : NaN;
      var e = item ? parseInt(item.e, 10) : NaN;
      if (!isNaN(s) && !isNaN(e) && e > now) {
        return {
          s: s,
          e: e,
          title: item.title,
          link: item.link,
          allDay: !!(item && (item.a === 1 || item.a === '1' || item.a === true))
        };
      }
    }
    return null;
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
      pad2: pad2,
      computeUnits: computeUnits,
      computeDigits: computeDigits,
      ringFraction: ringFraction,
      resolveState: resolveState,
      advanceQueue: advanceQueue
    };
  }

  if (typeof document === 'undefined' || typeof window === 'undefined') {
    return; /* Node / non-browser: pure helpers only. */
  }

  /* ---------- DOM layer ---------- */

  var instances = [];
  var observer = null;
  var timerStarted = false;

  if (typeof IntersectionObserver !== 'undefined') {
    observer = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        for (var j = 0; j < instances.length; j++) {
          if (instances[j].root === entries[i].target) {
            instances[j].visible = entries[i].isIntersecting;
          }
        }
      }
    });
  }

  function parseJsonAttr(el, name, fallback) {
    var raw = el.getAttribute(name);
    if (!raw) {
      return fallback;
    }
    try {
      var parsed = JSON.parse(raw);
      return parsed === null ? fallback : parsed;
    } catch (err) {
      return fallback;
    }
  }

  function buildInstance(root) {
    if (root.getAttribute('data-tecc-init') === '1') {
      return null;
    }
    var start = parseInt(root.getAttribute('data-tecc-start'), 10);
    var end = parseInt(root.getAttribute('data-tecc-end'), 10);
    var region = root.querySelector('[data-tecc-region]');
    if (isNaN(start) || isNaN(end) || !region) {
      return null;
    }
    root.setAttribute('data-tecc-init', '1');
    var queue = parseJsonAttr(root, 'data-tecc-next', []);
    if (Object.prototype.toString.call(queue) !== '[object Array]') {
      queue = [];
    }
    /* Cache the ticking-box markup for rollover. Before-state renders carry
       live [data-tecc-unit] spans in the region; during/after renders (which
       may be full-page-cached with a stale state) instead carry the box in an
       inert <template data-tecc-box> so rollover still has markup to restore. */
    var boxHtml = '';
    if (region.querySelector('[data-tecc-unit]')) {
      boxHtml = region.innerHTML;
    } else {
      var boxTpl = region.querySelector('template[data-tecc-box]');
      if (boxTpl) {
        boxHtml = boxTpl.innerHTML;
      }
    }
    return {
      root: root,
      region: region,
      start: start,
      end: end,
      labels: parseJsonAttr(root, 'data-tecc-labels', {}),
      queue: queue,
      allDay: root.getAttribute('data-tecc-allday') === '1',
      ongoing: root.getAttribute('data-tecc-ongoing') === '1',
      totalDays: root.getAttribute('data-tecc-total-days'),
      boxHtml: boxHtml,
      state: '',
      visible: true
    };
  }

  /* XSS safety: message divs are built with createElement + textContent. */
  function renderMessage(inst, text, cls) {
    var div = document.createElement('div');
    div.className = String(cls || '');
    div.textContent = String(text || '');
    inst.region.innerHTML = '';
    inst.region.appendChild(div);
  }

  /**
   * Keep every unit wrap visible — hiding H/M/S for all-day events made
   * them look broken vs timed ones. window.TECC_DEBUG = true logs all-day flags.
   */
  function applyAllDay(inst) {
    var wraps = inst.region.querySelectorAll('[data-tecc-unit-wrap]');
    var i;
    for (i = 0; i < wraps.length; i++) {
      wraps[i].hidden = false;
    }
    if (window.TECC_DEBUG && window.console && console.debug) {
      console.debug('[tecc] units visible', {
        allDay: !!inst.allDay,
        start: inst.start,
        end: inst.end,
        units: wraps.length
      });
    }
  }

  function toggleUpcoming(inst, state) {
    var els = inst.root.querySelectorAll('[data-tecc-upcoming-only]');
    for (var i = 0; i < els.length; i++) {
      els[i].hidden = 'before' !== state;
    }
  }

  function updateDigitsTo(inst, target, now) {
    var digits = computeDigits(target, now);
    var spans = inst.region.querySelectorAll('[data-tecc-unit]');
    for (var i = 0; i < spans.length; i++) {
      var unit = spans[i].getAttribute('data-tecc-unit');
      if (Object.prototype.hasOwnProperty.call(digits, unit)) {
        spans[i].textContent = digits[unit];
      }
    }
    updateRings(inst, target, now);
  }

  function updateDigits(inst, now) {
    updateDigitsTo(inst, inst.start, now);
  }

  /* Ring skin: write the 0..1 fill fraction into each ring's --tecc-ring. */
  function updateRings(inst, target, now) {
    var rings = inst.region.querySelectorAll('[data-tecc-ring]');
    if (!rings.length) {
      return;
    }
    var units = computeUnits(target, now);
    for (var i = 0; i < rings.length; i++) {
      var unit = rings[i].getAttribute('data-tecc-ring');
      var value = Object.prototype.hasOwnProperty.call(units, unit) ? units[unit] : 0;
      rings[i].style.setProperty('--tecc-ring', String(ringFraction(unit, value, inst.totalDays)));
    }
  }

  /* State-driven kicker text (before -> title, during -> ongoing, after ->
     ended) + the ENDS-IN sublabel, when the template provides them. */
  function setKicker(inst, state) {
    var kicker = inst.root.querySelector('[data-tecc-kicker]');
    if (!kicker) {
      return;
    }
    var text = ('before' === state) ? inst.labels.title
      : (('during' === state) ? inst.labels.ongoing : inst.labels.ended);
    if (typeof text === 'string' && text) {
      kicker.textContent = text;
    }
  }

  /* Timer eyebrow (Begins in / Ends in): "Begins in" before start, "Ends in"
     during an ongoing event, hidden otherwise. A no-op when the template didn't
     render the element (the show-timer-label toggle is off). */
  function applyTimerLabel(inst, state) {
    var el = inst.root.querySelector('[data-tecc-endsin]');
    if (!el) {
      return;
    }
    var text = '';
    if ('before' === state) {
      text = inst.labels.beginsIn || '';
    } else if ('during' === state && inst.ongoing) {
      text = inst.labels.endsIn || '';
    }
    if (text) {
      el.textContent = text;
      el.hidden = false;
    } else {
      el.hidden = true;
    }
  }

  function restoreBox(inst) {
    if (inst.boxHtml && !inst.region.querySelector('[data-tecc-unit]')) {
      inst.region.innerHTML = inst.boxHtml;
    }
  }

  function setText(root, selector, value) {
    if (typeof value !== 'string') {
      return;
    }
    var els = root.querySelectorAll(selector);
    for (var i = 0; i < els.length; i++) {
      if ('[data-tecc-link]' === selector) {
        els[i].setAttribute('href', value);
      } else {
        els[i].textContent = value;
      }
    }
  }

  /**
   * Advance to the next occurrence, restore the cached ticking box.
   * Returns false (graceful skip) when no box markup was ever cached.
   */
  function rollOver(inst, now) {
    if (!inst.boxHtml) {
      return false;
    }
    var next = advanceQueue(inst.queue, now);
    if (!next) {
      return false;
    }
    inst.start = next.s;
    inst.end = next.e;
    inst.allDay = !!next.allDay;
    if (inst.allDay) {
      inst.root.setAttribute('data-tecc-allday', '1');
    } else {
      inst.root.removeAttribute('data-tecc-allday');
    }
    setText(inst.root, '[data-tecc-title]', next.title);
    setText(inst.root, '[data-tecc-link]', next.link);
    inst.region.innerHTML = inst.boxHtml;
    applyAllDay(inst);
    return true;
  }

  function applyState(inst, state, now) {
    inst.state = state;
    inst.root.setAttribute('data-tecc-state', state);
    toggleUpcoming(inst, state);
    setKicker(inst, state);
    applyTimerLabel(inst, state);

    if ('before' === state) {
      restoreBox(inst);
      applyAllDay(inst);
      updateDigitsTo(inst, inst.start, now);
    } else if ('during' === state && inst.ongoing) {
      // In-progress: keep the box and count down to the END with the ENDS IN
      // eyebrow, instead of freezing on a "Happening now" message.
      restoreBox(inst);
      applyAllDay(inst);
      updateDigitsTo(inst, inst.end, now);
    } else if ('during' === state) {
      renderMessage(inst, inst.labels.now, inst.labels.nowClass);
    } else {
      renderMessage(inst, inst.labels.ended, inst.labels.endedClass);
    }
  }

  function tickInstance(inst, now) {
    var state = resolveState(inst.start, inst.end, now);
    if ('after' === state && rollOver(inst, now)) {
      state = resolveState(inst.start, inst.end, now);
    }
    if (state !== inst.state) {
      applyState(inst, state, now); /* Transitions run even off-screen. */
    } else if (inst.visible) {
      /* Digit-only updates skip hidden instances. */
      if ('before' === state) {
        updateDigitsTo(inst, inst.start, now);
      } else if ('during' === state && inst.ongoing) {
        updateDigitsTo(inst, inst.end, now);
      }
    }
  }

  /* One shared 1s driver for every instance; a broken one can't kill it. */
  function tickAll() {
    var now = Math.floor(Date.now() / 1000);
    for (var i = instances.length - 1; i >= 0; i--) {
      try {
        /* Prune roots removed from the DOM (e.g. replaced admin previews)
           so repeated preview refreshes cannot grow the array unbounded. */
        if (instances[i].root.isConnected === false) {
          if (observer) {
            observer.unobserve(instances[i].root);
          }
          instances.splice(i, 1);
          continue;
        }
        tickInstance(instances[i], now);
      } catch (err) {
        /* Ignore: keep the other instances ticking. */
      }
    }
  }

  function scan(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var roots = scope.querySelectorAll('[data-tecc="1"]');
    for (var i = 0; i < roots.length; i++) {
      var inst = buildInstance(roots[i]);
      if (inst) {
        instances.push(inst);
        if (observer) {
          observer.observe(inst.root);
        }
      }
    }
    if (instances.length) {
      tickAll();
      if (!timerStarted) {
        timerStarted = true;
        setInterval(tickAll, 1000);
      }
    }
  }

  /* Public hook: the admin live preview injects fresh instances and
     rescans. buildInstance() is idempotent (data-tecc-init marker). */
  window.teccCountdown = { scan: scan };

  if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }
})();
