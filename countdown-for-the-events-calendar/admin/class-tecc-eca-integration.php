<?php
/**
 * Boots the shared ECA dashboard module for Event Countdown.
 *
 * Mirrors the sibling Events Addons integrations (ECT / ECMD / EPTA / ESPBP)
 * exactly: the vendored module in admin/eca-dashboard/ is a byte-identical copy
 * of the shared source, and the registry picks the newest submitted copy across
 * all sibling addons.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TECC_ECA_Integration' ) ) {

	/**
	 * Single entry point for ECA dashboard integration.
	 */
	final class TECC_ECA_Integration {

		const DASHBOARD_PAGE_SLUG = 'cool-plugins-events-addon';
		const DASHBOARD_VERSION   = '1.0.0';

		/**
		 * Load dashboard classes and register this addon as the Countdown host.
		 */
		public static function boot_admin() {
			if ( ! defined( 'ECA_DASHBOARD_VERSION' ) ) {
				define( 'ECA_DASHBOARD_VERSION', self::DASHBOARD_VERSION ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			}

			require_once TECC_PLUGIN_DIR . 'admin/eca-dashboard/includes/class-eca-addon-map.php';
			require_once TECC_PLUGIN_DIR . 'admin/eca-dashboard/includes/class-eca-dashboard-environment.php';
			require_once TECC_PLUGIN_DIR . 'admin/eca-dashboard/includes/class-eca-dashboard-registry.php';
			require_once TECC_PLUGIN_DIR . 'admin/eca-dashboard/includes/class-eca-dashboard-i18n.php';
			require_once TECC_PLUGIN_DIR . 'admin/eca-dashboard/includes/class-eca-dashboard-page.php';

			ECA_Dashboard_Registry::submit( ECA_DASHBOARD_VERSION, TECC_PLUGIN_DIR . 'admin/eca-dashboard/' );
			ECA_Dashboard_Registry::register_addon(
				array(
					'slug'          => 'countdown',
					'host_slug'     => 'countdown',
					'text_domain'   => 'countdown-for-the-events-calendar',
					'dashboard_url' => TECC_PLUGIN_URL . 'admin/eca-dashboard/',
					'admin_urls'    => array(
						'countdown' => admin_url( 'admin.php?page=countdown_for_the_events_calendar' ),
						'divi'      => admin_url( 'admin.php?page=' . self::DASHBOARD_PAGE_SLUG ),
						'widgets'   => admin_url( 'admin.php?page=' . self::DASHBOARD_PAGE_SLUG ),
						'spb'       => admin_url( 'admin.php?page=' . self::DASHBOARD_PAGE_SLUG ),
						'speakers'  => admin_url( 'admin.php?page=esas-speaker-sponsor-settings' ),
						// Workflow method-tab id (only when Shortcodes is present / registered).
						'shortcode' => admin_url( 'admin.php?page=tribe_events-events-template-settings' ),
					),
					'menu'          => array(
						'slug'     => self::DASHBOARD_PAGE_SLUG,
						// Titles translated in ECA_Dashboard_Page::register_menus() on admin_menu (WP 6.7+).
						'position' => 9,
					),
					// The shared manifest still sells 2.0 as shortcode-only. Override
					// via a fragment rather than editing the vendored JSON: keeps
					// admin/eca-dashboard/ byte-identical to the shared source, and
					// still applies when a sibling's copy wins the registry.
					// Plain string on purpose - card copy is untranslated JSON in the
					// base manifest, and __() here would run before init (WP 6.7).
					'manifest_version'  => '2026.07.10',
					'manifest_fragment' => array(
						'sections' => array(
							'other' => array(
								'cards' => array(
									'countdown' => array(
										'desc' => 'Add a live countdown to your next event — shortcode, Gutenberg block, Elementor widget, or a sitewide floating bar.',
									),
								),
							),
						),
					),
				)
			);

			add_action( 'plugins_loaded', array( 'ECA_Dashboard_Registry', 'boot' ), 20 );
		}
	}
}
