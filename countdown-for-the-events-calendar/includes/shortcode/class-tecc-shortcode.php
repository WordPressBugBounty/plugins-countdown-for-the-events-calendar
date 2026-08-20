<?php
/**
 * Back-compat shortcode `[events-calendar-countdown]` -> shared renderer.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Shortcode;

use CoolPlugins\Countdown\Render\Args;
use CoolPlugins\Countdown\Render\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Shortcode\Shortcode' ) ) {

	/**
	 * Registers the shortcode tag and routes it to the shared renderer.
	 *
	 * @since 2.0.0
	 */
	final class Shortcode {

		/**
		 * The registered dispatcher (the admin live preview renders through
		 * the exact same entry point as the front end).
		 *
		 * @var Shortcode|null
		 */
		private static $instance = null;

		/**
		 * The registered dispatcher instance, if any.
		 *
		 * @since 2.0.0
		 * @return Shortcode|null
		 */
		public static function instance() {
			return self::$instance;
		}

		/**
		 * Register the tag.
		 *
		 * @since 2.0.0
		 */
		public function __construct() {
			self::$instance = $this;

			add_shortcode( 'events-calendar-countdown', array( $this, 'render' ) );
		}

		/**
		 * Shortcode callback.
		 *
		 * @since 2.0.0
		 * @param mixed $atts Raw shortcode attributes.
		 * @return string
		 */
		public function render( $atts = array() ) {
			$atts = is_array( $atts ) ? $atts : array();

			return Renderer::render( Args::from_shortcode( $atts ) );
		}
	}
}
