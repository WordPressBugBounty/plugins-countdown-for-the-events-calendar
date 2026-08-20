<?php
/**
 * Elementor integration bootstrap (free Elementor APIs only).
 *
 * Wires the "Events Addons" widget category and the Countdown widget into
 * Elementor. Loaded from the main Plugin bootstrap on `plugins_loaded` (5),
 * which fires BEFORE Elementor loads — so all wiring is deferred to
 * `elementor/loaded` when Elementor is not up yet.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Editors\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Editors\Elementor\Elementor_Integration' ) ) {

	/**
	 * Registers the widget category and the Countdown widget with Elementor.
	 *
	 * @since 2.0.0
	 */
	final class Elementor_Integration {

		/**
		 * Whether hooks() already ran (idempotency guard).
		 *
		 * @var bool
		 */
		private static $hooked = false;

		/**
		 * Entry point, called from the Plugin bootstrap on plugins_loaded (5).
		 *
		 * Elementor boots later on plugins_loaded, so when it is not loaded
		 * yet we defer all wiring to `elementor/loaded`. If Elementor never
		 * loads, the deferred callback simply never fires — no fatals.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function init() {
			if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
				add_action( 'elementor/loaded', array( __CLASS__, 'hooks' ), 10 );
			} else {
				self::hooks();
			}
		}

		/**
		 * Attach the Elementor registration hooks (runs at most once).
		 *
		 * `elementor/elements/categories_registered` always fires before
		 * widgets are registered, so the category exists by the time the
		 * widget declares it.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function hooks() {
			if ( true === self::$hooked ) {
				return;
			}

			self::$hooked = true;

			add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ), 10, 1 );
			add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ), 10, 1 );
			add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ), 10 );
		}

		/**
		 * Editor-only assets: the starter-preset card grid (styles + the JS that
		 * seeds the widget's controls when a card is clicked).
		 *
		 * @since 2.1.0
		 * @return void
		 */
		public static function enqueue_editor_assets() {
			wp_enqueue_style(
				'tecc-elementor-editor',
				TECC_CSS_URL . '/tecc-elementor-editor.css',
				array(),
				TECC_VERSION_CURRENT
			);

			wp_enqueue_script(
				'tecc-elementor-presets',
				TECC_JS_DIR . '/tecc-elementor-presets.js',
				array( 'jquery' ),
				TECC_VERSION_CURRENT,
				true
			);

			if ( class_exists( 'CoolPlugins\Countdown\Render\Presets' ) ) {
				wp_localize_script(
					'tecc-elementor-presets',
					'teccPresets',
					\CoolPlugins\Countdown\Render\Presets::for_js()
				);
			}
		}

		/**
		 * Register the shared "Events Addons" widget category.
		 *
		 * @since 2.0.0
		 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
		 * @return void
		 */
		public static function register_category( $elements_manager ) {
			if ( ! is_callable( array( $elements_manager, 'add_category' ) ) ) {
				return;
			}

			$elements_manager->add_category(
				'tecc-events-addons',
				array(
					'title' => __( 'Events Addons', 'countdown-for-the-events-calendar' ),
					'icon'  => 'eicon-calendar',
				)
			);
		}

		/**
		 * Load and register the Countdown widget.
		 *
		 * Feature-detects the manager API: Elementor >= 3.5 exposes
		 * `register()`, older builds expose `register_widget_type()`.
		 *
		 * @since 2.0.0
		 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
		 * @return void
		 */
		public static function register_widgets( $widgets_manager ) {
			if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
				return;
			}

			require_once TECC_PLUGIN_DIR . 'includes/editors/elementor/widgets/class-tecc-countdown-widget.php';

			if ( ! class_exists( 'CoolPlugins\Countdown\Editors\Elementor\Countdown_Widget' ) ) {
				return;
			}

			$widget = new Countdown_Widget();

			if ( is_callable( array( $widgets_manager, 'register' ) ) ) {
				$widgets_manager->register( $widget );
			} elseif ( is_callable( array( $widgets_manager, 'register_widget_type' ) ) ) {
				$widgets_manager->register_widget_type( $widget );
			}
		}
	}
}
