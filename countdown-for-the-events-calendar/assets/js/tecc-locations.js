/**
 * tecc-locations.js — client-side visibility for auto-display locations
 * (Phase 4 Display Rules).
 *
 * Cache-safety carve-out: the server resolves WHERE a rule
 * matches per URL, but per-visitor conditions (logged_in / mobile) and
 * dismissal must never vary the cached HTML — so they are evaluated here.
 *
 * Each [data-tecc-location] element may carry:
 * - data-tecc-client  JSON {require:[], forbid:[]} with keys 'logged_in'
 *   (body.logged-in class) and 'mobile' (max-width: 782px). The element is
 *   visible only if ALL require conditions pass AND NO forbid condition
 *   matches. Elements with require conditions ship [hidden] and are revealed
 *   here once they qualify.
 * - data-tecc-dismiss  the storage key for this rule's dismissal record.
 * - data-tecc-close    what a close click MEANS, one of:
 *     event    hide until the countdown targets a different event (default)
 *     session  hide until the browser session ends (sessionStorage)
 *     week     hide for seven days
 *     forever  hide permanently
 *   The record is JSON {e,x}: `e` fingerprints the event the visitor
 *   dismissed ('*' = any) and `x` is an expiry in ms (0 = none). The
 *   fingerprint is the rendered card's own data-tecc-start epoch, so no
 *   event ID has to be plumbed through the cache-safe markup. A pre-2.1
 *   record is the bare string '1' (permanent, no event stamp) and is
 *   honoured only under 'forever' — under every other mode it is cleared,
 *   which is exactly the never-comes-back bug this replaces.
 *
 * ES5 only: no jQuery, no arrow functions, no let/const.
 */
