<?php
/**
 * Schema versioning + idempotent, additive-only upgrade routine.
 *
 * Every additive option change bumps VERSION and adds an upgrade step.
 * Steps must be idempotent and NEVER destructive — legacy options
 * (`tecc_settings`, `tecc-v`, `tecc-install-date`, …) are read-time mapped,
 * never rewritten.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Schema' ) ) {

	/**
	 * Tracks the plugin data-schema version across updates.
	 *
	 * @since 2.0.0
	 */
	final class Schema {

		/**
		 * Current schema version. Bump on every additive option change.
		 */
		const VERSION = 4;

		/**
		 * Option name storing the installed schema version.
		 */
		const OPTION = 'tecc_schema_version';

		/**
		 * Run pending upgrade steps when the stored version is behind.
		 *
		 * Runs on `plugins_loaded` (and on activation). A plugin UPDATE does
		 * not fire the activation hook, so this is the only reliable
		 * update-time entry point.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function maybe_upgrade() {
			$installed = (int) get_option( self::OPTION, 0 );

			if ( $installed >= self::VERSION ) {
				return;
			}

			// A pre-2.0 tecc-v means this is an UPGRADE (fresh installs stamp
			// 2.0.0 in activate() first) — flag the one-time what's-new notice;
			// add_option + the schema bump make it run at most once.
			if ( $installed < 2 ) {
				$tecc_prev = get_option( 'tecc-v' );
				if ( is_string( $tecc_prev ) && '' !== $tecc_prev && version_compare( $tecc_prev, '2.0.0', '<' ) ) {
					add_option( 'tecc_v2_welcome', 'show', '', 'no' );
				}
			}

			if ( $installed < 2 ) {
				self::upgrade_to_2();
			}
			if ( $installed < 3 ) {
				self::upgrade_to_3();
			}
			if ( $installed < 4 ) {
				self::upgrade_to_4();
			}

			update_option( self::OPTION, self::VERSION );
		}

		/**
		 * Schema v2: seed the Display Rules option with autoload OFF — the
		 * rules list must never ride along on every request's alloptions.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private static function upgrade_to_2() {
			add_option( 'tecc_display_rules', array(), '', 'no' );
		}

		/**
		 * Schema v3: drop the short-lived `tecc_review_prompt` option.
		 *
		 * It duplicated the legacy `tecc-ratingDiv` flag without changing any
		 * behaviour (both were written together, and either one silenced the
		 * ask), so `tecc-ratingDiv` is now the single source of truth. Only
		 * pre-release 2.0 builds ever wrote this key; delete_option is a no-op
		 * everywhere else.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private static function upgrade_to_3() {
			delete_option( 'tecc_review_prompt' );
			delete_option( 'tecc_review_shown' );
		}

		/**
		 * Schema v4: retire the v1 render engine.
		 *
		 * The legacy renderer and its `tecc_engine_mode` switch shipped as a
		 * rollback net during the 2.0 rewrite and were removed once v2 was
		 * verified. Every site renders v2 now, so the option is meaningless;
		 * delete_option is a no-op on sites that never had it.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private static function upgrade_to_4() {
			delete_option( 'tecc_engine_mode' );
		}
	}
}
