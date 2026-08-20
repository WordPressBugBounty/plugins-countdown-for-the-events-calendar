/**
 * tecc-carousel.js — multi-event carousel runtime (Phase 4).
 *
 * Markup contract (includes/templates/card.php): a [data-tecc-carousel] root
 * containing [data-tecc-viewport] > [data-tecc-track] > [data-tecc-slide]*,
 * [data-tecc-prev] / [data-tecc-next] buttons and an empty [data-tecc-dots]
 * tablist that this script fills with one tab button per slide.
 *
 * Navigation translates the track (translateX(-index * 100%), sign flipped
 * under RTL); prev/next wrap around. Keyboard: ArrowLeft/ArrowRight on the
 * region; roving tabindex on the dots. Swipe via Pointer Events. Autoplay is
 * OFF unless data-tecc-autoplay="<ms>" is present, pauses on hover and
 * focus-within, and never runs under prefers-reduced-motion. Inactive slides
 * get aria-hidden plus tabindex=-1 on their links/buttons.
 *
 * ES5 only: no jQuery, no arrow functions, no let/const.
 */
(function () {
  'use strict';

  if (typeof document === 'undefined' || typeof window === 'undefined') {
    return; /* Non-browser environment. */
  }

  var uid = 0;
  var reducedMotion = false;
  try {
    reducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  } catch (err) {
    reducedMotion = false;
  }

  function isRtl(el) {
    if (window.getComputedStyle) {
      return 'rtl' === window.getComputedStyle(el).direction;
    }
    return false;
  }

  /**
   * Toggle "inert-like" focusability of a slide's interactive elements:
   * hidden slides get tabindex=-1 on links/buttons, the active slide has
   * the attribute removed again.
   */
  function setSlideFocusable(slide, active) {
    var els = slide.querySelectorAll('a[href], button');
    for (var i = 0; i < els.length; i++) {
      if (active) {
        els[i].removeAttribute('tabindex');
      } else {
        els[i].setAttribute('tabindex', '-1');
      }
    }
  }

  /* The nav arrows + dots are <span role="button|tab"> (not <button>) so theme
     and page-builder `button {}` rules can't restyle them. Spans don't fire on
     Enter/Space like a native button, so bridge the key to the click action. */
  function isActivateKey(e) {
    var k = e.key || e.keyCode;
    return 'Enter' === k || ' ' === k || 'Spacebar' === k || 13 === k || 32 === k;
  }

  function setup(root) {
    if (root.getAttribute('data-tecc-carousel-init') === '1') {
      return;
    }

    var viewport = root.querySelector('[data-tecc-viewport]');
    var track = root.querySelector('[data-tecc-track]');
    var dotsWrap = root.querySelector('[data-tecc-dots]');
    var prevBtn = root.querySelector('[data-tecc-prev]');
    var nextBtn = root.querySelector('[data-tecc-next]');

    if (!viewport || !track) {
      return;
    }

    var slideNodes = track.querySelectorAll('[data-tecc-slide]');
    if (!slideNodes.length) {
      return;
    }

    root.setAttribute('data-tecc-carousel-init', '1');

    var slides = [];
    var dots = [];
    var index = 0;
    var i;

    if (!root.id) {
      uid++;
      root.id = 'tecc-carousel-' + uid;
    }

    for (i = 0; i < slideNodes.length; i++) {
      slides.push(slideNodes[i]);
      if (!slides[i].id) {
        slides[i].id = root.id + '-slide-' + i;
      }
    }

    function update() {
      var sign = isRtl(track) ? 1 : -1;
      track.style.transform = 'translateX(' + (sign * index * 100) + '%)';
      for (var j = 0; j < slides.length; j++) {
        var active = j === index;
        if (active) {
          slides[j].removeAttribute('aria-hidden');
        } else {
          slides[j].setAttribute('aria-hidden', 'true');
        }
        setSlideFocusable(slides[j], active);
        if (dots[j]) {
          dots[j].setAttribute('aria-selected', active ? 'true' : 'false');
          dots[j].setAttribute('tabindex', active ? '0' : '-1');
        }
      }
    }

    function goTo(target) {
      index = ((target % slides.length) + slides.length) % slides.length; /* Wrap. */
      update();
    }

    /* Dots: one tab button per slide (server ships the container empty). */
    if (dotsWrap) {
      for (i = 0; i < slides.length; i++) {
        (function (n) {
          var dot = document.createElement('span');
          dot.className = 'tecc-cd__dot';
          dot.setAttribute('role', 'tab');
          dot.setAttribute('tabindex', '-1');
          dot.setAttribute('aria-controls', slides[n].id);
          /* The slide label is server-rendered + translated ("1 of 3"). */
          dot.setAttribute('aria-label', slides[n].getAttribute('aria-label') || String(n + 1));
          dot.addEventListener('click', function () {
            goTo(n);
          });
          dot.addEventListener('keydown', function (e) {
            if (isActivateKey(e)) {
              e.preventDefault();
              goTo(n);
            }
          });
          dotsWrap.appendChild(dot);
          dots.push(dot);
        })(i);
      }
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        goTo(index - 1);
      });
      prevBtn.addEventListener('keydown', function (e) {
        if (isActivateKey(e)) {
          e.preventDefault();
          goTo(index - 1);
        }
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        goTo(index + 1);
      });
      nextBtn.addEventListener('keydown', function (e) {
        if (isActivateKey(e)) {
          e.preventDefault();
          goTo(index + 1);
        }
      });
    }

    /* Keyboard: arrows on the region navigate; when focus is on a dot the
       roving tabindex follows the active slide. */
    root.addEventListener('keydown', function (e) {
      var key = e.key || e.keyCode;
      var left = 'ArrowLeft' === key || 37 === key;
      var right = 'ArrowRight' === key || 39 === key;
      if (!left && !right) {
        return;
      }
      e.preventDefault();
      var dir = right ? 1 : -1;
      if (isRtl(track)) {
        dir = -dir;
      }
      var fromDot = !!(dotsWrap && e.target && dotsWrap.contains && dotsWrap.contains(e.target));
      goTo(index + dir);
      if (fromDot && dots[index] && dots[index].focus) {
        dots[index].focus();
      }
    });

    /* Swipe via Pointer Events (40px threshold). */
    if (typeof PointerEvent !== 'undefined') {
      var downX = null;
      var downId = null;
      viewport.addEventListener('pointerdown', function (e) {
        downX = e.clientX;
        downId = e.pointerId;
      });
      viewport.addEventListener('pointerup', function (e) {
        if (null === downX || e.pointerId !== downId) {
          return;
        }
        var dx = e.clientX - downX;
        downX = null;
        downId = null;
        if (Math.abs(dx) < 40) {
          return;
        }
        var forward = dx < 0; /* Swipe toward the inline start = advance. */
        if (isRtl(track)) {
          forward = !forward;
        }
        goTo(index + (forward ? 1 : -1));
      });
      viewport.addEventListener('pointercancel', function () {
        downX = null;
        downId = null;
      });
    }

    /* Autoplay: opt-in via data-tecc-autoplay="<ms>", never under
       prefers-reduced-motion, paused while hovered or focus is within. */
    var autoplayMs = parseInt(root.getAttribute('data-tecc-autoplay'), 10);
    var autoplayTimer = null;

    function stopAutoplay() {
      if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
      }
    }

    function startAutoplay() {
      if (reducedMotion || autoplayTimer || isNaN(autoplayMs) || autoplayMs < 1000) {
        return;
      }
      autoplayTimer = setInterval(function () {
        goTo(index + 1);
      }, autoplayMs);
    }

    if (!reducedMotion && !isNaN(autoplayMs) && autoplayMs >= 1000) {
      root.addEventListener('mouseenter', stopAutoplay);
      root.addEventListener('mouseleave', startAutoplay);
      root.addEventListener('focusin', stopAutoplay);
      root.addEventListener('focusout', startAutoplay);
      startAutoplay();
    }

    update();
  }

  function scan(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var roots = scope.querySelectorAll('[data-tecc-carousel]');
    for (var i = 0; i < roots.length; i++) {
      try {
        setup(roots[i]);
      } catch (err) {
        /* Ignore: a broken instance must not kill the others. */
      }
    }
  }

  /* Public hook: dynamic content (admin preview, AJAX) injects fresh
     carousels and rescans. setup() is idempotent (init marker). */
  window.teccCarousel = { scan: scan };

  if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }
})();
