/**
 * Display Rules admin builder (Phase 4).
 *
 * Renders rule cards from the JSON stored in #tecc-rules-json, keeps the DOM
 * as the source of truth, and serializes the cards back into the hidden field
 * on every change and on form submit. The server-side sanitizer
 * (includes/display/class-tecc-rules.php) validates everything on save.
 *
 * Vanilla ES5. No jQuery. No innerHTML from data.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */
( function() {
	'use strict';

	var MAX_RULES      = 50;
	var MAX_CONDITIONS = 20;

	/* Per-rule live-preview state (Sitewide Display tab). */
	var appEl        = null;
	var fieldEl      = null;
	var previewBoxEl = null;
	var previewTimer = null;
	var previewReqId = 0;
	var activeCard   = null;
	var cardSeq      = 0;

	/**
	 * Condition types that show a value input.
	 */
	var VALUE_TYPES = {
		post_type: 1,
		page_id: 1,
		post_id: 1,
		tec_category: 1,
		tec_tag: 1,
		tec_venue: 1,
		url_contains: 1
	};

	/**
	 * Translate helper — uses wp.i18n when the lead enqueues it, falls back
	 * to the source string.
	 *
	 * @param {string} text Source string.
	 * @return {string}
	 */
	function t( text ) {
		if ( window.wp && window.wp.i18n && window.wp.i18n.__ ) {
			return window.wp.i18n.__( text, 'countdown-for-the-events-calendar' );
		}
		return text;
	}

	function init() {
		var app   = document.getElementById( 'tecc-rules-app' );
		var field = document.getElementById( 'tecc-rules-json' );
		var tpl   = document.getElementById( 'tecc-rule-template' );

		if ( ! app || ! field || ! tpl ) {
			return;
		}

		appEl        = app;
		fieldEl      = field;
		previewBoxEl = document.getElementById( 'tecc-rules-preview' );

		var rules  = parseJson( field.value, [] );
		var i;

		if ( Object.prototype.toString.call( rules ) !== '[object Array]' ) {
			rules = [];
		}

		for ( i = 0; i < rules.length; i++ ) {
			addCard( app, tpl, rules[ i ], true );
		}

		wireApp( app, tpl, field );
		serialize( app, field );

		// Let the settings module trigger a preview when the tab is shown.
		window.teccRules = {
			onTab: function( name ) {
				if ( 'sitewide' === name ) {
					previewActive();
				}
			}
		};

		// Preview immediately if the Sitewide tab is the active tab on load.
		var panel = document.querySelector( '[data-tecc-tab-panel="sitewide"]' );
		if ( panel && ( ' ' + panel.className + ' ' ).indexOf( ' is-active ' ) !== -1 ) {
			previewActive();
		}
	}

	/* -------------------------------------------------------------------------
	 * Per-rule sub-tabs (Display Conditions | Event Source | Styles)
	 * ---------------------------------------------------------------------- */

	function toggleRuleSubtab( card, name ) {
		var buttons = card.querySelectorAll( '.tecc-rule-subtab-button' );
		var panels  = card.querySelectorAll( '.tecc-rule-subpanel' );
		var i, isActive;

		for ( i = 0; i < buttons.length; i++ ) {
			isActive = buttons[ i ].getAttribute( 'data-tecc-rule-subtab' ) === name;
			buttons[ i ].classList.toggle( 'is-active', isActive );
			buttons[ i ].setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			buttons[ i ].setAttribute( 'tabindex', isActive ? '0' : '-1' );
		}
		for ( i = 0; i < panels.length; i++ ) {
			panels[ i ].classList.toggle( 'is-active', panels[ i ].getAttribute( 'data-tecc-rule-subtab-panel' ) === name );
		}
	}

	/**
	 * Pair per-rule sub-tabs to their panels with unique ids (cloned cards
	 * can't share static ids) and add roving-tabindex + arrow-key navigation
	 * (WAI-ARIA Tabs pattern), matching the other two tablist levels.
	 *
	 * @param {Element} card    Rule card.
	 * @param {number}  cardIx  Unique card index (for id namespacing).
	 */
	function initRuleSubtabs( card, cardIx ) {
		var buttons = card.querySelectorAll( '.tecc-rule-subtab-button' );
		var panels  = card.querySelectorAll( '.tecc-rule-subpanel' );
		var i;

		for ( i = 0; i < panels.length; i++ ) {
			var pname = panels[ i ].getAttribute( 'data-tecc-rule-subtab-panel' );
			panels[ i ].id = 'tecc-rsp-' + cardIx + '-' + pname;
			panels[ i ].setAttribute( 'aria-labelledby', 'tecc-rst-' + cardIx + '-' + pname );
			panels[ i ].setAttribute( 'tabindex', '0' );
		}

		for ( i = 0; i < buttons.length; i++ ) {
			var bname = buttons[ i ].getAttribute( 'data-tecc-rule-subtab' );
			buttons[ i ].id = 'tecc-rst-' + cardIx + '-' + bname;
			buttons[ i ].setAttribute( 'aria-controls', 'tecc-rsp-' + cardIx + '-' + bname );
			buttons[ i ].setAttribute( 'tabindex', buttons[ i ].classList.contains( 'is-active' ) ? '0' : '-1' );

			( function( index ) {
				buttons[ index ].addEventListener( 'keydown', function( event ) {
					var target = null;
					if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
						target = buttons[ ( index + 1 ) % buttons.length ];
					} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
						target = buttons[ ( index - 1 + buttons.length ) % buttons.length ];
					} else if ( 'Home' === event.key ) {
						target = buttons[ 0 ];
					} else if ( 'End' === event.key ) {
						target = buttons[ buttons.length - 1 ];
					}
					if ( target ) {
						event.preventDefault();
						toggleRuleSubtab( card, target.getAttribute( 'data-tecc-rule-subtab' ) );
						target.focus();
					}
				} );
			}( i ) );
		}
	}

	/* -------------------------------------------------------------------------
	 * Sitewide status badge (Active when >= 1 enabled rule)
	 * ---------------------------------------------------------------------- */

	function updateBadge() {
		var badge = document.getElementById( 'tecc-sitewide-badge' );
		var cards, i, cb, active;

		if ( ! badge || ! appEl ) {
			return;
		}

		cards  = appEl.querySelectorAll( '.tecc-rule-card' );
		active = false;
		for ( i = 0; i < cards.length; i++ ) {
			cb = cards[ i ].querySelector( '.tecc-rule-enabled' );
			if ( cb && cb.checked ) {
				active = true;
				break;
			}
		}

		badge.textContent = active ? t( 'Active' ) : t( 'Inactive' );
		badge.setAttribute( 'data-state', active ? 'active' : 'inactive' );
		badge.className = 'tecc-badge ' + ( active ? 'is-active' : 'is-inactive' );
	}

	/* -------------------------------------------------------------------------
	 * Per-rule live preview (shares teccAdminSettings restUrl + nonce)
	 * ---------------------------------------------------------------------- */

	function setActiveCard( card ) {
		if ( card ) {
			activeCard = card;
		}
	}

	function previewActive() {
		if ( ! appEl ) {
			return;
		}
		if ( ! activeCard || ! activeCard.parentNode ) {
			activeCard = appEl.querySelector( '.tecc-rule-card' );
		}
		if ( activeCard ) {
			previewCard( activeCard );
			return;
		}

		// No displays at all: there is nothing to fetch, so the server-rendered
		// "Loading preview" placeholder would sit there forever and read as broken.
		showRulesEmpty();
	}

	/**
	 * Replace the preview with the no-displays-yet message.
	 */
	function showRulesEmpty() {
		var cfg = window.teccAdminSettings || {};
		var i18n = cfg.i18n || {};
		var box = previewBoxEl || document.getElementById( 'tecc-rules-preview' );
		var p;

		if ( ! box ) {
			return;
		}

		hideRulesOverlay();
		box.innerHTML = '';
		p = document.createElement( 'p' );
		p.className = 'tecc-preview-empty';
		p.textContent = i18n.noDisplays || 'No sitewide displays yet. Add one and it will preview here.';
		box.appendChild( p );
	}

	function schedulePreview() {
		var cfg = window.teccAdminSettings || {};
		var delay = parseInt( cfg.debounce, 10 ) || 200;

		showRulesOverlay(); // Instant feedback while the fetch is debounced.

		if ( previewTimer ) {
			window.clearTimeout( previewTimer );
		}
		previewTimer = window.setTimeout( function() {
			previewTimer = null;
			if ( activeCard && activeCard.parentNode ) {
				previewCard( activeCard );
			}
		}, delay );
	}

	function rulesOverlay() {
		// The overlay now lives in the browser-frame viewport (a grand-parent
		// of the box), so resolve it from the enclosing frame.
		var frame = ( previewBoxEl && previewBoxEl.closest ) ? previewBoxEl.closest( '.tecc-preview-frame' ) : null;
		return frame ? frame.querySelector( '.tecc-preview-overlay' ) : null;
	}

	function showRulesOverlay() {
		var o = rulesOverlay();
		if ( o ) {
			o.classList.add( 'is-visible' );
		}
		if ( previewBoxEl ) {
			previewBoxEl.setAttribute( 'aria-busy', 'true' );
		}
	}

	function hideRulesOverlay() {
		var o = rulesOverlay();
		if ( o ) {
			o.classList.remove( 'is-visible' );
		}
		if ( previewBoxEl ) {
			previewBoxEl.setAttribute( 'aria-busy', 'false' );
		}
	}

	function showRulesError() {
		var cfg = window.teccAdminSettings || {};
		var i18n = cfg.i18n || {};
		var p;

		if ( ! previewBoxEl ) {
			return;
		}
		p = document.createElement( 'p' );
		p.className = 'tecc-preview-error';
		p.textContent = i18n.previewError || 'Preview unavailable.';
		previewBoxEl.innerHTML = '';
		previewBoxEl.appendChild( p );
	}

	// Valid location slugs the preview stage can position for.
	var PREVIEW_LOCATIONS = [ 'top-bar', 'footer-bar', 'footer-middle', 'footer-left', 'footer-right' ];

	// Turn the sitewide preview box into a "page mock" and mark WHERE the display
	// sits, so the CSS can place it in a corner / flush bar / floating middle.
	function applyPreviewLocation( location ) {
		if ( ! previewBoxEl ) {
			return;
		}
		var cls = previewBoxEl.className.replace( /(^|\s)tecc-(preview-stage|loc-[a-z-]+)(?=\s|$)/g, ' ' ).replace( /\s+/g, ' ' ).replace( /^\s+|\s+$/g, '' );
		if ( PREVIEW_LOCATIONS.indexOf( location ) !== -1 ) {
			cls += ( cls ? ' ' : '' ) + 'tecc-preview-stage tecc-loc-' + location;
		}
		previewBoxEl.className = cls;
	}

	function previewCard( card ) {
		var cfg = window.teccAdminSettings || {};
		var rule, reqId;

		if ( previewTimer ) {
			window.clearTimeout( previewTimer );
			previewTimer = null;
		}

		if ( ! card || ! previewBoxEl || ! cfg.restUrl || ! window.fetch ) {
			hideRulesOverlay();
			return;
		}

		rule = serializeRule( card, 0 );
		showRulesOverlay();
		previewReqId++;
		reqId = previewReqId;

		window.fetch( cfg.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce || ''
			},
			body: JSON.stringify( { rule: rule } )
		} ).then( function( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			return response.json();
		} ).then( function( data ) {
			if ( reqId !== previewReqId ) {
				return;
			}
			// Position the preview stage to match the chosen location (corner
			// card, flush top/bottom bar, or floating middle).
			applyPreviewLocation( rule.location );
			// Server-rendered, trusted same-site admin content (Renderer escapes).
			previewBoxEl.innerHTML = ( data && typeof data.html === 'string' ) ? data.html : '';
			if ( window.teccCountdown && typeof window.teccCountdown.scan === 'function' ) {
				window.teccCountdown.scan( previewBoxEl );
			}
			if ( window.teccCarousel && typeof window.teccCarousel.scan === 'function' ) {
				window.teccCarousel.scan( previewBoxEl );
			}
			hideRulesOverlay();
		} ).catch( function() {
			if ( reqId === previewReqId ) {
				showRulesError();
				hideRulesOverlay();
			}
		} );
	}

	/**
	 * @param {string} raw      JSON string.
	 * @param {*}      fallback Returned when parsing fails.
	 * @return {*}
	 */
	function parseJson( raw, fallback ) {
		if ( ! raw ) {
			return fallback;
		}
		try {
			var parsed = JSON.parse( raw );
			return ( parsed === null || typeof parsed !== 'object' ) ? fallback : parsed;
		} catch ( e ) {
			return fallback;
		}
	}

	/**
	 * Clone the rule template, populate it from a rule object, append it.
	 *
	 * @param {Element} app       App container.
	 * @param {Element} tpl       #tecc-rule-template.
	 * @param {Object}  rule      Rule object (may be a fresh default).
	 * @param {boolean} collapsed Start collapsed (existing rules) or expanded (new).
	 */
	function addCard( app, tpl, rule, collapsed ) {
		var fragment = document.importNode( tpl.content, true );
		var card     = fragment.querySelector( '.tecc-rule-card' );

		if ( ! card ) {
			return;
		}

		rule = rule || {};

		card.setAttribute( 'data-tecc-rule-id', typeof rule.id === 'string' ? rule.id : '' );

		setChecked( card, '.tecc-rule-enabled', rule.enabled !== false );
		setValue( card, '.tecc-rule-location', rule.location || 'footer-bar' );
		setValue( card, '.tecc-rule-source', rule.source || 'next-upcoming' );

		// Carousel drives limit: > 1 means carousel on with that count (2..5).
		var lim = parseInt( rule.limit, 10 ) || 1;
		setChecked( card, '.tecc-rule-carousel', lim > 1 );
		setValue( card, '.tecc-rule-limit', String( Math.max( 2, Math.min( 5, lim > 1 ? lim : 2 ) ) ) );
		setValue( card, '.tecc-rule-close-action', rule.close_action || 'event' );
		setValue( card, '.tecc-rule-style', rule.style || 'dark' );
		setValue( card, '.tecc-rule-cd-style', rule.countdown_style || 'box' );
		setValue( card, '.tecc-rule-cd-size', String( parseInt( rule.countdown_size, 10 ) || 28 ) );

		var filter = ( rule.filter && typeof rule.filter === 'object' ) ? rule.filter : {};
		setValue( card, '.tecc-rule-categories', listToCsv( filter.category ) );
		setValue( card, '.tecc-rule-event-ids', listToCsv( rule.event_ids || [] ) );

		var ruleDesign = ( rule.design && typeof rule.design === 'object' ) ? rule.design : {};
		seedDesign( card, rule.style || 'dark', ruleDesign );
		// Size/spacing sliders + width + image position (independent of Light/Dark).
		var dimKeys = [ 'title_size', 'body_size', 'radius', 'border', 'padding' ];
		for ( var di = 0; di < dimKeys.length; di++ ) {
			if ( ruleDesign[ dimKeys[ di ] ] != null ) {
				setValue( card, '[data-tecc-design="' + dimKeys[ di ] + '"]', String( ruleDesign[ dimKeys[ di ] ] ) );
			}
		}
		setValue( card, '[data-tecc-design="width"]', ruleDesign.width || 'auto' );
		setValue( card, '.tecc-rule-imgpos', rule.image_position || 'none' );

		var conditions = ( rule.conditions && typeof rule.conditions === 'object' ) ? rule.conditions : {};
		populateConditions( card, 'include', conditions.include || [ { type: 'entire_site', value: '' } ] );
		populateConditions( card, 'exclude', conditions.exclude || [] );

		toggleSource( card );
		toggleCarousel( card );
		syncRangeOutputs( card );
		updateSummary( card );
		setCollapsed( card, !! collapsed );
		updateDisabledClass( card );
		initRuleSubtabs( card, ++cardSeq );

		app.appendChild( fragment );
	}

	function setValue( scope, selector, value ) {
		var el = scope.querySelector( selector );
		if ( el ) {
			el.value = value;
		}
	}

	function setChecked( scope, selector, checked ) {
		var el = scope.querySelector( selector );
		if ( el ) {
			el.checked = !! checked;
		}
	}

	/**
	 * @param {*} list Array of slugs/ids (validated shape) or CSV string.
	 * @return {string} CSV for the text input.
	 */
	function listToCsv( list ) {
		if ( typeof list === 'string' ) {
			return list;
		}
		if ( Object.prototype.toString.call( list ) === '[object Array]' ) {
			return list.join( ', ' );
		}
		return '';
	}


	/**
	 * Fill one condition bucket with rows.
	 *
	 * @param {Element} card   Rule card.
	 * @param {string}  bucket 'include' | 'exclude'.
	 * @param {Array}   list   [{type,value}].
	 */
	function populateConditions( card, bucket, list ) {
		var i;
		for ( i = 0; i < list.length; i++ ) {
			if ( list[ i ] && typeof list[ i ].type === 'string' ) {
				addConditionRow( card, bucket, list[ i ].type, list[ i ].value );
			}
		}
	}

	/**
	 * Append one condition row (cloned from the card's nested template).
	 *
	 * @param {Element} card   Rule card.
	 * @param {string}  bucket 'include' | 'exclude'.
	 * @param {string}  type   Condition type.
	 * @param {*}       value  Stored value (string or list).
	 */
	function addConditionRow( card, bucket, type, value ) {
		var group = card.querySelector( '.tecc-rule-conditions-group[data-tecc-bucket="' + bucket + '"]' );
		var tpl   = card.querySelector( '.tecc-condition-template' );

		if ( ! group || ! tpl ) {
			return;
		}

		var list     = group.querySelector( '.tecc-rule-condition-list' );
		var fragment = document.importNode( tpl.content, true );
		var row      = fragment.querySelector( '.tecc-condition-row' );

		if ( ! list || ! row ) {
			return;
		}

		var typeSelect = row.querySelector( '.tecc-condition-type' );
		var valueInput = row.querySelector( '.tecc-condition-value' );

		if ( typeSelect ) {
			typeSelect.value = type || 'entire_site';
		}
		if ( valueInput ) {
			valueInput.value = listToCsv( value ) || ( typeof value === 'string' ? value : '' );
		}

		toggleConditionValue( row );
		list.appendChild( fragment );
	}

	/**
	 * Show the value input only for value-typed conditions.
	 *
	 * @param {Element} row Condition row.
	 */
	function toggleConditionValue( row ) {
		var typeSelect = row.querySelector( '.tecc-condition-type' );
		var valueInput = row.querySelector( '.tecc-condition-value' );

		if ( ! typeSelect || ! valueInput ) {
			return;
		}
		valueInput.style.display = VALUE_TYPES[ typeSelect.value ] ? '' : 'none';
	}

	/**
	 * Show/hide the filtered / selected sub-sections for the chosen source.
	 *
	 * @param {Element} card Rule card.
	 */
	function toggleSource( card ) {
		var source   = card.querySelector( '.tecc-rule-source' );
		var filtered = card.querySelector( '.tecc-rule-filtered' );
		var selected = card.querySelector( '.tecc-rule-selected' );
		var value    = source ? source.value : '';

		if ( filtered ) {
			filtered.style.display = ( 'filtered' === value ) ? '' : 'none';
		}
		if ( selected ) {
			selected.style.display = ( 'selected' === value ) ? '' : 'none';
		}
	}

	// Locations that force a banner (single event only) — the carousel is a
	// corner-card feature, so it's hidden for these.
	var BANNER_LOCATIONS = [ 'top-bar', 'footer-bar', 'footer-middle' ];

	// Mirror every range slider's value into its paired <output> (limit + timer
	// size). Cards are cloned after init, so this is card-scoped, not global.
	function syncRangeOutputs( card ) {
		var ranges = card.querySelectorAll( '.tecc-range' );
		var i, out;
		for ( i = 0; i < ranges.length; i++ ) {
			out = ranges[ i ].parentNode ? ranges[ i ].parentNode.querySelector( '.tecc-range__value' ) : null;
			if ( out ) {
				out.textContent = ranges[ i ].value;
			}
		}
	}

	// Carousel toggle reveals the 2..5 count slider and keeps its readout in
	// sync. The whole carousel control is hidden on banner-forced locations,
	// where the server clamps limit to 1 regardless.
	function toggleCarousel( card ) {
		var location  = card.querySelector( '.tecc-rule-location' );
		var carousel  = card.querySelector( '.tecc-rule-carousel-field' );
		var on        = card.querySelector( '.tecc-rule-carousel' );
		var field     = card.querySelector( '.tecc-rule-limit-field' );
		var range     = card.querySelector( '.tecc-rule-limit' );
		var out       = card.querySelector( '.tecc-rule-limit-out' );
		var isBanner  = location && BANNER_LOCATIONS.indexOf( location.value ) !== -1;

		if ( carousel ) {
			carousel.style.display = isBanner ? 'none' : '';
		}
		if ( field ) {
			field.style.display = ( ! isBanner && on && on.checked ) ? '' : 'none';
		}
		if ( range && out ) {
			out.textContent = range.value;
		}
	}

	/**
	 * Keep the header summary in sync: "{Location label} - {Source label}".
	 *
	 * @param {Element} card Rule card.
	 */
	function updateSummary( card ) {
		var summary  = card.querySelector( '.tecc-rule-summary' );
		var location = card.querySelector( '.tecc-rule-location' );
		var source   = card.querySelector( '.tecc-rule-source' );

		if ( ! summary ) {
			return;
		}
		// Use the SHORT location name (drop the "(banner)"/"(card)" suffix) so the
		// collapsed card header reads cleanly instead of truncating mid-word.
		var loc = selectedLabel( location ).replace( /\s*\([^)]*\)\s*$/, '' ).trim();
		summary.textContent = loc + ' — ' + selectedLabel( source );
	}

	function selectedLabel( select ) {
		if ( select && select.selectedIndex >= 0 && select.options[ select.selectedIndex ] ) {
			return select.options[ select.selectedIndex ].textContent;
		}
		return '';
	}

	function setCollapsed( card, collapsed ) {
		var toggle = card.querySelector( '.tecc-rule-toggle' );

		if ( collapsed ) {
			card.className += ( card.className ? ' ' : '' ) + 'tecc-collapsed';
		} else {
			card.className = card.className.replace( /(^|\s)tecc-collapsed(\s|$)/g, ' ' ).replace( /^\s+|\s+$/g, '' );
		}
		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
		}
	}

	function isCollapsed( card ) {
		return ( ' ' + card.className + ' ' ).indexOf( ' tecc-collapsed ' ) !== -1;
	}

	function updateDisabledClass( card ) {
		var enabled = card.querySelector( '.tecc-rule-enabled' );
		var off     = enabled && ! enabled.checked;
		var has     = ( ' ' + card.className + ' ' ).indexOf( ' tecc-rule-off ' ) !== -1;

		if ( off && ! has ) {
			card.className += ' tecc-rule-off';
		} else if ( ! off && has ) {
			card.className = card.className.replace( /(^|\s)tecc-rule-off(\s|$)/g, ' ' ).replace( /^\s+|\s+$/g, '' );
		}
	}

	/**
	 * Small inline notice next to an anchor element (cap guards).
	 *
	 * @param {Element} anchor  Element the notice appears after.
	 * @param {string}  message Notice text.
	 */
	function showNotice( anchor, message ) {
		var parent = anchor.parentNode;
		var old    = parent.querySelector( '.tecc-rules-notice' );
		var notice;

		if ( old ) {
			parent.removeChild( old );
		}

		notice = document.createElement( 'span' );
		notice.className = 'tecc-rules-notice';
		notice.textContent = message;
		parent.insertBefore( notice, anchor.nextSibling );

		window.setTimeout( function() {
			if ( notice.parentNode ) {
				notice.parentNode.removeChild( notice );
			}
		}, 5000 );
	}

	/**
	 * Walk the cards in DOM order and write the JSON into the hidden field.
	 *
	 * @param {Element} app   App container.
	 * @param {Element} field #tecc-rules-json.
	 */
	function serialize( app, field ) {
		var cards = app.querySelectorAll( '.tecc-rule-card' );
		var rules = [];
		var i;

		for ( i = 0; i < cards.length; i++ ) {
			rules.push( serializeRule( cards[ i ], i ) );
		}

		field.value = JSON.stringify( rules );
		updateBadge();
	}

	/**
	 * @param {Element} card  Rule card.
	 * @param {number}  index Position in the list.
	 * @return {Object} Rule object matching the server schema.
	 */
	function serializeRule( card, index ) {
		var id = card.getAttribute( 'data-tecc-rule-id' ) || '';

		if ( ! id ) {
			id = 'r-' + ( index + 1 ) + '-' + Math.random().toString( 36 ).slice( 2, 8 );
			card.setAttribute( 'data-tecc-rule-id', id );
		}

		return {
			id: id,
			enabled: !! getChecked( card, '.tecc-rule-enabled' ),
			location: getValue( card, '.tecc-rule-location' ),
			source: getValue( card, '.tecc-rule-source' ),
			filter: {
				category: getValue( card, '.tecc-rule-categories' ),
				tag: ''
			},
			event_ids: serializeEventIds( card ),
			// Carousel off -> a single event; on -> the 2..5 slider value.
			limit: getChecked( card, '.tecc-rule-carousel' ) ? Math.max( 2, Math.min( 5, parseInt( getValue( card, '.tecc-rule-limit' ), 10 ) || 2 ) ) : 1,
			style: getValue( card, '.tecc-rule-style' ),
			countdown_style: getValue( card, '.tecc-rule-cd-style' ),
			countdown_size: parseInt( getValue( card, '.tecc-rule-cd-size' ), 10 ) || 0,
			image_position: getValue( card, '.tecc-rule-imgpos' ),
			design: serializeDesign( card ),
			// Colours now live in rule.design; the legacy CSS-var token override
			// layer is retired (kept in the schema as an empty object).
			tokens: {},
			close_action: getValue( card, '.tecc-rule-close-action' ) || 'event',
			conditions: {
				include: serializeConditions( card, 'include' ),
				exclude: serializeConditions( card, 'exclude' )
			}
		};
	}

	function getValue( scope, selector ) {
		var el = scope.querySelector( selector );
		return el ? el.value : '';
	}

	function getChecked( scope, selector ) {
		var el = scope.querySelector( selector );
		return el ? el.checked : false;
	}

	// The sitewide Styles tab writes real design values (data-tecc-design
	// inputs). Colours + width are strings; the size/radius/border/padding
	// sliders are ints. Collect them into rule.design.
	function serializeDesign( card ) {
		var inputs = card.querySelectorAll( '[data-tecc-design]' );
		var design = {};
		var i, key, type;

		for ( i = 0; i < inputs.length; i++ ) {
			key = inputs[ i ].getAttribute( 'data-tecc-design' );
			if ( ! key ) {
				continue;
			}
			type = inputs[ i ].type;
			if ( 'range' === type || 'number' === type ) {
				design[ key ] = parseInt( inputs[ i ].value, 10 ) || 0;
			} else {
				design[ key ] = inputs[ i ].value;
			}
		}
		return design;
	}

	// Seed the colour pickers from the Light/Dark preset, then apply any stored
	// per-rule overrides on top.
	function seedDesign( card, style, ruleDesign ) {
		var catalogue = window.teccSitewideStyles || {};
		var seed      = catalogue[ style ] || catalogue.dark || {};
		var pickers   = card.querySelectorAll( '[data-tecc-design]' );
		var i, key, val;

		ruleDesign = ruleDesign || {};
		for ( i = 0; i < pickers.length; i++ ) {
			// Light/Dark only reseeds the COLOUR pickers, never the size/spacing
			// sliders or the width field.
			if ( 'color' !== pickers[ i ].type ) {
				continue;
			}
			key = pickers[ i ].getAttribute( 'data-tecc-design' );
			val = ( ruleDesign[ key ] || seed[ key ] || '' );
			// <input type="color"> can't hold 'transparent'; show white for it.
			if ( ! val || 'transparent' === val ) {
				val = '#ffffff';
			}
			if ( key ) {
				pickers[ i ].value = val;
			}
		}
	}

	// Specific-events source is a comma-separated ID field.
	function serializeEventIds( card ) {
		var raw   = getValue( card, '.tecc-rule-event-ids' );
		var parts = String( raw ).split( ',' );
		var ids   = [];
		var i, id;

		for ( i = 0; i < parts.length; i++ ) {
			id = parseInt( parts[ i ], 10 );
			if ( id ) {
				ids.push( id );
			}
		}
		return ids;
	}

	function serializeConditions( card, bucket ) {
		var group = card.querySelector( '.tecc-rule-conditions-group[data-tecc-bucket="' + bucket + '"]' );
		var out   = [];
		var rows, i, type, value;

		if ( ! group ) {
			return out;
		}

		rows = group.querySelectorAll( '.tecc-condition-row' );
		for ( i = 0; i < rows.length; i++ ) {
			type  = getValue( rows[ i ], '.tecc-condition-type' );
			value = VALUE_TYPES[ type ] ? getValue( rows[ i ], '.tecc-condition-value' ) : '';
			out.push( { type: type, value: value } );
		}
		return out;
	}

	function countConditions( card ) {
		return card.querySelectorAll( '.tecc-condition-row' ).length;
	}

	/**
	 * Find the ancestor rule card of an element.
	 *
	 * @param {Element} el Descendant element.
	 * @return {Element|null}
	 */
	function closestCard( el ) {
		while ( el && el.nodeType === 1 ) {
			if ( ( ' ' + el.className + ' ' ).indexOf( ' tecc-rule-card ' ) !== -1 ) {
				return el;
			}
			el = el.parentNode;
		}
		return null;
	}

	function hasClass( el, name ) {
		return el && el.className && ( ' ' + el.className + ' ' ).indexOf( ' ' + name + ' ' ) !== -1;
	}

	// Walk up from a click target to the nearest ancestor carrying `name`
	// (stopping at `stop`). Buttons hold icon/text spans, so a click's target is
	// often a child; this resolves the button instead of missing the match.
	function closestClass( el, name, stop ) {
		while ( el && el.nodeType === 1 && el !== stop ) {
			if ( hasClass( el, name ) ) {
				return el;
			}
			el = el.parentNode;
		}
		return null;
	}

	// Pure-style fields whose change only sets a CSS custom property — these can
	// update the live preview in place, no server re-render. Keys mirror
	// Args::DESIGN_VARS so the front end and this stay in lockstep.
	var RULE_LIVE_VARS = {
		'main': { v: '--tecc-main' },
		'alt': { v: '--tecc-alt' },
		'title_color': { v: '--tecc-title-color' },
		'text_color': { v: '--tecc-text-color' },
		'bg': { v: '--tecc-card-bg' },
		'title_size': { v: '--tecc-title-size', px: true },
		'body_size': { v: '--tecc-body-size', px: true },
		'radius': { v: '--tecc-radius', px: true },
		'border': { v: '--tecc-border', px: true },
		'padding': { v: '--tecc-pad', px: true }
	};

	// Apply a colour/size change to the already-rendered preview instead of
	// re-fetching it. Only for the card the preview currently shows. Returns
	// true when handled (caller then skips schedulePreview()).
	function applyRuleLiveStyle( card, target ) {
		if ( ! card || card !== activeCard || ! previewBoxEl || ! target ) {
			return false;
		}
		var spec;
		if ( hasClass( target, 'tecc-rule-cd-size' ) ) {
			spec = { v: '--tecc-cd-size', px: true };
		} else {
			var key = target.getAttribute ? target.getAttribute( 'data-tecc-design' ) : null;
			spec = key ? RULE_LIVE_VARS[ key ] : null;
		}
		if ( ! spec ) {
			return false;
		}
		var cds = previewBoxEl.querySelectorAll( '.tecc-cd' );
		if ( ! cds.length ) {
			return false;
		}
		var val = target.value;
		if ( spec.px ) {
			val = ( parseInt( val, 10 ) || 0 ) + 'px';
		}
		for ( var i = 0; i < cds.length; i++ ) {
			cds[ i ].style.setProperty( spec.v, val );
			// The border only paints with the --bordered class (server-added when
			// border > 0); toggle it so a live width change shows immediately.
			if ( '--tecc-border' === spec.v ) {
				cds[ i ].classList.toggle( 'tecc-cd--bordered', ( parseInt( val, 10 ) || 0 ) > 0 );
			}
		}
		// Cancel any pending debounced re-render so it can't land a moment later
		// and clobber this in-place style with a stale render.
		if ( previewTimer ) {
			window.clearTimeout( previewTimer );
			previewTimer = null;
		}
		return true;
	}

	/**
	 * Wire all behavior: delegated change/click on the app, add-rule button,
	 * form submit.
	 *
	 * @param {Element} app    App container.
	 * @param {Element} tpl    #tecc-rule-template.
	 * @param {Element} field  Hidden JSON field.
	 */
	function wireApp( app, tpl, field ) {
		var addButton = document.getElementById( 'tecc-add-rule' );
		var form      = field.form;

		app.addEventListener( 'change', function( e ) {
			var target = e.target;
			var card   = closestCard( target );

			if ( ! card ) {
				return;
			}

			if ( hasClass( target, 'tecc-rule-source' ) ) {
				toggleSource( card );
			}
			if ( hasClass( target, 'tecc-rule-carousel' ) ) {
				toggleCarousel( card );
			}
			// Switching Light/Dark resets the colour pickers to that preset.
			if ( hasClass( target, 'tecc-rule-style' ) ) {
				seedDesign( card, target.value, {} );
			}
			if ( hasClass( target, 'tecc-condition-type' ) ) {
				var row = target.parentNode;
				while ( row && ! hasClass( row, 'tecc-condition-row' ) ) {
					row = row.parentNode;
				}
				if ( row ) {
					toggleConditionValue( row );
				}
			}
			if ( hasClass( target, 'tecc-rule-enabled' ) ) {
				updateDisabledClass( card );
			}
			if ( hasClass( target, 'tecc-rule-location' ) ) {
				// Banner-forced locations hide the carousel (single event only).
				toggleCarousel( card );
			}
			if ( hasClass( target, 'tecc-rule-location' ) || hasClass( target, 'tecc-rule-source' ) ) {
				updateSummary( card );
			}

			var wasActive = card === activeCard;
			serialize( app, field );
			setActiveCard( card );
			// A committed colour on the previewed card updates it in place.
			if ( wasActive && applyRuleLiveStyle( card, target ) ) {
				return;
			}
			schedulePreview();
		} );

		app.addEventListener( 'input', function( e ) {
			var card = closestCard( e.target );
			var wasActive = card && card === activeCard;
			if ( card && hasClass( e.target, 'tecc-range' ) ) {
				var rangeField = e.target.parentNode;
				var out = rangeField ? rangeField.querySelector( '.tecc-range__value' ) : null;
				if ( out ) {
					out.textContent = e.target.value;
				}
			}
			serialize( app, field );
			if ( card ) {
				setActiveCard( card );
				// Dragging a colour/size slider on the previewed card is live —
				// no re-render. Everything else falls through to a debounced fetch.
				if ( wasActive && applyRuleLiveStyle( card, e.target ) ) {
					return;
				}
				schedulePreview();
			}
		} );

		app.addEventListener( 'click', function( e ) {
			var target = e.target;
			var card   = closestCard( target );

			if ( ! card ) {
				return;
			}

			var subtabBtn = closestClass( target, 'tecc-rule-subtab-button', card );
			if ( subtabBtn ) {
				toggleRuleSubtab( card, subtabBtn.getAttribute( 'data-tecc-rule-subtab' ) );
				return;
			}
			if ( hasClass( target, 'tecc-rule-preview' ) ) {
				setActiveCard( card );
				previewCard( card );
				return;
			}

			if ( hasClass( target, 'tecc-rule-up' ) ) {
				var prev = card.previousElementSibling;
				if ( prev ) {
					app.insertBefore( card, prev );
					serialize( app, field );
				}
			} else if ( hasClass( target, 'tecc-rule-down' ) ) {
				var next = card.nextElementSibling;
				if ( next ) {
					app.insertBefore( next, card );
					serialize( app, field );
				}
			} else if ( hasClass( target, 'tecc-rule-toggle' ) ) {
				setCollapsed( card, ! isCollapsed( card ) );
				if ( ! isCollapsed( card ) ) {
					setActiveCard( card );
					previewCard( card );
				}
			} else if ( hasClass( target, 'tecc-rule-delete' ) ) {
				if ( window.confirm( t( 'Delete this rule?' ) ) ) {
					card.parentNode.removeChild( card );
					serialize( app, field );
					// Deleting the last card leaves nothing to preview.
					if ( card === activeCard ) {
						activeCard = null;
					}
					previewActive();
				}
			} else if ( hasClass( target, 'tecc-rule-add-include' ) || hasClass( target, 'tecc-rule-add-exclude' ) ) {
				if ( countConditions( card ) >= MAX_CONDITIONS ) {
					showNotice( target, t( 'Condition limit reached for this rule (20).' ) );
					return;
				}
				addConditionRow( card, hasClass( target, 'tecc-rule-add-include' ) ? 'include' : 'exclude', 'entire_site', '' );
				serialize( app, field );
			} else if ( hasClass( target, 'tecc-condition-remove' ) ) {
				var conditionRow = target.parentNode;
				while ( conditionRow && ! hasClass( conditionRow, 'tecc-condition-row' ) ) {
					conditionRow = conditionRow.parentNode;
				}
				if ( conditionRow ) {
					conditionRow.parentNode.removeChild( conditionRow );
					serialize( app, field );
				}
			}
		} );

		if ( addButton ) {
			addButton.addEventListener( 'click', function() {
				if ( app.querySelectorAll( '.tecc-rule-card' ).length >= MAX_RULES ) {
					showNotice( addButton, t( 'Rule limit reached (50).' ) );
					return;
				}
				addCard( app, tpl, {
					enabled: true,
					location: 'footer-bar',
					source: 'next-upcoming',
					limit: 1,
					// Dark reads well on any page it floats over; users switch to
					// the tinted-light preset per rule where the page is dark.
					style: 'dark',
					conditions: {
						include: [ { type: 'entire_site', value: '' } ],
						// Default new displays to hide on mobile — a floating banner
						// or bar is most likely to be unwanted on small screens; the
						// user can remove this row. (Only new rules; stored rules
						// load their own saved conditions untouched.)
						exclude: [ { type: 'is_mobile', value: '' } ]
					}
				}, false );
				serialize( app, field );
				setActiveCard( app.querySelector( '.tecc-rule-card:last-child' ) );
				previewActive();
			} );
		}

		if ( form ) {
			form.addEventListener( 'submit', function() {
				serialize( app, field );
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
