<?php
/**
 * Plugin bootstrap singleton (replaces the legacy `EventsCalendarCountdown`).
 *
 * Reproduces every v1.5.4 boot behavior through a guarded, prefixed skeleton,
 * EXCEPT the retired `$wp_filter` cross-plugin notice stripping
 * (`ect_hide_unrelated_notices`) — only its `ect_display_admin_notices`
 * bridge survives (the shared review/opt-in notices render through it).
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Plugin' ) ) {

	/**
	 * Main plugin controller. Boots on `plugins_loaded` priority 5.
	 *
	 * @since 2.0.0
	 */
	final class Plugin {

		/**
		 * Singleton instance.
		 *
		 * @var Plugin|null
		 */
		private static $instance = null;

		/**
		 * Create/return the singleton. Hooked on `plugins_loaded` priority 5.
		 *
		 * @since 2.0.0
		 * @return Plugin
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Wire all hooks. Private — use instance().
		 *
		 * @since 2.0.0
		 */
		private function __construct() {
			Schema::maybe_upgrade();

			add_action( 'init', array( $this, 'load_textdomain' ) );

			$this->boot_render_path();

			// REST routes register on rest_api_init, which fires on REST
			// requests where is_admin() is false — hook unconditionally.
			Admin\Settings\Settings_Rest::init();

			// Editor adapters (each hard-gates its own host editor).
			Editors\Block\Block::init();

			// Explicit require: the filename class-tecc-elementor.php does not
			// match the autoloader's class-name convention.
			if ( ! class_exists( 'CoolPlugins\Countdown\Editors\Elementor\Elementor_Integration' ) ) {
				require_once TECC_PLUGIN_DIR . 'includes/editors/elementor/class-tecc-elementor.php';
			}
			if ( class_exists( 'CoolPlugins\Countdown\Editors\Elementor\Elementor_Integration' ) ) {
				Editors\Elementor\Elementor_Integration::init();
			}

			// Shared ecosystem modules load at plugins_loaded 10 (parity with
			// v1.5.4 `tecc_check_event_calender_installed`) so sibling addons
			// keep their historical class_exists race order.
			add_action( 'plugins_loaded', array( $this, 'boot_shared_modules' ), 10 );

			// Cool Timeline pattern: load CPFM on every request so
			// CPFM_Usage_Cron exists when wp-cron.php fires (not is_admin()).
			self::boot_cpfm_framework();

			if ( is_admin() ) {
				$this->boot_admin();
			} else {
				// Display Rules runtime (footer / floating / bars auto-display).
				Display\Locations::init();
			}

			// Register usage cron once (static-guarded), admin + WP-Cron.
			add_action( 'init', array( $this, 'register_cpfm_usage_cron' ), 5 );
		}

		/**
		 * Shared, ecosystem-wide consent flag. Written ONLY by the corner popup
		 * (and any onboarding step) — never by this plugin's own checkbox.
		 */
		const CONSENT_MASTER = 'cpfm_opt_in_choice_cool_events';

		/**
		 * This plugin's own override. Written ONLY by the settings checkbox.
		 */
		const CONSENT_LOCAL = 'tecc-cpfm-data-sharing';

		/**
		 * One-shot post-activation redirect transient (sibling ECTBE/EPTA pattern).
		 * Set only on a genuinely fresh install; consumed on the next admin_init.
		 */
		const REDIRECT_TRANSIENT = 'tecc_activation_redirect';

		/**
		 * Has the shared popup been answered either way?
		 *
		 * The inline checkbox stays hidden until it has: before that there is no
		 * meaningful default to show, and offering a second consent control
		 * before the first has been answered is just confusing.
		 *
		 * @since 2.0.0
		 * @return bool
		 */
		public static function consent_answered() {
			return in_array( get_option( self::CONSENT_MASTER ), array( 'yes', 'no' ), true );
		}

		/**
		 * Should THIS plugin share usage data?
		 *
		 * Two levels, matching the rest of the Events Addons family:
		 *
		 *   - the shared popup sets the MASTER for every addon at once;
		 *   - each plugin's own checkbox may override it, and turning that off
		 *     stops OUR data and nobody else's.
		 *
		 * With no local choice recorded the master is inherited, so an addon
		 * installed after the user opted in is on — which is what it actually is.
		 *
		 * @since 2.0.0
		 * @return bool
		 */
		public static function data_sharing_enabled() {
			$local = get_option( self::CONSENT_LOCAL );

			if ( in_array( $local, array( 'yes', 'no' ), true ) ) {
				return ( 'yes' === $local );
			}

			return ( 'yes' === get_option( self::CONSENT_MASTER ) );
		}

		/**
		 * Boot the front-end render path.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private function boot_render_path() {
			new Shortcode\Shortcode();

			Render\Assets::init();
		}

		/**
		 * Require every cpfm-feedback module's class definition, once.
		 *
		 * Loaded on every request (Cool Timeline pattern) so CPFM_Usage_Cron
		 * exists when wp-cron.php fires. Also used from activate() before
		 * plugins_loaded. Safe to call more than once — CPFM_Loader is
		 * idempotent.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private static function boot_cpfm_framework() {
			if ( ! class_exists( 'CPFM_Loader' ) ) {
				$file = TECC_PLUGIN_DIR . 'admin/cpfm-feedback/class-cpfm-loader.php';
				if ( ! file_exists( $file ) ) {
					return;
				}
				require_once $file;
			}
			if ( class_exists( 'CPFM_Loader' ) ) {
				\CPFM_Loader::load();
			}
		}

		/**
		 * Register the shared usage-data cron (admin + WP-Cron).
		 *
		 * Cool Timeline pattern: static once-guard so repeated init / opt-in
		 * / activation paths never double-attach the hook.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function register_cpfm_usage_cron() {
			static $usage_cron_registered = false;

			if ( $usage_cron_registered || ! class_exists( 'CPFM_Usage_Cron' ) ) {
				return;
			}

			$usage_cron_registered = true;

			\CPFM_Usage_Cron::cpfm_register( self::usage_cron_config() );
		}

		/**
		 * This plugin's own config for the shared usage cron. Split out so
		 * activate() — which runs before plugins_loaded on the activation
		 * request — can register the exact same config without duplicating it.
		 *
		 * @since 2.0.0
		 * @return array<string, mixed>
		 */
		private static function usage_cron_config() {
			return array(
				'id'                       => 'tecc',
				'plugin_name'              => 'Event Countdown for The Events Calendar',
				'version'                  => TECC_VERSION_CURRENT,
				'api'                      => TECC_FEEDBACK_API,
				'cron_hook'                => 'tecc_extra_data_update',
				// Native master+override consent (see CPFM_Usage_Cron's own
				// docblock) reproduces data_sharing_enabled() exactly, so no
				// consent_callback is needed here.
				'consent_master_option'    => self::CONSENT_MASTER,
				'consent_override_option'  => self::CONSENT_LOCAL,
				'install_date_option'      => 'tecc-install-date',
				'initial_version_option'   => 'tecc_initial_save_version',
				'site_key'                 => '27',
			);
		}

		/**
		 * Load shared ecosystem modules on plugins_loaded 10 (v1.5.4 parity).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function boot_shared_modules() {
			// Shared deactivation-feedback modal. Deferred to init 1: config
			// carries translated strings (WP 6.7 JIT-textdomain notice).
			if ( is_admin() ) {
				add_action( 'init', array( $this, 'register_deactivation_feedback' ), 1 );
			}

			add_action( 'cpfm_register_notice', array( $this, 'register_cpfm_notice' ), 10 );
			add_action( 'cpfm_after_opt_in_tecc', array( $this, 'send_data_after_opt_in' ), 10, 1 );
			add_action( 'cpfm_after_opt_out_tecc', array( $this, 'clear_data_after_opt_out' ), 10, 1 );
		}

		/**
		 * Register this plugin's deactivation survey with the shared framework.
		 *
		 * Hooked to `init` 1: config carries translated strings (WP 6.7
		 * JIT-textdomain notice); the module never calls __() itself, so the
		 * strings stay in our domain.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function register_deactivation_feedback() {
			if ( ! class_exists( 'CPFM_Deactivation_Feedback' ) ) {
				return;
			}

			$name = 'Event Countdown for The Events Calendar';

			\CPFM_Deactivation_Feedback::cpfm_register(
				array(
					'id'          => 'tecc',
					'slug'        => 'countdown-for-the-events-calendar',
					'plugin_name' => $name,
					'version'     => TECC_VERSION_CURRENT,
					'api'         => TECC_FEEDBACK_API,
					'site_key'    => 27,

					// OUR option names — the module must not guess them.
					'install_date_option'    => 'tecc-install-date',
					'initial_version_option' => 'tecc_initial_save_version',

					'reasons'                => array(
						'not_working'  => array(
							'title'       => __( "The plugin isn't working", 'countdown-for-the-events-calendar' ),
							'placeholder' => __( 'Which problem did you run into? We read every reply.', 'countdown-for-the-events-calendar' ),
						),
						'not_expected' => array(
							'title'       => __( "It didn't do what I expected", 'countdown-for-the-events-calendar' ),
							'placeholder' => __( 'What were you hoping it would do?', 'countdown-for-the-events-calendar' ),
						),
						'found_better' => array(
							'title'       => __( 'I found a better plugin', 'countdown-for-the-events-calendar' ),
							'placeholder' => __( 'Mind sharing which one?', 'countdown-for-the-events-calendar' ),
						),
						'temporary'    => array(
							'title'       => __( "It's a temporary deactivation", 'countdown-for-the-events-calendar' ),
							'placeholder' => '',
						),
						'other'        => array(
							'title'       => __( 'Another reason', 'countdown-for-the-events-calendar' ),
							'placeholder' => __( 'Please tell us more', 'countdown-for-the-events-calendar' ),
						),
					),

					'i18n'                   => array(
						'title'           => __( 'Before you go…', 'countdown-for-the-events-calendar' ),
						/* translators: %s: plugin name (bold). */
						'intro'           => __( 'What made you deactivate %s? Your answer helps us fix it.', 'countdown-for-the-events-calendar' ),
						'submit'          => __( 'Submit & Deactivate', 'countdown-for-the-events-calendar' ),
						'skip'            => __( 'Skip & Deactivate', 'countdown-for-the-events-calendar' ),
						'deactivating'    => __( 'Deactivating…', 'countdown-for-the-events-calendar' ),
						'pick_reason'     => __( 'Please choose a reason.', 'countdown-for-the-events-calendar' ),
						'close_label'     => __( 'Close', 'countdown-for-the-events-calendar' ),
						/* translators: %s: company name. */
						'byline'          => __( 'A plugin by %s', 'countdown-for-the-events-calendar' ),
						// Must describe the FULL payload: submitting is deliberate and
						// is not consent-gated, so this is the disclosure.
						'consent'         => __( 'Submitting shares your reason plus your site URL, admin email and basic environment details (PHP, WordPress, active plugins). Skip & Deactivate sends nothing.', 'countdown-for-the-events-calendar' ),
					),
				)
			);
		}

		/**
		 * Register the shared consent notice with CPFM (category `cool_events`).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function register_cpfm_notice() {
			if ( ! class_exists( 'CPFM_Feedback_Notice' ) || ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$notice = array(
				'title'          => __( 'Events Addons By Cool Plugins', 'countdown-for-the-events-calendar' ),
				'message'        => __( 'Help us make this plugin more compatible with your site by sharing non-sensitive site data. ', 'countdown-for-the-events-calendar' ),
				'pages'          => array( 'cool-plugins-events-addon', 'countdown_for_the_events_calendar' ),
				'always_show_on' => array( 'cool-plugins-events-addon', 'countdown_for_the_events_calendar' ),
				'plugin_name'    => 'tecc',

				// Panel-wide chrome (header, consent copy, buttons) - only the
				// FIRST plugin to register ever supplies this; see
				// CPFM_Feedback_Notice's own $panel_i18n docblock for why it
				// must come from the host and never be hardcoded in that
				// shared file.
				'i18n'           => array(
					'panel_title'         => __( 'Help Improve Plugins', 'countdown-for-the-events-calendar' ),
					'more_info'           => __( 'More info', 'countdown-for-the-events-calendar' ),
					'consent_intro'       => __( 'Opt in to receive email updates about security improvements, new features, helpful tutorials, and occasional special offers. We\'ll collect:', 'countdown-for-the-events-calendar' ),
					'consent_item_site'   => __( 'Your website home URL and WordPress admin email.', 'countdown-for-the-events-calendar' ),
					'consent_item_compat' => __( 'To check plugin compatibility, we will collect the following: list of active plugins and themes, PHP, MySQL and WordPress versions, memory limit, whether the site is multisite, and the site language. ', 'countdown-for-the-events-calendar' ),
					'consent_link'        => __( 'Click here', 'countdown-for-the-events-calendar' ),
					'yes_label'           => __( "Yes, it's OK", 'countdown-for-the-events-calendar' ),
					'no_label'            => __( 'No, Thanks', 'countdown-for-the-events-calendar' ),
				),
			);

			\CPFM_Feedback_Notice::cpfm_register_notice( 'cool_events', $notice );

			if ( ! isset( $GLOBALS['cool_plugins_feedback'] ) ) {
				$GLOBALS['cool_plugins_feedback'] = array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- shared ecosystem global.
			}

			$GLOBALS['cool_plugins_feedback']['cool_events'][] = $notice; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- shared ecosystem global.
		}

		/**
		 * Send usage data right after the user opts in (consent given).
		 *
		 * @since 2.0.0
		 * @param string $category CPFM consent category.
		 * @return void
		 */
		public function send_data_after_opt_in( $category ) {
			if ( 'cool_events' !== $category ) {
				return;
			}
			// Deliberately does NOT touch CONSENT_LOCAL: a per-plugin opt-out
			// survives shared popup changes.

			$this->register_cpfm_usage_cron();

			// Defer the send to cron so a slow feedback endpoint never blocks
			// the user's opt-in click.
			if ( class_exists( 'CPFM_Usage_Cron' ) ) {
				\CPFM_Usage_Cron::cpfm_schedule_event( 'tecc_extra_data_update' );
			}
			if ( ! wp_next_scheduled( 'tecc_extra_data_update' ) ) {
				wp_schedule_single_event( time(), 'tecc_extra_data_update' );
			}
		}

		/**
		 * React to a shared opt-OUT (from this plugin's notice or any sibling
		 * Events Addon): drop the local mirror and stop this plugin's cron
		 * immediately instead of waiting for the cron's own self-unschedule.
		 *
		 * @since 2.0.0
		 * @param string $category CPFM consent category.
		 * @return void
		 */
		public function clear_data_after_opt_out( $category ) {
			if ( 'cool_events' !== $category ) {
				return;
			}
			// Deliberately does NOT touch CONSENT_LOCAL (see send_data_after_opt_in).
			if ( wp_next_scheduled( 'tecc_extra_data_update' ) ) {
				wp_clear_scheduled_hook( 'tecc_extra_data_update' );
			}
		}

		/**
		 * Admin-only wiring.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private function boot_admin() {
			// CPFM framework is already loaded in the constructor (Cool Timeline
			// pattern). Settings / review / notices stay admin-only here.
			Admin\Settings\Settings_Page::init();

			// Review ask via the shared CPFM_Review framework. Deferred to init 1:
			// config carries translated strings (WP 6.7 JIT-textdomain notice).
			add_action( 'init', array( $this, 'register_review_ask' ), 1 );

			// One-time "2.0 is here" notice for upgrading users; deferred to
			// init 1 for the same reason.
			add_action( 'init', array( $this, 'register_welcome_notice' ), 1 );

			// Shared Events Addons dashboard (vendored module + thin boot).
			if ( ! class_exists( 'TECC_ECA_Integration' ) ) {
				require_once TECC_PLUGIN_DIR . 'admin/class-tecc-eca-integration.php';
			}
			if ( class_exists( 'TECC_ECA_Integration' ) ) {
				\TECC_ECA_Integration::boot_admin();
			}

			add_filter( 'plugin_action_links_' . plugin_basename( TECC_PLUGIN_FILE ), array( $this, 'plugin_action_links' ), 10, 1 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_cpfm_admin_assets' ), 10 );
			add_action( 'admin_init', array( $this, 'maybe_activation_redirect' ) );

			// Arm at admin_print_scripts (v1.5.4 timing) so sibling addons that
			// still race for the shared single-fire lock keep their order.
			add_action( 'admin_print_scripts', array( $this, 'hook_shared_notices' ), 10 );
		}

		/**
		 * Consume the fresh-install redirect transient (one shot).
		 *
		 * Only new installs land on the Events Addons dashboard. Reactivation,
		 * updates, bulk activate, AJAX, cron, and network admin are skipped.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function maybe_activation_redirect() {
			$target = get_transient( self::REDIRECT_TRANSIENT );
			if ( ! $target ) {
				return;
			}
			delete_transient( self::REDIRECT_TRANSIENT );

			if ( wp_doing_ajax() || wp_doing_cron() || is_network_admin() ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- bulk-activation marker only.
			if ( isset( $_GET['activate-multi'] ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$page = class_exists( 'ECA_Dashboard_Page' )
				? \ECA_Dashboard_Page::PAGE_SLUG
				: 'cool-plugins-events-addon';

			wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
			exit;
		}

		/**
		 * Register the one-time "2.0 is here" notice with the shared framework.
		 *
		 * The gate option is set by Schema::maybe_upgrade() only when it detects
		 * an existing pre-2.0 `tecc-v`, so a fresh install never sees this.
		 *
		 * Hooked to `init` 1 — the copy below is translated.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function register_welcome_notice() {
			if ( ! class_exists( 'CPFM_Welcome_Notice' ) ) {
				return;
			}

			\CPFM_Welcome_Notice::cpfm_register(
				array(
					'id'             => 'tecc',
					'option'         => 'tecc_v2_welcome',
					'settings_url'   => admin_url( 'admin.php?page=countdown_for_the_events_calendar' ),
					'screens'        => array(
						'plugins',
						'events-addons_page_countdown_for_the_events_calendar',
					),
					'inline_screens' => array( 'events-addons_page_countdown_for_the_events_calendar' ),
					'i18n'           => array(
						'headline'    => __( 'Event Countdown 2.0 major update.', 'countdown-for-the-events-calendar' ),
						'body'        => __( 'The plugin has been rewritten completely, with new features and designs.', 'countdown-for-the-events-calendar' ),
						'cta'         => __( 'See new shortcode & use it', 'countdown-for-the-events-calendar' ),
						'dismiss'     => __( 'Dismiss', 'countdown-for-the-events-calendar' ),
						'close_label' => __( 'Close', 'countdown-for-the-events-calendar' ),
					),
				)
			);
		}

		/**
		 * Register this plugin with the shared review framework.
		 *
		 * Strings are passed already translated (the framework never calls
		 * __(), so they extract in our own domain). Hooked to `init` 1: config
		 * carries translated strings (WP 6.7 JIT-textdomain notice).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function register_review_ask() {
			if ( ! class_exists( 'CPFM_Review' ) ) {
				return;
			}

			$name = 'Event Countdown';

			\CPFM_Review::cpfm_register(
				array(
					'id'          => 'tecc',
					'plugin_file' => TECC_PLUGIN_FILE,
					'plugin_name' => $name,
					'review_url'  => 'https://wordpress.org/support/plugin/countdown-for-the-events-calendar/reviews/#new-post',
					'capability'  => 'activate_plugins',

					// Cross-family quiet window after ANY sibling's ask renders;
					// keep the value identical across the family. Does not apply
					// on own_screens; 0 disables.
					'quiet_days'  => 7,

					// Our own panel: exempt from the cross-plugin quiet period, so a
					// sibling's ask elsewhere never silences ours in our own UI.
					'own_screens' => array( 'events-addons_page_countdown_for_the_events_calendar' ),

					// Ask once the install is a day old. Existing users inherit
					// their real install date, so they qualify immediately.
					'trigger'     => array(
						'type'  => 'install_age',
						'hours' => 24,
					),

					'notice'      => array(
						'enabled'  => true,
						'template' => 'two_step',
						'screens'        => array(
							'plugins',
							'edit-tribe_events',
							'events-addons_page_countdown_for_the_events_calendar',
						),
						// ONLY our own full-bleed panel needs to opt out of core's
						// notice relocation - its h1 lives inside the hero, so an
						// un-relocated notice would land mid-panel. On plugins.php
						// and All Events the relocation is correct, and blocking it
						// strands the notice above the page title.
						'inline_screens' => array( 'events-addons_page_countdown_for_the_events_calendar' ),
					),
					'row'         => array( 'enabled' => true ),

					'legacy'      => array(
						'done_options'   => array(
							// Match on VALUE 'yes' only — legacy seeds
							// tecc-ratingDiv='no' at activation, so accepting 'no'
							// would silence the ask for the whole user base.
							'tecc-ratingDiv'     => 'yes',
							'tecc_review_prompt' => array( 'yes', 'done', 'dismissed' ),
							'tecc_review_shown'  => array( 'yes', 'done', 'dismissed' ),
						),
						'done_user_meta' => array(
							'eca_review_dismissed' => array( 'in_key' => 'countdown' ),
						),
						'install_dates'  => array( 'tecc-installDate', 'tecc-install-date' ),
						// Keep any still-installed sibling that reads the old flag in sync.
						'mirror_write'   => array( 'tecc-ratingDiv' => 'yes' ),
					),

					'i18n'        => array(
						'like_question' => sprintf(
							/* translators: %s: plugin name. */
							__( 'Do you like the %s plugin?', 'countdown-for-the-events-calendar' ),
							$name
						),
						'yes_button'    => __( 'Yes, I like it', 'countdown-for-the-events-calendar' ),
						'dismiss_link'  => __( 'Not good, dismiss', 'countdown-for-the-events-calendar' ),
						'later_link'    => __( 'Ask me later', 'countdown-for-the-events-calendar' ),
						'thanks_line'   => __( 'That is great to hear! A quick review on WordPress.org would really help us.', 'countdown-for-the-events-calendar' ),
						'submit_button' => __( 'Submit review', 'countdown-for-the-events-calendar' ),
						'no_link'       => __( 'I do not like it, dismiss', 'countdown-for-the-events-calendar' ),
						'row_question'  => __( 'Do you like this plugin?', 'countdown-for-the-events-calendar' ),
						'inline_title'  => sprintf(
							/* translators: %s: plugin name. */
							__( 'Enjoying %s?', 'countdown-for-the-events-calendar' ),
							$name
						),
						'inline_text'   => __( 'A short review helps other event organisers find it.', 'countdown-for-the-events-calendar' ),
						'close_label'   => __( 'Close', 'countdown-for-the-events-calendar' ),
					),
				)
			);
		}

		/**
		 * Add the Settings link on the Plugins screen row (v1.5.4 parity).
		 *
		 * @since 2.0.0
		 * @param array $links Existing action links.
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			$links[] = '<a style="font-weight:bold" href="' . esc_url( get_admin_url( null, 'admin.php?page=countdown_for_the_events_calendar' ) ) . '">' . esc_html__( 'Settings', 'countdown-for-the-events-calendar' ) . '</a>';

			return $links;
		}

		/**
		 * Enqueue the shared CPFM consent scripts (v1.5.4 parity).
		 *
		 * Screen-scoped to our own settings page: both scripts only ever touch
		 * markup that lives there (#tecc-cpfm-data-sharing, .tecc-see-terms /
		 * .tecc-terms-box in admin/settings/views/panel.php) - they were loading
		 * on every admin screen site-wide before this check existed.
		 *
		 * @since 2.0.0
		 * @param string $hook_suffix Current admin page hook.
		 * @return void
		 */
		public function enqueue_cpfm_admin_assets( $hook_suffix = '' ) {
			if ( ! class_exists( 'CoolPlugins\Countdown\Admin\Settings\Settings_Page' )
				|| ! \CoolPlugins\Countdown\Admin\Settings\Settings_Page::is_settings_screen( $hook_suffix ) ) {
				return;
			}

			wp_enqueue_script( 'cpfm-settings-data-share', TECC_PLUGIN_URL . 'admin/cpfm-feedback/js/cpfm-admin-share-data.js', array(), TECC_VERSION_CURRENT, true );
			wp_enqueue_script( 'cpfm-setting-js', TECC_PLUGIN_URL . 'admin/cpfm-feedback/js/cpfm-setting.js', array(), TECC_VERSION_CURRENT, true );
			wp_localize_script(
				'cpfm-setting-js',
				'cpfm_ajax_obj',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'cpfm_nonce_action' ),
				)
			);
		}

		/**
		 * Claim the ecosystem-wide notice bridge if no sibling claimed it yet.
		 *
		 * The ECT_ADMIN_NOTICE_* constants are the cross-plugin single-fire
		 * locks shared by ALL Events Addons siblings — the names must stay
		 * exactly as-is. The v1.5.4 `$wp_filter` notice-stripping around this
		 * bridge is retired; only the bridge remains.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function hook_shared_notices() {
			if ( defined( 'ECT_ADMIN_NOTICE_HOOKED' ) ) {
				return;
			}

			define( 'ECT_ADMIN_NOTICE_HOOKED', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- shared ecosystem lock.

			add_action( 'admin_notices', array( $this, 'render_shared_notices' ), PHP_INT_MAX );
		}

		/**
		 * Fire the shared notice action exactly once per request.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function render_shared_notices() {
			if ( defined( 'ECT_ADMIN_NOTICE_RENDERED' ) ) {
				return;
			}

			define( 'ECT_ADMIN_NOTICE_RENDERED', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- shared ecosystem lock.

			do_action( 'ect_display_admin_notices' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shared ecosystem action consumed by sibling addons.
		}

		/**
		 * Load the text domain + seed first-install bookkeeping (v1.5.4 parity).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'countdown-for-the-events-calendar', false, basename( dirname( TECC_PLUGIN_FILE ) ) . '/languages/' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound

			if ( ! get_option( 'tecc_initial_save_version' ) ) {
				add_option( 'tecc_initial_save_version', TECC_VERSION_CURRENT );
			}

			if ( ! get_option( 'tecc-install-date' ) ) {
				add_option( 'tecc-install-date', gmdate( 'Y-m-d H:i:s' ) );
			}
		}

		/**
		 * Activation handler (v1.5.4 parity). Fires ONLY on real (re)activation,
		 * never on update — update-time changes belong in Schema::maybe_upgrade().
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function activate() {
			// Fresh install only: check BEFORE writing install stamps so a
			// deactivate→reactivate (or an update — which never reaches here)
			// never redirects again. Sibling ECTBE/EPTA use the same gate.
			$is_fresh_install = ( false === get_option( 'tecc-install-date', false ) )
				&& ( false === get_option( 'tecc-v', false ) );

			if ( $is_fresh_install ) {
				set_transient( self::REDIRECT_TRANSIENT, 'dashboard', MINUTE_IN_SECONDS );
			}

			update_option( 'tecc-v', TECC_VERSION_CURRENT );
			update_option( 'tecc-type', 'FREE' );

			// First-install stamp only — rewriting it on every activation would
			// reset the review clock.
			if ( ! get_option( 'tecc-installDate' ) ) {
				add_option( 'tecc-installDate', gmdate( 'Y-m-d H:i:s' ) );
			}

			// Only seed the legacy rating flag on a first install — never reset a
			// user who already rated (or already dismissed) back to "ask again".
			if ( false === get_option( 'tecc-ratingDiv' ) ) {
				add_option( 'tecc-ratingDiv', 'no' );
			}

			// Consent read fresh from the shared flag; this only decides whether
			// to pre-schedule — the cron send re-checks it.
			$tecc_consented = self::data_sharing_enabled();
			if ( $tecc_consented ) {
				// On the activation request plugins_loaded already fired without
				// us, so the framework (and the cron class's `every_30_days`
				// schedule filter, registered as a side effect of cpfm_register())
				// must be loaded here before scheduling.
				self::boot_cpfm_framework();
				if ( class_exists( 'CPFM_Usage_Cron' ) ) {
					\CPFM_Usage_Cron::cpfm_register( self::usage_cron_config() );
				}

				if ( ! wp_next_scheduled( 'tecc_extra_data_update' ) ) {
					wp_schedule_event( time(), 'every_30_days', 'tecc_extra_data_update' );
				}
			}

			if ( ! get_option( 'tecc_initial_save_version' ) ) {
				add_option( 'tecc_initial_save_version', TECC_VERSION_CURRENT );
			}

			if ( ! get_option( 'tecc-install-date' ) ) {
				add_option( 'tecc-install-date', gmdate( 'Y-m-d H:i:s' ) );
			}

			Schema::maybe_upgrade();
		}

		/**
		 * Deactivation handler: clear every tecc_ cron.
		 * No data is deleted on deactivation.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function deactivate() {
			if ( wp_next_scheduled( 'tecc_extra_data_update' ) ) {
				wp_clear_scheduled_hook( 'tecc_extra_data_update' );
			}
		}
	}
}
