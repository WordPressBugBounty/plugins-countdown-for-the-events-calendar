/**
 * Event Countdown — Elementor editor: starter-preset card grid.
 *
 * Clicking a preset card SEEDS this widget's own controls with that preset's
 * values (colours, sizes, layout, toggles) via Elementor's settings command, so
 * every control stays editable afterwards. Nothing is applied at render time —
 * the widget always renders from its own control values.
 *
 * @since 2.1.0
 */
( function ( $ ) {
	'use strict';

	/**
	 * Preset bundle key -> [ Elementor control id, value shape ].
	 * Mirrors the block's TECC_PRESET_KEY_MAP, targeting tecc_* control ids.
	 * Shapes: 'str' plain string, 'slider' { unit, size }, 'switch' 'yes'|''.
	 */
	var MAP = {
		template: [ 'tecc_template', 'str' ],
		'countdown-style': [ 'tecc_countdown_style', 'str' ],
		'image-position': [ 'tecc_image_position', 'str' ],
		'image-gap': [ 'tecc_image_gap', 'switch' ],
		'content-align': [ 'tecc_content_align', 'str' ],
		'main-color': [ 'tecc_main_color', 'str' ],
		'alternate-color': [ 'tecc_alt_color', 'str' ],
		'title-color': [ 'tecc_title_color', 'str' ],
		'text-color': [ 'tecc_text_color', 'str' ],
		'bg-color': [ 'tecc_card_bg', 'str' ],
		'title-size': [ 'tecc_title_size', 'slider' ],
		'body-size': [ 'tecc_body_size', 'slider' ],
		'countdown-size': [ 'tecc_countdown_size', 'slider' ],
		border: [ 'tecc_border', 'slider' ],
		radius: [ 'tecc_radius', 'slider' ],
		padding: [ 'tecc_padding', 'slider' ],
		width: [ 'tecc_width', 'width' ],
		shadow: [ 'tecc_shadow', 'switch' ],
		'show-image': [ 'tecc_show_image', 'switch' ],
		'show-venue': [ 'tecc_show_venue', 'switch' ],
		'show-cost': [ 'tecc_show_cost', 'switch' ],
		'show-button': [ 'tecc_show_button', 'switch' ],
		'show-seconds': [ 'tecc_show_seconds', 'switch' ],
		'show-divider': [ 'tecc_show_divider', 'switch' ]
	};

	function coerce( shape, raw ) {
		if ( 'switch' === shape ) {
			return ( 'yes' === raw || true === raw ) ? 'yes' : '';
		}
		if ( 'slider' === shape ) {
			return { unit: 'px', size: parseInt( raw, 10 ) || 0 };
		}
		if ( 'width' === shape ) {
			// The width control is a px/% slider. "auto" (fill) maps to 100%.
			var s = String( raw ).trim();
			if ( '' === s || 'auto' === s.toLowerCase() ) {
				return { unit: '%', size: 100 };
			}
			if ( s.slice( -1 ) === '%' ) {
				return { unit: '%', size: parseInt( s, 10 ) || 100 };
			}
			return { unit: 'px', size: parseInt( s, 10 ) || 0 };
		}
		return String( raw );
	}

	/**
	 * The container of the widget whose panel is open. Elementor exposes this a
	 * few different ways depending on version, so try them in order.
	 */
	function activeContainer() {
		if ( ! window.elementor ) {
			return null;
		}

		// 1) The panel's current page view (most versions).
		try {
			var page = elementor.getPanelView().getCurrentPageView();
			var c = page && page.getOption ? page.getOption( 'container' ) : null;
			if ( c ) {
				return c;
			}
			if ( page && page.model && elementor.getContainer ) {
				var byModel = elementor.getContainer( page.model.get( 'id' ) );
				if ( byModel ) {
					return byModel;
				}
			}
		} catch ( e ) {}

		// 2) The current edit selection.
		try {
			var sel = elementor.selection.getElements();
			if ( sel && sel.length ) {
				return sel[ 0 ];
			}
		} catch ( e ) {}

		// 3) Whatever element is flagged as being edited in the preview.
		try {
			var el = elementor.$previewContents.find( '.elementor-element-editable' ).first();
			var id = el.length ? el.data( 'id' ) : null;
			if ( id ) {
				return elementor.getContainer( id );
			}
		} catch ( e ) {}

		return null;
	}

	function currentMode( container ) {
		try {
			var m = container.settings.get( 'tecc_preset_mode' );
			return 'dark' === m ? 'dark' : 'light';
		} catch ( e ) {
			return 'light';
		}
	}

	function applyPreset( id ) {
		var container = activeContainer();
		var cat = window.teccPresets;
		if ( ! container || ! cat || ! cat.presets || ! cat.presets[ id ] ) {
			return;
		}

		var mode = currentMode( container );
		var bundle = cat.presets[ id ][ mode ] || cat.presets[ id ].light;
		if ( ! bundle ) {
			return;
		}

		var settings = {};
		Object.keys( bundle ).forEach( function ( key ) {
			var m = MAP[ key ];
			if ( m ) {
				settings[ m[ 0 ] ] = coerce( m[ 1 ], bundle[ key ] );
			}
		} );

		// external:true so the panel inputs visibly refresh to the seeded values.
		window.$e.run( 'document/elements/settings', {
			container: container,
			settings: settings,
			options: { external: true }
		} );
	}

	/**
	 * Delegated from the document so it works no matter when Elementor renders
	 * the panel (binding on a panel-open hook is timing-fragile — the script can
	 * load after the hook has already fired for the open widget).
	 */
	$( document ).on( 'click', '.tecc-preset-card', function ( e ) {
		e.preventDefault();
		e.stopPropagation();

		var $card = $( this );
		$card.closest( '[data-tecc-preset-grid]' ).find( '.tecc-preset-card' ).removeClass( 'is-active' );
		$card.addClass( 'is-active' );

		applyPreset( $card.attr( 'data-tecc-preset' ) );
	} );
}( jQuery ) );
