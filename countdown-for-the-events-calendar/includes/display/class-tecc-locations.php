<?php
/**
 * Display Rules runtime: context snapshot, winner resolution and the
 * wp_footer printer for footer / floating / bar locations.
 *
 * Cache-safety: the context is URL-derived only, events resolve server-side
 * per URL, the timer uses absolute epochs, and the per-visitor conditions
 * (logged-in / mobile) plus dismissal are evaluated client-side — so cached
 * pages stay correct for every visitor.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Display;

use CoolPlugins\Countdown\Render\Args;
use CoolPlugins\Countdown\Render\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Display\Locations' ) ) {

	/**
	 * Auto-display runtime.
	 *
	 * @since 2.0.0
	 */
	final class Locations {

		/**
		 * Survivors ready to print: {location, html, client, rule_id}.
		 *
		 * @var array[]
		 */
		private static $queue = array();

		/**
		 * Hook the runtime (front end only).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function init() {
			add_action( 'template_redirect', array( __CLASS__, 'prepare' ), 10 );
			add_action( 'wp_footer', array( __CLASS__, 'print_locations' ), 20 );
		}

		/**
		 * Resolve winners + render EARLY (template_redirect) so the renderer's
		 * conditional asset enqueues land in wp_head. A matched rule whose
		 * source resolves to zero events renders nothing and enqueues nothing.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function prepare() {
			if ( is_admin() || is_feed() || is_robots() || is_embed() ) {
				return;
			}

			$rules = Rules::all();
			if ( empty( $rules ) ) {
				return;
			}

			$context  = self::snapshot();
			$timezone = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
			$winners  = Rules_Evaluator::winning_rules( $context, $rules, time(), $timezone );

			foreach ( $winners as $location => $winner ) {
				$rule = $winner['rule'];

				// Shared rule->args factory (identical mapping the admin rule
				// preview uses, so preview == front end).
				$html = Renderer::render( Args::from_rule( $rule, $context, true ) );

				// A layered baseline (rendered when the winner carries per-visitor
				// conditions) keeps this location from going blank for visitors the
				// winner doesn't apply to. Both share a layer group so the browser
				// shows exactly one (winner when eligible, else baseline).
				$fallback      = isset( $winner['fallback'] ) ? $winner['fallback'] : null;
				$fallback_html = ( $fallback ) ? Renderer::render( Args::from_rule( $fallback['rule'], $context, true ) ) : '';

				if ( '' === $html ) {
					// Winner matched but has no events. If a baseline exists
					// and DOES have events, it covers every visitor here.
					if ( '' !== $fallback_html ) {
						self::$queue[] = array(
							'location' => $location,
							'html'     => $fallback_html,
							'client'   => $fallback['client'],
							'rule_id'  => $fallback['rule']['id'],
							'group'    => $fallback['rule']['id'],
							'close'    => self::close_action( $fallback['rule'] ),
						);
					}
					continue;
				}

				$group = $rule['id'];
				self::$queue[] = array(
					'location' => $location,
					'html'     => $html,
					'client'   => $winner['client'],
					'rule_id'  => $rule['id'],
					'group'    => $group,
					'close'    => self::close_action( $rule ),
				);

				if ( '' !== $fallback_html ) {
					self::$queue[] = array(
						'location' => $location,
						'html'     => $fallback_html,
						'client'   => $fallback['client'],
						'rule_id'  => $fallback['rule']['id'],
						'group'    => $group,
						'close'    => self::close_action( $fallback['rule'] ),
					);
				}
			}

			if ( ! empty( self::$queue ) ) {
				wp_enqueue_style( 'tecc-locations', TECC_CSS_URL . '/tecc-locations.css', array( 'tecc-tokens' ), TECC_VERSION_CURRENT );
				wp_enqueue_script( 'tecc-locations', TECC_JS_DIR . '/tecc-locations.js', array(), TECC_VERSION_CURRENT, true );
			}
		}

		/**
		 * Resolve a rule's close-button behaviour, defaulting rules saved before
		 * the setting existed to 'event' rather than the old permanent hide.
		 *
		 * @since 2.1.0
		 * @param array $rule Sanitized rule.
		 * @return string One of Rules::CLOSE_ACTIONS.
		 */
		private static function close_action( array $rule ) {
			$mode = isset( $rule['close_action'] ) ? (string) $rule['close_action'] : '';
			return in_array( $mode, Rules::CLOSE_ACTIONS, true ) ? $mode : 'event';
		}

		/**
		 * Print the surviving locations at wp_footer.
		 *
		 * Floating/bar markup is ALWAYS server-rendered; dismissal only hides
		 * client-side (localStorage) — server logic never branches on it.
		 * Dismiss is a keyboard-accessible role=button span.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function print_locations() {
			foreach ( self::$queue as $entry ) {
				$client      = $entry['client'];
				$has_require = ! empty( $client['require'] );
				$has_client  = $has_require || ! empty( $client['forbid'] );
				// Every location floats now, so all are dismissible.
				$dismissible = true;

				$group = isset( $entry['group'] ) ? $entry['group'] : $entry['rule_id'];

				printf(
					'<div class="tecc-location tecc-location--%1$s" data-tecc-location="%1$s" data-tecc-rule="%2$s" data-tecc-layer-group="%3$s"',
					esc_attr( $entry['location'] ),
					esc_attr( $entry['rule_id'] ),
					esc_attr( $group )
				);

				if ( $has_client ) {
					printf( ' data-tecc-client="%s"', esc_attr( (string) wp_json_encode( $client ) ) );
				}
				if ( $dismissible ) {
					printf(
						' data-tecc-dismiss="tecc-dm-%1$s" data-tecc-close="%2$s"',
						esc_attr( $entry['rule_id'] ),
						esc_attr( isset( $entry['close'] ) ? $entry['close'] : 'event' )
					);
				}
				if ( $has_require ) {
					// Hidden until the browser confirms the visitor qualifies.
					echo ' hidden';
				}

				echo '>';

				if ( $dismissible ) {
					// A <span> (not a <button>) so theme/Elementor default button
					// styles can't override the close control; JS wires click +
					// Enter/Space and it carries role/tabindex for a11y.
					printf(
						'<span class="tecc-location__dismiss" role="button" tabindex="0" aria-label="%s">&times;</span>',
						esc_attr__( 'Dismiss countdown', 'countdown-for-the-events-calendar' )
					);
				}

				echo $entry['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fully escaped by the renderer.

				echo '</div>';
			}
		}

		/**
		 * URL-derived context snapshot (nothing per-visitor in here).
		 *
		 * @since 2.0.0
		 * @return array
		 */
		public static function snapshot() {
			$post_id        = 0;
			$post_type      = '';
			$single_event   = false;
			$event_archive  = false;
			$categories     = array();
			$tags           = array();
			$venue_id       = 0;

			if ( is_singular() ) {
				$post_id   = (int) get_queried_object_id();
				$post_type = (string) get_post_type( $post_id );

				if ( 'tribe_events' === $post_type ) {
					$single_event = true;
					$categories   = self::term_slugs( $post_id, 'tribe_events_cat' );
					$tags         = self::term_slugs( $post_id, 'post_tag' );

					if ( function_exists( 'tribe_get_venue_id' ) ) {
						$venue_id = (int) tribe_get_venue_id( $post_id );
					}
				}
			} elseif ( is_post_type_archive( 'tribe_events' ) ) {
				$event_archive = true;
				$post_type     = 'tribe_events';
			} elseif ( is_tax( 'tribe_events_cat' ) ) {
				$event_archive = true;
				$post_type     = 'tribe_events';
				$term          = get_queried_object();
				if ( $term && isset( $term->slug ) ) {
					$categories[] = (string) $term->slug;
				}
			}

			$url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

			return array(
				'is_front'         => is_front_page(),
				'is_blog'          => is_home(),
				'is_tec'           => $single_event || $event_archive,
				'is_single_event'  => $single_event,
				'single_event_id'  => $single_event ? $post_id : 0,
				'is_event_archive' => $event_archive,
				'post_type'        => $post_type,
				'post_id'          => $post_id,
				'tec_categories'   => $categories,
				'tec_tags'         => $tags,
				'tec_venue_id'     => $venue_id,
				'url'              => $url,
			);
		}

		/**
		 * Term slugs for a post ('' guarded).
		 *
		 * @since 2.0.0
		 * @param int    $post_id  Post id.
		 * @param string $taxonomy Taxonomy name.
		 * @return string[]
		 */
		private static function term_slugs( $post_id, $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! is_array( $terms ) ) {
				return array();
			}

			$slugs = array();
			foreach ( $terms as $term ) {
				if ( isset( $term->slug ) ) {
					$slugs[] = (string) $term->slug;
				}
			}

			return $slugs;
		}

		/**
		 * Test/preview access to the pending queue.
		 *
		 * @since 2.0.0
		 * @return array[]
		 */
		public static function queue() {
			return self::$queue;
		}

		/**
		 * Reset the queue (harness use).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function reset() {
			self::$queue = array();
		}
	}
}
