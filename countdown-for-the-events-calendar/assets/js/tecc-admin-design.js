/**
 * Event Countdown for The Events Calendar — flexible-design helpers.
 *
 * Vanilla-JS wiring for the settings panel:
 *   1. Preset gallery + Light/Dark toggle -> writes a bundle of control values
 *      from the ONE canonical catalogue localised as window.teccPresets.
 *
 * tecc-admin-settings.js owns the shortcode + live preview; this module only
 * mutates field values and then dispatches the events it listens for. No jQuery,
 * ES5 only, nothing exposed globally.
 *
 * @since 2.0.0
 */
( function () {
	'use strict';

	var FORM_SELECTOR = '.tecc-settings-form';

	// The canonical preset catalogue (Presets::for_js), localised by PHP.
	var CATALOGUE = ( 'undefined' !== typeof window.teccPresets && window.teccPresets ) ? window.teccPresets : { modes: [ 'light', 'dark' ], baseline: {}, presets: {} };

	// Current colour mode for the gallery. Layout is mode-independent; the
	// toggle only swaps the five colour keys.
	var currentMode = 'light';

	// The preset the Light/Dark toggle acts on. Seeded with the first catalogue
	// preset so the toggle works on page load WITHOUT first clicking a tile
	// a tile click updates it to the chosen preset.
	var currentPreset = firstPresetId();

	function firstPresetId() {
		var ids = CATALOGUE.presets ? Object.keys( CATALOGUE.presets ) : [];
		return ids.length ? ids[ 0 ] : '';
	}

	/**
	 * Run a callback once the DOM is ready.
	 *
	 * @param {Function} fn Callback.
	 */
	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	/**
	 * Dispatch a bubbling DOM event with an old-browser fallback.
	 *
	 * @param {Element} el   Target element.
	 * @param {string}  type Event type ('input' | 'change').
	 */
	function fire( el, type ) {
		var evt;
		if ( ! el ) {
			return;
		}
		try {
			evt = new Event( type, { bubbles: true } );
		} catch ( e ) {
			evt = document.createEvent( 'Event' );
			evt.initEvent( type, true, false );
		}
		el.dispatchEvent( evt );
	}

	/**
	 * Is a value present in an array (ES5 indexOf helper)?
	 *
	 * @param {Array}  arr List.
	 * @param {string} val Needle.
	 * @return {boolean} True when found.
	 */
	function contains( arr, val ) {
		var i;
		for ( i = 0; i < arr.length; i++ ) {
			if ( arr[ i ] === val ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Split a comma-separated string into trimmed, non-empty tokens.
	 *
	 * @param {string} value CSV.
	 * @return {Array} Tokens.
	 */
	function csvToList( value ) {
		var parts = ( value || '' ).split( ',' );
		var out = [];
		var i, item;
		for ( i = 0; i < parts.length; i++ ) {
			item = parts[ i ].replace( /^\s+|\s+$/g, '' );
			if ( '' !== item ) {
				out.push( item );
			}
		}
		return out;
	}

	/* --------------------------------------------------------------------
	 * 2. Preset gallery
	 * ----------------------------------------------------------------- */

	/**
	 * Toggle the .is-selected class on a radio's choice-card/pill label.
	 *
	 * @param {Element} radio    Radio input.
	 * @param {boolean} selected Whether it is now checked.
	 */
	function markChoiceLabel( radio, selected ) {
		var label = null;
		if ( radio.closest ) {
			label = radio.closest( '.tecc-choice-card, .tecc-choice-pill' );
		}
		if ( ! label || ! label.classList ) {
			return;
		}
		if ( selected ) {
			label.classList.add( 'is-selected' );
		} else {
			label.classList.remove( 'is-selected' );
		}
	}

	/**
	 * Apply one field key -> value onto the form.
	 *
	 * @param {Element} form  The settings form.
	 * @param {string}  key   tecc_settings sub-key.
	 * @param {string}  value Desired value.
	 */
	function setField( form, key, value ) {
		var name = 'tecc_settings[' + key + ']';
		var radios = form.querySelectorAll( 'input[type="radio"][name="' + name + '"]' );
		var checkbox = form.querySelector( 'input[type="checkbox"][name="' + name + '"]' );
		var input, i, match, out;

		if ( radios.length ) {
			for ( i = 0; i < radios.length; i++ ) {
				match = radios[ i ].value === value;
				radios[ i ].checked = match;
				markChoiceLabel( radios[ i ], match );
			}
			return;
		}

		// Toggle switch (checkbox 'yes' + hidden 'no' fallback share the name).
		if ( checkbox ) {
			checkbox.checked = ( value === checkbox.value || 'yes' === value );
			return;
		}

		input = form.querySelector( '[name="' + name + '"]' );
		if ( input ) {
			input.value = value;
			// Range slider: mirror the new value into its paired <output>.
			if ( 'range' === input.type && input.getAttribute( 'data-tecc-range' ) ) {
				out = document.getElementById( input.getAttribute( 'data-tecc-range' ) );
				if ( out ) {
					out.textContent = input.value;
				}
			}
		}
	}

	/**
	 * Apply a whole bundle (key -> value) onto the form.
	 *
	 * @param {Element} form   The settings form.
	 * @param {Object}  bundle tecc_settings sub-key -> value.
	 */
	function applyBundle( form, bundle ) {
		var key;
		for ( key in bundle ) {
			if ( Object.prototype.hasOwnProperty.call( bundle, key ) ) {
				setField( form, key, String( bundle[ key ] ) );
			}
		}
	}

	/**
	 * Apply a preset tile in the current colour mode, then re-sync once.
	 *
	 * Baseline first so no stale value from a previous preset survives, then
	 * the preset's own values.
	 *
	 * @param {string} presetId Catalogue preset id.
	 */
	function applyPreset( presetId ) {
		var preset = CATALOGUE.presets[ presetId ];
		var form = document.querySelector( FORM_SELECTOR );
		var bundle;

		if ( ! preset || ! form ) {
			return;
		}

		bundle = preset[ currentMode ] || preset.light || {};

		if ( CATALOGUE.baseline ) {
			applyBundle( form, CATALOGUE.baseline );
		}
		applyBundle( form, bundle );

		// One change event lets tecc-admin-settings.js re-sync choice classes,
		// the shortcode string and the live preview.
		fire( form, 'change' );
	}

	/**
	 * Mark the active preset tile.
	 *
	 * @param {NodeList} tiles  All preset tiles.
	 * @param {Element}  active The clicked tile.
	 */
	function markTile( tiles, active ) {
		var i;
		for ( i = 0; i < tiles.length; i++ ) {
			tiles[ i ].classList.toggle( 'is-selected', tiles[ i ] === active );
			if ( tiles[ i ].setAttribute ) {
				tiles[ i ].setAttribute( 'aria-pressed', tiles[ i ] === active ? 'true' : 'false' );
			}
		}
	}

	function initPresets() {
		var tiles = document.querySelectorAll( '[data-tecc-preset]' );
		var modeButtons = document.querySelectorAll( '[data-tecc-preset-mode]' );
		var i;

		function onTile() {
			currentPreset = this.getAttribute( 'data-tecc-preset' );
			markTile( tiles, this );
			applyPreset( currentPreset );
		}

		for ( i = 0; i < tiles.length; i++ ) {
			tiles[ i ].addEventListener( 'click', onTile );
		}

		function onMode() {
			var mode = this.getAttribute( 'data-tecc-preset-mode' );
			var selected, on, j;
			if ( ! contains( CATALOGUE.modes, mode ) ) {
				return;
			}
			currentMode = mode;
			for ( j = 0; j < modeButtons.length; j++ ) {
				on = modeButtons[ j ] === this;
				modeButtons[ j ].classList.toggle( 'is-active', on );
				modeButtons[ j ].setAttribute( 'aria-checked', on ? 'true' : 'false' );
			}
			// Re-apply the current preset in the new mode so the toggle
			// live-updates. Falls back to the default (currentPreset) when no
			// tile has been clicked yet, so Light/Dark works on first use.
			if ( currentPreset && CATALOGUE.presets && CATALOGUE.presets[ currentPreset ] ) {
				applyPreset( currentPreset );
				selected = document.querySelector( '[data-tecc-preset="' + currentPreset + '"]' );
				if ( selected ) {
					markTile( tiles, selected );
				}
			}
		}

		for ( i = 0; i < modeButtons.length; i++ ) {
			modeButtons[ i ].addEventListener( 'click', onMode );
		}
	}

	/* --------------------------------------------------------------------
	 * 3. Card-background "transparent" control
	 *
	 * <input type="color"> cannot hold 'transparent', so the real value lives
	 * in a hidden field. A colour picker and a Transparent checkbox drive it;
	 * presets that set bg-color to 'transparent'/a hex flow through the hidden
	 * field and are reflected back into this UI on the form change they fire.
	 * ----------------------------------------------------------------- */
	function initBgTransparent() {
		var form = document.querySelector( FORM_SELECTOR );
		if ( ! form ) {
			return;
		}
		var picker = form.querySelector( '[data-tecc-bg-picker]' );
		var hidden = form.querySelector( '[data-tecc-bg-value]' );
		var toggle = form.querySelector( '[data-tecc-bg-transparent]' );
		if ( ! picker || ! hidden || ! toggle ) {
			return;
		}

		// Push the current picker/toggle state into the hidden field and tell
		// the generator/preview. Called on manual edits only.
		function commit() {
			hidden.value = toggle.checked ? 'transparent' : picker.value;
			picker.disabled = toggle.checked;
			fire( hidden, 'input' );
		}

		// Reflect the hidden field (e.g. after a preset) back into the UI
		// WITHOUT re-dispatching, so it can't loop with the generator.
		function reflect() {
			var isTransparent = 'transparent' === String( hidden.value ).toLowerCase();
			toggle.checked = isTransparent;
			picker.disabled = isTransparent;
			if ( ! isTransparent && /^#[0-9a-fA-F]{6}$/.test( hidden.value ) ) {
				picker.value = hidden.value;
			}
		}

		picker.addEventListener( 'input', commit );
		toggle.addEventListener( 'change', commit );
		// A preset sets the hidden field then fires 'change' on the form.
		form.addEventListener( 'change', reflect );
	}

	ready( function () {
		initPresets();
		initBgTransparent();
	} );
} )();
