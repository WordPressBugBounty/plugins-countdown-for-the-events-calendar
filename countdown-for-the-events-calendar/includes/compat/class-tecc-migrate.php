<?php
/**
 * Read-time legacy + design-attribute mapping.
 *
 * Maps v1.x shortcode attributes AND the v2 flexible-design attributes to the
 * normalized `$args` schema at render time. No option is ever rewritten.
 *
 * Two source models, chosen by whether the new `source` attribute is present:
 *   NEW: source=events|category + source-id=all|<ids> + show-ongoing + no-of-events
 *   LEGACY: the strict future > list > id precedence the old renderer drove
 *     (A = autostart-next-countdown, F = autostart-future-countdown,
 *      L = future-events-list). Kept byte-for-byte for back-compat.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Compat\Migrate' ) ) {

	/**
	 * Pure legacy-attr/design-attr → v2-args mapping.
	 *
	 * @since 2.0.0
	 */
	final class Migrate {

		/**
		 * The real v1 shortcode attributes. Dead `fontsize`/`textsize`
		 * are dropped.
		 *
		 * @var string[]
		 */
		const LEGACY_ATTRS = array(
			'id',
			'backgroundcolor',
			'font-color',
			'show-seconds',
			'show-image',
			'size',
			'event-start',
			'event-end',
			'autostart-next-countdown',
			'autostart-future-countdown',
			'future-events-list',
			'autostart-text',
			'main-title',
		);

		/**
		 * v2 flexible-design attributes (additive).
		 *
		 * @var string[]
		 */
		const V2_ATTRS = array(
			// Source.
			'source',
			'source-id',
			'source-ui',
			'categories',
			'show-ongoing',
			'no-of-events',
			// Content.
			'show-venue',
			'show-cost',
			'show-button',
			'show-divider',
			'show-timer-label',
			'show-status',
			// Labels.
			'ongoing-title',
			'ended-title',
			'button-text',
			// Layout.
			'template',
			'style',
			'countdown-style',
			'image-position',
			'image-gap',
			'content-align',
			'limit',
			// Design.
			'main-color',
			'alternate-color',
			'title-color',
			'text-color',
			'bg-color',
			'accent-color',
			'title-size',
			'body-size',
			'countdown-size',
			'border',
			'radius',
			'padding',
			'width',
			'shadow',
		);

		/**
		 * Map raw shortcode attributes to (un-normalized) v2 args.
		 *
		 * @since 2.0.0
		 * @param array $atts Raw shortcode attributes.
		 * @return array Partial v2 args (Args::normalize() finishes the job).
		 */
		public static function from_shortcode_atts( array $atts ) {
			$atts = self::whitelist( $atts );
			$args = self::map_source( $atts );

			return array_merge( $args, self::map_presentation( $atts ), self::map_labels( $atts, $args['source'] ) );
		}

		/**
		 * Map the stored `tecc_settings` option to (un-normalized) v2 args.
		 * Bridges the `event_id` (option) vs `id` (shortcode) key mismatch.
		 *
		 * @since 2.0.0
		 * @param array $options The tecc_settings option array.
		 * @return array Partial v2 args.
		 */
		public static function from_options( array $options ) {
			if ( isset( $options['event_id'] ) && ! isset( $options['id'] ) ) {
				$options['id'] = $options['event_id'];
			}

			return self::from_shortcode_atts( $options );
		}

		/**
		 * Drop everything outside the accepted set.
		 *
		 * @since 2.0.0
		 * @param array $atts Raw attributes.
		 * @return array
		 */
		private static function whitelist( array $atts ) {
			$keep = array_merge( self::LEGACY_ATTRS, self::V2_ATTRS );
			$out  = array();

			foreach ( $atts as $key => $value ) {
				if ( in_array( (string) $key, $keep, true ) ) {
					$out[ (string) $key ] = $value;
				}
			}

			return $out;
		}

		/**
		 * Resolve the event source. New model when `source` is present, else
		 * the legacy F > L > id truth table.
		 *
		 * @since 2.0.0
		 * @param array $atts Whitelisted attributes.
		 * @return array {source, event_id?, event_ids?, filter?, ongoing?, limit?}
		 */
		private static function map_source( array $atts ) {
			// These apply to BOTH models — a saved option round-trips without a
			// `source` attribute but must still honor show-ongoing / no-of-events.
			$ongoing = ! isset( $atts['show-ongoing'] ) || 'no' !== $atts['show-ongoing'];
			$limit   = isset( $atts['no-of-events'] ) ? (int) $atts['no-of-events'] : 1;
			$base    = array(
				'ongoing' => $ongoing,
				'limit'   => $limit,
			);

			// A saved default persists the CHOSEN source explicitly (source-ui) so
			// several populated source fields can't make it flip. It is
			// authoritative when there is no explicit shortcode `source` attr.
			if ( ! isset( $atts['source'] ) && isset( $atts['source-ui'] ) ) {
				$ui = (string) $atts['source-ui'];
				if ( 'category' === $ui ) {
					$cats = self::parse_token_list( isset( $atts['categories'] ) ? (string) $atts['categories'] : '' );
					// Empty picker → nothing to show (never invent "next upcoming").
					return empty( $cats )
						? array_merge( $base, array( 'source' => 'none' ) )
						: array_merge( $base, array( 'source' => 'filtered', 'filter' => array( 'category' => $cats ) ) );
				}
				if ( 'events' === $ui ) {
					$ids = isset( $atts['future-events-list'] ) ? self::parse_id_list( $atts['future-events-list'] ) : array();
					// Panel picks map to source=selected (honours the carousel limit);
					// legacy-list — raw future-events-list attr only — collapses to
					// one soonest event. Empty picker -> blank, not next-upcoming.
					return empty( $ids )
						? array_merge( $base, array( 'source' => 'none' ) )
						: array_merge( $base, array( 'source' => 'selected', 'event_ids' => $ids ) );
				}
				if ( 'upcoming' === $ui ) {
					return array_merge( $base, array( 'source' => 'next-upcoming' ) );
				}
			}

			// New model: an explicit `source`, OR a stored `categories` selection
			// (the settings panel persists `categories` without a `source` key).
			$source_attr = '';
			if ( isset( $atts['source'] ) ) {
				$source_attr = (string) $atts['source'];
			} elseif ( isset( $atts['categories'] ) && '' !== trim( (string) $atts['categories'] ) ) {
				$source_attr = 'category';
			}

			// ---- New model ---------------------------------------------------
			if ( '' !== $source_attr ) {
				if ( isset( $atts['source-id'] ) ) {
					$raw_id = (string) $atts['source-id'];
				} elseif ( 'category' === $source_attr && isset( $atts['categories'] ) ) {
					$raw_id = (string) $atts['categories'];
				} else {
					$raw_id = 'all';
				}

				if ( 'category' === $source_attr ) {
					$cats = self::parse_token_list( $raw_id );
					if ( empty( $cats ) ) {
						// 'all' / empty → no category chosen yet (panel blank state).
						return array_merge( $base, array( 'source' => 'none' ) );
					}
					return array_merge(
						$base,
						array(
							'source' => 'filtered',
							'filter' => array( 'category' => $cats ),
						)
					);
				}

				// source = events.
				// 'all' is the Upcoming-radio encoding (next site-wide event).
				// An empty source-id is the Specific-events radio with no chips —
				// never invent an upcoming event for that.
				$raw_trim = strtolower( trim( $raw_id ) );
				if ( 'all' === $raw_trim ) {
					return array_merge( $base, array( 'source' => 'next-upcoming' ) );
				}
				if ( '' === $raw_trim ) {
					return array_merge( $base, array( 'source' => 'none' ) );
				}

				$ids = self::parse_id_list( $raw_id );
				if ( empty( $ids ) ) {
					return array_merge( $base, array( 'source' => 'none' ) );
				}

				return array_merge(
					$base,
					array(
						'source'    => 'selected',
						'event_ids' => $ids,
					)
				);
			}

			// ---- Legacy model (F > L > id) -----------------------------------
			$a = isset( $atts['autostart-next-countdown'] ) && 'yes' === $atts['autostart-next-countdown'];
			$f = isset( $atts['autostart-future-countdown'] ) && 'yes' === $atts['autostart-future-countdown'];
			$l = isset( $atts['future-events-list'] ) ? self::parse_id_list( $atts['future-events-list'] ) : array();

			if ( $a && $f ) {
				return array_merge( $base, array( 'source' => 'next-upcoming' ) );
			}

			if ( $a && ! empty( $l ) ) {
				return array_merge(
					$base,
					array(
						'source'    => 'legacy-list',
						'event_ids' => $l,
					)
				);
			}

			if ( isset( $atts['id'] ) && is_numeric( $atts['id'] ) && (int) $atts['id'] > 0 ) {
				// Populate event_ids too (the canonical selected-source field the
				// block/Elementor/rules use), so every surface resolves a single
				// selected event identically. event_id stays for back-compat.
				return array_merge(
					$base,
					array(
						'source'    => 'selected',
						'event_id'  => (int) $atts['id'],
						'event_ids' => array( (int) $atts['id'] ),
					)
				);
			}

			return array_merge( $base, array( 'source' => 'none' ) );
		}

		/**
		 * Parse an id list in BOTH stored forms (array of ids / CSV string).
		 * "0"/empty entries are inert.
		 *
		 * @since 2.0.0
		 * @param mixed $raw List value.
		 * @return int[]
		 */
		public static function parse_id_list( $raw ) {
			if ( is_string( $raw ) ) {
				$raw = explode( ',', $raw );
			}
			if ( ! is_array( $raw ) ) {
				return array();
			}

			$ids = array();
			foreach ( $raw as $candidate ) {
				if ( is_numeric( is_string( $candidate ) ? trim( $candidate ) : $candidate ) ) {
					$id = (int) $candidate;
					if ( $id > 0 ) {
						$ids[] = $id;
					}
				}
			}

			return array_values( array_unique( $ids ) );
		}

		/**
		 * Parse a token list (category ids OR slugs) from array/CSV.
		 *
		 * @since 2.0.0
		 * @param mixed $raw List value.
		 * @return string[]
		 */
		public static function parse_token_list( $raw ) {
			if ( is_string( $raw ) ) {
				$raw = explode( ',', $raw );
			}
			if ( ! is_array( $raw ) ) {
				return array();
			}

			$tokens = array();
			foreach ( $raw as $candidate ) {
				$token = trim( (string) $candidate );
				if ( '' !== $token && 'all' !== strtolower( $token ) ) {
					$tokens[] = $token;
				}
			}

			return array_values( array_unique( $tokens ) );
		}

		/**
		 * Colors/sizes/layout → tokens (back-compat) + design (new) + toggles.
		 *
		 * @since 2.0.0
		 * @param array $atts Whitelisted attributes.
		 * @return array
		 */
		private static function map_presentation( array $atts ) {
			$args = array(
				'tokens' => array(),
				'design' => array(),
			);

			// Legacy color tokens (kept for the classic/minimal templates + the
			// existing token contract) AND the new design colors.
			if ( ! empty( $atts['backgroundcolor'] ) ) {
				$args['tokens']['bg']    = (string) $atts['backgroundcolor'];
				$args['design']['main']  = (string) $atts['backgroundcolor'];
			}
			if ( ! empty( $atts['font-color'] ) ) {
				$args['tokens']['fg']   = (string) $atts['font-color'];
				$args['design']['alt']  = (string) $atts['font-color'];
			}
			if ( ! empty( $atts['accent-color'] ) ) {
				$args['tokens']['accent'] = (string) $atts['accent-color'];
			}

			// New design colors override the legacy mapping.
			$color_map = array(
				'main-color'      => 'main',
				'alternate-color' => 'alt',
				'title-color'     => 'title_color',
				'text-color'      => 'text_color',
				'bg-color'        => 'bg',
			);
			foreach ( $color_map as $attr => $key ) {
				if ( ! empty( $atts[ $attr ] ) ) {
					$args['design'][ $key ] = (string) $atts[ $attr ];
				}
			}

			// Sizes. Legacy small/medium/large maps to a countdown size.
			if ( ! empty( $atts['size'] ) ) {
				$args['size']                     = (string) $atts['size'];
				$legacy_size                      = array(
					'small'  => 20,
					'medium' => 24,
					'large'  => 28,
				);
				$args['design']['countdown_size'] = isset( $legacy_size[ $atts['size'] ] ) ? $legacy_size[ $atts['size'] ] : 28;
			}
			$dim_map = array(
				'countdown-size' => 'countdown_size',
				'title-size'     => 'title_size',
				'body-size'      => 'body_size',
				'border'         => 'border',
				'radius'         => 'radius',
				'padding'        => 'padding',
			);
			foreach ( $dim_map as $attr => $key ) {
				if ( isset( $atts[ $attr ] ) && '' !== $atts[ $attr ] ) {
					$args['design'][ $key ] = (int) $atts[ $attr ];
				}
			}
			// Shortcode-path only: unset/auto width caps at a readable 480px inside
			// free-flowing content; explicit values pass through raw so
			// validate_design() keeps %/rem units.
			$tecc_w = isset( $atts['width'] ) ? trim( (string) $atts['width'] ) : '';
			if ( '' !== $tecc_w && 'auto' !== strtolower( $tecc_w ) ) {
				$args['design']['width'] = $tecc_w;
			} else {
				$args['design']['width'] = '480';
			}
			if ( isset( $atts['shadow'] ) ) {
				$args['design']['shadow'] = ( 'yes' === $atts['shadow'] );
			}

			// Toggles (legacy defaults preserved).
			if ( isset( $atts['show-seconds'] ) && '' !== $atts['show-seconds'] ) {
				$args['show_seconds'] = ( 'no' !== $atts['show-seconds'] );
			}
			if ( isset( $atts['show-image'] ) ) {
				$args['show_image'] = ( 'yes' === $atts['show-image'] );
			}
			if ( isset( $atts['show-venue'] ) ) {
				$args['show_venue'] = ( 'no' !== $atts['show-venue'] );
			}
			if ( isset( $atts['show-cost'] ) ) {
				$args['show_cost'] = ( 'yes' === $atts['show-cost'] );
			}
			if ( isset( $atts['show-button'] ) ) {
				$args['show_button'] = ( 'no' !== $atts['show-button'] );
			}
			if ( isset( $atts['show-divider'] ) ) {
				$args['show_divider'] = ( 'yes' === $atts['show-divider'] );
			}
			if ( isset( $atts['show-timer-label'] ) ) {
				$args['show_timer_label'] = ( 'no' !== $atts['show-timer-label'] );
			}
			if ( isset( $atts['show-status'] ) ) {
				$args['show_status'] = ( 'no' !== $atts['show-status'] );
			}

			// Shortcode embeds default to the modern card; template="classic"
			// keeps the legacy DOM.
			$args['template'] = ( ! empty( $atts['template'] ) ) ? (string) $atts['template'] : 'card';
			if ( ! empty( $atts['style'] ) ) {
				$args['style'] = (string) $atts['style'];
			}
			if ( ! empty( $atts['countdown-style'] ) ) {
				$args['countdown_style'] = (string) $atts['countdown-style'];
			}
			if ( ! empty( $atts['image-position'] ) ) {
				$args['image_position'] = (string) $atts['image-position'];
			}
			if ( isset( $atts['image-gap'] ) ) {
				$args['image_gap'] = ( 'no' !== $atts['image-gap'] );
			}
			if ( ! empty( $atts['content-align'] ) ) {
				$args['content_align'] = (string) $atts['content-align'];
			}
			if ( ! empty( $atts['limit'] ) ) {
				$args['limit'] = (int) $atts['limit'];
			}

			return $args;
		}

		/**
		 * Mode-sensitive labels + the new ongoing/ended titles.
		 *
		 * @since 2.0.0
		 * @param array  $atts   Whitelisted attributes.
		 * @param string $source Resolved source id.
		 * @return array {labels: array}
		 */
		private static function map_labels( array $atts, $source ) {
			// v1's autostart-text ("refresh page to see the next event") has no v2
			// equivalent: the rollover happens client-side via data-tecc-next. The
			// attribute is still accepted, just inert.
			$title = isset( $atts['main-title'] ) && '' !== $atts['main-title']
				? (string) $atts['main-title']
				: __( 'Next Event', 'countdown-for-the-events-calendar' );

			$ongoing = isset( $atts['ongoing-title'] ) && '' !== $atts['ongoing-title']
				? (string) $atts['ongoing-title']
				: ( isset( $atts['event-start'] ) && '' !== $atts['event-start']
					? (string) $atts['event-start']
					: __( 'Event in Progress', 'countdown-for-the-events-calendar' ) );

			$ended = isset( $atts['ended-title'] ) && '' !== $atts['ended-title']
				? (string) $atts['ended-title']
				: ( isset( $atts['event-end'] ) && '' !== $atts['event-end']
					? (string) $atts['event-end']
					: __( 'This Event Has Ended', 'countdown-for-the-events-calendar' ) );

			return array(
				'labels' => array(
					'title'   => $title,
					'ended'   => $ended,
					'now'       => __( 'Happening now!', 'countdown-for-the-events-calendar' ),
					'ongoing'   => $ongoing,
					'begins_in' => __( 'Begins in', 'countdown-for-the-events-calendar' ),
					'ends_in'   => __( 'ENDS IN', 'countdown-for-the-events-calendar' ),
					'button'    => ( isset( $atts['button-text'] ) && '' !== $atts['button-text'] )
						? (string) $atts['button-text']
						: __( 'Find out more', 'countdown-for-the-events-calendar' ),
				),
			);
		}
	}
}
