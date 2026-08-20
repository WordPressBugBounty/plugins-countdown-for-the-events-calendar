/**
 * Event Countdown — source token-select (events / categories).
 *
 * A dependency-free select2-style multi-select: a search box + result menu fed
 * live from the REST /source endpoint, and removable chips. For events, each
 * chip carries a hidden future-events-list[] input so the save path and the
 * generator read it unchanged; for categories the chips drive a single hidden
 * CSV field. Every mutation dispatches a form 'input' so the generator + live
 * preview refresh. Vanilla ES5, no jQuery, nothing global.
 *
 * @since 2.1.0
 */
( function () {
	'use strict';

	var config = window.teccAdminSettings || {};
	var SOURCE_URL = config.sourceUrl || '';
	var NONCE = config.nonce || '';
	var DEBOUNCE = parseInt( config.debounce, 10 ) || 300;
	var form = document.querySelector( '.tecc-settings-form' );

	if ( ! form || ! SOURCE_URL || ! window.fetch ) {
		return;
	}

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function fire( el, type ) {
		var evt;
		try {
			evt = new Event( type, { bubbles: true } );
		} catch ( e ) {
			evt = document.createEvent( 'Event' );
			evt.initEvent( type, true, false );
		}
		el.dispatchEvent( evt );
	}

	function trim( s ) {
		return String( s == null ? '' : s ).replace( /^\s+|\s+$/g, '' );
	}

	// Escape a label before it goes into chip innerHTML (titles are our own
	// data, but escape anyway — defence in depth).
	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = String( s == null ? '' : s );
		return d.innerHTML;
	}

	function initTs( root ) {
		var type = 'category' === root.getAttribute( 'data-tecc-ts' ) ? 'category' : 'events';
		var chips = root.querySelector( '[data-tecc-ts-chips]' );
		var input = root.querySelector( '[data-tecc-ts-input]' );
		var menu = root.querySelector( '[data-tecc-ts-menu]' );
		var csvField = root.querySelector( '[data-tecc-ts-value]' );
		var timer = null;
		var reqId = 0;
		var activeIndex = -1;

		if ( ! chips || ! input || ! menu ) {
			return;
		}

		function selectedValues() {
			var out = [];
			var nodes = chips.querySelectorAll( '.tecc-ts-chip' );
			var i;
			for ( i = 0; i < nodes.length; i++ ) {
				out.push( nodes[ i ].getAttribute( 'data-value' ) );
			}
			return out;
		}

		function hasValue( v ) {
			var vals = selectedValues();
			var i;
			for ( i = 0; i < vals.length; i++ ) {
				if ( vals[ i ] === String( v ) ) {
					return true;
				}
			}
			return false;
		}

		function syncStore() {
			if ( 'category' === type && csvField ) {
				csvField.value = selectedValues().join( ',' );
			}
			// Events keep their values in per-chip hidden inputs. Either way,
			// let the generator + live preview pick up the change.
			fire( form, 'input' );
		}

		function addChip( value, label ) {
			var chip, html;
			value = String( value );
			if ( '' === value || hasValue( value ) ) {
				return;
			}
			chip = document.createElement( 'span' );
			chip.className = 'tecc-ts-chip';
			chip.setAttribute( 'data-value', value );
			html = '<span class="tecc-ts-chip-label">' + esc( label || value ) + '</span>' +
				'<button type="button" class="tecc-ts-remove" aria-label="' + esc( ( config.i18n && config.i18n.removeChip ) || 'Remove' ) + '">&times;</button>';
			if ( 'events' === type ) {
				html += '<input type="hidden" name="tecc_settings[future-events-list][]" value="' + esc( value ) + '" />';
			}
			chip.innerHTML = html;
			chips.appendChild( chip );
			syncStore();
		}

		function removeChip( chip ) {
			if ( chip && chip.parentNode ) {
				chip.parentNode.removeChild( chip );
				syncStore();
			}
		}

		function closeMenu() {
			menu.hidden = true;
			menu.innerHTML = '';
			input.setAttribute( 'aria-expanded', 'false' );
			activeIndex = -1;
		}

		function renderMenu( rows ) {
			var i, li;
			menu.innerHTML = '';
			for ( i = 0; i < rows.length; i++ ) {
				if ( hasValue( rows[ i ].id ) ) {
					continue;
				}
				li = document.createElement( 'li' );
				li.className = 'tecc-ts-option';
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'data-value', rows[ i ].id );
				li.textContent = rows[ i ].text;
				menu.appendChild( li );
			}
			if ( ! menu.children.length ) {
				closeMenu();
				return;
			}
			menu.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			activeIndex = -1;
		}

		function search() {
			var q = trim( input.value );
			var myId;
			var url;
			reqId++;
			myId = reqId;
			url = SOURCE_URL + ( SOURCE_URL.indexOf( '?' ) > -1 ? '&' : '?' ) +
				'type=' + encodeURIComponent( type ) +
				'&search=' + encodeURIComponent( q ) +
				'&include=' + encodeURIComponent( selectedValues().join( ',' ) );
			window.fetch( url, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': NONCE }
			} ).then( function ( r ) {
				return r.ok ? r.json() : { results: [] };
			} ).then( function ( d ) {
				if ( myId !== reqId ) {
					return;
				}
				renderMenu( ( d && d.results ) ? d.results : [] );
			} ).catch( function () {
				if ( myId === reqId ) {
					closeMenu();
				}
			} );
		}

		function debouncedSearch() {
			if ( timer ) {
				window.clearTimeout( timer );
			}
			timer = window.setTimeout( search, DEBOUNCE );
		}

		function markActive( opts ) {
			var i;
			for ( i = 0; i < opts.length; i++ ) {
				opts[ i ].classList.toggle( 'is-active', i === activeIndex );
			}
			if ( opts[ activeIndex ] && opts[ activeIndex ].scrollIntoView ) {
				opts[ activeIndex ].scrollIntoView( { block: 'nearest' } );
			}
		}

		function chooseOption( li ) {
			addChip( li.getAttribute( 'data-value' ), li.textContent );
			input.value = '';
			activeIndex = -1;
			// Multi-select: refresh the open list (skip already-chosen) instead of
			// closing. Closing + input.focus() left the input focused, so the next
			// click did not fire "focus" and the menu looked stuck until blur.
			search();
			input.focus();
		}

		// stopPropagation so typing does not bubble to the form's "input"
		// listener (that was closing/blurring this control via syncSource).
		input.addEventListener( 'input', function ( e ) {
			if ( e && e.stopPropagation ) {
				e.stopPropagation();
			}
			debouncedSearch();
		} );
		input.addEventListener( 'focus', search ); // Load the first page on focus.
		// Already-focused input: a click must still reopen a closed menu.
		input.addEventListener( 'click', function () {
			if ( menu.hidden ) {
				search();
			}
		} );

		input.addEventListener( 'keydown', function ( e ) {
			var opts = menu.querySelectorAll( '.tecc-ts-option' );
			var last;
			if ( 'ArrowDown' === e.key || 40 === e.keyCode ) {
				e.preventDefault();
				if ( menu.hidden ) {
					search();
					return;
				}
				activeIndex = Math.min( opts.length - 1, activeIndex + 1 );
				markActive( opts );
			} else if ( 'ArrowUp' === e.key || 38 === e.keyCode ) {
				e.preventDefault();
				if ( menu.hidden ) {
					search();
					return;
				}
				activeIndex = Math.max( 0, activeIndex - 1 );
				markActive( opts );
			} else if ( 'Enter' === e.key || 13 === e.keyCode ) {
				// ALWAYS swallow Enter here. This input sits inside the settings
				// form, which has a submit button, so an unhandled Enter triggers
				// implicit submission - saving and reloading the page mid-search.
				e.preventDefault();
				if ( ! menu.hidden && activeIndex > -1 && opts[ activeIndex ] ) {
					chooseOption( opts[ activeIndex ] );
				}
			} else if ( 'Escape' === e.key || 27 === e.keyCode ) {
				closeMenu();
			} else if ( ( 'Backspace' === e.key || 8 === e.keyCode ) && '' === input.value ) {
				last = chips.querySelector( '.tecc-ts-chip:last-child' );
				if ( last ) {
					removeChip( last );
				}
			}
		} );

		// mousedown (not click) so the option is chosen before the input blurs.
		menu.addEventListener( 'mousedown', function ( e ) {
			var li = e.target;
			while ( li && li !== menu && ! ( li.classList && li.classList.contains( 'tecc-ts-option' ) ) ) {
				li = li.parentNode;
			}
			if ( li && li.classList && li.classList.contains( 'tecc-ts-option' ) ) {
				e.preventDefault();
				chooseOption( li );
			}
		} );

		// Keep keyboard highlight in sync with the hovered row (avoids a stuck
		// .is-active style that no longer matches the pointer).
		menu.addEventListener( 'mouseover', function ( e ) {
			var li = e.target;
			var opts;
			var i;
			while ( li && li !== menu && ! ( li.classList && li.classList.contains( 'tecc-ts-option' ) ) ) {
				li = li.parentNode;
			}
			if ( ! li || li === menu ) {
				return;
			}
			opts = menu.querySelectorAll( '.tecc-ts-option' );
			for ( i = 0; i < opts.length; i++ ) {
				if ( opts[ i ] === li ) {
					activeIndex = i;
					markActive( opts );
					break;
				}
			}
		} );

		chips.addEventListener( 'click', function ( e ) {
			var b = e.target;
			if ( b && b.classList && b.classList.contains( 'tecc-ts-remove' ) ) {
				removeChip( b.parentNode );
				input.focus();
			}
		} );

		// Focusing the control focuses the input (and opens via focus/click).
		root.querySelector( '.tecc-ts-control' ).addEventListener( 'mousedown', function ( e ) {
			if ( e.target === input ) {
				return;
			}
			if ( e.target && e.target.classList && e.target.classList.contains( 'tecc-ts-remove' ) ) {
				return;
			}
			// Chip chrome / empty padding: put caret in the search field.
			if ( e.target === e.currentTarget ||
				( e.target.classList && (
					e.target.classList.contains( 'tecc-ts-chips' ) ||
					e.target.classList.contains( 'tecc-ts-chip' ) ||
					e.target.classList.contains( 'tecc-ts-chip-label' )
				) ) ) {
				e.preventDefault();
				input.focus();
				if ( menu.hidden ) {
					search();
				}
			}
		} );

		// Outside click closes. Ignore detached targets (option nodes removed
		// during mousedown choose) — those made contains() false and raced the
		// reopen fetch, leaving the menu closed while the input stayed focused.
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target || ! e.target.isConnected ) {
				return;
			}
			if ( ! root.contains( e.target ) ) {
				closeMenu();
			}
		} );
	}

	ready( function () {
		var roots = document.querySelectorAll( '[data-tecc-ts]' );
		var i;
		for ( i = 0; i < roots.length; i++ ) {
			initTs( roots[ i ] );
		}
	} );
} )();
