<?php
/**
 * Display Rules storage + structure-validating sanitizer.
 *
 * Rules live in the option `tecc_display_rules` (autoload=false, seeded by
 * Schema v2). Hard caps: TECC_RULES_MAX rules, TECC_CONDITIONS_MAX conditions
 * per rule. Every field is enum-whitelisted / typed — a bad rule is dropped,
 * never partially stored.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Display;

use CoolPlugins\Countdown\Render\Args;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Display\Rules' ) ) {

	/**
	 * Rule schema, caps and sanitization.
	 *
	 * @since 2.0.0
	 */
	final class Rules {

		const OPTION         = 'tecc_display_rules';
		const MAX_RULES      = 50;
		const MAX_CONDITIONS = 20;

		/**
		 * Valid rule locations — five floating placements (one countdown max per
		 * location per page). All float over the page (no inline footer).
		 *
		 * @var string[]
		 */
		const LOCATIONS = array( 'top-bar', 'footer-bar', 'footer-left', 'footer-right', 'footer-middle' );

		/**
		 * Back-compat aliases: pre-2.1 location slugs -> current slug. Keeps a
		 * rule saved before the rename valid on the next load instead of being
		 * dropped as an unknown location.
		 *
		 * @var array<string,string>
		 */
		const LOCATION_ALIASES = array(
			'bar-top'        => 'top-bar',
			'bar-bottom'     => 'footer-bar',
			'floating-left'  => 'footer-left',
			'floating-right' => 'footer-right',
			'footer'         => 'footer-bar',
		);

		/**
		 * Locations that render as a full-width / centered BANNER; the rest render
		 * as a corner CARD. The template is forced by location — there is no
		 * user-facing template control for a display.
		 *
		 * @var string[]
		 */
		const BANNER_LOCATIONS = array( 'top-bar', 'footer-bar', 'footer-middle' );

		/**
		 * What a visitor's close click does. 'event' hides until the countdown
		 * targets a different event, 'session' until the tab closes, 'week' for
		 * seven days, 'forever' permanently (the pre-2.1 behaviour).
		 *
		 * @since 2.1.0
		 * @var array
		 */
		const CLOSE_ACTIONS = array( 'event', 'session', 'week', 'forever' );

		/**
		 * Valid rule event sources.
		 *
		 * @var string[]
		 */
		const SOURCES = array( 'next-upcoming', 'next-in-context', 'filtered', 'selected' );

		/**
		 * Condition types. Value format per type:
		 *  - (none):       entire_site, front_page, blog, all_tec_pages,
		 *                  single_event, event_archive, user_logged_in, is_mobile
		 *  - slug:         post_type
		 *  - id list:      page_id, post_id, tec_venue
		 *  - slug list:    tec_category, tec_tag
		 *  - string:       url_contains
		 *
		 * user_logged_in and is_mobile are CLIENT-side conditions (the
		 * cache-safety carve-out): the evaluator carries them to the wrapper,
		 * JS decides.
		 *
		 * @var string[]
		 */
		const CONDITION_TYPES = array(
			'entire_site',
			'front_page',
			'blog',
			'all_tec_pages',
			'single_event',
			'event_archive',
			'post_type',
			'page_id',
			'post_id',
			'tec_category',
			'tec_tag',
			'tec_venue',
			'url_contains',
			'user_logged_in',
			'is_mobile',
		);

		/**
		 * Client-evaluated condition types.
		 *
		 * @var string[]
		 */
		const CLIENT_TYPES = array( 'user_logged_in', 'is_mobile' );

		/**
		 * Stored rules (validated shape).
		 *
		 * @since 2.0.0
		 * @return array[]
		 */
		public static function all() {
			$rules = get_option( self::OPTION, array() );

			if ( ! is_array( $rules ) ) {
				return array();
			}

			// Map legacy slugs on READ too — the render path consumes this raw,
			// and an unmapped slug emits a dead CSS class; template re-forced for consistency.
			foreach ( $rules as &$rule ) {
				if ( is_array( $rule ) && isset( $rule['location'], self::LOCATION_ALIASES[ $rule['location'] ] ) ) {
					$rule['location'] = self::LOCATION_ALIASES[ $rule['location'] ];
					$rule['template'] = in_array( $rule['location'], self::BANNER_LOCATIONS, true ) ? 'banner' : 'card';
				}
			}
			unset( $rule );

			return $rules;
		}

		/**
		 * register_setting sanitize callback. Accepts the JSON string the
		 * admin builder submits (or an already-decoded array) and returns a
		 * fully validated rule list. Invalid input -> the existing option
		 * (never destroys rules on a malformed save).
		 *
		 * @since 2.0.0
		 * @param mixed $input JSON string or array.
		 * @return array[]
		 */
		public static function sanitize( $input ) {
			if ( is_string( $input ) ) {
				$input = json_decode( wp_unslash( $input ), true );
			}

			if ( ! is_array( $input ) ) {
				return self::all();
			}

			$clean = array();
			$index = 0;

			foreach ( $input as $rule ) {
				if ( count( $clean ) >= self::MAX_RULES ) {
					break; // Hard cap.
				}
				if ( ! is_array( $rule ) ) {
					continue;
				}

				$valid = self::sanitize_rule( $rule, $index );
				if ( null !== $valid ) {
					$clean[] = $valid;
					$index++;
				}
			}

			return $clean;
		}

		/**
		 * Validate one rule. Returns null when the rule is structurally
		 * unusable (bad location/source).
		 *
		 * @since 2.0.0
		 * @param array $rule  Raw rule.
		 * @param int   $index Position (used for priority default + id).
		 * @return array|null
		 */
		private static function sanitize_rule( array $rule, $index ) {
			$location = isset( $rule['location'] ) ? (string) $rule['location'] : '';
			$source   = isset( $rule['source'] ) ? (string) $rule['source'] : '';

			// Map a legacy slug forward before validating.
			if ( isset( self::LOCATION_ALIASES[ $location ] ) ) {
				$location = self::LOCATION_ALIASES[ $location ];
			}

			if ( ! in_array( $location, self::LOCATIONS, true ) || ! in_array( $source, self::SOURCES, true ) ) {
				return null;
			}

			$id = isset( $rule['id'] ) ? sanitize_key( (string) $rule['id'] ) : '';
			if ( '' === $id || strlen( $id ) > 40 ) {
				$id = 'r-' . ( $index + 1 ) . '-' . substr( uniqid(), -6 );
			}

			// Template is FORCED by location (banner for bars/middle, card for
			// the corners); any submitted template is ignored. Style is limited
			// to the Light/Dark presets the display UI offers.
			$template = in_array( $location, self::BANNER_LOCATIONS, true ) ? 'banner' : 'card';
			$style    = ( isset( $rule['style'] ) && in_array( $rule['style'], array( 'light', 'dark' ), true ) ) ? $rule['style'] : 'dark';

			$limit = isset( $rule['limit'] ) ? max( 1, min( 5, (int) $rule['limit'] ) ) : 1;
			// Carousel (limit > 1) is card-only — keep stored rules consistent
			// with the render-time clamp in Args::normalize().
			if ( $limit > 1 && 'card' !== $template ) {
				$limit = 1;
			}

			$filter = isset( $rule['filter'] ) && is_array( $rule['filter'] ) ? $rule['filter'] : array();

			return array(
				'id'         => $id,
				'enabled'    => ! empty( $rule['enabled'] ),
				// Priority IS the drag order: the rule's position in the saved
				// list. No user-facing priority field — reorder to re-prioritize.
				'priority'   => ( $index + 1 ) * 10,
				'location'   => $location,
				'source'     => $source,
				'filter'     => array(
					'category' => array_slice( self::slug_list( isset( $filter['category'] ) ? $filter['category'] : array() ), 0, 20 ),
					'tag'      => array_slice( self::slug_list( isset( $filter['tag'] ) ? $filter['tag'] : array() ), 0, 20 ),
				),
				'event_ids'  => array_slice( self::id_list( isset( $rule['event_ids'] ) ? $rule['event_ids'] : array() ), 0, 20 ),
				'limit'      => $limit,
				'template'   => $template,
				'style'      => $style,
				'countdown_style' => in_array( isset( $rule['countdown_style'] ) ? $rule['countdown_style'] : '', array( 'box', 'ring', 'inline' ), true ) ? $rule['countdown_style'] : 'box',
				'countdown_size'  => min( 120, max( 0, isset( $rule['countdown_size'] ) ? absint( $rule['countdown_size'] ) : 0 ) ),
				'image_position'  => in_array( isset( $rule['image_position'] ) ? $rule['image_position'] : '', array( 'none', 'top', 'left', 'right' ), true ) ? $rule['image_position'] : 'none',
				'design'     => self::sanitize_design( isset( $rule['design'] ) && is_array( $rule['design'] ) ? $rule['design'] : array() ),
				'tokens'     => Args::validate_tokens( isset( $rule['tokens'] ) && is_array( $rule['tokens'] ) ? $rule['tokens'] : array() ),
				// Missing key = pre-2.1 rule; default 'event', not the old permanent hide.
				'close_action' => in_array( isset( $rule['close_action'] ) ? $rule['close_action'] : '', self::CLOSE_ACTIONS, true ) ? $rule['close_action'] : 'event',
				'conditions' => self::sanitize_conditions( isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array() ),
			);
		}

		/**
		 * Validate a rule's colour pickers (the sitewide Styles tab writes real
		 * colours, seeded by Light/Dark). Only the five design colours are kept,
		 * each hex-validated; bg alone may be the literal 'transparent'. Unset or
		 * invalid keys are dropped so from_rule() falls back to the style seed.
		 *
		 * @since 2.1.0
		 * @param array $design Raw {main,alt,title_color,text_color,bg}.
		 * @return array Validated colour subset.
		 */
		private static function sanitize_design( array $design ) {
			$out = array();
			foreach ( array( 'main', 'alt', 'title_color', 'text_color', 'bg' ) as $key ) {
				if ( ! isset( $design[ $key ] ) || ! is_string( $design[ $key ] ) ) {
					continue;
				}
				$raw = trim( $design[ $key ] );
				if ( 'bg' === $key && 'transparent' === strtolower( $raw ) ) {
					$out[ $key ] = 'transparent';
					continue;
				}
				$hex = sanitize_hex_color( $raw );
				if ( $hex ) {
					$out[ $key ] = $hex;
				}
			}
			// Numeric size / spacing controls (bounds mirror Args::DESIGN).
			$ints = array(
				'title_size' => array( 8, 80 ),
				'body_size'  => array( 8, 48 ),
				'radius'     => array( 0, 80 ),
				'border'     => array( 0, 20 ),
				'padding'    => array( 0, 80 ),
			);
			foreach ( $ints as $key => $bounds ) {
				if ( isset( $design[ $key ] ) && '' !== $design[ $key ] ) {
					$out[ $key ] = max( $bounds[0], min( $bounds[1], absint( $design[ $key ] ) ) );
				}
			}
			// Width — unit-aware (auto | px | %), mirroring Args::validate_design.
			if ( isset( $design['width'] ) && '' !== $design['width'] ) {
				$w = strtolower( trim( (string) $design['width'] ) );
				if ( 'auto' === $w ) {
					$out['width'] = 'auto';
				} elseif ( preg_match( '/^(\d{1,4})(px)?$/', $w, $m ) ) {
					$out['width'] = max( 120, min( 1600, (int) $m[1] ) ) . 'px';
				} elseif ( preg_match( '/^(\d{1,3})%$/', $w, $m ) ) {
					$n            = (int) $m[1];
					$out['width'] = ( $n >= 10 && $n <= 100 ) ? $n . '%' : 'auto';
				} elseif ( preg_match( '/^(\d{1,3})(rem|em|vw)$/', $w, $m ) ) {
					$out['width'] = max( 1, min( 100, (int) $m[1] ) ) . $m[2];
				} else {
					$out['width'] = 'auto';
				}
			}
			return $out;
		}

		/**
		 * Validate include/exclude condition lists (shared MAX_CONDITIONS cap).
		 *
		 * @since 2.0.0
		 * @param array $conditions {include:[], exclude:[]}.
		 * @return array
		 */
		private static function sanitize_conditions( array $conditions ) {
			$out    = array(
				'include' => array(),
				'exclude' => array(),
			);
			$budget = self::MAX_CONDITIONS;

			foreach ( array( 'include', 'exclude' ) as $bucket ) {
				$list = isset( $conditions[ $bucket ] ) && is_array( $conditions[ $bucket ] ) ? $conditions[ $bucket ] : array();

				foreach ( $list as $condition ) {
					if ( $budget <= 0 ) {
						break 2; // Hard cap across both buckets.
					}
					if ( ! is_array( $condition ) || ! isset( $condition['type'] ) ) {
						continue;
					}

					$type = (string) $condition['type'];
					if ( ! in_array( $type, self::CONDITION_TYPES, true ) ) {
						continue;
					}

					$raw = isset( $condition['value'] ) ? $condition['value'] : '';
					$out[ $bucket ][] = array(
						'type'  => $type,
						'value' => self::sanitize_condition_value( $type, $raw ),
					);
					$budget--;
				}
			}

			// A rule with no include conditions can never match — normalize
			// the common intent by defaulting to entire_site.
			if ( empty( $out['include'] ) ) {
				$out['include'][] = array(
					'type'  => 'entire_site',
					'value' => '',
				);
			}

			return $out;
		}

		/**
		 * Per-type condition value validation.
		 *
		 * @since 2.0.0
		 * @param string $type Condition type (whitelisted).
		 * @param mixed  $raw  Raw value.
		 * @return mixed Normalized value (array for lists, string otherwise).
		 */
		private static function sanitize_condition_value( $type, $raw ) {
			switch ( $type ) {
				case 'post_type':
					return sanitize_key( is_scalar( $raw ) ? (string) $raw : '' );

				case 'page_id':
				case 'post_id':
				case 'tec_venue':
					return self::id_list( $raw );

				case 'tec_category':
				case 'tec_tag':
					return self::slug_list( $raw );

				case 'url_contains':
					$value = sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' );
					return substr( $value, 0, 200 );
			}

			return '';
		}

		/**
		 * CSV/array -> positive int list.
		 *
		 * @since 2.0.0
		 * @param mixed $raw Raw list.
		 * @return int[]
		 */
		private static function id_list( $raw ) {
			if ( is_string( $raw ) ) {
				$raw = explode( ',', $raw );
			}

			$ids = array();
			foreach ( (array) $raw as $candidate ) {
				$id = absint( is_scalar( $candidate ) ? $candidate : 0 );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}

			return array_values( array_unique( $ids ) );
		}

		/**
		 * CSV/array -> sanitized slug list.
		 *
		 * @since 2.0.0
		 * @param mixed $raw Raw list.
		 * @return string[]
		 */
		private static function slug_list( $raw ) {
			if ( is_string( $raw ) ) {
				$raw = explode( ',', $raw );
			}

			$slugs = array();
			foreach ( (array) $raw as $candidate ) {
				$slug = sanitize_title( trim( is_scalar( $candidate ) ? (string) $candidate : '' ) );
				if ( '' !== $slug ) {
					$slugs[] = $slug;
				}
			}

			return array_values( array_unique( $slugs ) );
		}
	}
}
