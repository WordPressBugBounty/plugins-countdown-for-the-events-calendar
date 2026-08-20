<?php
/**
 * Modern settings panel controller.
 *
 * Registers the SAME submenu slug (`countdown_for_the_events_calendar`,
 * parent `cool-plugins-events-addon`, admin_menu priority 50),
 * a single settings group with a MERGING sanitizer (a v2 save must
 * never destroy legacy keys), screen-scoped assets, and the usage-data AJAX
 * endpoint the retired legacy panel used to provide.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Admin\Settings;

use CoolPlugins\Countdown\Render\Args;
use CoolPlugins\Countdown\Tec\Tec;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Admin\Settings\Settings_Page' ) ) {

	/**
	 * Settings panel: menu, fields, sanitize-merge, assets.
	 *
	 * @since 2.0.0
	 */
	final class Settings_Page {

		/**
		 * Immutable menu slug (bookmarks + dashboard deep-link contract).
		 */
		const MENU_SLUG = 'countdown_for_the_events_calendar';

		/**
		 * English-locale screen id (parent title is translated — use is_settings_screen()).
		 */
		const SCREEN_ID = 'events-addons_page_countdown_for_the_events_calendar';

		/**
		 * Wire all hooks. Called from the Plugin bootstrap (admin only).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 50 );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ), 10 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 10 );
			add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ), 10 );
			// Keep this settings screen clean: strip other plugins' admin notices,
			// review nags, etc. SCOPED to our own screen only (see the method) —
			// deliberately NOT the retired v1.x global cross-plugin notice strip.
			add_action( 'admin_head', array( __CLASS__, 'suppress_foreign_notices' ), PHP_INT_MAX );
			// The shared CPFM admin JS posts to this unprefixed action name;
			// renaming it would break the vendored module's script contract.
			add_action( 'wp_ajax_cpfm_save_usage_data_sharing', array( __CLASS__, 'save_usage_data_sharing' ), 10 );
		}

		/**
		 * Consent option names, resolved without a hard dependency on Plugin.
		 *
		 * Plugin holds the canonical constants, but this screen must never fatal
		 * just because that class was not reachable — a white settings page is a
		 * far worse failure than a checkbox rendering from a literal.
		 *
		 * @since 2.0.0
		 * @param string $which 'master' or 'local'.
		 * @return string
		 */
		private static function consent_option( $which ) {
			if ( class_exists( 'CoolPlugins\Countdown\Plugin' ) ) {
				return ( 'master' === $which )
					? \CoolPlugins\Countdown\Plugin::CONSENT_MASTER
					: \CoolPlugins\Countdown\Plugin::CONSENT_LOCAL;
			}

			return ( 'master' === $which ) ? 'cpfm_opt_in_choice_cool_events' : 'tecc-cpfm-data-sharing';
		}

		/**
		 * Effective consent for THIS plugin: our override, else the shared master.
		 *
		 * @since 2.0.0
		 * @return bool
		 */
		private static function data_sharing_enabled() {
			if ( class_exists( 'CoolPlugins\Countdown\Plugin' ) ) {
				return \CoolPlugins\Countdown\Plugin::data_sharing_enabled();
			}

			$local = get_option( self::consent_option( 'local' ) );
			if ( in_array( $local, array( 'yes', 'no' ), true ) ) {
				return ( 'yes' === $local );
			}

			return ( 'yes' === get_option( self::consent_option( 'master' ) ) );
		}

		/**
		 * Has the shared popup been answered either way?
		 *
		 * @since 2.0.0
		 * @return bool
		 */
		private static function consent_answered() {
			if ( class_exists( 'CoolPlugins\Countdown\Plugin' ) ) {
				return \CoolPlugins\Countdown\Plugin::consent_answered();
			}

			return in_array( get_option( self::consent_option( 'master' ) ), array( 'yes', 'no' ), true );
		}

		/**
		 * Same slug, same parent, same capability as v1.x.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function register_menu() {
			add_submenu_page(
				'cool-plugins-events-addon',
				__( 'Event Countdown Settings', 'countdown-for-the-events-calendar' ),
				__( 'Event Countdown', 'countdown-for-the-events-calendar' ),
				'manage_options',
				self::MENU_SLUG,
				array( __CLASS__, 'render_page' ),
				50
			);
		}

		/**
		 * True on our settings page in any locale.
		 *
		 * WP prefixes the screen id with the translated parent title, so the
		 * English SCREEN_ID does not match. The submenu slug is stable.
		 *
		 * @param string $hook Optional hook_suffix from admin_enqueue_scripts.
		 * @return bool
		 */
		public static function is_settings_screen( $hook = '' ) {
			$needle = '_page_' . self::MENU_SLUG;
			if ( is_string( $hook ) && substr( $hook, -strlen( $needle ) ) === $needle ) {
				return true;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- identifying the admin page, not mutating.
			return isset( $_GET['page'] ) && self::MENU_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
		}

		/**
		 * One settings group, two options, one nonce (retires the legacy
		 * duplicate settings_fields()/register_setting() bug).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function register_settings() {
			register_setting(
				'tecc_settings_group',
				'tecc_settings',
				array( 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ) )
			);
			register_setting(
				'tecc_settings_group',
				'tecc_display_rules',
				array( 'sanitize_callback' => array( 'CoolPlugins\Countdown\Display\Rules', 'sanitize' ) )
			);
		}

		/**
		 * MERGING sanitizer: load the existing option, validate only
		 * the submitted keys, array_merge on top. Legacy keys that the form
		 * did not submit (or that only older versions wrote) survive intact.
		 *
		 * @since 2.0.0
		 * @param mixed $input Raw submitted value.
		 * @return array Merged, validated option value.
		 */
		public static function sanitize_settings( $input ) {
			$existing = get_option( 'tecc_settings', array() );
			$existing = is_array( $existing ) ? $existing : array();

			if ( ! is_array( $input ) ) {
				return $existing;
			}

			$clean = array();

			// yes/no selects (legacy string convention preserved).
			foreach ( array( 'autostart-next-countdown', 'autostart-future-countdown', 'show-seconds', 'show-image', 'show-venue', 'show-button', 'show-cost', 'show-divider', 'show-timer-label', 'show-status', 'show-ongoing', 'image-gap', 'shadow' ) as $flag ) {
				if ( isset( $input[ $flag ] ) ) {
					$clean[ $flag ] = ( 'yes' === $input[ $flag ] ) ? 'yes' : 'no';
				}
			}

			if ( isset( $input['event_id'] ) ) {
				$clean['event_id'] = absint( $input['event_id'] );
			}

			// Multi-checkbox lists submit NOTHING when empty — the marker
			// field distinguishes "cleared by the user" from "not submitted".
			if ( isset( $input['future-events-list'] ) ) {
				$clean['future-events-list'] = array_values( array_filter( array_map( 'absint', (array) $input['future-events-list'] ) ) );
			} elseif ( isset( $input['future-events-list-marker'] ) ) {
				$clean['future-events-list'] = array();
			}

			foreach ( array( 'backgroundcolor', 'font-color', 'accent-color', 'main-color', 'alternate-color', 'title-color', 'text-color', 'bg-color' ) as $color ) {
				if ( isset( $input[ $color ] ) ) {
					$raw = trim( (string) $input[ $color ] );
					// The card background alone may be the literal 'transparent'
					// (Classic presets). validate_design gates that keyword to
					// the bg key too, so it can never reach another colour slot.
					if ( 'bg-color' === $color && 'transparent' === strtolower( $raw ) ) {
						$clean[ $color ] = 'transparent';
					} else {
						$hex             = sanitize_hex_color( $raw );
						$clean[ $color ] = $hex ? $hex : '';
					}
				}
			}

			foreach ( array( 'main-title', 'event-start', 'event-end', 'ongoing-title', 'ended-title', 'button-text' ) as $label ) {
				if ( isset( $input[ $label ] ) ) {
					$clean[ $label ] = sanitize_text_field( wp_unslash( (string) $input[ $label ] ) );
				}
			}

			// The chosen source (drives the panel radio + from_options source).
			if ( isset( $input['source-ui'] ) && in_array( $input['source-ui'], array( 'upcoming', 'events', 'category' ), true ) ) {
				$clean['source-ui'] = $input['source-ui'];
			}

			foreach ( array( 'template', 'style', 'size', 'countdown_style', 'image_position', 'content_align' ) as $enum ) {
				// Form uses hyphenated keys for the new enums; ENUMS uses underscores.
				$in_key = str_replace( '_', '-', $enum );
				if ( isset( $input[ $in_key ] ) && in_array( $input[ $in_key ], Args::ENUMS[ $enum ], true ) ) {
					$clean[ $in_key ] = $input[ $in_key ];
				}
			}

			// Integer dimension controls (clamped again in Args::validate_design).
			foreach ( array( 'countdown-size', 'title-size', 'body-size', 'radius', 'padding', 'border' ) as $dim ) {
				if ( isset( $input[ $dim ] ) && '' !== $input[ $dim ] ) {
					$clean[ $dim ] = absint( $input[ $dim ] );
				}
			}
			// Carousel size: hard-clamp at the source (max is client-side only).
			if ( isset( $input['no-of-events'] ) && '' !== $input['no-of-events'] ) {
				$clean['no-of-events'] = max( 1, min( 5, absint( $input['no-of-events'] ) ) );
			}
			if ( isset( $input['width'] ) ) {
				// Unit-aware, mirroring Args::validate_design — absint() here used
				// to silently drop the unit ('70%' -> 70 -> a 120px sliver).
				$w = strtolower( trim( (string) $input['width'] ) );
				if ( 'auto' === $w || '' === $w ) {
					$clean['width'] = 'auto';
				} elseif ( preg_match( '/^(\d{1,4})(px)?$/', $w, $wm ) ) {
					$clean['width'] = max( 120, min( 1600, (int) $wm[1] ) ) . 'px';
				} elseif ( preg_match( '/^(\d{1,3})%$/', $w, $wm ) ) {
					$wn             = (int) $wm[1];
					$clean['width'] = ( $wn >= 10 && $wn <= 100 ) ? $wn . '%' : 'auto';
				} elseif ( preg_match( '/^(\d{1,3})(rem|em|vw)$/', $w, $wm ) ) {
					$clean['width'] = max( 1, min( 100, (int) $wm[1] ) ) . $wm[2];
				} else {
					$clean['width'] = 'auto';
				}
			}

			// Category list: CSV of ids/slugs (sanitized, capped).
			if ( isset( $input['categories'] ) ) {
				$raw_cats = is_array( $input['categories'] ) ? $input['categories'] : (string) wp_unslash( $input['categories'] );
				$tokens   = is_array( $raw_cats ) ? $raw_cats : explode( ',', $raw_cats );
				$cats   = array();
				foreach ( $tokens as $token ) {
					$token = trim( (string) $token );
					if ( '' !== $token ) {
						$cats[] = is_numeric( $token ) ? (string) absint( $token ) : sanitize_title( $token );
					}
				}
				$clean['categories'] = implode( ',', array_slice( array_values( array_unique( array_filter( $cats ) ) ), 0, 20 ) );
			}

			// Unknown submitted keys (incl. the marker) are dropped by design.
			return array_merge( $existing, $clean );
		}

		/**
		 * Render the panel (view partial).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function render_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$tecc_settings = get_option( 'tecc_settings', array() );
			$tecc_settings = is_array( $tecc_settings ) ? $tecc_settings : array();

			$tecc_events      = Tec::picker_events();

			// Keep the stored selection in the picker even after its event ends —
			// otherwise the browser falls back to option 0 and a routine save wipes it.
			$tecc_stored_id = isset( $tecc_settings['event_id'] ) ? absint( $tecc_settings['event_id'] ) : 0;
			if ( $tecc_stored_id ) {
				$tecc_listed = false;
				foreach ( $tecc_events as $tecc_event_row ) {
					if ( $tecc_event_row['id'] === $tecc_stored_id ) {
						$tecc_listed = true;
						break;
					}
				}
				if ( ! $tecc_listed ) {
					$tecc_stored_post = get_post( $tecc_stored_id );
					if ( $tecc_stored_post && 'tribe_events' === $tecc_stored_post->post_type ) {
						array_unshift(
							$tecc_events,
							array(
								'id'    => $tecc_stored_id,
								'title' => $tecc_stored_post->post_title . ' ' . __( '(event has ended)', 'countdown-for-the-events-calendar' ),
							)
						);
					}
				}
			}

			// Read the SHARED flag, never the per-plugin mirror — the mirror drifts,
			// and a privacy toggle must never show off while sharing is on. Hidden
			// until the shared popup has been answered; local override wins once set.
			$tecc_choice               = get_option( 'cpfm_opt_in_choice_cool_events' );
			$tecc_data_sharing_visible = self::consent_answered();
			$tecc_data_sharing         = self::data_sharing_enabled() ? 'yes' : 'no';

			$tecc_rules      = \CoolPlugins\Countdown\Display\Rules::all();
			$tecc_categories = Tec::picker_categories();

			// Drives the hero info card: pitch the Elementor widget when Elementor
			// is present, otherwise pitch the Gutenberg block.
			$tecc_elementor_active = ( did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ) );

			$tecc_view_file = TECC_PLUGIN_DIR . 'admin/settings/views/panel.php';
			if ( file_exists( $tecc_view_file ) ) {
				include $tecc_view_file;
			}
		}

		/**
		 * Add a body class on our screen so the panel can go full-bleed to the
		 * WordPress menu (kills the default #wpcontent left padding via CSS).
		 *
		 * @since 2.1.0
		 * @param string $classes Space-separated body classes.
		 * @return string
		 */
		public static function body_class( $classes ) {
			if ( self::is_settings_screen() ) {
				$classes .= ' tecc-fullbleed';
			}

			return $classes;
		}

		/**
		 * Silence foreign admin notices on OUR settings screen only.
		 *
		 * admin_head fires after every notice is hooked but before the
		 * admin_notices/all_admin_notices dispatch, so removing the actions here
		 * gives a clean panel without touching notices on any other screen. This
		 * is the sanctioned per-screen pattern — NOT the aggressive global
		 * cross-plugin notice removal retired from v1.x.
		 *
		 * @since 2.1.0
		 * @return void
		 */
		public static function suppress_foreign_notices() {
			if ( ! self::is_settings_screen() ) {
				return;
			}

			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
			remove_all_actions( 'user_admin_notices' );

			// FOREIGN notices only — OUR own two are re-attached to `tecc_admin_
			// notices`, which the view fires ABOVE the hero at full width (WP would
			// otherwise print them after the first h1, i.e. mid-panel).
			if ( class_exists( 'CPFM_Welcome_Notice' ) ) {
				add_action( 'tecc_admin_notices', array( 'CPFM_Welcome_Notice', 'cpfm_maybe_render' ) );
			}
			if ( class_exists( 'CPFM_Review_Notice' ) ) {
				add_action( 'tecc_admin_notices', array( 'CPFM_Review_Notice', 'cpfm_maybe_render' ) );
			}
		}

		/**
		 * Screen-scoped assets: panel UI + the REAL front-end bundle so the
		 * live preview looks and ticks exactly like the site.
		 *
		 * @since 2.0.0
		 * @param string $hook_suffix Current admin page hook.
		 * @return void
		 */
		public static function enqueue( $hook_suffix = '' ) {
			if ( ! self::is_settings_screen( $hook_suffix ) ) {
				return;
			}

			// Shared Events Addons dashboard chrome (header + hero + buttons).
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'eca-dashboard-base', TECC_PLUGIN_URL . 'admin/eca-dashboard/assets/css/eca-base.css', array(), TECC_VERSION_CURRENT );

			wp_enqueue_style( 'tecc-admin-settings', TECC_CSS_URL . '/tecc-admin-settings.css', array( 'eca-dashboard-base' ), TECC_VERSION_CURRENT );
			wp_enqueue_script( 'tecc-admin-settings', TECC_JS_DIR . '/tecc-admin-settings.js', array( 'tecc-countdown' ), TECC_VERSION_CURRENT, true );

			// Display Rules builder — depends on the settings handle so
			// the localized teccAdminSettings global (restUrl + nonce) is present
			// before the rule preview reads it (no load-order luck).
			wp_enqueue_style( 'tecc-admin-rules', TECC_CSS_URL . '/tecc-admin-rules.css', array( 'tecc-admin-settings' ), TECC_VERSION_CURRENT );
			wp_enqueue_script( 'tecc-admin-rules', TECC_JS_DIR . '/tecc-admin-rules.js', array( 'wp-i18n', 'tecc-admin-settings', 'tecc-countdown' ), TECC_VERSION_CURRENT, true );

			// The five Light/Dark colours that seed each sitewide display's colour
			// pickers (the sitewide Styles tab writes real colours, not overrides).
			$tecc_sw_styles = array();
			foreach ( array( 'light', 'dark' ) as $tecc_sw_mode ) {
				$tecc_sw_bundle = \CoolPlugins\Countdown\Render\Args::SITEWIDE_STYLES[ $tecc_sw_mode ];
				$tecc_sw_styles[ $tecc_sw_mode ] = array(
					'main'        => $tecc_sw_bundle['main'],
					'alt'         => $tecc_sw_bundle['alt'],
					'title_color' => $tecc_sw_bundle['title_color'],
					'text_color'  => $tecc_sw_bundle['text_color'],
					'bg'          => $tecc_sw_bundle['bg'],
				);
			}
			wp_localize_script( 'tecc-admin-rules', 'teccSitewideStyles', $tecc_sw_styles );

			// Flexible-design controls + presets (in-page design surface).
			wp_enqueue_style( 'tecc-admin-design', TECC_CSS_URL . '/tecc-admin-design.css', array( 'tecc-admin-settings' ), TECC_VERSION_CURRENT );
			wp_enqueue_script( 'tecc-admin-design', TECC_JS_DIR . '/tecc-admin-design.js', array( 'tecc-admin-settings' ), TECC_VERSION_CURRENT, true );

			// The ONE canonical preset catalogue (6 layouts x Light/Dark), shared
			// with the block/Elementor — the design JS maps its tecc_settings keys
			// onto the panel controls.
			wp_localize_script( 'tecc-admin-design', 'teccPresets', \CoolPlugins\Countdown\Render\Presets::for_js() );

			// Source token-select (events / categories) over the /source endpoint.
			wp_enqueue_script( 'tecc-admin-source', TECC_JS_DIR . '/tecc-admin-source.js', array( 'tecc-admin-settings' ), TECC_VERSION_CURRENT, true );
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'tecc-admin-rules', 'countdown-for-the-events-calendar', TECC_PLUGIN_DIR . 'languages' );
			}

			// Front-end preview assets (registered handles from Render\Assets
			// are front-end only, so register/enqueue directly here).
			wp_enqueue_style( 'tecc-tokens', TECC_CSS_URL . '/tokens.css', array(), TECC_VERSION_CURRENT );
			wp_enqueue_style( 'tecc-templates', TECC_CSS_URL . '/templates.css', array( 'tecc-tokens' ), TECC_VERSION_CURRENT );
			wp_enqueue_style( 'countdown-css', TECC_CSS_URL . '/countdown.css', array(), TECC_VERSION_CURRENT );
			wp_enqueue_script( 'tecc-countdown', TECC_JS_DIR . '/tecc-countdown.js', array(), TECC_VERSION_CURRENT, true );
			// A card rule with 2+ events renders a carousel in the sitewide preview.
			wp_enqueue_script( 'tecc-carousel', TECC_JS_DIR . '/tecc-carousel.js', array(), TECC_VERSION_CURRENT, true );

			wp_localize_script(
				'tecc-admin-settings',
				'teccAdminSettings',
				array(
					'restUrl'   => esc_url_raw( rest_url( 'tecc/v1/preview' ) ),
					'sourceUrl' => esc_url_raw( rest_url( 'tecc/v1/source' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'debounce'  => 350,
					// Unit labels for the LIVE preview when the countdown size crosses
					// the abbreviation threshold (must match the card/banner templates).
					'units'     => array(
						'full'  => array(
							'days'    => __( 'days', 'countdown-for-the-events-calendar' ),
							'hours'   => __( 'hours', 'countdown-for-the-events-calendar' ),
							'minutes' => __( 'min', 'countdown-for-the-events-calendar' ),
							'seconds' => __( 'sec', 'countdown-for-the-events-calendar' ),
						),
						'short' => array(
							'days'    => _x( 'D', 'days unit abbreviation', 'countdown-for-the-events-calendar' ),
							'hours'   => _x( 'H', 'hours unit abbreviation', 'countdown-for-the-events-calendar' ),
							'minutes' => _x( 'M', 'minutes unit abbreviation', 'countdown-for-the-events-calendar' ),
							'seconds' => _x( 'S', 'seconds unit abbreviation', 'countdown-for-the-events-calendar' ),
						),
					),
					'i18n'      => array(
						'copied'       => __( 'Copied!', 'countdown-for-the-events-calendar' ),
						'previewError' => __( 'Preview unavailable.', 'countdown-for-the-events-calendar' ),
						'updating'     => __( 'Updating preview…', 'countdown-for-the-events-calendar' ),
						'live'         => __( 'Live preview', 'countdown-for-the-events-calendar' ),
						'selectEvent'  => __( 'Select an event to see the preview.', 'countdown-for-the-events-calendar' ),
						'removeChip'   => __( 'Remove', 'countdown-for-the-events-calendar' ),
						'copiedPaste'  => __( 'Copied! Paste it on your page now.', 'countdown-for-the-events-calendar' ),
						'noDisplays'   => __( 'No sitewide displays yet. Add one and it will preview here.', 'countdown-for-the-events-calendar' ),
					),
				)
			);
		}

		/**
		 * Usage-data sharing AJAX (verbatim parity with the retired legacy
		 * panel: cap + nonce + immediate send on opt-in, clear on opt-out).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function save_usage_data_sharing() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You do not have sufficient permissions to access this page.', 'countdown-for-the-events-calendar' ) );
			}

			check_ajax_referer( 'cpfm_nonce_action', 'nonce' );

			$choice = isset( $_POST['opt_in'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['opt_in'] ) ) ? 'yes' : 'no';

			// Writes OUR override only — touching the shared master would turn
			// this checkbox into a kill switch for every Events Addon.
			update_option( self::consent_option( 'local' ), $choice );

			if ( 'yes' === $choice ) {
				// Defer the send to cron so a slow feedback endpoint never blocks the user's click.
				if ( ! wp_next_scheduled( 'tecc_extra_data_update' ) ) {
					wp_schedule_single_event( time(), 'tecc_extra_data_update' );
				}
			} elseif ( wp_next_scheduled( 'tecc_extra_data_update' ) ) {
				wp_clear_scheduled_hook( 'tecc_extra_data_update' );
			}

			wp_send_json_success( 'Saved' );
		}
	}
}