(function () {
  'use strict';

  if (typeof document === 'undefined' || typeof window === 'undefined') {
    return; /* Non-browser environment. */
  }

  var MOBILE_QUERY = '(max-width: 782px)';

  /* Layered locations: entries sharing a data-tecc-layer-group are a priority-
     ordered stack for ONE location (a per-visitor-gated winner + a server
     baseline). Only the first eligible entry in the group is shown. */
  var groups = {};

  var DAY_MS = 86400000;

  /* Web storage can throw outright (Safari private mode, blocked storage) —
     every access is guarded and simply degrades to "not dismissed". 'session'
     mode is the only one that uses sessionStorage. */
  function storageArea(mode) {
    try {
      return ('session' === mode) ? window.sessionStorage : window.localStorage;
    } catch (err) {
      return null;
    }
  }

  function storageGet(key, mode) {
    try {
      var area = storageArea(mode);
      return area ? area.getItem(key) : null;
    } catch (err) {
      return null;
    }
  }

  function storageSet(key, value, mode) {
    try {
      var area = storageArea(mode);
      if (area) {
        area.setItem(key, value);
      }
    } catch (err) {
      /* Ignore: dismissal simply won't persist. */
    }
  }

  function storageRemove(key, mode) {
    try {
      var area = storageArea(mode);
      if (area) {
        area.removeItem(key);
      }
    } catch (err) {
      /* Ignore. */
    }
  }

  /* Fingerprint the event currently shown in this location: the first rendered
     start epoch. When the countdown rolls to the next event this changes, which
     is what makes an 'event'-scoped dismissal expire on its own. */
  function eventKey(el) {
    var node = el.querySelector('[data-tecc-start]');
    return node ? String(node.getAttribute('data-tecc-start') || '') : '';
  }

  /* Is a stored dismissal still in force? Clears records that are not. */
  function isDismissed(key, mode, el) {
    var raw = storageGet(key, mode);
    if (!raw) {
      return false;
    }

    if ('1' === raw) {
      /* Pre-2.1 permanent record. Only 'forever' still means forever. */
      if ('forever' === mode) {
        return true;
      }
      storageRemove(key, mode);
      return false;
    }

    var rec;
    try {
      rec = JSON.parse(raw);
    } catch (err) {
      rec = null;
    }
    if (!rec || 'object' !== typeof rec) {
      storageRemove(key, mode);
      return false;
    }

    var expires = Number(rec.x) || 0;
    if (expires > 0 && nowMs() >= expires) {
      storageRemove(key, mode);
      return false;
    }

    var stamped = String(rec.e || '*');
    if ('*' !== stamped && stamped !== eventKey(el)) {
      /* A different event is being counted down now — the visitor never
         dismissed this one. */
      storageRemove(key, mode);
      return false;
    }

    return true;
  }

  function nowMs() {
    return (new Date()).getTime();
  }

  /* Write the record the chosen mode implies. */
  function recordDismissal(key, mode, el) {
    var rec = { e: '*', x: 0 };
    if ('event' === mode) {
      rec.e = eventKey(el) || '*';
    } else if ('week' === mode) {
      rec.x = nowMs() + (7 * DAY_MS);
    }
    storageSet(key, JSON.stringify(rec), mode);
  }

  function conditionMet(name, mql) {
    if ('logged_in' === name) {
      return !!(document.body && document.body.classList && document.body.classList.contains('logged-in'));
    }
    if ('mobile' === name) {
      return !!(mql && mql.matches);
    }
    return false; /* Unknown conditions never pass (conservative). */
  }

  function toStringArray(value) {
    var out = [];
    if (Object.prototype.toString.call(value) === '[object Array]') {
      for (var i = 0; i < value.length; i++) {
        if ('string' === typeof value[i]) {
          out.push(value[i]);
        }
      }
    }
    return out;
  }

  /* Whether this entry's per-visitor conditions pass (require/forbid), IGNORING
     dismissal — used to pick which layer owns the location. */
  function passesConditions(entry) {
    var i;
    for (i = 0; i < entry.require.length; i++) {
      if (!conditionMet(entry.require[i], entry.mql)) {
        return false;
      }
    }
    for (i = 0; i < entry.forbid.length; i++) {
      if (conditionMet(entry.forbid[i], entry.mql)) {
        return false;
      }
    }
    return true;
  }

  /* One display per location: the first layer whose conditions pass OWNS the
     location; if that layer is dismissed the whole location hides (a dismiss
     does not reveal the baseline). A group of one is just "show iff eligible".*/
  function applyGroup(id) {
    var list = groups[id] || [];
    var chosen = null;
    var i;
    for (i = 0; i < list.length; i++) {
      if (passesConditions(list[i])) {
        chosen = list[i];
        break;
      }
    }
    for (i = 0; i < list.length; i++) {
      var show = list[i] === chosen && ! chosen.dismissed;
      if (show) {
        list[i].el.removeAttribute('hidden');
      } else {
        list[i].el.setAttribute('hidden', 'hidden');
      }
    }
    syncBars();
  }

  function evaluate(entry) {
    applyGroup(entry.group);
  }

  /* Full-width bars must not cover page content: measure each visible bar and
     expose its height + a body class the CSS turns into body padding. */
  function px(n) {
    return (n > 0 ? Math.round(n) : 0) + 'px';
  }

  function syncBar(selector, cls, cssVar) {
    var body = document.body;
    var root = document.documentElement;
    if (!body || !root) {
      return;
    }
    var bar = document.querySelector(selector + ':not([hidden])');
    if (bar) {
      body.classList.add(cls);
      root.style.setProperty(cssVar, px(bar.offsetHeight));
    } else {
      body.classList.remove(cls);
      root.style.removeProperty(cssVar);
    }
  }

  function syncBars() {
    syncBar('.tecc-location--top-bar', 'tecc-has-top-bar', '--tecc-top-bar-h');
    syncBar('.tecc-location--footer-bar', 'tecc-has-footer-bar', '--tecc-footer-bar-h');
    syncDiviHeaderOffset();
  }

  /**
   * Divi: measure fixed/Theme Builder header height so CSS can pad
   * #page-container after we slide the header below our top-bar.
   * (Fixed elements often have offsetParent === null — use offsetHeight.)
   */
  function syncDiviHeaderOffset() {
    var body = document.body;
    var root = document.documentElement;
    if (!body || !root || !body.classList.contains('et_divi_theme')) {
      return;
    }
    if (!body.classList.contains('tecc-has-top-bar')) {
      root.style.removeProperty('--tecc-divi-header-h');
      return;
    }
    var h = 0;
    var topHeader = document.getElementById('top-header');
    var mainHeader = document.getElementById('main-header');
    var tbHeader = document.querySelector('.et-l--header');
    if (topHeader && topHeader.offsetHeight) {
      h += topHeader.offsetHeight;
    }
    if (mainHeader && mainHeader.offsetHeight) {
      h += mainHeader.offsetHeight;
    } else if (tbHeader && tbHeader.offsetHeight) {
      h += tbHeader.offsetHeight;
    }
    if (h > 0) {
      root.style.setProperty('--tecc-divi-header-h', px(h));
    } else {
      root.style.removeProperty('--tecc-divi-header-h');
    }
  }

  function setup(el) {
    if (el.getAttribute('data-tecc-location-init') === '1') {
      return;
    }
    el.setAttribute('data-tecc-location-init', '1');

    // The dismiss button is a sibling of the rendered card, so lift the card's
    // accent colours up to the location wrapper — the close button is then
    // tinted from the display's own preset colours.
    var cd = el.querySelector('.tecc-cd');
    if (cd && cd.style) {
      var main = cd.style.getPropertyValue('--tecc-main');
      var alt = cd.style.getPropertyValue('--tecc-alt');
      if (main) {
        el.style.setProperty('--tecc-main', main);
      }
      if (alt) {
        el.style.setProperty('--tecc-alt', alt);
      }
    }

    var client = null;
    var raw = el.getAttribute('data-tecc-client');
    if (raw) {
      try {
        client = JSON.parse(raw);
      } catch (err) {
        client = null;
      }
    }

    var group = el.getAttribute('data-tecc-layer-group') || el.getAttribute('data-tecc-rule') || ('tecc-loc-' + Math.random());

    var entry = {
      el: el,
      group: group,
      require: toStringArray(client && client.require),
      forbid: toStringArray(client && client.forbid),
      mql: null,
      dismissed: false
    };

    if (!groups[group]) {
      groups[group] = [];
    }
    groups[group].push(entry);

    var dismissKey = el.getAttribute('data-tecc-dismiss');
    var closeMode = el.getAttribute('data-tecc-close') || 'event';
    if (dismissKey && isDismissed(dismissKey, closeMode, el)) {
      entry.dismissed = true;
    }

    var dismissBtn = el.querySelector('.tecc-location__dismiss');
    if (dismissBtn) {
      var dismiss = function () {
        if (dismissKey) {
          recordDismissal(dismissKey, closeMode, el);
        }
        entry.dismissed = true;
        evaluate(entry);
      };
      dismissBtn.addEventListener('click', dismiss);
      // The dismiss control is a <span role="button">, so wire keyboard too.
      dismissBtn.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ' || ev.keyCode === 13 || ev.keyCode === 32) {
          ev.preventDefault();
          dismiss();
        }
      });
    }

    /* 'mobile' conditions re-evaluate live on viewport changes. */
    var needsMobile = entry.require.indexOf('mobile') !== -1 || entry.forbid.indexOf('mobile') !== -1;
    if (needsMobile && window.matchMedia) {
      entry.mql = window.matchMedia(MOBILE_QUERY);
      var onChange = function () {
        evaluate(entry);
      };
      if (entry.mql.addEventListener) {
        entry.mql.addEventListener('change', onChange);
      } else if (entry.mql.addListener) {
        entry.mql.addListener(onChange); /* Older Safari. */
      }
    }

    evaluate(entry);
  }

  function scan(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var els = scope.querySelectorAll('[data-tecc-location]');
    for (var i = 0; i < els.length; i++) {
      try {
        setup(els[i]);
      } catch (err) {
        /* Ignore: a broken location must not kill the others. */
      }
    }
  }

  /* Public hook for previews/dynamic content; setup() is idempotent. */
  window.teccLocations = { scan: scan };

  /* Bars can change height on reflow (responsive wrap); keep padding in sync. */
  if (window.addEventListener) {
    window.addEventListener('resize', syncBars);
  }

  if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', function () {
      scan();
      syncBars();
    });
  } else {
    scan();
    syncBars();
  }
})();
