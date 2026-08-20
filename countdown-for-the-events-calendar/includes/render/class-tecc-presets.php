<?php
/**
 * Starter design presets — the ONE canonical source.
 *
 * A preset is authoring sugar: selecting it writes a whole bundle of control
 * values (layout + colours + toggles) into the editor. It is NOT a render path
 * and NOT the render-time `style` seed (Args::STYLE_PRESETS) — once a preset is
 * applied the design is fully explicit, so the shortcode, block, Elementor
 * widget and the settings preview all render identically from those values.
 *
 * Model: SIX base layouts, each with a Light and a
 * Dark colour set. The editor shows six tiles + a Light/Dark switch; the switch
 * only swaps the colour axis, never the layout. Every bundle is expressed in the
 * settings-option ("tecc_settings[...]") key vocabulary so it funnels through the
 * exact same Migrate/from_options path a saved setting does.
 *
 * @package CoolPlugins\Countdown
 * @since   2.1.0
 */

namespace CoolPlugins\Countdown\Render;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Presets' ) ) {

	/**
	 * Canonical starter-preset catalogue.
	 *
	 * @since 2.1.0
	 */
	class Presets {

		/**
		 * Colour-mode axis.
		 */
		const MODES = array( 'light', 'dark' );

		/**
		 * The tecc_settings keys a colour mode is allowed to set. A preset's
		 * Light and Dark bundles must differ ONLY in these keys — the layout is
		 * mode-independent. The harness enforces it.
		 *
		 * @var string[]
		 */
		const COLOR_KEYS = array( 'main-color', 'alternate-color', 'title-color', 'text-color', 'bg-color' );

		/**
		 * A clean baseline for every control a preset can touch, at its default.
		 *
		 * Applied BEFORE a preset's own values (in the editor and in bundle())
		 * so switching presets never leaves a stale value from the previous one.
		 * Mirrors Args::DESIGN / Args defaults — the harness asserts it cannot
		 * drift from them.
		 *
		 * @since 2.1.0
		 * @return array<string,string|int>
		 */
		public static function baseline() {
			return array(
				'template'        => 'card',
				'countdown-style' => 'box',
				'image-position'  => 'top',
				'image-gap'       => 'yes',
				'content-align'   => 'left',
				'main-color'      => '#4395cb',
				'alternate-color' => '#ffffff',
				'title-color'     => '#1d2327',
				'text-color'      => '#50575e',
				'bg-color'        => '#ffffff',
				'title-size'      => 20,
				'body-size'       => 14,
				'countdown-size'  => 28,
				'border'          => 0,
				'radius'          => 10,
				'padding'         => 15,
				'width'           => 'auto',
				'shadow'          => 'no',
				'show-image'      => 'no',
				'show-venue'      => 'yes',
				'show-cost'       => 'no',
				'show-button'     => 'yes',
				'show-seconds'    => 'yes',
				'show-divider'    => 'no',
			);
		}

		/**
		 * The six base layouts: id => [ label, hint, base (mode-independent),
		 * colors => [ light => [...], dark => [...] ] ].
		 *
		 * `base` and each colour set use tecc_settings keys. Anything a layout
		 * omits inherits from baseline().
		 *
		 * @since 2.1.0
		 * @return array
		 */
		public static function layouts() {
			return array(

				'classic-top' => array(
					'label'   => __( 'Classic Design', 'countdown-for-the-events-calendar' ),
					'tagline' => __( 'Transparent, image on top', 'countdown-for-the-events-calendar' ),
					'hint'    => __( 'Transparent card that blends in — closest to the classic look.', 'countdown-for-the-events-calendar' ),
					'base'  => array(
						'template'        => 'card',
						'countdown-style' => 'box',
						'image-position'  => 'top',
						'content-align'   => 'center',
						'show-image'      => 'yes',
						'radius'          => 0,
						'padding'         => 0,
						'border'          => 0,
						'shadow'          => 'no',
						'countdown-size'  => 22,
						'title-size'      => 20,
					),
					'colors' => array(
						'light' => array(
							'bg-color'        => 'transparent',
							'title-color'     => '#1d2327',
							'text-color'      => '#50575e',
							'main-color'      => '#2271b1',
							'alternate-color' => '#ffffff',
						),
						'dark'  => array(
							'bg-color'        => '#12151b',
							'title-color'     => '#f0f2f5',
							'text-color'      => '#aab3c0',
							'main-color'      => '#7c6cf0',
							'alternate-color' => '#ffffff',
						),
					),
				),

				'bold-poster' => array(
					'label'   => __( 'Bold Poster', 'countdown-for-the-events-calendar' ),
					'tagline' => __( 'Full image, solid card', 'countdown-for-the-events-calendar' ),
					'hint'    => __( 'Image on top, generous spacing, rounded corners and a soft shadow.', 'countdown-for-the-events-calendar' ),
					'base'  => array(
						'template'        => 'card',
						'countdown-style' => 'box',
						'image-position'  => 'top',
						'content-align'   => 'left',
						'show-image'      => 'yes',
						'radius'          => 18,
						'padding'         => 22,
						'shadow'          => 'yes',
						'countdown-size'  => 26,
						'title-size'      => 24,
					),
					'colors' => array(
						'light' => array(
							'bg-color'        => '#f6f5ff',
							'title-color'     => '#0d1117',
							'text-color'      => '#5b6675',
							'main-color'      => '#6d5efc',
							'alternate-color' => '#ffffff',
						),
						'dark'  => array(
							'bg-color'        => '#0e1116',
							'title-color'     => '#eef2f7',
							'text-color'      => '#9aa6b6',
							'main-color'      => '#7c6cf0',
							'alternate-color' => '#ffffff',
						),
					),
				),

				'modern-split' => array(
					'label'   => __( 'Modern Split', 'countdown-for-the-events-calendar' ),
					'tagline' => __( 'Flush image, split layout', 'countdown-for-the-events-calendar' ),
					'hint'    => __( 'Full-height image flush to the left edge, content on the right.', 'countdown-for-the-events-calendar' ),
					'base'  => array(
						'template'        => 'card',
						'countdown-style' => 'ring',
						'image-position'  => 'left',
						'image-gap'       => 'no',
						'content-align'   => 'left',
						'show-image'      => 'yes',
						'radius'          => 16,
						'padding'         => 20,
						'shadow'          => 'yes',
						'countdown-size'  => 24,
						'title-size'      => 22,
						'width'           => 800,
					),
					'colors' => array(
						'light' => array(
							'bg-color'        => '#ffffff',
							'title-color'     => '#0d1117',
							'text-color'      => '#5b6675',
							'main-color'      => '#2f9bff',
							'alternate-color' => '#ffffff',
						),
						'dark'  => array(
							'bg-color'        => '#0f1720',
							'title-color'     => '#ffffff',
							'text-color'      => '#9aa4b2',
							'main-color'      => '#5b8cff',
							'alternate-color' => '#ffffff',
						),
					),
				),

				'classic-left' => array(
					'label'   => __( 'Side Image', 'countdown-for-the-events-calendar' ),
					'tagline' => __( 'Transparent, image left', 'countdown-for-the-events-calendar' ),
					'hint'    => __( 'Image on the left, left-aligned content, transparent card.', 'countdown-for-the-events-calendar' ),
					'base'  => array(
						'template'        => 'card',
						'countdown-style' => 'ring',
						'image-position'  => 'left',
						'image-gap'       => 'yes',
						'content-align'   => 'left',
						'show-image'      => 'yes',
						'radius'          => 0,
						'padding'         => 0,
						'border'          => 0,
						'shadow'          => 'no',
						'countdown-size'  => 22,
						'title-size'      => 20,
						'width'           => 800,
					),
					'colors' => array(
						'light' => array(
							'bg-color'        => 'transparent',
							'title-color'     => '#1d2327',
							'text-color'      => '#50575e',
							'main-color'      => '#2271b1',
							'alternate-color' => '#ffffff',
						),
						'dark'  => array(
							'bg-color'        => '#12151b',
							'title-color'     => '#f0f2f5',
							'text-color'      => '#aab3c0',
							'main-color'      => '#7c6cf0',
							'alternate-color' => '#ffffff',
						),
					),
				),

				'classic-noimage' => array(
					'label'   => __( 'Minimal Text', 'countdown-for-the-events-calendar' ),
					'tagline' => __( 'No image, centered', 'countdown-for-the-events-calendar' ),
					'hint'    => __( 'Text-only classic card, centered, transparent background.', 'countdown-for-the-events-calendar' ),
					'base'  => array(
						'template'        => 'card',
						'countdown-style' => 'inline',
						'image-position'  => 'top',
						'content-align'   => 'center',
						'show-image'      => 'no',
						'radius'          => 0,
						'padding'         => 0,
						'border'          => 0,
						'shadow'          => 'no',
						'countdown-size'  => 22,
						'title-size'      => 20,
					),
					'colors' => array(
						'light' => array(
							'bg-color'        => 'transparent',
							'title-color'     => '#1d2327',
							'text-color'      => '#50575e',
							'main-color'      => '#2271b1',
							'alternate-color' => '#ffffff',
						),
						'dark'  => array(
							'bg-color'        => '#12151b',
							'title-color'     => '#f0f2f5',
							'text-color'      => '#aab3c0',
							'main-color'      => '#7c6cf0',
							'alternate-color' => '#ffffff',
						),
					),
				),

				'banner' => array(
					'label'   => __( 'Promo Banner', 'countdown-for-the-events-calendar' ),
					'tagline' => __( 'Wide strip, inline timer', 'countdown-for-the-events-calendar' ),
					'hint'    => __( 'Full-width strip: details left, countdown and button right.', 'countdown-for-the-events-calendar' ),
					'base'  => array(
						'template'        => 'banner',
						'countdown-style' => 'box',
						'image-position'  => 'left',
						'content-align'   => 'left',
						'show-image'      => 'no',
						'show-divider'    => 'yes',
						'radius'          => 6,
						'padding'         => 18,
						'shadow'          => 'no',
						'countdown-size'  => 22,
						'title-size'      => 20,
					),
					'colors' => array(
						'light' => array(
							'bg-color'        => '#eef4ff',
							'title-color'     => '#0d1117',
							'text-color'      => '#41506a',
							'main-color'      => '#2f6bff',
							'alternate-color' => '#ffffff',
						),
						'dark'  => array(
							'bg-color'        => '#12151b',
							'title-color'     => '#ffffff',
							'text-color'      => '#9aa4b2',
							'main-color'      => '#7c6cf0',
							'alternate-color' => '#ffffff',
						),
					),
				),
			);
		}

		/**
		 * Is this a known preset id?
		 *
		 * @since 2.1.0
		 * @param string $id Preset id.
		 * @return bool
		 */
		public static function exists( $id ) {
			$layouts = self::layouts();

			return is_string( $id ) && isset( $layouts[ $id ] );
		}

		/**
		 * The full control bundle for a preset in a given colour mode.
		 *
		 * baseline() + layout base + the mode's colour set, as tecc_settings
		 * keys. Feed this to Args::from_options() (merged with a source) to get
		 * normalized render args — the same path a saved setting takes.
		 *
		 * @since 2.1.0
		 * @param string $id   Preset id.
		 * @param string $mode 'light' | 'dark'.
		 * @return array<string,string|int> Empty array on an unknown id.
		 */
		public static function bundle( $id, $mode = 'light' ) {
			if ( ! self::exists( $id ) ) {
				return array();
			}

			$mode    = in_array( $mode, self::MODES, true ) ? $mode : 'light';
			$layout  = self::layouts();
			$layout  = $layout[ $id ];
			$colors  = isset( $layout['colors'][ $mode ] ) ? $layout['colors'][ $mode ] : array();

			return array_merge( self::baseline(), $layout['base'], $colors );
		}

		/**
		 * A preset bundle as JS-ready data for the editors (block/Elementor/
		 * panel all localise this ONE catalogue and map the tecc_settings keys
		 * onto their own control names). Labels/hints included for the tiles.
		 *
		 * @since 2.1.0
		 * @return array
		 */
		public static function for_js() {
			$out = array(
				'modes'    => self::MODES,
				'baseline' => self::baseline(),
				'presets'  => array(),
			);

			foreach ( self::layouts() as $id => $layout ) {
				$out['presets'][ $id ] = array(
					'label'  => $layout['label'],
					'hint'   => $layout['hint'],
					'light'  => self::bundle( $id, 'light' ),
					'dark'   => self::bundle( $id, 'dark' ),
				);
			}

			return $out;
		}
	}
}
