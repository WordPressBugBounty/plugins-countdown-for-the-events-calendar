<?php
/**
 * Tiny PSR-4-ish autoloader for the CoolPlugins\Countdown namespace.
 *
 * Maps `CoolPlugins\Countdown\{Sub\}Class_Name` to
 * `{includes|admin}/{sub/}class-tecc-{class-name}.php` (WordPress file naming).
 * The `Admin` sub-namespace resolves under `admin/`, everything else under
 * `includes/`. PHP 7.2 compatible.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Autoloader' ) ) {

	/**
	 * Class autoloader for all CoolPlugins\Countdown classes.
	 *
	 * @since 2.0.0
	 */
	final class Autoloader {

		/**
		 * Namespace prefix handled by this autoloader.
		 */
		const PREFIX = 'CoolPlugins\\Countdown\\';

		/**
		 * Register the autoloader with SPL.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function register() {
			spl_autoload_register( array( __CLASS__, 'autoload' ) );
		}

		/**
		 * Load a class file for a fully qualified class name.
		 *
		 * @since 2.0.0
		 * @param string $class_name Fully qualified class name.
		 * @return void
		 */
		public static function autoload( $class_name ) {
			if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
				return;
			}

			$relative = substr( $class_name, strlen( self::PREFIX ) );
			$parts    = explode( '\\', $relative );
			$class    = array_pop( $parts );

			// `Admin\*` classes live under admin/, everything else under includes/.
			$base = 'includes/';
			if ( ! empty( $parts ) && 'Admin' === $parts[0] ) {
				$base = 'admin/';
				array_shift( $parts );
			}

			$subdir = '';
			if ( ! empty( $parts ) ) {
				$subdir = strtolower( implode( '/', $parts ) ) . '/';
			}

			$file = TECC_PLUGIN_DIR . $base . $subdir . 'class-tecc-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}
}
