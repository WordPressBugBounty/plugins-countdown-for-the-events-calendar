<?php
/*
Plugin Name:Event Countdown for The Events Calendar
Plugin URI:https://eventscalendaraddons.com/
Description:Event Countdown for The Events Calendar provides the ability to create Beautiful Countdown for <a href="http://wordpress.org/plugins/the-events-calendar/">The Events Calendar (by Modern Tribe)</a> events with just a few clicks.
Version:1.5.0
License:GPL2
Author:Cool Plugins
Author URI:https://coolplugins.net/?utm_source=tecc_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=plugins_list
License URI:https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: countdown-for-the-events-calendar
Requires Plugins: the-events-calendar
*/

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
if ( ! defined( 'TECC_VERSION_CURRENT' ) ) {
	define( 'TECC_VERSION_CURRENT', '1.5.0' );
}

define( 'TECC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TECC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

if ( ! defined( 'TECC_JS_DIR' ) ) {
	define( 'TECC_JS_DIR', TECC_PLUGIN_URL . 'assets/js' );
}
if ( ! defined( 'TECC_CSS_URL' ) ) {
	define( 'TECC_CSS_URL', TECC_PLUGIN_URL . 'assets/css' );
}
if ( ! defined( 'TECC_FEEDBACK_API' ) ) {
	define( 'TECC_FEEDBACK_API', 'https://feedback.coolplugins.net/' );
}
/**
 * Cool EventsCalendarCountdown main class.
 */


if ( ! class_exists( 'EventsCalendarCountdown' ) ) {
//phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	class EventsCalendarCountdown {

		/**
		 * Construct the plugin object
		 */
		public function __construct() {
			$this->tecc_include_files();
			/*** Installation and uninstallation hooks */
			register_activation_hook( __FILE__, array( $this, 'tecc_activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'tecc_deactivate' ) );
			add_action( 'init', array( $this, 'tecc_load_textdomain' ) );
			add_action( 'plugins_loaded', array( $this, 'tecc_check_event_calender_installed' ) );
			$this->tecc_require_files();
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'tecc_settings_page' ) );
			add_action('admin_enqueue_scripts', array($this, 'tecc_css'));
		}

		/**
		 * Load the plugin text domain for translation.
		 */
		public function tecc_load_textdomain() {
			load_plugin_textdomain( 'tecc', false, basename( dirname( __FILE__ ) ) . '/languages/' );//phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound

			if (!get_option( 'tecc_initial_save_version' ) ) {
                add_option( 'tecc_initial_save_version', TECC_VERSION_CURRENT );
            }

            if(!get_option( 'tecc-install-date' ) ) {
                add_option( 'tecc-install-date', gmdate('Y-m-d h:i:s') );
            }
		}

		public function tecc_include_files(){
			require_once TECC_PLUGIN_DIR . 'admin/cpfm-feedback/cron/class-cron.php';
		}

		public static function tecc_display_header() {
			// Required plugins list (path + minimum version)
			$required_plugins = [
				'countdown-for-the-events-calendar/countdown-for-events-calendar.php' => '1.4.16',
				'cp-events-calendar-modules-for-divi-pro/cp-events-calendar-modules-for-divi-pro.php' => '2.0.2',
				'event-page-templates-addon-for-the-events-calendar/the-events-calendar-event-details-page-templates.php' => '1.7.15',
				'events-block-for-the-events-calendar/events-block-for-the-event-calender.php' => '1.3.12',
				'event-single-page-builder-pro/event-single-page-builder-pro.php' => '2.0.1',
				'events-search-addon-for-the-events-calendar/events-calendar-search-addon.php' => '1.2.18',
				'events-speakers-and-sponsors/events-speakers-and-sponsors.php' => '1.1.1',
				'events-widgets-for-elementor-and-the-events-calendar/events-widgets-for-elementor-and-the-events-calendar.php' => '1.6.28',
				'events-widgets-pro/events-widgets-pro.php' => '3.0.1',
				'template-events-calendar/events-calendar-templates.php' => '2.5.4',
				'the-events-calendar-templates-and-shortcode/the-events-calendar-templates-and-shortcode.php' => '4.0.1',
			];

			$show_header = true;

			// Loop through all plugins
			foreach ($required_plugins as $plugin_path => $min_version) {

				// Plugin active hai?
				if (is_plugin_active($plugin_path)) {

					// Plugin data get karo
					$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path);
					$current_version = $plugin_data['Version'];

					// Version check
					if (version_compare($current_version, $min_version, '<=')) {
						$show_header = false;
						break;
					}
				}
			}
			return $show_header;
		}
		static public function get_admin_parent_slug_universal() {
                global $submenu, $plugin_page, $typenow;

                // 1. Determine the current "slug" or "post_type"
                $current_page = '';
                
                if ( ! empty( $plugin_page ) ) {
                    // It's a custom admin page (?page=...)
                    $current_page = $plugin_page;
                } elseif ( ! empty( $typenow ) ) {
                    // It's a post type page (?post_type=...)
                    $current_page = 'edit.php?post_type=' . $typenow;
                } else {
                    // Fallback for default posts/pages
                    $screen = get_current_screen();
                    if ( isset( $screen->post_type ) && 'post' !== $screen->post_type ) {
                        $current_page = 'edit.php?post_type=' . $screen->post_type;
                    } elseif ( isset( $screen->base ) && 'edit' === $screen->base ) {
                        $current_page = 'edit.php';
                    }
                }

                if ( empty( $current_page ) || empty( $submenu ) ) {
                    return '';
                }

                // 2. Search the submenu array
                foreach ( $submenu as $parent_slug => $sub_items ) {
                    foreach ( $sub_items as $sub_item ) {
                        if ( isset( $sub_item[2] ) && $sub_item[2] === $current_page ) {
                            return $parent_slug;
                        }
                    }
                }

                return '';
            }
		public function tecc_css() {
			wp_enqueue_script('cpfm-settings-data-share', TECC_PLUGIN_URL . 'admin/cpfm-feedback/js/cpfm-admin-share-data.js', array(), TECC_VERSION_CURRENT, true);
			wp_enqueue_script('cpfm-setting-js', TECC_PLUGIN_URL . 'admin/cpfm-feedback/js/cpfm-setting.js', array(), TECC_VERSION_CURRENT, true);
			wp_localize_script('cpfm-setting-js', 'cpfm_ajax_obj', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('cpfm_nonce_action')
			));
			$screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
            $parent_file = ['events-addons_page_tribe-events-shortcode-template-settings',
                            'events-addons_page_tribe_events-events-template-settings',
                            'toplevel_page_cool-plugins-events-addon',
                            'events-addons_page_cool-events-registration',
                            'events-addons_page_countdown_for_the_events_calendar',
                            'edit-epta',
                            'edit-esas_speaker',
                            'edit-esas_sponsor',
                            'events-addons_page_esas-speaker-sponsor-settings',
                            'edit-ewpe'];
                if (in_array($screen_id, $parent_file)){
					wp_enqueue_style( 'cool-plugins-events-addon', TECC_PLUGIN_URL . 'admin/events-addon-page/assets/css/styles.min.css', array(), TECC_VERSION_CURRENT, 'all' );
				}
			if (self::tecc_display_header() && in_array($screen_id, $parent_file) ) {
				// Common admin notice filter script (runs only on our target pages)
				wp_enqueue_script(
					'tecc-admin-notice-filter',
					TECC_PLUGIN_URL . 'assets/js/tecc-admin-notice-filter.js',
					array( 'jquery' ),
					TECC_VERSION_CURRENT,
					true
				);

				wp_localize_script(
					'tecc-admin-notice-filter',
					'tecc_notice_filter',
					array(
						'nonce'             => wp_create_nonce( 'tecc_notice_filter' ),
						'allowedBodyClasses' => array(
							'events-addons_page_tribe-events-shortcode-template-settings',
							'events-addons_page_tribe_events-events-template-settings',
							'toplevel_page_cool-plugins-events-addon',
							'events-addons_page_cool-events-registration',
							'events-addons_page_countdown_for_the_events_calendar',
							'post-type-epta',
							'post-type-esas_speaker',
							'post-type-esas_sponsor',
							'events-addons_page_esas-speaker-sponsor-settings',
							'post-type-ewpe',
						),
					)
				);
			}
		}
		/*
		Check The Event calender is installled or not. If user has not installed yet then show notice
		*/
		public function tecc_check_event_calender_installed() {
			if (is_admin()) {
				require_once TECC_PLUGIN_DIR . '/admin/feedback/admin-feedback-form.php';
			}

			if(!class_exists('CPFM_Feedback_Notice')){
				require_once TECC_PLUGIN_DIR . 'admin/cpfm-feedback/cpfm-feedback-notice.php';
			}
	
			add_action('cpfm_register_notice', function () {
			
				if (!class_exists('CPFM_Feedback_Notice') || !current_user_can('manage_options')) {
					return;
				}
				$notice = [
					'title' => __('Events Addons By Cool Plugins', 'countdown-for-the-events-calendar'),
					'message' => __('Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'countdown-for-the-events-calendar'),
					'pages' => ['cool-plugins-events-addon', 'countdown_for_the_events_calendar'],
					'always_show_on' => ['cool-plugins-events-addon', 'countdown_for_the_events_calendar'], // This enables auto-show
					'plugin_name'=>'tecc',
					
				];
	
				CPFM_Feedback_Notice::cpfm_register_notice('cool_events', $notice);
	
					if (!isset($GLOBALS['cool_plugins_feedback'])) {
						$GLOBALS['cool_plugins_feedback'] = [];//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					}
				
					$GLOBALS['cool_plugins_feedback']['cool_events'][] = $notice;//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		   
			});
			add_action('cpfm_after_opt_in_tecc', function($category) {
	
				if ($category === 'cool_events') {
					TECC_cronjob::tecc_send_data();
				}
			});
		}

		public function tecc_require_files() {
			if ( is_admin() ) {

				require_once __DIR__ . '/admin/events-addon-page/events-addon-page.php';
				cool_plugins_events_addon_settings_page( 'the-events-calendar', 'cool-plugins-events-addon', '📅 Events Addons For The Events Calendar' );

				require_once TECC_PLUGIN_DIR . 'includes/tecc-setting-panel.php';
				require_once TECC_PLUGIN_DIR . 'admin/feedback-notice/tecc-feedback-notice.php';
				new teccFeedbackNotice();
			}
			require_once TECC_PLUGIN_DIR . 'includes/tecc-shortcode.php';
			new CountdownShortcode();
			require_once TECC_PLUGIN_DIR . 'includes/tecc-functions.php';

		}

		/*** Add links in plugin list page */
		public function tecc_settings_page( $links ) {
			$links[] = '<a style="font-weight:bold" href="' . esc_url( get_admin_url( null, 'admin.php?page=countdown_for_the_events_calendar' ) ) . '">' . __( 'Settings', 'countdown-for-the-events-calendar' ) . '</a>';
			return $links;
		}

			// set settings on plugin activation
		public function tecc_activate() {
			update_option( 'tecc-v', TECC_VERSION_CURRENT );
			update_option( 'tecc-type', 'FREE' );
			update_option( 'tecc-installDate', gmdate( 'Y-m-d h:i:s' ) );
			update_option( 'tecc-ratingDiv', 'no' );

			$review_option = get_option("tecc-cpfm-data-sharing");

			if ($review_option === 'yes') {
				if (!wp_next_scheduled('tecc_extra_data_update')) {

					wp_schedule_event(time(), 'every_30_days', 'tecc_extra_data_update');

				}
			}

			if (!get_option( 'tecc_initial_save_version' ) ) {
                add_option( 'tecc_initial_save_version', TECC_VERSION_CURRENT );
            }

            if(!get_option( 'tecc-install-date' ) ) {
                add_option( 'tecc-install-date', gmdate('Y-m-d h:i:s') );
            }
		}

		public function tecc_deactivate() {
			if (wp_next_scheduled('tecc_extra_data_update')) {
				wp_clear_scheduled_hook('tecc_extra_data_update');
			}
		}


	} //class end here
}

$tecc = new EventsCalendarCountdown();
