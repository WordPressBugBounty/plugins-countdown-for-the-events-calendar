/* =========================================================================
   eca-dashboard-resolver.js  —  PURE resolver for the shared ECA dashboard.

   resolveDashboard(env, manifest) -> viewModel

   - Zero DOM, zero I/O, no globals, no copy. The ONLY place the conditional
     rules live. 1:1 twin of the future PHP resolve_dashboard().
   - env    : which addon tiers + builders are active (see CONDITIONAL-DASHBOARD-PLAN.md §2)
   - manifest: all copy/urls/videos/steps (eca-dashboard-manifest.json)
   - viewModel: what to show — sections/tabs/cards/hero/banner/premium.

   Rules R0..R10 and the corrected Case-11 Static gate are documented inline.
   Runs in browser (window.ECADashboardResolver) and Node (module.exports).
   ========================================================================= */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.ECADashboardResolver = factory();
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  function interp(s, map) {
    return String(s).replace(/\{(\w+)\}/g, function (_, k) {
      return map[k] != null ? map[k] : '{' + k + '}';
    });
  }
  function thumb(id) { return 'https://img.youtube.com/vi/' + id + '/hqdefault.jpg'; }

  // Deep-merge hostOverrides[hostSlug] onto a clone of the shared base manifest
  // so every plugin can ship an identical JSON file; host-specific copy is applied
  // conditionally here (widgets / divi / spb), not by editing per-plugin manifests.
  function deepMerge(target, source) {
    if (!source || typeof source !== 'object' || Array.isArray(source)) {
      return Array.isArray(source) ? source.slice() : source;
    }
    var out = target && typeof target === 'object' && !Array.isArray(target) ? Object.assign({}, target) : {};
    Object.keys(source).forEach(function (key) {
      // Never merge the override map into itself as content.
      if (key === 'hostOverrides') return;
      var sv = source[key];
      if (Array.isArray(sv)) {
        out[key] = sv.slice();
      } else if (sv && typeof sv === 'object') {
        out[key] = deepMerge(out[key], sv);
      } else {
        out[key] = sv;
      }
    });
    return out;
  }

  function cloneManifest(manifest) {
    try {
      return JSON.parse(JSON.stringify(manifest));
    } catch (e) {
      return manifest;
    }
  }

  function applyHostOverrides(manifest, hostSlug) {
    var overrides = (manifest && manifest.hostOverrides && manifest.hostOverrides[hostSlug]) || null;
    if (!overrides) return manifest;
    // Clone first so wp_localize_script's shared ECA_MANIFEST is never mutated.
    var next = deepMerge(cloneManifest(manifest), overrides);
    next.hostOverrides = manifest.hostOverrides;
    return next;
  }

  function resolveDashboard(env, rawManifest) {
    var hostSlug = env.hostAddonSlug || 'eca';
    var manifest = applyHostOverrides(rawManifest || {}, hostSlug);
    var A = env.addons || {};
    var S = manifest.strings || {};
    var CTA = S.cta || {};

    // utm_source is the HOST (active) add-on's code — the plugin the user is
    // currently in — NOT the add-on the link points to. utm_content is where on
    // the page the click came from; utm_campaign is where it's going. wp.org
    // links (installs, reviews) get NO UTM.
    var hostAddon = (manifest.addons && manifest.addons[hostSlug]) || {};
    var hostUtmSource = hostAddon.utmSource || 'eca_plugin';
    function utm(url, campaign, content) {
      if (!url) return url;
      if (url.indexOf('wordpress.org') !== -1) return url;   // no UTM on wp.org
      var sep = url.indexOf('?') >= 0 ? '&' : '?';
      return url + sep + 'utm_source=' + hostUtmSource + '&utm_medium=inside' +
        '&utm_campaign=' + campaign + '&utm_content=' + content;
    }

    /* ---- R0: derive flags once --------------------------------------- */
    function tier(id, t) { var a = A[id] || {}; return !!a[t]; }
    function tierInfo(id, t) { var a = A[id] || {}; return { status: a[t + 'Status'] || (a[t] ? 'active' : 'absent'), init: a[t + 'Init'] || '', slug: a[t + 'Slug'] || '' }; }
    function addonActive(id) { return tier(id, 'free') || tier(id, 'pro'); }
    function proActive(id) { return tier(id, 'pro'); }

    var esb = addonActive('esb'), widgets = addonActive('widgets'),
        divi = addonActive('divi'), spb = addonActive('spb'),
        speakers = addonActive('speakers'), search = addonActive('search'),
        countdown = addonActive('countdown');

    var ED = !!env.editors.elementor, DV = !!env.editors.divi,
        BR = !!env.editors.bricks, WB = !!env.editors.wpbakery,
        BLK = env.editors.blockEditor !== false, // default true
        CE = !!env.editors.classicEditor;
    var anyEditor = ED || DV || BR || WB;
    var spEditorCount = (ED ? 1 : 0) + (DV ? 1 : 0);           // Bricks/WPBakery NOT counted
    var listingAddonActive = esb || (widgets && ED) || (divi && DV); // a dormant addon doesn't count
    var singlePageAddonActive = spb || proActive('divi');
    var proCount = ['esb', 'widgets', 'divi', 'spb', 'speakers']
      .filter(function (id) { return proActive(id); }).length;

    function freePluginAction(driverId, driver, content) {
      var info = tierInfo(driverId, 'free');
      if (info.status === 'inactive' && info.init) {
        return {
          kind: 'primary', label: CTA.activate || 'Activate', icon: 'admin-plugins',
          url: '#', external: false, action: 'activate', addon: driverId,
          init: info.init, slug: info.slug
        };
      }
      // In-dashboard install also activates — label must say so.
      if (info.slug) {
        return {
          kind: 'primary', label: CTA.installActivate || 'Install & Activate', icon: 'download',
          url: '#', external: false, action: 'install', addon: driverId,
          init: info.init || (info.slug ? info.slug + '/' + info.slug + '.php' : ''), slug: info.slug
        };
      }
      // No slug → wordpress.org (manual install only).
      return {
        kind: 'primary', label: CTA.installFree || 'Install Free', icon: 'download',
        url: utm(driver.orgUrl, 'install', content), external: true,
        action: null, addon: driverId, init: '', slug: ''
      };
    }

    // Pro installed on disk but inactive → Activate Pro (orange) instead of buy links.
    function proPluginAction(driverId) {
      var info = tierInfo(driverId, 'pro');
      if (info.status === 'inactive' && info.init) {
        return {
          kind: 'accent', label: CTA.activatePro || 'Activate Pro', icon: 'star-filled',
          url: '#', external: false, action: 'activate', addon: driverId,
          init: info.init, slug: info.slug, pro: 1
        };
      }
      return null;
    }

    function howtoStepsForTab(tabId, cfg) {
      // Shortcode: Classic Editor plugin → button flow; otherwise generic generate/paste.
      if (tabId === 'shortcode' && CE && cfg.stepsClassic && cfg.stepsClassic.length) {
        return cfg.stepsClassic;
      }
      // Dual-tier drivers (widgets, divi, esb, …): Pro-specific steps when Pro is active.
      var driverId = cfg.driver;
      if (driverId && proActive(driverId) && cfg.stepsPro && cfg.stepsPro.length) {
        return cfg.stepsPro;
      }
      return cfg.steps;
    }

    /* ---- method-tab builder (listings + single page) ----------------- */
    function buildMethodTab(sectionKey, tabId, cfg) {
      var content = sectionKey === 'listings' ? 'dashboard_listing' : 'dashboard_single';
      var driver = manifest.addons[cfg.driver];
      var cap = cfg.capability;                 // 'free' | 'pro'
      var driverActive = addonActive(cfg.driver);
      var driverPro = proActive(cfg.driver);
      var driverFreeOnly = driverActive && !driverPro;

      var mode = cap === 'pro'
        ? (driverPro ? 'HOWTO' : 'PROMO_PRO')
        : (driverActive ? 'HOWTO' : 'PROMO_FREE');

      var actions = [], install = null, note = null, steps = null;

      var ownedNote = null;
      var proAct = proPluginAction(cfg.driver);

      if (mode === 'HOWTO') {
        steps = { title: S.howtoTitle, items: howtoStepsForTab(tabId, cfg) };
        // Primary is "View Docs" (plugin docs) — no in-app create/modal.
        actions.push({ kind: 'primary', label: CTA.viewDocs, icon: 'media-document', url: utm(driver.docsUrl, 'docs', content), external: true });
        actions.push({ kind: 'ghost', label: CTA.viewDemo, icon: 'external', url: utm(driver.demoUrl, 'demo', content), external: true });
        // R7: quiet orange upgrade link only when the free tier is active but Pro isn't.
        // Prefer Activate Pro when Pro is already on disk but inactive.
        if (cap === 'free' && driverFreeOnly) {
          if (proAct) {
            actions.push(proAct);
          } else if (driver.proUrl) {
            actions.push({ kind: 'accent', label: CTA.upgradePro, icon: 'star-filled', url: utm(driver.proUrl, 'get_pro', content), external: true });
          }
        }
      } else if (mode === 'PROMO_FREE') {
        install = {
          tone: 'free',
          logo: driver.icon,
          title: cfg.installTitle || interp(S.installFreeTitle, { addon: driver.name }),
          text: cfg.installText || interp(S.installFreeText, { addon: driver.name })
        };
        // Owner already has Pro inactive — don't push free Install; offer Activate Pro.
        if (proAct) {
          if (sectionKey === 'singlePage' && cfg.driver === 'spb' && cap === 'free') {
            install.tone = 'pro';
            install.title = interp(S.installProTitle, { addonPro: driver.proName });
            install.text = interp(S.installProText, { feature: cfg.feature, addonPro: driver.proName });
          }
          ownedNote = CTA.proOwnedNote || 'You already own Pro — just activate it.';
          actions.push(proAct);
        } else {
          actions.push(freePluginAction(cfg.driver, driver, content));
        }
        actions.push({ kind: 'ghost', label: CTA.viewDemos, icon: 'external', url: utm(driver.demoUrl, 'demo', content), external: true });
      } else { // PROMO_PRO
        install = {
          tone: 'pro',
          logo: driver.icon,
          title: cfg.installTitle || interp(S.installProTitle, { addonPro: driver.proName }),
          text: cfg.installText || interp(S.installProText, { feature: cfg.feature, addonPro: driver.proName })
        };
        if (proAct) {
          ownedNote = CTA.proOwnedNote || 'You already own Pro — just activate it.';
          actions.push(proAct);
        } else {
          actions.push({ kind: 'accent', label: CTA.upgradePro, icon: 'star-filled', url: utm(driver.proUrl, 'get_pro', content), external: true });
        }
        actions.push({ kind: 'ghost', label: CTA.viewDemos, icon: 'external', url: utm(driver.demoUrl, 'demo', content), external: true });
      }

      // Notes (blue, free-safe). Single-page Pro tabs keep the free Static path visible.
      if (sectionKey === 'singlePage' && cap === 'pro' && mode === 'PROMO_PRO') {
        note = { text: S.staticFallbackNote };
      } else if (sectionKey === 'listings' && (tabId === 'elementor' || tabId === 'divi') && mode === 'PROMO_FREE' && esb) {
        note = { text: interp(S.shortcodeInBuilderNote, { editor: cfg.noteEditor || cfg.label }) };
      }

      return {
        id: tabId, label: cfg.label, shortLabel: cfg.shortLabel || cfg.label,
        icon: cfg.icon, tabDesc: cfg.tabDesc, description: cfg.description,
        driver: cfg.driver, proBadge: (cap === 'pro' && mode === 'PROMO_PRO'),
        mode: mode,
        video: { youtubeId: driver.videoId, thumb: thumb(driver.videoId) },
        steps: steps, install: install, note: note, ownedNote: ownedNote, actions: actions
      };
    }

    // default-tab rule (both sections): first non-PROMO_PRO tab, else first.
    function pick(tabs) {
      var idx = -1;
      for (var i = 0; i < tabs.length; i++) { if (tabs[i].mode !== 'PROMO_PRO') { idx = i; break; } }
      return { showNav: tabs.length > 1, defaultTab: (idx >= 0 ? tabs[idx] : tabs[0] || {}).id || null };
    }

    // Section head follows the condition: the multi-option "select your editor/
    // method" framing when there are tabs to choose from, or a non-"select" title
    // + the single method's own description when only one option shows (no nav) —
    // so the heading always matches the content below it.
    function sectionHead(sec, tabs) {
      if (tabs.length > 1) return { title: sec.subtitle, desc: sec.desc };
      var only = tabs[0];
      return { title: sec.subtitleSingle || sec.subtitle, desc: (only && only.description) || sec.desc };
    }

    /* ---- SECTION 1: listings ----------------------------------------- */
    var Lsec = manifest.sections.listings;
    var listingVisible = {
      elementor: ED, divi: DV, bricks: BR, wpbakery: WB,
      shortcode: (esb || !anyEditor),
      block: (esb || !anyEditor) && BLK
    };
    var Ltabs = Lsec.order.filter(function (id) { return listingVisible[id]; })
      .map(function (id) { return buildMethodTab('listings', id, Lsec.tabs[id]); });

    /* ---- SECTION 2: single page -------------------------------------- */
    var Ssec = manifest.sections.singlePage;
    // *** Case-11 corrected Static gate ***
    var staticVisible = spb || BR || WB || spEditorCount === 0 || (spEditorCount === 2 && esb);
    var spVisible = { elementorTpl: ED, diviTpl: DV, static: staticVisible };
    var Stabs = Ssec.order.filter(function (id) { return spVisible[id]; })
      .map(function (id) { return buildMethodTab('singlePage', id, Ssec.tabs[id]); });

    /* ---- SECTION 3: other utilities (always all 3) ------------------- */
    var Osec = manifest.sections.other;
    function buildCard(cardId, cfg) {
      var driver = manifest.addons[cfg.driver];
      var cap = cfg.capability;
      var active = addonActive(cfg.driver);
      var proInfo = tierInfo(cfg.driver, 'pro');
      var actions = [], pill, ownedNote = null;
      var proAct = proPluginAction(cfg.driver);

      // Same free/pro ownership rules as widgets/spb/esb:
      // Pro on disk but inactive → Speakers-style PROMO_PRO (Activate Pro).
      var mode;
      if (active) {
        mode = 'SETTINGS';
      } else if (proInfo.status === 'inactive' || cap === 'pro') {
        mode = 'PROMO_PRO';
      } else {
        mode = 'PROMO_FREE';
      }

      if (mode === 'SETTINGS') {
        actions.push({ kind: 'primary', label: cfg.settingsLabel, icon: cfg.settingsIcon || (cap === 'pro' || proActive(cfg.driver) ? 'plus-alt' : 'admin-generic'), url: '#', external: false });
        // Free active + Pro installed inactive → Activate Pro (shared proPluginAction).
        if (proAct) {
          ownedNote = CTA.proOwnedNote || 'You already own Pro — just activate it.';
          actions.push(proAct);
          pill = { kind: 'pro', label: 'Pro' };
        } else {
          pill = { kind: 'active', label: 'Active' };
        }
        actions.push({ kind: 'ghost', label: CTA.viewDemo, url: utm(driver.demoUrl, 'demo', 'dashboard_other'), external: true });
      } else if (mode === 'PROMO_PRO') {
        if (proAct) {
          ownedNote = CTA.proOwnedNote || 'You already own Pro — just activate it.';
          actions.push(proAct);
        } else {
          actions.push({ kind: 'accent', label: CTA.getPro, icon: 'star-filled', url: utm(driver.proUrl, 'get_pro', 'dashboard_other'), external: true });
        }
        actions.push({ kind: 'ghost', label: CTA.viewDemos, url: utm(driver.demoUrl, 'demo', 'dashboard_other'), external: true });
        // Pro-only products (speakers) keep "Pro only"; free+pro products (search) use "Pro".
        pill = (cap === 'pro')
          ? { kind: 'pro-only', label: 'Pro only' }
          : { kind: 'pro', label: 'Pro' };
      } else { // PROMO_FREE
        actions.push(freePluginAction(cfg.driver, driver, 'dashboard_other'));
        actions.push({ kind: 'ghost', label: CTA.viewDemos, url: utm(driver.demoUrl, 'demo', 'dashboard_other'), external: true });
        pill = { kind: 'free', label: 'Free' };
      }
      return { id: cardId, driver: cfg.driver, label: cfg.label, icon: cfg.icon, desc: cfg.desc, note: cfg.note || null, ownedNote: ownedNote, mode: mode, pill: pill, actions: actions };
    }
    var Ocards = Osec.order.map(function (id) { return buildCard(id, Osec.cards[id]); });

    /* ---- HERO -------------------------------------------------------- */
    // Host plugins (module-four / speakers) pin their framed hero even when
    // another builder is also active — otherwise Elementor detection would
    // steal Divi's "Build Events with Divi" title, etc.
    var heroKey;
    if (hostSlug === 'speakers') {
      heroKey = 'speakers';
    } else if (hostSlug === 'widgets') {
      heroKey = widgets ? 'elementorPro' : 'elementorPush';
    } else if (hostSlug === 'divi') {
      heroKey = 'divi';
    } else if (hostSlug === 'spb' && singlePageAddonActive && !listingAddonActive) {
      heroKey = 'singleOnly';
    } else if (proCount >= 2) {
      heroKey = 'power';
    } else if (ED) {
      heroKey = widgets ? 'elementorPro' : 'elementorPush';
    } else if (DV) {
      heroKey = 'divi';
    } else if (listingAddonActive && singlePageAddonActive) {
      heroKey = 'covered';
    } else if (singlePageAddonActive && !listingAddonActive) {
      heroKey = 'singleOnly';
    } else {
      heroKey = 'default';
    }
    if (!manifest.hero || !manifest.hero[heroKey]) {
      heroKey = 'default';
    }
    var hero = { key: heroKey, title: manifest.hero[heroKey].title, desc: manifest.hero[heroKey].desc };

    /* ---- Recommended free add-ons (builder-aware pack) ---------------- */
    // Always recommend builder-agnostic free add-ons: SPB, Search, Countdown.
    // Native plugins only when their builder is present:
    //   Elementor → widgets free; Divi → divi free.
    // Shortcodes free only when not already active (this Free host normally is).
    // Never recommend Divi modules on Elementor-only sites (and vice versa).
    var recStrings = S.recommendedAddons || {};
    var packIds = ['spb', 'search', 'countdown'];
    if (ED) packIds.push('widgets');
    if (DV) packIds.push('divi');
    if (!addonActive('esb')) packIds.push('esb');
    var anyRelatedPro = !!env.anyRelatedProPresent;
    var canManageRec = !(env.canManagePlugins === false);
    var recPlugins = [];
    packIds.forEach(function (id) {
      var addonDef = manifest.addons[id] || {};
      var info = tierInfo(id, 'free');
      var plugin = {
        id: id,
        name: addonDef.name || id,
        icon: addonDef.icon || '',
        freeStatus: info.status,
        action: null,
        slug: info.slug || '',
        init: info.init || ''
      };
      if (info.status === 'active') {
        plugin.action = null;
      } else if (info.status === 'inactive' && info.init) {
        plugin.action = 'activate';
        plugin.init = info.init;
      } else if (info.slug) {
        plugin.action = 'install';
        plugin.init = info.init || (info.slug + '/' + info.slug + '.php');
        plugin.slug = info.slug;
      }
      recPlugins.push(plugin);
    });
    var recPending = recPlugins.filter(function (p) { return p.action; });
    var recommendedAddons = {
      show: !!(packIds.length && recPending.length && canManageRec && !anyRelatedPro && !env.recommendedDone),
      title: recStrings.title || CTA.recommendedTitle || 'Recommended Addons for The Events Calendar',
      desc: recStrings.desc || CTA.recommendedDesc || 'Install all recommended plugins to get the complete experience.',
      ctaLabel: recStrings.cta || CTA.recommendedCta || 'Install Addons',
      doneMessage: recStrings.done || CTA.recommendedDone || 'All recommended addons have been installed and activated successfully.',
      plugins: recPlugins
    };

    /* ---- ASK FOR REVIEW (per-addon data; render picks the target by the
       currently-selected tab and only shows it for INSTALLED addons that have
       a wp.org listing — Speakers is Pro-only so it has no reviewUrl). ----- */
    var review = { copy: manifest.review, addons: {}, dismissed: env.dismissed || [] };
    Object.keys(manifest.addons).forEach(function (id) {
      var a = manifest.addons[id];
      if (a.reviewUrl) review.addons[id] = { name: a.name, url: a.reviewUrl }; // wp.org -> no UTM
    });

    /* ---- PREMIUM (stack-aware) --------------------------------------- */
    var premiumVisible = {
      widgets: !proActive('widgets') && ED,
      esb: !proActive('esb'),
      spb: !proActive('spb') && !(DV && !ED && !BR),   // hidden in Divi-only stacks
      divi: !proActive('divi') && DV,
      speakers: !proActive('speakers'),
      // Same rule as other dual-tier Pros: show until Search Pro is active.
      search: !proActive('search')
    };
    var pcards = manifest.premium.order.filter(function (id) { return premiumVisible[id]; })
      .map(function (id) {
        var c = manifest.premium.cards[id], addon = manifest.addons[id];
        var proAct = proPluginAction(id);
        return {
          id: id, name: c.name, icon: c.icon, desc: c.desc,
          demoUrl: utm(addon.demoUrl, 'demo', 'dashboard_promo'),
          proUrl: utm(addon.proUrl, 'get_pro', 'dashboard_promo'),
          activateAction: proAct
        };
      });
    var premium = { show: pcards.length > 0, title: manifest.premium.title, cards: pcards };

    /* ---- HEADER links (Get Support / Check Docs) --------------------- */
    var H = manifest.header || {};
    var header = {
      docsUrl: utm(H.docsUrl, 'docs', 'dashboard_header'),
      supportUrl: utm(H.supportUrl, 'support', 'dashboard_header')
    };

    /* ---- assemble ---------------------------------------------------- */
    return {
      meta: { schemaVersion: manifest.schemaVersion, manifestVersion: manifest.manifestVersion, hostAddonSlug: hostSlug, proCount: proCount },
      defaultSection: listingAddonActive ? 'listings' : (spb ? 'single-page' : 'listings'),
      hero: hero,
      recommendedAddons: recommendedAddons,
      header: header,
      review: review,
      jobs: [
        { id: 'listings', label: Lsec.jobLabel, sub: Lsec.jobSub, icon: Lsec.jobIcon, panel: 'listings' },
        { id: 'single-page', label: Ssec.jobLabel, sub: Ssec.jobSub, icon: Ssec.jobIcon, panel: 'single-page' },
        { id: 'other', label: Osec.jobLabel, sub: Osec.jobSub, icon: Osec.jobIcon, panel: 'other' }
      ],
      sections: {
        listings: { key: 'listings', head: sectionHead(Lsec, Ltabs), showNav: pick(Ltabs).showNav, defaultTab: pick(Ltabs).defaultTab, tabs: Ltabs },
        singlePage: { key: 'single-page', head: sectionHead(Ssec, Stabs), showNav: pick(Stabs).showNav, defaultTab: pick(Stabs).defaultTab, tabs: Stabs },
        other: { key: 'other', head: { title: Osec.subtitle, desc: Osec.desc }, cards: Ocards }
      },
      premium: premium
    };
  }

  return { resolveDashboard: resolveDashboard };
}));
