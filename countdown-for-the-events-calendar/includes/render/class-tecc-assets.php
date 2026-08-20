<?php
/**
 * Front-end asset registration + conditional enqueue.
 *
 * Assets load ONLY when a countdown actually renders: handles are
 * registered up-front on wp_enqueue_scripts, but enqueued from the
 * renderer at render time.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Render;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Render\Assets' ) ) {

	/**
	 * Registers and conditionally enqueues the v2 front-end assets.
	 *
	 * @since 2.0.0
	 */
	final class Assets {

		/**
		 * Hook registration. Called once from the Plugin bootstrap.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function init() {
			// init (not wp_enqueue_scripts): the handles must be resolvable in
			// EVERY context — front end, admin, block-editor iframe (block.json
			// lists them by handle name) and REST block-renderer requests.
			add_action( 'init', array( __CLASS__, 'register' ), 20 );
		}

		/**
		 * Register (not enqueue) all v2 handles.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function register() {
			if ( wp_style_is( 'tecc-tokens', 'registered' ) ) {
				return;
			}
			wp_register_style( 'tecc-tokens', TECC_CSS_URL . '/tokens.css', array(), TECC_VERSION_CURRENT );
			wp_register_style( 'tecc-templates', TECC_CSS_URL . '/templates.css', array( 'tecc-tokens' ), TECC_VERSION_CURRENT );
			wp_register_script( 'tecc-countdown', TECC_JS_DIR . '/tecc-countdown.js', array(), TECC_VERSION_CURRENT, true );
			wp_register_script( 'tecc-carousel', TECC_JS_DIR . '/tecc-carousel.js', array(), TECC_VERSION_CURRENT, true );

			// Classic stylesheet: the untouched legacy countdown.css. The
			// legacy bootstrap may have registered it already; keep one handle.
			if ( ! wp_style_is( 'countdown-css', 'registered' ) ) {
				wp_register_style( 'countdown-css', TECC_CSS_URL . '/countdown.css', array(), TECC_VERSION_CURRENT );
			}
		}

		/**
		 * Enqueue exactly what the rendered instance needs.
		 *
		 * @since 2.0.0
		 * @param array $args        Normalized args.
		 * @param int   $event_count Actually-resolved event count.
		 * @return void
		 */
		public static function enqueue( array $args, $event_count = 1 ) {
			// Late render (shortcode in content runs after wp_enqueue_scripts):
			// register on demand so direct enqueues still work.
			if ( ! wp_style_is( 'tecc-tokens', 'registered' ) ) {
				self::register();
			}

			wp_enqueue_style( 'tecc-tokens' );

			if ( 'classic' === $args['template'] ) {
				wp_enqueue_style( 'countdown-css' );
			} else {
				wp_enqueue_style( 'tecc-templates' );
			}

			wp_enqueue_script( 'tecc-countdown' );

			// Carousel runtime: only when the card template ACTUALLY renders
			// 2+ slides (resolved count, not the requested limit).
			if ( 'card' === $args['template'] && (int) $event_count > 1 ) {
				wp_enqueue_script( 'tecc-carousel' );
			}
		}
	}
}
