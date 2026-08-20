<?php
/**
 * REST live-preview route.
 *
 * POST /wp-json/tecc/v1/preview  { atts: { legacy attr names } } -> { html }
 *
 * The preview renders through the EXACT same pipeline as the front-end
 * shortcode (Args::from_shortcode -> Renderer::render), so WYSIWYG parity
 * is guaranteed by construction, and the input surface is identical to the
 * already-hardened shortcode surface (whitelist + validation + escaping).
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Admin\Settings;

use CoolPlugins\Countdown\Compat\Migrate;
use CoolPlugins\Countdown\Render\Args;
use CoolPlugins\Countdown\Render\Renderer;
use CoolPlugins\Countdown\Tec\Tec;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Admin\Settings\Settings_Rest' ) ) {

	/**
	 * Registers and serves the admin live-preview endpoint.
	 *
	 * @since 2.0.0
	 */
	final class Settings_Rest {

		/**
		 * How many suggestions the source picker shows on its plain initial open
		 * (no search term). The rest are one keystroke away; already-selected
		 * chips are always returned on top of this cap.
		 *
		 * @var int
		 */
		const INITIAL_SUGGESTIONS = 4;

		/**
		 * Hook route registration. Called unconditionally from the Plugin
		 * bootstrap (REST requests are not is_admin()).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function init() {
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 10 );
		}

		/**
		 * Register the preview route.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function register_routes() {
			register_rest_route(
				'tecc/v1',
				'/preview',
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'preview' ),
					'permission_callback' => array( __CLASS__, 'can_preview' ),
					'args'                => array(
						'atts' => array(
							'type'     => 'object',
							'required' => false,
						),
						'rule' => array(
							'type'     => 'object',
							'required' => false,
						),
					),
				)
			);

			// Searchable source picker for the select2 multi-selects (events OR
			// categories). select2-shaped: { results: [ { id, text } ] }.
			register_rest_route(
				'tecc/v1',
				'/source',
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'source' ),
					'permission_callback' => array( __CLASS__, 'can_preview' ),
					'args'                => array(
						'type'    => array( 'type' => 'string', 'required' => false ),
						'search'  => array( 'type' => 'string', 'required' => false ),
						'include' => array( 'type' => 'string', 'required' => false ),
					),
				)
			);
		}

		/**
		 * Searchable source rows for select2 (events or categories).
		 *
		 * Loads a first page unfiltered (~10 shown, up to 30 fetched) and
		 * narrows on the typed term; already-selected ids are always resolved
		 * so their chips show a label, not a bare id. Read-only, editor-gated.
		 *
		 * @since 2.1.0
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response { results: [ { id, text } ] }
		 */
		public static function source( $request ) {
			$type    = 'category' === (string) $request->get_param( 'type' ) ? 'category' : 'events';
			$search  = sanitize_text_field( (string) $request->get_param( 'search' ) );
			// Cap the pre-select list: it only needs to label existing chips,
			// and an uncapped list would drive an unbounded per_page query.
			$include = array_slice( array_values( array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'include' ) ) ) ) ), 0, 50 );

			$results = array();
			if ( 'category' === $type ) {
				foreach ( \CoolPlugins\Countdown\Tec\Tec::picker_categories( $search, $include ) as $row ) {
					$results[] = array( 'id' => (int) $row['id'], 'text' => (string) $row['name'] );
				}
			} else {
				foreach ( \CoolPlugins\Countdown\Tec\Tec::picker_events( 30, $search, $include ) as $row ) {
					$results[] = array( 'id' => (int) $row['id'], 'text' => (string) $row['title'] );
				}
			}

			// On the plain initial open (no search term) show only a few
			// suggestions — the rest are one search away. Explicitly-included
			// ids (labels for already-selected chips) are ALWAYS kept so a chip
			// never falls back to a bare id.
			if ( '' === $search ) {
				$inc   = array_flip( $include );
				$kept  = array();
				$extra = array();
				foreach ( $results as $row ) {
					if ( isset( $inc[ $row['id'] ] ) ) {
						$kept[] = $row;
					} else {
						$extra[] = $row;
					}
				}
				$results = array_merge( $kept, array_slice( $extra, 0, self::INITIAL_SUGGESTIONS ) );
			}

			return rest_ensure_response( array( 'results' => $results ) );
		}

		/**
		 * Explicit permission callback: logged-in editors only.
		 * Cookie auth requires a valid X-WP-Nonce, which the admin JS sends.
		 *
		 * @since 2.0.0
		 * @return bool
		 */
		public static function can_preview() {
			return current_user_can( 'edit_posts' );
		}

		/**
		 * Render a preview from whitelisted shortcode attributes.
		 *
		 * Only keys the shortcode itself accepts (Migrate whitelist) pass
		 * through, and every value is forced to a scalar string before it
		 * reaches the (already validating) Args pipeline. The visibility
		 * guard applies unchanged — drafts never render.
		 *
		 * @since 2.0.0
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response
		 */
		public static function preview( $request ) {
			// Display-rule preview branch (Sitewide Display tab). Strictly
			// additive: only taken when a `rule` object is present; otherwise
			// the shortcode `atts` path below runs verbatim.
			$rule_raw = $request->get_param( 'rule' );
			if ( is_array( $rule_raw ) ) {
				return self::preview_rule( $rule_raw );
			}

			$raw  = $request->get_param( 'atts' );
			$raw  = is_array( $raw ) ? $raw : array();
			$keep = array_merge( Migrate::LEGACY_ATTRS, Migrate::V2_ATTRS );
			$atts = array();

			foreach ( $raw as $key => $value ) {
				if ( in_array( (string) $key, $keep, true ) && is_scalar( $value ) ) {
					$atts[ (string) $key ] = (string) $value;
				}
			}

			// Specific-events / category with nothing chosen → blank preview
			// (never invent the site-wide next upcoming event).
			$preview_source = isset( $atts['source'] ) ? (string) $atts['source'] : '';
			$preview_sid    = isset( $atts['source-id'] ) ? trim( (string) $atts['source-id'] ) : '';
			if ( in_array( $preview_source, array( 'events', 'category' ), true ) && '' === $preview_sid ) {
				$pick = ( 'category' === $preview_source )
					? esc_html__( 'Choose a category to see the preview.', 'countdown-for-the-events-calendar' )
					: esc_html__( 'Select an event to see the preview.', 'countdown-for-the-events-calendar' );

				return rest_ensure_response( array( 'html' => '<p class="tecc-preview-empty">' . $pick . '</p>' ) );
			}

			// Render through the SAME dispatcher the front end uses, so the
			// preview can never drift from real front-end output.
			$dispatcher = class_exists( 'CoolPlugins\Countdown\Shortcode\Shortcode', false )
				? \CoolPlugins\Countdown\Shortcode\Shortcode::instance()
				: null;

			$html = $dispatcher
				? (string) $dispatcher->render( $atts )
				: Renderer::render( Args::from_shortcode( $atts ) );

			if ( '' === $html ) {
				$html = '<p class="tecc-preview-empty">' . esc_html( self::empty_reason() ) . '</p>';
			}

			return rest_ensure_response( array( 'html' => $html ) );
		}

		/**
		 * Render a Display-Rule preview (Sitewide Display tab).
		 *
		 * Validates through the PUBLIC Rules::sanitize (never the private
		 * sanitize_rule) and renders through the SAME Args::from_rule factory
		 * the front-end runtime uses, with auto=false so an empty result shows
		 * the empty-state message instead of nothing. No page context exists
		 * in admin, so next-in-context resolves to the site-wide fallback.
		 *
		 * @since 2.0.0
		 * @param array $rule_raw Raw rule object from the builder.
		 * @return \WP_REST_Response
		 */
		private static function preview_rule( array $rule_raw ) {
			$rules = \CoolPlugins\Countdown\Display\Rules::sanitize( array( $rule_raw ) );

			if ( empty( $rules ) ) {
				return rest_ensure_response(
					array(
						'html' => '<p class="tecc-preview-empty">' . esc_html__( 'This display needs a valid location and event source before it can preview.', 'countdown-for-the-events-calendar' ) . '</p>',
					)
				);
			}

			$html = Renderer::render( Args::from_rule( $rules[0], array(), false ) );

			if ( '' === $html ) {
				// A site-wide cause (TEC off, nothing published) is more useful than
				// blaming this display for something no display could show.
				$reason = self::empty_reason( __( 'No event currently matches this display.', 'countdown-for-the-events-calendar' ) );
				$html   = '<p class="tecc-preview-empty">' . esc_html( $reason ) . '</p>';
			}

			return rest_ensure_response( array( 'html' => $html ) );
		}

		/**
		 * Why is the preview empty? Name the cause the user can act on.
		 *
		 * "Nothing matched" is useless when the real problem is that TEC is off
		 * or the site has no events at all — those need a different next step
		 * than tweaking the countdown's settings.
		 *
		 * @since 2.0.0
		 * @param string $fallback Message when events exist but none matched.
		 * @return string
		 */
		private static function empty_reason( $fallback = '' ) {
			if ( ! class_exists( 'CoolPlugins\Countdown\Tec\Tec' ) || ! Tec::available() ) {
				// Tec::available() is false for BOTH "missing" and "too old"; telling
				// someone on TEC 5.x it is "not active" sends them hunting for a
				// plugin that is already running.
				if ( class_exists( 'Tribe__Events__Main' ) && defined( 'TECC_MIN_TEC_VERSION' ) ) {
					return sprintf(
						/* translators: %s: minimum required The Events Calendar version. */
						__( 'The Events Calendar needs to be version %s or newer for the preview to work.', 'countdown-for-the-events-calendar' ),
						TECC_MIN_TEC_VERSION
					);
				}

				return __( 'The Events Calendar is not active, so there is nothing to preview.', 'countdown-for-the-events-calendar' );
			}

			$counts = wp_count_posts( 'tribe_events' );
			if ( ! $counts || empty( $counts->publish ) ) {
				return __( 'No published events yet. Publish an event and it will show up here.', 'countdown-for-the-events-calendar' );
			}

			if ( '' !== $fallback ) {
				return $fallback;
			}

			return __( 'No upcoming events match this setup right now.', 'countdown-for-the-events-calendar' );
		}
	}
}
