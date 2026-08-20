/**
 * Event Countdown for The Events Calendar — settings panel behavior.
 *
 * Top tabs + left-card sub-tabs (both ARIA tablists), source-radio logic, live
 * shortcode generator, and the REST-powered live preview with a loading
 * overlay. Vanilla JS, no jQuery. Reads config from the localized global
 * `teccAdminSettings` = { restUrl, nonce, debounce, i18n }.
 *
 * @since 2.0.0
 */
( function() {
	'use strict';

	var config = window.teccAdminSettings || {};
	var i18n = config.i18n || {};
	var DEBOUNCE_MS = parseInt( config.debounce, 10 ) || 200;

	var form = document.querySelector( '.tecc-settings-form' );
	var wrap = document.querySelector( '.tecc-settings-wrap' );

	if ( ! form || ! wrap ) {
		return;
	}

	var previewBox = document.getElementById( 'tecc-preview' );
	var previewTimer = null;
	var previewRequestId = 0;
	var currentTab = 'shortcode';
	var currentSubTab = '';
	var subTabList = null;

	/* Remember the open top tab + left sub-tab so a Settings-API save (which
	 * POSTs and redirects without the URL hash) reopens the same place. */
	var TAB_STORE_KEY = 'teccActiveTabs';

	function persistTabs() {
		try {
			window.sessionStorage.setItem( TAB_STORE_KEY, JSON.stringify( { tab: currentTab, subtab: currentSubTab } ) );
		} catch ( e ) {}
	}

	function readSavedTabs() {
		try {
			return JSON.parse( window.sessionStorage.getItem( TAB_STORE_KEY ) || 'null' );
		} catch ( e ) {
			return null;
		}
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	function radioValue( name, fallback ) {
		var checked = form.querySelector( 'input[name="' + name + '"]:checked' );
		return checked ? checked.value : fallback;
	}

	function fieldValue( name ) {
		var nodes = form.querySelectorAll( '[name="' + name + '"]' );
		if ( ! nodes.length ) {
			return '';
		}
		// Toggle-switch pattern: a checkbox ('yes') plus a companion hidden
		// fallback ('no') share one name. The effective value is the checkbox's
		// when checked, otherwise the fallback — reading nodes[0] would always
		// yield the hidden 'no'.
		var checkbox = null;
		var fallback = null;
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( 'checkbox' === nodes[ i ].type ) {
				checkbox = nodes[ i ];
			} else if ( ! fallback ) {
				fallback = nodes[ i ];
			}
		}
		if ( checkbox ) {
			return checkbox.checked ? checkbox.value : ( fallback ? fallback.value : 'no' );
		}
		return nodes[ 0 ].value;
	}

	/* -------------------------------------------------------------------------
	 * Reusable ARIA tablist
	 *
	 * Wires click + Left/Right/Home/End keyboard navigation, roving tabindex,
	 * and aria-selected across a set of [role="tab"] buttons. `activate(name)`
	 * does the panel toggling; this only manages focus/ARIA state.
	 * ---------------------------------------------------------------------- */

	function setupTablist( buttons, attr, activate ) {
		var list = [];
		var i;

		for ( i = 0; i < buttons.length; i++ ) {
			list.push( buttons[ i ] );
		}

		function focusButton( index ) {
			var target = list[ ( index + list.length ) % list.length ];
			if ( target ) {
				target.focus();
				select( target.getAttribute( attr ) );
			}
		}

		function select( name ) {
			var j;
			activate( name );
			for ( j = 0; j < list.length; j++ ) {
				var isActive = list[ j ].getAttribute( attr ) === name;
				list[ j ].setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				list[ j ].setAttribute( 'tabindex', isActive ? '0' : '-1' );
			}
		}

		for ( i = 0; i < list.length; i++ ) {
			( function( index ) {
				list[ index ].addEventListener( 'click', function() {
					select( list[ index ].getAttribute( attr ) );
				} );
				list[ index ].addEventListener( 'keydown', function( event ) {
					switch ( event.key ) {
						case 'ArrowRight':
						case 'ArrowDown':
							event.preventDefault();
							focusButton( index + 1 );
							break;
						case 'ArrowLeft':
						case 'ArrowUp':
							event.preventDefault();
							focusButton( index - 1 );
							break;
						case 'Home':
							event.preventDefault();
							focusButton( 0 );
							break;
						case 'End':
							event.preventDefault();
							focusButton( list.length - 1 );
							break;
					}
				} );
			}( i ) );
		}

		return { select: select };
	}

	/* -------------------------------------------------------------------------
	 * 1. Top tabs (remembered in location.hash)
	 * ---------------------------------------------------------------------- */

	var tabButtons = wrap.querySelectorAll( '.tecc-tab-nav [data-tecc-tab]' );
	var tabPanels = wrap.querySelectorAll( '[data-tecc-tab-panel]' );

	/* Legacy hash keys from the pre-revamp 5-tab layout map to the new 3 tabs. */
	var LEGACY_TABS = {
		templates: 'shortcode',
		defaults: 'shortcode',
		generator: 'shortcode',
		rules: 'sitewide'
	};

	function activateTab( name ) {
		var i;
		var found = false;

		for ( i = 0; i < tabPanels.length; i++ ) {
			if ( tabPanels[ i ].getAttribute( 'data-tecc-tab-panel' ) === name ) {
				found = true;
				break;
			}
		}

		if ( ! found ) {
			return;
		}

		currentTab = name;

		for ( i = 0; i < tabButtons.length; i++ ) {
			tabButtons[ i ].classList.toggle( 'is-active', tabButtons[ i ].getAttribute( 'data-tecc-tab' ) === name );
		}
		for ( i = 0; i < tabPanels.length; i++ ) {
			tabPanels[ i ].classList.toggle( 'is-active', tabPanels[ i ].getAttribute( 'data-tecc-tab-panel' ) === name );
		}

		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', '#tab-' + name );
		}
		persistTabs();

		if ( 'shortcode' === name ) {
			refresh( true );
		}

		// Let the rules module refresh its own preview when shown.
		if ( window.teccRules && typeof window.teccRules.onTab === 'function' ) {
			window.teccRules.onTab( name );
		}
	}

	function initTabs() {
		var tabs = setupTablist( tabButtons, 'data-tecc-tab', activateTab );
		var match = /^#tab-([a-z]+)$/.exec( window.location.hash || '' );
		var saved = readSavedTabs();

		if ( match ) {
			// A deep-link hash wins over the remembered tab.
			tabs.select( LEGACY_TABS[ match[ 1 ] ] || match[ 1 ] );
		} else if ( saved && saved.tab ) {
			// Reopen the tab that was active when the page was last saved.
			tabs.select( saved.tab );
		}
	}

	/* -------------------------------------------------------------------------
	 * 2. Left-card sub-tabs (Content & Source / Styles & Templates)
	 * ---------------------------------------------------------------------- */

	function initSubTabs() {
		var buttons = wrap.querySelectorAll( '.tecc-subtab-nav [data-tecc-subtab]' );
		var panels = wrap.querySelectorAll( '[data-tecc-subtab-panel]' );

		if ( ! buttons.length ) {
			return;
		}

		function activate( name ) {
			var i;
			currentSubTab = name;
			for ( i = 0; i < buttons.length; i++ ) {
				buttons[ i ].classList.toggle( 'is-active', buttons[ i ].getAttribute( 'data-tecc-subtab' ) === name );
			}
			for ( i = 0; i < panels.length; i++ ) {
				panels[ i ].classList.toggle( 'is-active', panels[ i ].getAttribute( 'data-tecc-subtab-panel' ) === name );
			}
			persistTabs();
		}

		subTabList = setupTablist( buttons, 'data-tecc-subtab', activate );

		// Seed the tracked sub-tab from the initially-active button, then reopen
		// the one that was active at the last save (if any).
		for ( var s = 0; s < buttons.length; s++ ) {
			if ( buttons[ s ].classList.contains( 'is-active' ) ) {
				currentSubTab = buttons[ s ].getAttribute( 'data-tecc-subtab' );
				break;
			}
		}
		var saved = readSavedTabs();
		if ( saved && saved.subtab && subTabList && subTabList.select ) {
			// Only restore a sub-tab that still exists — ids can be renamed or
			// removed across plugin updates, and selecting a missing one would
			// deactivate every panel and blank the left card.
			for ( var v = 0; v < buttons.length; v++ ) {
				if ( buttons[ v ].getAttribute( 'data-tecc-subtab' ) === saved.subtab ) {
					subTabList.select( saved.subtab );
					break;
				}
			}
		}
	}

	/* -------------------------------------------------------------------------
	 * 3. Countdown source radios -> hidden autostart fields + conditionals
	 * ---------------------------------------------------------------------- */

	function currentSource() {
		// Fallback only — PHP always pre-checks a radio.
		return radioValue( 'tecc_source_ui', 'events' );
	}

	function syncSource() {
		var source = currentSource();
		var nextField = form.querySelector( 'input[name="tecc_settings[autostart-next-countdown]"]' );
		var futureField = form.querySelector( 'input[name="tecc_settings[autostart-future-countdown]"]' );
		var blocks = form.querySelectorAll( '[data-tecc-source-vis]' );
		var i;
		var visibleFor;

		// Keep the saved-option (from_options) back-compat keys roughly correct;
		// the generated shortcode below emits the authoritative source attrs.
		if ( nextField ) {
			nextField.value = 'yes';
		}
		if ( futureField ) {
			futureField.value = ( 'upcoming' === source ) ? 'yes' : 'no';
		}
		// Persist the CHOSEN source explicitly so a saved default round-trips to
		// the right radio (from_options + the panel both read source-ui).
		var uiField = form.querySelector( '[data-tecc-source-ui]' );
		if ( uiField ) {
			uiField.value = source;
		}

		for ( i = 0; i < blocks.length; i++ ) {
			visibleFor = ( blocks[ i ].getAttribute( 'data-tecc-source-vis' ) || '' ).split( ' ' );
			blocks[ i ].style.display = ( visibleFor.indexOf( source ) !== -1 ) ? '' : 'none';
		}
	}

	// Close token-select menus only when the source radio actually changes.
	// Must NOT live in syncSource(): that runs on every form "input" (including
	// typing in the events/categories search), which blurred the field mid-keystroke.
	function closeTokenSelectMenus() {
		var openMenus = form.querySelectorAll( '[data-tecc-ts-menu]:not([hidden])' );
		var tsInputs = form.querySelectorAll( '[data-tecc-ts-input]' );
		var i;
		for ( i = 0; i < openMenus.length; i++ ) {
			openMenus[ i ].hidden = true;
			openMenus[ i ].innerHTML = '';
		}
		for ( i = 0; i < tsInputs.length; i++ ) {
			tsInputs[ i ].setAttribute( 'aria-expanded', 'false' );
			if ( document.activeElement === tsInputs[ i ] ) {
				tsInputs[ i ].blur();
			}
		}
	}

	function syncChoiceClasses() {
		var labels = wrap.querySelectorAll( '.tecc-choice-card, .tecc-choice-pill' );
		var i;
		var input;

		for ( i = 0; i < labels.length; i++ ) {
			input = labels[ i ].querySelector( 'input[type="radio"]' );
			labels[ i ].classList.toggle( 'is-selected', !! ( input && input.checked ) );
		}
	}

	/* -------------------------------------------------------------------------
	 * 4. buildAtts() — mirror the server-side attribute mapping
	 * ---------------------------------------------------------------------- */

	/* Design-attribute defaults (mirror Args::DESIGN + the enum defaults).
	   Only NON-default values are emitted so the shortcode stays short. */
	var ATT_DEFAULTS = {
		'template': 'card',
		'countdown-style': 'box',
		'main-color': '#4395cb',
		'alternate-color': '#ffffff',
		'title-color': '#1d2327',
		'text-color': '#50575e',
		'bg-color': '#ffffff',
		'countdown-size': '28',
		'title-size': '20',
		'body-size': '14',
		'image-position': 'top',
		'image-gap': 'yes',
		'content-align': 'left',
		'radius': '10',
		'padding': '15',
		'border': '0',
		'width': 'auto',
		'shadow': 'no',
		'show-image': 'no',
		'show-venue': 'yes',
		'show-cost': 'no',
		'show-button': 'yes',
		'show-seconds': 'yes',
		'show-divider': 'no',
		'show-timer-label': 'yes',
		'show-status': 'yes',
		'show-ongoing': 'yes'
	};

	function normHex( v ) {
		return String( v || '' ).toLowerCase();
	}

	function buildAtts() {
		var atts = {};
		var source = currentSource();
		var i, checked, ids, val, def;
		var designKeys = [
			'template', 'countdown-style', 'main-color', 'alternate-color', 'title-color',
			'text-color', 'bg-color', 'countdown-size', 'title-size', 'body-size',
			'image-position', 'image-gap', 'content-align', 'radius', 'padding', 'border',
			'width', 'shadow', 'show-image', 'show-venue', 'show-cost', 'show-button',
			'show-seconds', 'show-divider', 'show-timer-label', 'show-status', 'show-ongoing'
		];
		var labelKeys = [ 'main-title', 'ongoing-title', 'ended-title', 'button-text' ];

		// Source model.
		if ( 'category' === source ) {
			atts.source = 'category';
			val = fieldValue( 'tecc_settings[categories]' ).replace( /^\s+|\s+$/g, '' );
			// Empty → blank preview (do not encode as 'all' / next-upcoming).
			atts[ 'source-id' ] = val ? val : '';
		} else if ( 'events' === source ) {
			atts.source = 'events';
			// The token-select renders one hidden future-events-list[] input per
			// selected chip (there are no checkboxes to be ":checked" anymore).
			checked = form.querySelectorAll( 'input[name="tecc_settings[future-events-list][]"]' );
			ids = [];
			for ( i = 0; i < checked.length; i++ ) {
				ids.push( checked[ i ].value );
			}
			// Empty chips → blank preview. Only the Upcoming radio uses 'all'.
			atts[ 'source-id' ] = ids.length ? ids.join( ',' ) : '';
		} else {
			atts.source = 'events';
			atts[ 'source-id' ] = 'all';
		}

		// Design attributes — emit only when they differ from the default.
		for ( i = 0; i < designKeys.length; i++ ) {
			val = fieldValue( 'tecc_settings[' + designKeys[ i ] + ']' );
			// countdown-style is still a radio pill group; template is now a
			// hidden field the preset writes, so read it as a plain field.
			if ( 'countdown-style' === designKeys[ i ] ) {
				val = radioValue( 'tecc_settings[' + designKeys[ i ] + ']', ATT_DEFAULTS[ designKeys[ i ] ] );
			}
			def = ATT_DEFAULTS[ designKeys[ i ] ];
			if ( '' === val ) {
				continue;
			}
			if ( normHex( val ) === normHex( def ) ) {
				continue; // Default — skip.
			}
			atts[ designKeys[ i ] ] = val;
		}

		// Number of events (only when a carousel is requested).
		val = parseInt( fieldValue( 'tecc_settings[no-of-events]' ), 10 ) || 1;
		if ( val > 1 ) {
			atts[ 'no-of-events' ] = String( val );
		}

		// Labels (only when non-empty).
		for ( i = 0; i < labelKeys.length; i++ ) {
			val = fieldValue( 'tecc_settings[' + labelKeys[ i ] + ']' ).replace( /^\s+|\s+$/g, '' );
			if ( val ) {
				atts[ labelKeys[ i ] ] = val;
			}
		}

		return atts;
	}

	/* -------------------------------------------------------------------------
	 * 5. buildShortcode() — deterministic, alphabetically sorted
	 * ---------------------------------------------------------------------- */

	function buildShortcode( atts ) {
		var keys = [];
		var out = '[events-calendar-countdown';
		var key, value, i;

		for ( key in atts ) {
			if ( Object.prototype.hasOwnProperty.call( atts, key ) ) {
				keys.push( key );
			}
		}
		keys.sort();

		for ( i = 0; i < keys.length; i++ ) {
			value = String( atts[ keys[ i ] ] ).replace( /"/g, '' );
			out += ' ' + keys[ i ] + '="' + value + '"';
		}

		return out + ']';
	}

	var shortcodeOutput = document.getElementById( 'tecc-shortcode-output' );

	function updateShortcodeOutput( atts ) {
		if ( shortcodeOutput ) {
			shortcodeOutput.textContent = buildShortcode( atts );
		}
	}

	/* -------------------------------------------------------------------------
	 * 6. Live preview over REST (with loading overlay)
	 * ---------------------------------------------------------------------- */

	// The overlay lives inside the frame's viewport (a grand-parent of the
	// box now), so walk up to the viewport rather than the immediate parent.
	function frameFor( box ) {
		return ( box && box.closest ) ? box.closest( '.tecc-preview-frame' ) : null;
	}

	function overlayFor( box ) {
		var frame = frameFor( box );
		return frame ? frame.querySelector( '.tecc-preview-overlay' ) : null;
	}

	// Flip the top-bar status pill between "Live preview" and "Updating…".
	function setStatus( box, updating ) {
		var frame = frameFor( box );
		var pill = frame ? frame.querySelector( '[data-tecc-preview-status]' ) : null;
		var text = pill ? pill.querySelector( '.tecc-preview-status-text' ) : null;
		if ( pill ) {
			pill.classList.toggle( 'is-updating', !! updating );
		}
		if ( text ) {
			text.textContent = updating ? ( i18n.updating || 'Updating preview…' ) : ( i18n.live || 'Live preview' );
		}
	}

	function showOverlay( box ) {
		var overlay = overlayFor( box );
		if ( overlay ) {
			overlay.classList.add( 'is-visible' );
		}
		if ( box ) {
			box.setAttribute( 'aria-busy', 'true' );
		}
		setStatus( box, true );
	}

	function hideOverlay( box ) {
		var overlay = overlayFor( box );
		if ( overlay ) {
			overlay.classList.remove( 'is-visible' );
		}
		if ( box ) {
			box.setAttribute( 'aria-busy', 'false' );
		}
		setStatus( box, false );
	}

	/* -------------------------------------------------------------------------
	 * Preview frame width controls (Zoomed out / Desktop 1280)
	 * ---------------------------------------------------------------------- */

	function wireFrame( frame ) {
		var viewport = frame.querySelector( '.tecc-preview-viewport' );
		var scaler = frame.querySelector( '.tecc-preview-scaler' );
		var box = frame.querySelector( '.tecc-preview-box' );
		var btns = frame.querySelectorAll( '[data-tecc-pw]' );
		// The starting mode is whichever button PHP marked active, so the two
		// can never drift and each frame can default differently.
		var activeBtn = frame.querySelector( '[data-tecc-pw].is-active' );
		var mode = activeBtn ? activeBtn.getAttribute( 'data-tecc-pw' ) : '1280';
		var i, mo;

		if ( ! viewport || ! scaler || ! box ) {
			return;
		}

		// Desktop mode scrolls a real-size page mock; bring the positioned
		// sitewide display into view so "footer right" lands bottom-right, etc.
		function scrollToDisplay() {
			var display = box.querySelector( '.tecc-cd' );
			if ( ! display ) {
				return;
			}
			viewport.scrollLeft = Math.max( 0, display.offsetLeft + ( display.offsetWidth / 2 ) - ( viewport.clientWidth / 2 ) );
			viewport.scrollTop = Math.max( 0, display.offsetTop + ( display.offsetHeight / 2 ) - ( viewport.clientHeight / 2 ) );
		}

		function apply() {
			var avail = viewport.clientWidth;
			// A location-positioned sitewide "page mock": size the box to a real
			// desktop-width PAGE (with a page-ish height) and position the display
			// within it. Zoomed out scales the whole page to fit; Desktop is
			// pixel-true at 1280 (the viewport scrolls). This reconciles the
			// location stage with the width modes instead of disabling them.
			if ( box.className.indexOf( 'tecc-preview-stage' ) !== -1 ) {
				var pageW  = 1280;
				var pageH  = Math.round( pageW * 0.62 );
				box.style.width = pageW + 'px';
				box.style.height = pageH + 'px';
				if ( 'fit' === mode ) {
					var s = Math.min( 1, avail / pageW );
					scaler.style.transform = 'scale(' + s + ')';
					scaler.style.width = pageW + 'px';
					scaler.style.height = ( pageH * s ) + 'px';
				} else {
					scaler.style.transform = '';
					scaler.style.width = '';
					scaler.style.height = '';
					scrollToDisplay();
				}
				return;
			}
			if ( 'fit' === mode ) {
				// Zoomed out: the full desktop layout scaled DOWN to fit the pane
				// so it's all visible at once, no scroll.
				var target = 1280;
				var scale = Math.min( 1, avail / target );
				box.style.width = target + 'px';
				scaler.style.transform = 'scale(' + scale + ')';
				scaler.style.width = target + 'px';
				scaler.style.height = ( box.offsetHeight * scale ) + 'px';
			} else {
				// Desktop: pixel-true at the device width; the viewport scrolls
				// horizontally when it's wider than the pane.
				box.style.width = '1280px';
				scaler.style.transform = '';
				scaler.style.width = '';
				scaler.style.height = '';
			}
		}

		function onBtn() {
			var j;
			mode = this.getAttribute( 'data-tecc-pw' );
			for ( j = 0; j < btns.length; j++ ) {
				btns[ j ].classList.toggle( 'is-active', btns[ j ] === this );
			}
			apply();
		}

		for ( i = 0; i < btns.length; i++ ) {
			btns[ i ].addEventListener( 'click', onBtn );
		}

		// Re-fit whenever the rendered content is replaced (either preview JS
		// swaps the box's children) — no cross-file coupling needed.
		if ( window.MutationObserver ) {
			mo = new MutationObserver( function() { apply(); } );
			mo.observe( box, { childList: true } );
		}
		window.addEventListener( 'resize', apply );

		apply();
	}

	function initPreviewFrames() {
		var frames = document.querySelectorAll( '.tecc-preview-frame' );
		var f;
		for ( f = 0; f < frames.length; f++ ) {
			wireFrame( frames[ f ] );
		}
	}

	function showPreviewError() {
		var p;

		if ( ! previewBox ) {
			return;
		}

		p = document.createElement( 'p' );
		p.className = 'tecc-preview-error';
		p.textContent = i18n.previewError || 'Preview unavailable.';
		previewBox.innerHTML = '';
		previewBox.appendChild( p );
	}

	function showPreviewBlank( message ) {
		if ( ! previewBox ) {
			return;
		}
		var p = document.createElement( 'p' );
		p.className = 'tecc-preview-empty';
		p.textContent = message || i18n.selectEvent || 'Select an event to see the preview.';
		previewBox.innerHTML = '';
		previewBox.appendChild( p );
	}

	function needsEventSelection( atts ) {
		if ( ! atts || ! atts.source ) {
			return false;
		}
		// Upcoming radio encodes source=events + source-id=all — that is a real
		// choice and must still preview. Empty source-id means the Specific
		// events / Category picker has nothing selected yet.
		if ( 'events' === atts.source || 'category' === atts.source ) {
			return ! atts[ 'source-id' ];
		}
		return false;
	}

	function requestPreview( atts ) {
		var requestId;

		// No place to render / no transport -> clear the overlay and bail so
		// it can never get stuck visible.
		if ( ! previewBox || ! config.restUrl || ! window.fetch ) {
			hideOverlay( previewBox );
			return;
		}

		if ( needsEventSelection( atts ) ) {
			showPreviewBlank( i18n.selectEvent );
			hideOverlay( previewBox );
			return;
		}

		previewRequestId++;
		requestId = previewRequestId;

		window.fetch( config.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || ''
			},
			body: JSON.stringify( { atts: atts } )
		} ).then( function( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			return response.json();
		} ).then( function( data ) {
			if ( requestId !== previewRequestId ) {
				return; // A newer request superseded this one; it owns the overlay.
			}
			// Server-rendered, trusted same-site admin content (Renderer escapes).
			previewBox.innerHTML = ( data && typeof data.html === 'string' ) ? data.html : '';
			if ( window.teccCountdown && typeof window.teccCountdown.scan === 'function' ) {
				window.teccCountdown.scan( previewBox );
			}
			if ( window.teccCarousel && typeof window.teccCarousel.scan === 'function' ) {
				window.teccCarousel.scan( previewBox );
			}
			hideOverlay( previewBox );
		} ).catch( function() {
			if ( requestId === previewRequestId ) {
				showPreviewError();
				hideOverlay( previewBox );
			}
		} );
	}

	function refresh( immediate ) {
		var atts = buildAtts();

		updateShortcodeOutput( atts );

		// The shortcode preview only lives on the Shortcode tab.
		if ( 'shortcode' !== currentTab ) {
			return;
		}

		showOverlay( previewBox ); // Instant feedback while the fetch is debounced.

		if ( previewTimer ) {
			window.clearTimeout( previewTimer );
			previewTimer = null;
		}

		if ( immediate ) {
			requestPreview( atts );
			return;
		}

		previewTimer = window.setTimeout( function() {
			previewTimer = null;
			requestPreview( buildAtts() );
		}, DEBOUNCE_MS );
	}

	// Style-only fields that map 1:1 to a CSS custom property on the rendered
	// card — changing them needs NO markup, so we update the live preview's vars
	// in place instead of a REST re-render (instant, no network). Anything that
	// changes markup/structure (source, template, toggles, alignment, bg
	// transparency, width) still goes through refresh().
	var LIVE_STYLE = {
		'main-color': { v: '--tecc-main' },
		'alternate-color': { v: '--tecc-alt' },
		'title-color': { v: '--tecc-title-color' },
		'text-color': { v: '--tecc-text-color' },
		'countdown-size': { v: '--tecc-cd-size', px: true },
		'title-size': { v: '--tecc-title-size', px: true },
		'body-size': { v: '--tecc-body-size', px: true },
		'radius': { v: '--tecc-radius', px: true },
		'padding': { v: '--tecc-pad', px: true },
		'border': { v: '--tecc-border', px: true }
	};

	// The card/banner templates switch the unit labels to D/H/M/S when the
	// countdown is inline OR the size is <= 20px. The live-var update below
	// doesn't re-render, so mirror that switch in the preview DOM here.
	function updatePreviewUnitLabels() {
		if ( ! previewBox || ! config.units ) {
			return;
		}
		var size = parseInt( fieldValue( 'tecc_settings[countdown-size]' ), 10 ) || 28;
		var style = radioValue( 'tecc_settings[countdown-style]', 'box' );
		var map = ( 'inline' === style || ( size > 0 && size <= 20 ) ) ? config.units.short : config.units.full;
		var wraps = previewBox.querySelectorAll( '.tecc-cd__unit' );
		for ( var i = 0; i < wraps.length; i++ ) {
			var unit = wraps[ i ].getAttribute( 'data-tecc-unit-wrap' );
			var label = wraps[ i ].querySelector( '.tecc-cd__label' );
			if ( label && map[ unit ] ) {
				label.textContent = map[ unit ];
			}
		}
	}

	function applyLiveStyle( target ) {
		if ( ! previewBox || ! target || ! target.name || 'shortcode' !== currentTab ) {
			return false;
		}
		var m = /^tecc_settings\[([^\]]+)\]$/.exec( target.name );
		if ( ! m || ! LIVE_STYLE[ m[1] ] ) {
			return false;
		}
		// Nothing rendered yet (or an error state) -> let refresh() do a full render.
		var els = previewBox.querySelectorAll( '.tecc-cd' );
		if ( ! els.length ) {
			return false;
		}
		var spec = LIVE_STYLE[ m[1] ];
		var val = fieldValue( 'tecc_settings[' + m[1] + ']' );
		if ( spec.px ) {
			val = ( parseInt( val, 10 ) || 0 ) + 'px';
		}
		// The design vars ride inline on every .tecc-cd (wrapper + carousel
		// slides); custom props inherit, so setting them cascades to the timer.
		for ( var i = 0; i < els.length; i++ ) {
			els[ i ].style.setProperty( spec.v, val );
		}
		// Crossing the size threshold flips the unit labels (days <-> D).
		if ( 'countdown-size' === m[1] ) {
			updatePreviewUnitLabels();
		}
		// The border only paints when the card carries the --bordered class
		// (added server-side when border > 0); toggle it so a fresh preset whose
		// border was 0 shows the new border live instead of waiting on a render.
		if ( 'border' === m[1] ) {
			var bordered = ( parseInt( val, 10 ) || 0 ) > 0;
			for ( i = 0; i < els.length; i++ ) {
				els[ i ].classList.toggle( 'tecc-cd--bordered', bordered );
			}
		}
		return true;
	}

	function onFormMutation( e ) {
		var target = e && e.target ? e.target : null;
		// Search box inside the events/categories token-select — typing must not
		// refresh the shortcode/preview (only chip add/remove should).
		if ( target && target.getAttribute && null !== target.getAttribute( 'data-tecc-ts-input' ) ) {
			return;
		}
		if ( target && target.name === 'tecc_source_ui' ) {
			closeTokenSelectMenus();
		}
		syncSource();
		syncChoiceClasses();
		// Pure style tweak -> update the preview's CSS vars live (no re-render),
		// but still refresh the copyable shortcode string.
		if ( e && applyLiveStyle( target ) ) {
			updateShortcodeOutput( buildAtts() );
			return;
		}
		refresh( false );
	}

	/* -------------------------------------------------------------------------
	 * 7. Copy shortcode
	 * ---------------------------------------------------------------------- */

	function initCopyButton() {
		// Two triggers, one behaviour: the button under the shortcode and the one
		// in the preview chrome. The chrome button confirms IN PLACE (its own label
		// swaps), so the confirmation is where the click was.
		var triggers = document.querySelectorAll( '#tecc-copy-shortcode, [data-tecc-preview-copy]' );
		var feedback = document.querySelector( '.tecc-copy-feedback' );
		var feedbackTimer = null;
		var i;

		if ( ! triggers.length || ! shortcodeOutput ) {
			return;
		}

		// The polite live region stays the announcement channel for assistive tech;
		// a swapped button label alone is not reliably announced.
		function announce() {
			if ( ! feedback ) {
				return;
			}
			feedback.textContent = i18n.copied || 'Copied!';
			if ( feedbackTimer ) {
				window.clearTimeout( feedbackTimer );
			}
			feedbackTimer = window.setTimeout( function() {
				feedback.textContent = '';
				feedbackTimer = null;
			}, 2000 );
		}

		// Swap this trigger's own label to the confirmation, then put it back.
		function flash( btn ) {
			var label = btn.querySelector( '[data-tecc-copy-label]' );
			if ( ! label ) {
				return;
			}
			if ( btn.teccCopyTimer ) {
				window.clearTimeout( btn.teccCopyTimer );
			}
			if ( ! btn.teccCopyIdle ) {
				btn.teccCopyIdle = label.textContent;
			}
			label.textContent = i18n.copiedPaste || 'Copied! Paste it on your page now.';
			btn.className += ' is-copied';
			btn.teccCopyTimer = window.setTimeout( function() {
				label.textContent = btn.teccCopyIdle;
				btn.className = btn.className.replace( / ?is-copied/, '' );
				btn.teccCopyTimer = null;
			}, 2600 );
		}

		function copied( btn ) {
			announce();
			flash( btn );
		}

		function fallbackCopy( text, btn ) {
			var area = document.createElement( 'textarea' );

			area.value = text;
			area.setAttribute( 'readonly', 'readonly' );
			area.style.position = 'absolute';
			area.style.insetInlineStart = '-9999px';
			area.style.left = '-9999px';
			document.body.appendChild( area );
			area.select();

			try {
				if ( document.execCommand( 'copy' ) ) {
					copied( btn );
				}
			} catch ( err ) {
				// Clipboard unavailable; leave the shortcode selectable.
			}

			document.body.removeChild( area );
		}

		function wire( btn ) {
			btn.addEventListener( 'click', function() {
				var text = shortcodeOutput.textContent || '';

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then(
						function() {
							copied( btn );
						},
						function() {
							fallbackCopy( text, btn );
						}
					);
				} else {
					fallbackCopy( text, btn );
				}
			} );
		}

		for ( i = 0; i < triggers.length; i++ ) {
			wire( triggers[ i ] );
		}
	}

	/* -------------------------------------------------------------------------
	 * Boot
	 * ---------------------------------------------------------------------- */

	// Mirror any range slider's value into its paired <output> live. The output
	// id is named in the slider's data-tecc-range attribute. Preview refresh is
	// handled separately by the form 'input' listener.
	function initRanges() {
		var ranges = form.querySelectorAll( 'input[type="range"][data-tecc-range]' );
		for ( var i = 0; i < ranges.length; i++ ) {
			( function( slider ) {
				var out = document.getElementById( slider.getAttribute( 'data-tecc-range' ) );
				if ( ! out ) {
					return;
				}
				var sync = function() {
					out.textContent = slider.value;
				};
				slider.addEventListener( 'input', sync );
				sync();
			}( ranges[ i ] ) );
		}
	}


	// The three status-title fields only make sense while the status label is
	// shown, so reveal/hide them with the show-status toggle.
	function initStatusFields() {
		var toggle = form.querySelector( 'input[type="checkbox"][name="tecc_settings[show-status]"]' );
		var fields = form.querySelector( '[data-tecc-status-fields]' );
		if ( ! toggle || ! fields ) {
			return;
		}
		var sync = function() {
			fields.hidden = ! toggle.checked;
		};
		toggle.addEventListener( 'change', sync );
		sync();
	}

	// Fade out the "Settings saved" confirmation after a few seconds.
	function initSavedNotice() {
		var notice = document.querySelector( '[data-tecc-saved-notice]' );
		if ( ! notice ) {
			return;
		}
		window.setTimeout( function() {
			notice.style.opacity = '0';
			window.setTimeout( function() {
				if ( notice.parentNode ) {
					notice.parentNode.removeChild( notice );
				}
			}, 450 );
		}, 4000 );
	}

	// Button-text field only applies while the event button is shown.
	function initButtonField() {
		var toggle = form.querySelector( 'input[type="checkbox"][name="tecc_settings[show-button]"]' );
		var field = form.querySelector( '[data-tecc-button-field]' );
		if ( ! toggle || ! field ) {
			return;
		}
		var sync = function() {
			field.hidden = ! toggle.checked;
		};
		toggle.addEventListener( 'change', sync );
		sync();
	}

	function init() {
		initTabs();
		initSubTabs();
		initCopyButton();
		initPreviewFrames();
		initRanges();
		initStatusFields();
		initButtonField();
		initSavedNotice();

		form.addEventListener( 'input', onFormMutation );
		form.addEventListener( 'change', onFormMutation );

		syncSource();
		syncChoiceClasses();

		// Only the shortcode tab owns #tecc-preview; fire its first render only
		// when it is the active tab (the sitewide tab handles its own).
		updateShortcodeOutput( buildAtts() );
		if ( 'shortcode' === currentTab ) {
			refresh( true );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
