<?php
/**
 * The ONE `$args` contract — every adapter normalizes to this
 * schema; the renderer and templates never branch on the adapter.
 *
 * Adapters build $args, never markup:
 *   shortcode -> Args::from_shortcode($atts)   (legacy F>L>id map)
 *   options   -> Args::from_options($settings) (reads event_id, not id)
 *   block/Elementor -> Args::from_block / Args::from_elementor
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Render;

use CoolPlugins\Countdown\Compat\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Render\Args' ) ) {

	/**
	 * Normalizes and sanitizes render arguments.
	 *
	 * @since 2.0.0
	 */
	final class Args {

		/**
		 * Valid values for enum-typed args.
		 *
		 * @var array
		 */
		const ENUMS = array(
			'source'          => array( 'selected', 'next-upcoming', 'next-in-context', 'filtered', 'legacy-list', 'none' ),
			'template'        => array( 'classic', 'card', 'minimal', 'banner' ),
			'style'           => array( 'default', 'dark', 'light', 'vivid' ),
			'size'            => array( 'small', 'medium', 'large' ),
			'countdown_style' => array( 'box', 'ring', 'inline' ),
			'image_position'  => array( 'top', 'left', 'right' ),
			'content_align'   => array( 'left', 'center', 'right' ),
		);

		/**
		 * Design token defaults + validators.
		 * value: [ default, type ] where type = 'color' | 'int:MIN:MAX' |
		 * 'width' (auto|int) | 'bool'.
		 *
		 * @var array
		 */
		const DESIGN = array(
			'main'           => array( '#4395cb', 'color' ),
			'alt'            => array( '#ffffff', 'color' ),
			'title_color'    => array( '#1d2327', 'color' ),
			'text_color'     => array( '#50575e', 'color' ),
			'bg'             => array( '#ffffff', 'color' ),
			'title_size'     => array( 20, 'int:8:80' ),
			'body_size'      => array( 14, 'int:8:48' ),
			'countdown_size' => array( 28, 'int:10:120' ),
			'border'         => array( 0, 'int:0:20' ),
			'radius'         => array( 10, 'int:0:80' ),
			'padding'        => array( 15, 'int:0:80' ),
			'width'          => array( 'auto', 'width' ),
			'shadow'         => array( false, 'bool' ),
		);

		/**
		 * The DESIGN keys a style preset is allowed to own.
		 *
		 * A preset only takes effect where the editor sends "unset", so only
		 * keys whose editor controls default to empty may appear here —
		 * anything else silently diverges between editors. Widening the list
		 * requires an empty default + an "inherit" sentinel in BOTH editors
		 * (phase3 harness asserts).
		 *
		 * @var array
		 */
		const PRESET_KEYS = array( 'main', 'alt', 'title_color', 'text_color', 'bg', 'countdown_size' );

		/**
		 * Style presets = design attribute bundles. The chosen preset seeds the
		 * design defaults; explicit design attributes override it (the owner's
		 * "presets update the selection" model). Only keys that differ from the
		 * hard DESIGN defaults need listing, and only keys in PRESET_KEYS may
		 * appear at all.
		 *
		 * @var array
		 */
		const STYLE_PRESETS = array(
			'default' => array(),
			'dark'    => array(
				'bg'          => '#0f1720',
				'title_color' => '#ffffff',
				'text_color'  => '#9aa4b2',
				'main'        => '#7c6cf0',
				'alt'         => '#ffffff',
			),
			'light'   => array(
				'bg'          => '#ffffff',
				'title_color' => '#1d2327',
				'text_color'  => '#50575e',
				'main'        => '#2271b1',
				'alt'         => '#ffffff',
			),
			'vivid'   => array(
				'bg'          => '#ffffff',
				'title_color' => '#1d2327',
				'text_color'  => '#50575e',
				'main'        => '#7c3aed',
				'alt'         => '#ffffff',
			),
		);

		/**
		 * Sitewide (Display-Rules) Light/Dark bundles — full design seeds (not
		 * just colours) because floats render unanchored over arbitrary pages.
		 *
		 * MAINTENANCE: every bundle must define every PRESET_KEYS colour or
		 * from_rule() silently falls back to the colours-only STYLE_PRESETS
		 * base (phase4 harness asserts).
		 *
		 * @var array
		 */
		const SITEWIDE_STYLES = array(
			'dark'  => array(
				'bg'          => '#0f1720',
				'title_color' => '#ffffff',
				'text_color'  => '#9aa4b2',
				'main'        => '#7c6cf0',
				'alt'         => '#ffffff',
				'radius'      => 12,
				'shadow'      => true,
				'border'      => 0,
			),
			'light' => array(
				'bg'          => '#eef2f9',
				'title_color' => '#17202b',
				'text_color'  => '#55617a',
				'main'        => '#2f6bff',
				'alt'         => '#ffffff',
				'radius'      => 12,
				'shadow'      => true,
				'border'      => 1,
			),
		);

		/**
		 * Canonical visual-editor control vocabulary.
		 *
		 * ONE list shared by the block and Elementor so no
		 * editor drifts from another: each adapter only translates its own key
		 * naming into these canonical keys, then hands them to from_editor().
		 *
		 * canonical key => target, where target is
		 *   design:<k> — a DESIGN key (validated by validate_design)
		 *   arg:<k>    — a top-level enum arg
		 *   flag:<k>   — a top-level boolean arg
		 *   label:<k>  — a labels[] entry
		 *
		 * @var array
		 */
		const EDITOR_CONTROLS = array(
			'main_color'      => 'design:main',
			'alt_color'       => 'design:alt',
			'title_color'     => 'design:title_color',
			'text_color'      => 'design:text_color',
			// Named card_bg, not bg_color: the legacy token control already
			// owns bgColor / tecc_bg_color (which seeds the countdown color),
			// and two controls must never resolve to the same editor key.
			'card_bg'         => 'design:bg',
			'title_size'      => 'design:title_size',
			'body_size'       => 'design:body_size',
			'countdown_size'  => 'design:countdown_size',
			'border'          => 'design:border',
			'radius'          => 'design:radius',
			'padding'         => 'design:padding',
			'width'           => 'design:width',
			'shadow'          => 'design:shadow',
			'countdown_style' => 'arg:countdown_style',
			'image_position'  => 'arg:image_position',
			'content_align'   => 'arg:content_align',
			'template'        => 'arg:template',
			'image_gap'       => 'flag:image_gap',
			'show_seconds'    => 'flag:show_seconds',
			'show_image'      => 'flag:show_image',
			'show_venue'      => 'flag:show_venue',
			'show_cost'       => 'flag:show_cost',
			'show_button'     => 'flag:show_button',
			'show_divider'    => 'flag:show_divider',
			'show_ongoing'    => 'flag:ongoing',
			'main_title'      => 'label:title',
			'ongoing_title'   => 'label:ongoing',
			'ended_title'     => 'label:ended',
			'button_text'     => 'label:button',
		);

		/**
		 * Maps a design key to the CSS custom property it emits.
		 *
		 * @var array
		 */
		const DESIGN_VARS = array(
			'main'           => '--tecc-main',
			'alt'            => '--tecc-alt',
			'title_color'    => '--tecc-title-color',
			'text_color'     => '--tecc-text-color',
			'bg'             => '--tecc-card-bg',
			'title_size'     => '--tecc-title-size',
			'body_size'      => '--tecc-body-size',
			'countdown_size' => '--tecc-cd-size',
			'border'         => '--tecc-border',
			'radius'         => '--tecc-radius',
			'padding'        => '--tecc-pad',
			'width'          => '--tecc-width',
		);

		/**
		 * Per-render instance counter — never derived from event_id, which
		 * collides when the same event renders twice on a page.
		 *
		 * @var int
		 */
		private static $instances = 0;

		/**
		 * Schema defaults. Classic defaults are PINNED to the legacy
		 * renderer's faithful values (bg #4395cb, fg #ffffff, size large,
		 * seconds on, button on, image off).
		 *
		 * @since 2.0.0
		 * @return array
		 */
		public static function defaults() {
			// design starts EMPTY so an adapter that omits it inherits the
			// chosen style preset (validate_design fills each key from the
			// preset, then the hard default). A fully-seeded default here would
			// mask the preset for adapters like from_rule().
			$design = array();

			return array(
				'source'          => 'none',
				'event_id'        => 0,
				'event_ids'       => array(),
				'filter'          => array(
					'category'  => array(),
					'tag'       => array(),
					'venue'     => array(),
					'organizer' => array(),
				),
				'ongoing'         => true,
				'limit'           => 1,
				'template'        => 'classic',
				'style'           => 'default',
				'tokens'          => array(),
				'design'          => $design,
				'countdown_style' => 'box',
				'image_position'  => 'top',
				'image_gap'       => true,
				'content_align'   => 'left',
				'show_seconds'    => true,
				'show_image'      => false,
				'show_venue'      => true,
				'show_button'     => true,
				'show_cost'       => false,
				'show_divider'    => false,
				'show_timer_label' => true,
				'show_status'     => true,
				'labels'          => array(
					'title'     => '',
					'ended'     => '',
					'now'       => '',
					'ongoing'   => '',
					'begins_in' => '',
					'ends_in'   => '',
					'button'    => '',
				),
				'size'            => 'large',
				'instance_id'     => '',
				'auto'            => false,
			);
		}

		/**
		 * Shortcode adapter: raw legacy atts -> normalized args.
		 *
		 * @since 2.0.0
		 * @param mixed $atts Raw shortcode attributes.
		 * @return array
		 */
		public static function from_shortcode( $atts ) {
			$atts = is_array( $atts ) ? $atts : array();

			return self::normalize( Migrate::from_shortcode_atts( $atts ) );
		}

		/**
		 * Settings-option adapter (reads `event_id`, not `id`).
		 *
		 * @since 2.0.0
		 * @param mixed $options The tecc_settings option value.
		 * @return array
		 */
		public static function from_options( $options ) {
			$options = is_array( $options ) ? $options : array();

			return self::normalize( Migrate::from_options( $options ) );
		}

		/**
		 * Gutenberg block adapter: block.json attributes -> normalized args.
		 * Attribute schema: src/block.json (kept in sync by the phase3 harness).
		 *
		 * @since 2.0.0
		 * @param mixed $attributes Block attributes.
		 * @return array
		 */
		public static function from_block( $attributes ) {
			$a = is_array( $attributes ) ? $attributes : array();

			$get = function ( $key, $default = '' ) use ( $a ) {
				return isset( $a[ $key ] ) ? $a[ $key ] : $default;
			};

			$source = in_array( $get( 'source' ), array( 'next-upcoming', 'selected', 'filtered' ), true ) ? $get( 'source' ) : 'next-upcoming';

			// "Specific event(s)" accepts one OR many comma-separated event IDs
			// (multiple = a carousel, capped by the Number-of-events limit). The
			// legacy single `eventId` attribute is the fallback for blocks saved
			// before the multi-id field existed.
			$block_ids = self::split_ids( $get( 'eventIds' ) );
			if ( empty( $block_ids ) && absint( $get( 'eventId', 0 ) ) ) {
				$block_ids = array( absint( $get( 'eventId', 0 ) ) );
			}

			$args = array(
				'source'    => $source,
				'event_id'  => $block_ids ? $block_ids[0] : 0,
				'event_ids' => $block_ids,
				'filter'    => array(
					'category' => self::csv_slugs( $get( 'categories' ) ),
					'tag'      => self::csv_slugs( $get( 'tags' ) ),
				),
				'limit'    => absint( $get( 'noOfEvents', 1 ) ),
				'template' => (string) $get( 'template', 'card' ),
				'style'    => (string) $get( 'stylePreset', 'default' ),
				'size'     => (string) $get( 'size', 'large' ),
				'tokens'   => array(
					'bg'     => (string) $get( 'bgColor' ),
					'fg'     => (string) $get( 'fgColor' ),
					'accent' => (string) $get( 'accentColor' ),
				),
				'design'   => array( 'countdown_size' => self::size_to_px( (string) $get( 'size', 'large' ) ) ),
				'labels'   => self::editor_labels( (string) $get( 'mainTitle' ), (string) $get( 'endedTitle' ) ),
			);

			// The legacy color pair still seeds main/alt; the canonical design
			// controls below override it when the user touches them.
			if ( '' !== (string) $get( 'bgColor' ) ) {
				$args['design']['main'] = (string) $get( 'bgColor' );
			}
			if ( '' !== (string) $get( 'fgColor' ) ) {
				$args['design']['alt'] = (string) $get( 'fgColor' );
			}

			// Canonical design/layout controls. Block attribute name = the
			// canonical key in camelCase (main_color -> mainColor).
			$values = array();
			foreach ( self::EDITOR_CONTROLS as $key => $target ) {
				$attr = lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $key ) ) ) );
				if ( array_key_exists( $attr, $a ) ) {
					$values[ $key ] = self::is_bool_control( $target ) ? (bool) $a[ $attr ] : $a[ $attr ];
				}
			}

			$args = self::from_editor( $values, $args );

			// A "specific event" selection without any id renders the empty
			// state rather than silently falling back to another event.
			if ( 'selected' === $source && empty( $block_ids ) ) {
				$args['source'] = 'none';
			}

			return self::normalize( $args );
		}

		/**
		 * Elementor widget adapter: control settings -> normalized args.
		 * Control keys: includes/editors/elementor/widgets/ (the phase3 harness
		 * asserts parity with the shortcode adapter).
		 *
		 * @since 2.0.0
		 * @param mixed $settings Widget settings (get_settings_for_display()).
		 * @return array
		 */
		public static function from_elementor( $settings ) {
			$s = is_array( $settings ) ? $settings : array();

			$get = function ( $key, $default = '' ) use ( $s ) {
				return isset( $s[ $key ] ) ? $s[ $key ] : $default;
			};

			$source = in_array( $get( 'tecc_source' ), array( 'next-upcoming', 'selected', 'filtered' ), true ) ? $get( 'tecc_source' ) : 'next-upcoming';

			// "Specific event(s)" accepts one OR many comma-separated event IDs
			// (multiple = a carousel, capped by Number of events). The same
			// tecc_event_id field held a single id before, which still parses.
			$elem_ids = self::split_ids( $get( 'tecc_event_id' ) );

			// Number of events is now a SLIDER (['size'=>N]); accept the old
			// scalar too.
			$elem_limit = $get( 'tecc_no_of_events', 1 );
			$elem_limit = ( is_array( $elem_limit ) && isset( $elem_limit['size'] ) ) ? $elem_limit['size'] : $elem_limit;

			$args = array(
				'source'    => $source,
				'event_id'  => $elem_ids ? $elem_ids[0] : 0,
				'event_ids' => $elem_ids,
				'filter'    => array(
					'category' => self::csv_slugs( $get( 'tecc_categories' ) ),
					'tag'      => self::csv_slugs( $get( 'tecc_tags' ) ),
				),
				'limit'    => absint( $elem_limit ),
				// No visible template picker; the hidden tecc_template slot is
				// written only by the preset tiles (card|banner).
				'template' => in_array( $get( 'tecc_template' ), array( 'card', 'banner' ), true ) ? $get( 'tecc_template' ) : 'card',
				'design'   => array(),
				'labels'   => self::editor_labels( (string) $get( 'tecc_main_title' ), (string) $get( 'tecc_ended_title' ) ),
			);

			// Canonical design/layout controls. Elementor control id = the
			// canonical key prefixed with tecc_; switchers are 'yes' or ''.
			// A registered-but-off switcher is present as '' — that is a real
			// "off", so flags read the key's presence, not its emptiness.
			$values = array();
			foreach ( self::EDITOR_CONTROLS as $key => $target ) {
				$id = 'tecc_' . $key;
				if ( ! array_key_exists( $id, $s ) ) {
					continue;
				}
				if ( self::is_bool_control( $target ) ) {
					// Elementor omits nothing once a control is registered, so
					// '' means the user switched it off — except when the whole
					// control is missing (handled by the array_key_exists above).
					$values[ $key ] = 'yes' === $s[ $id ];
				} else {
					// Elementor SLIDER controls store ['size'=>N,'unit'=>'px'];
					// take the size. Plain NUMBER/scalar values pass through, so
					// this is back-compat for widgets saved before the switch.
					$raw = $s[ $id ];
					if ( 'width' === $key && is_array( $raw ) && isset( $raw['size'] ) ) {
						// Width is a px/% slider — the UNIT is significant, so keep
						// the full CSS length ('100%', '640px') rather than the bare
						// number, which validate_design() would read as pixels.
						$unit           = isset( $raw['unit'] ) ? (string) $raw['unit'] : 'px';
						$values[ $key ] = '' === (string) $raw['size'] ? 'auto' : $raw['size'] . $unit;
					} else {
						$values[ $key ] = ( is_array( $raw ) && isset( $raw['size'] ) ) ? $raw['size'] : $raw;
					}
				}
			}
			$args = self::from_editor( $values, $args );

			// Deliberately no preset branch: presets seed the widget's own controls
			// in the editor (tecc-elementor-presets.js); rendering purely from
			// controls keeps every manual edit working.

			if ( 'selected' === $source && empty( $elem_ids ) ) {
				$args['source'] = 'none';
			}

			return self::normalize( $args );
		}

		/**
		 * Display-rule adapter: a validated rule (Rules::sanitize shape) -> args.
		 *
		 * The ONE place that maps a rule to render args, shared by the
		 * Display-Rules runtime (Locations::prepare) AND the admin rule
		 * preview (Settings_Rest) so both render identically — no drift.
		 *
		 * @since 2.0.0
		 * @param array $rule    Validated rule.
		 * @param array $context URL-derived context ('' in admin preview).
		 * @param bool  $auto    True for auto-display (empty -> ''); false for
		 *                       admin preview (empty -> empty-state message).
		 * @return array Normalized args.
		 */
		public static function from_rule( array $rule, array $context = array(), $auto = true ) {
			$style = isset( $rule['style'] ) ? (string) $rule['style'] : 'default';

			// The sitewide Light/Dark choice SEEDS a full design panel (bg, colours,
			// border, elevation), then the rule's own colour pickers (rule.design)
			// override on top — the sitewide styles UI writes real colours rather
			// than the old checkbox override system. validate_design re-validates.
			$base        = isset( self::SITEWIDE_STYLES[ $style ] ) ? self::SITEWIDE_STYLES[ $style ] : array();
			$rule_design = ( isset( $rule['design'] ) && is_array( $rule['design'] ) ) ? $rule['design'] : array();
			// The rule's timer size is a design key too; fold it into the merge.
			if ( isset( $rule['countdown_size'] ) && (int) $rule['countdown_size'] > 0 ) {
				$rule_design['countdown_size'] = (int) $rule['countdown_size'];
			}
			$design      = array_merge( $base, $rule_design );

			// Image control: 'none' hides it, otherwise position the image.
			$image_pos = isset( $rule['image_position'] ) ? (string) $rule['image_position'] : 'none';

			return self::normalize(
				array(
					'source'          => isset( $rule['source'] ) ? $rule['source'] : 'none',
					'event_id'        => 0,
					'event_ids'       => isset( $rule['event_ids'] ) ? $rule['event_ids'] : array(),
					'filter'          => isset( $rule['filter'] ) ? $rule['filter'] : array(),
					'limit'           => isset( $rule['limit'] ) ? $rule['limit'] : 1,
					'template'        => isset( $rule['template'] ) ? $rule['template'] : 'card',
					'style'           => $style,
					'countdown_style' => isset( $rule['countdown_style'] ) ? $rule['countdown_style'] : 'box',
					'show_image'      => ( 'none' !== $image_pos ),
					'image_position'  => ( 'none' === $image_pos ) ? 'top' : $image_pos,
					'design'          => $design,
					'tokens'          => isset( $rule['tokens'] ) ? $rule['tokens'] : array(),
					'auto'      => (bool) $auto,
					'context'   => $context,
				)
			);
		}

		/**
		 * Shared label defaults for the visual-editor adapters (mirrors the
		 * shortcode adapter so all editors emit identical markup).
		 *
		 * @since 2.0.0
		 * @param string $title Heading label ('' -> legacy default).
		 * @param string $ended Ended message.
		 * @return array
		 */
		private static function editor_labels( $title, $ended ) {
			return array(
				'title'   => '' !== $title ? $title : __( 'Next Event', 'countdown-for-the-events-calendar' ),
				'ended'   => '' !== $ended ? $ended : __( 'This Event Has Ended', 'countdown-for-the-events-calendar' ),
				'now'       => __( 'Happening now!', 'countdown-for-the-events-calendar' ),
				'ongoing'   => __( 'Event in Progress', 'countdown-for-the-events-calendar' ),
				'begins_in' => __( 'Begins in', 'countdown-for-the-events-calendar' ),
				'ends_in'   => __( 'ENDS IN', 'countdown-for-the-events-calendar' ),
				'button'    => __( 'Find out more', 'countdown-for-the-events-calendar' ),
			);
		}

		/**
		 * Does an EDITOR_CONTROLS target hold a boolean?
		 *
		 * Not the same question as "is it a flag:" — `shadow` is a design key
		 * whose DESIGN type is bool, so it needs the same truthiness handling
		 * as a flag. Getting this wrong makes an explicit "off" read as
		 * "unset", which silently inherits a truthy preset instead.
		 *
		 * @since 2.0.0
		 * @param string $target An EDITOR_CONTROLS target.
		 * @return bool
		 */
		private static function is_bool_control( $target ) {
			if ( 0 === strpos( $target, 'flag:' ) ) {
				return true;
			}

			if ( 0 === strpos( $target, 'design:' ) ) {
				$key = substr( $target, strlen( 'design:' ) );

				return isset( self::DESIGN[ $key ] ) && 'bool' === self::DESIGN[ $key ][1];
			}

			return false;
		}

		/**
		 * Route canonical editor-control values (EDITOR_CONTROLS keys) onto a
		 * partial args array.
		 *
		 * Only keys the editor actually supplied are routed: an omitted or
		 * empty control leaves the slot untouched so the chosen style preset —
		 * and then the hard default — still applies. Booleans must already be
		 * real bools (each adapter converts its own truthiness, e.g. an
		 * Elementor switcher's 'yes').
		 *
		 * NOTE on preset inheritance: an editor fills every registered control
		 * with its default, so any control whose default is non-empty is always
		 * routed and therefore always beats the style preset. Only the keys
		 * listed in PRESET_KEYS can be preset-driven — see the constant.
		 *
		 * @since 2.0.0
		 * @param array $values Canonical key => value.
		 * @param array $args   Partial args being built.
		 * @return array The args with design/enum/flag/label slots filled.
		 */
		private static function from_editor( array $values, array $args ) {
			foreach ( self::EDITOR_CONTROLS as $key => $target ) {
				if ( ! isset( $values[ $key ] ) ) {
					continue;
				}

				$value = $values[ $key ];
				$parts = explode( ':', $target, 2 );
				$kind  = $parts[0];
				$slot  = $parts[1];

				// Empty strings mean "not set" for everything but booleans;
				// validate_design/normalize would otherwise read '' as a real
				// (invalid) choice and fall back instead of inheriting.
				if ( ! self::is_bool_control( $target ) && ( '' === $value || null === $value ) ) {
					continue;
				}

				// The countdown-size control's floor is 10, so 0 can never be a
				// real choice — every editor uses it as "inherit from the
				// coarse Size control" rather than leaving the attribute
				// default-less. Shared here so no editor drifts.
				if ( 'design:countdown_size' === $target && (int) $value <= 0 ) {
					continue;
				}

				switch ( $kind ) {
					case 'design':
						$args['design'][ $slot ] = $value;
						break;
					case 'arg':
						$args[ $slot ] = (string) $value;
						break;
					case 'flag':
						$args[ $slot ] = (bool) $value;
						break;
					case 'label':
						$args['labels'][ $slot ] = (string) $value;
						break;
				}
			}

			return $args;
		}

		/**
		 * Translate one legacy size choice into a countdown-digit size.
		 *
		 * Kept for editors that still expose the coarse small|medium|large
		 * control alongside the finer numeric one.
		 *
		 * @since 2.0.0
		 * @param string $size Legacy size (small|medium|large).
		 * @return int
		 */
		private static function size_to_px( $size ) {
			$map = array(
				'small'  => 20,
				'medium' => 24,
				'large'  => 28,
			);

			return isset( $map[ $size ] ) ? $map[ $size ] : 28;
		}

		/**
		 * Parse a comma-separated slug list.
		 *
		 * @since 2.0.0
		 * @param mixed $csv Raw CSV string.
		 * @return string[]
		 */
		private static function csv_slugs( $csv ) {
			if ( ! is_string( $csv ) || '' === trim( $csv ) ) {
				return array();
			}

			$slugs = array();
			foreach ( explode( ',', $csv ) as $slug ) {
				$slug = sanitize_title( trim( $slug ) );
				if ( '' !== $slug ) {
					$slugs[] = $slug;
				}
			}

			return $slugs;
		}

		/**
		 * Parse a comma-separated list of event IDs into unique positive ints.
		 * Accepts a single id, "84, 92" style lists, or an array (a multi-value
		 * control). normalize() still caps the final list.
		 *
		 * @since 2.0.0
		 * @param mixed $raw Raw CSV string or array of ids.
		 * @return int[]
		 */
		private static function split_ids( $raw ) {
			if ( is_array( $raw ) ) {
				$parts = $raw;
			} elseif ( is_string( $raw ) || is_numeric( $raw ) ) {
				$parts = explode( ',', (string) $raw );
			} else {
				return array();
			}

			$ids = array();
			foreach ( $parts as $part ) {
				$id = absint( trim( (string) $part ) );
				if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}

			return $ids;
		}

		/**
		 * Fill defaults, enforce enums/types, sanitize labels, validate
		 * tokens, and stamp a render-unique instance id.
		 *
		 * @since 2.0.0
		 * @param array $args Partial args from any adapter.
		 * @return array Complete, safe args.
		 */
		public static function normalize( array $args ) {
			$out = array_merge( self::defaults(), $args );

			foreach ( self::ENUMS as $key => $allowed ) {
				if ( ! in_array( $out[ $key ], $allowed, true ) ) {
					$defaults    = self::defaults();
					$out[ $key ] = $defaults[ $key ];
				}
			}

			$out['event_id']  = absint( $out['event_id'] );
			$out['event_ids'] = array_slice( array_values( array_filter( array_map( 'absint', (array) $out['event_ids'] ) ) ), 0, 20 );
			$out['limit']     = max( 1, min( 5, (int) $out['limit'] ) );
			$out['auto']      = (bool) $out['auto'];

			// The carousel is a card-template feature; any other
			// template renders exactly one event — clamp instead of silently
			// dropping events 2..N at the template layer.
			if ( $out['limit'] > 1 && 'card' !== $out['template'] ) {
				$out['limit'] = 1;
			}

			foreach ( array( 'show_seconds', 'show_image', 'show_venue', 'show_button', 'show_cost', 'show_divider', 'show_timer_label', 'show_status', 'ongoing', 'image_gap' ) as $flag ) {
				$out[ $flag ] = (bool) $out[ $flag ];
			}

			// Seed the design from the chosen style preset, then let explicit
			// design attributes override it.
			$preset        = isset( self::STYLE_PRESETS[ $out['style'] ] ) ? self::STYLE_PRESETS[ $out['style'] ] : array();
			$out['design'] = self::validate_design( is_array( $out['design'] ) ? $out['design'] : array(), $preset );

			$filter = is_array( $out['filter'] ) ? $out['filter'] : array();
			$out['filter'] = array(
				'category'  => array_map( 'sanitize_title', isset( $filter['category'] ) ? (array) $filter['category'] : array() ),
				'tag'       => array_map( 'sanitize_title', isset( $filter['tag'] ) ? (array) $filter['tag'] : array() ),
				'venue'     => array_map( 'absint', isset( $filter['venue'] ) ? (array) $filter['venue'] : array() ),
				'organizer' => array_map( 'absint', isset( $filter['organizer'] ) ? (array) $filter['organizer'] : array() ),
			);

			// Labels are PLAIN TEXT: sanitize_text_field in,
			// esc_html at every print site. Never wp_kses_post here.
			$labels = is_array( $out['labels'] ) ? $out['labels'] : array();
			$clean  = array();
			foreach ( array( 'title', 'ended', 'now', 'ongoing', 'begins_in', 'ends_in', 'button' ) as $label_key ) {
				$value                = isset( $labels[ $label_key ] ) ? $labels[ $label_key ] : '';
				$clean[ $label_key ] = sanitize_text_field( wp_unslash( (string) $value ) );
			}
			$out['labels'] = $clean;

			$out['tokens'] = self::validate_tokens( is_array( $out['tokens'] ) ? $out['tokens'] : array() );

			if ( '' === $out['instance_id'] ) {
				self::$instances++;
				$out['instance_id'] = 'tecc-cd-' . self::$instances . '-' . substr( uniqid(), -6 );
			}
			$out['instance_id'] = sanitize_key( $out['instance_id'] );

			return $out;
		}

		/**
		 * CSS-token validator: validate each token BY TYPE before
		 * it can reach a style attribute — esc_attr alone does NOT make a
		 * CSS custom-property value safe. Bad values are dropped (the
		 * preset default applies).
		 *
		 * @since 2.0.0
		 * @param array $tokens Raw token overrides.
		 * @return array Only provably-safe tokens.
		 */
		public static function validate_tokens( array $tokens ) {
			$safe = array();

			foreach ( array( 'bg', 'fg', 'accent' ) as $color_token ) {
				if ( empty( $tokens[ $color_token ] ) || ! is_string( $tokens[ $color_token ] ) ) {
					continue;
				}
				$hex = sanitize_hex_color( trim( $tokens[ $color_token ] ) );
				if ( $hex ) {
					$safe[ $color_token ] = $hex;
				}
			}

			foreach ( array( 'radius', 'gap', 'size' ) as $length_token ) {
				if ( empty( $tokens[ $length_token ] ) || ! is_string( $tokens[ $length_token ] ) ) {
					continue;
				}
				$candidate = trim( $tokens[ $length_token ] );
				if ( preg_match( '/^\d{1,4}(px|em|rem|%)$/', $candidate ) ) {
					$safe[ $length_token ] = $candidate;
				}
			}

			// Anything else: dropped by design.
			return $safe;
		}

		/**
		 * Build the inline CSS-custom-property string for the wrapper.
		 * Only validated tokens ever reach this point.
		 *
		 * @since 2.0.0
		 * @param array $tokens Validated tokens (from normalize()).
		 * @return string e.g. "--tecc-bg:#123456;--tecc-fg:#ffffff".
		 */
		public static function style_attr( array $tokens ) {
			$parts = array();

			foreach ( self::validate_tokens( $tokens ) as $token => $value ) {
				$parts[] = '--tecc-' . $token . ':' . $value;
			}

			return implode( ';', $parts );
		}

		/**
		 * Validate the design set BY TYPE (colors/ints/width/bool). A bad
		 * value falls back to its default — never reaches CSS unvalidated.
		 *
		 * @since 2.0.0
		 * @param array $design Raw design overrides.
		 * @param array $base   Preset bundle used as the per-key fallback.
		 * @return array Complete, safe design map (every key present).
		 */
		public static function validate_design( array $design, array $base = array() ) {
			$safe = array();

			foreach ( self::DESIGN as $key => $spec ) {
				$default = array_key_exists( $key, $base ) ? $base[ $key ] : $spec[0];
				$type    = $spec[1];
				$val     = array_key_exists( $key, $design ) ? $design[ $key ] : $default;

				if ( 'color' === $type ) {
					// 'transparent' is the one non-hex colour we allow, and ONLY
					// for the card background ('bg') — the Classic presets need a
					// see-through card. Allowing it on the text/accent keys would
					// let a low-privileged author blank the label or button via
					// the shortcode (backgroundcolor/font-color map to main/alt).
					// It is a fixed keyword, not free-text, so it is safe verbatim.
					if ( 'bg' === $key && is_string( $val ) && 'transparent' === strtolower( trim( $val ) ) ) {
						$safe[ $key ] = 'transparent';
					} else {
						$hex          = is_string( $val ) ? sanitize_hex_color( trim( $val ) ) : '';
						$safe[ $key ] = $hex ? $hex : $default;
					}
				} elseif ( 'bool' === $type ) {
					$safe[ $key ] = (bool) $val;
				} elseif ( 'width' === $type ) {
					$raw = is_string( $val ) ? strtolower( trim( $val ) ) : (string) (int) $val;

					// Unit-aware: a bare number becomes px; a percentage / rem /
					// em / vw is kept as-is; anything else falls back to auto. The
					// stored value is the FULL CSS length string ('800px','70%').
					if ( 'auto' === $raw || '' === $raw ) {
						$safe[ $key ] = 'auto';
					} elseif ( preg_match( '/^(\d{1,4})(px)?$/', $raw, $m ) ) {
						$n            = (int) $m[1];
						$safe[ $key ] = $n > 0 ? max( 120, min( 1600, $n ) ) . 'px' : 'auto';
					} elseif ( preg_match( '/^(\d{1,3})%$/', $raw, $m ) ) {
						$n            = (int) $m[1];
						$safe[ $key ] = ( $n >= 10 && $n <= 100 ) ? $n . '%' : 'auto';
					} elseif ( preg_match( '/^(\d{1,3})(rem|em|vw)$/', $raw, $m ) ) {
						$safe[ $key ] = max( 1, min( 100, (int) $m[1] ) ) . $m[2];
					} else {
						$safe[ $key ] = 'auto';
					}
				} elseif ( 0 === strpos( $type, 'int:' ) ) {
					$bounds       = explode( ':', $type );
					$safe[ $key ] = max( (int) $bounds[1], min( (int) $bounds[2], (int) $val ) );
				}
			}

			return $safe;
		}

		/**
		 * Inline CSS-custom-property string for the design set.
		 *
		 * @since 2.0.0
		 * @param array $design Validated design map (from normalize()).
		 * @return string
		 */
		public static function design_style_attr( array $design ) {
			$design = self::validate_design( $design );
			$px     = array( 'title_size', 'body_size', 'countdown_size', 'border', 'radius', 'padding' );
			$parts  = array();

			foreach ( self::DESIGN_VARS as $key => $var ) {
				if ( ! isset( $design[ $key ] ) ) {
					continue;
				}
				if ( in_array( $key, $px, true ) ) {
					$parts[] = $var . ':' . (int) $design[ $key ] . 'px';
				} elseif ( 'width' === $key ) {
					// Already a validated CSS length ('auto' | '800px' | '70%').
					$parts[] = $var . ':' . $design[ $key ];
				} else {
					$parts[] = $var . ':' . $design[ $key ];
				}
			}

			return implode( ';', $parts );
		}

		/**
		 * Layout classes derived from the normalized args.
		 *
		 * @since 2.0.0
		 * @param array $args Normalized args.
		 * @return string Space-separated BEM modifier classes.
		 */
		public static function design_classes( array $args ) {
			$align = array(
				'left'   => 'l',
				'center' => 'c',
				'right'  => 'r',
			);
			$a = isset( $align[ $args['content_align'] ] ) ? $align[ $args['content_align'] ] : 'l';

			$classes = array(
				'tecc-cd--cd-' . $args['countdown_style'],
				'tecc-cd--img-' . $args['image_position'],
				'tecc-cd--align-' . $a,
			);
			if ( ! empty( $args['image_gap'] ) ) {
				$classes[] = 'tecc-cd--img-gap';
			}
			// A solid (non-transparent) card background gets a minimum inner
			// spacing floor so content never touches the edges even at padding 0;
			// a transparent card stays flush to the page. See --tecc-pad-eff.
			$bg = isset( $args['design']['bg'] ) ? (string) $args['design']['bg'] : '';
			if ( '' !== $bg && 'transparent' !== strtolower( $bg ) ) {
				$classes[] = 'tecc-cd--has-bg';
			}
			if ( ! empty( $args['design']['shadow'] ) ) {
				$classes[] = 'tecc-cd--shadow';
			}
			if ( ! empty( $args['design']['border'] ) && (int) $args['design']['border'] > 0 ) {
				$classes[] = 'tecc-cd--bordered';
			}

			return implode( ' ', $classes );
		}
	}
}
