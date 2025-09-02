<?php
/*
Plugin Name:The Events Calendar Countdown Addon
Plugin URI:https://eventscalendaraddons.com/
Description:The Events Calendar CountDown Addon provides the ability to create Beautiful Countdown for <a href="http://wordpress.org/plugins/the-events-calendar/">The Events Calendar (by Modern Tribe)</a> events with just a few clicks.
Version:1.4.13
License:GPL2
Author:Cool Plugins
Author URI:https://coolplugins.net/?utm_source=tecc_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=plugins_list
License URI:https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain:tecc
Requires Plugins: the-events-calendar
*/

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
if ( ! defined( 'TECC_VERSION_CURRENT' ) ) {
	define( 'TECC_VERSION_CURRENT', '1.4.13' );
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
			load_plugin_textdomain( 'tecc', false, basename( dirname( __FILE__ ) ) . '/languages/' );

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

		public function tecc_css() {
			wp_enqueue_script('cpfm-settings-data-share', TECC_PLUGIN_URL . 'admin/cpfm-feedback/js/cpfm-admin-share-data.js', array(), TECC_VERSION_CURRENT);
			wp_enqueue_script('cpfm-setting-js', TECC_PLUGIN_URL . 'admin/cpfm-feedback/js/cpfm-setting.js', array(), TECC_VERSION_CURRENT);
			wp_localize_script('cpfm-setting-js', 'cpfm_ajax_obj', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('cpfm_nonce_action')
			));
		}
		/*
		Check The Event calender is installled or not. If user has not installed yet then show notice
		*/
		public function tecc_check_event_calender_installed() {
			if ( ! class_exists( 'Tribe__Events__Main' ) || ! defined( 'Tribe__Events__Main::VERSION' ) ) {
				add_action( 'admin_notices', array( $this, 'Install_TECC_Notice' ) );
			}
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
					'title' => __('Events Addons By Cool Plugins', 'tecc'),
					'message' => __('Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'tecc'),
					'pages' => ['cool-plugins-events-addon', 'countdown_for_the_events_calendar'],
					'always_show_on' => ['cool-plugins-events-addon', 'countdown_for_the_events_calendar'], // This enables auto-show
					'plugin_name'=>'tecc',
					
				];
	
				CPFM_Feedback_Notice::cpfm_register_notice('cool_events', $notice);
	
					if (!isset($GLOBALS['cool_plugins_feedback'])) {
						$GLOBALS['cool_plugins_feedback'] = [];
					}
				
					$GLOBALS['cool_plugins_feedback']['cool_events'][] = $notice;
		   
			});
			add_action('cpfm_after_opt_in_tecc', function($category) {
	
				if ($category === 'cool_events') {
					TECC_cronjob::tecc_send_data();
				}
			});
		}

		public function Install_TECC_Notice() {
			if ( current_user_can( 'activate_plugins' ) ) {
				$url   = 'plugin-install.php?tab=plugin-information&plugin=the-events-calendar&TB_iframe=true';
				$title = __( 'The Events Calendar', 'tribe-events-ical-importer' );

				printf(
					'<div class="error CTEC_Msz"><p>' .
					esc_html( __( '%1$s %2$s', 'tecc1' ) ),
					esc_html( __( 'In order to use our plugin, Please first install the latest version of', 'tecc1' ) ),
					sprintf(
						'<a href="%s" class="thickbox" title="%s">%s</a>',
						esc_url( $url ),
						esc_html( $title ),
						esc_html( $title )
					) . '</p></div>'
				);

			}
		}

		public function tecc_require_files() {
			if ( is_admin() ) {

				require_once __DIR__ . '/admin/events-addon-page/events-addon-page.php';
				cool_plugins_events_addon_settings_page( 'the-events-calendar', 'cool-plugins-events-addon', '📅 Events Addons For The Events Calendar' );

				require_once TECC_PLUGIN_DIR . 'includes/tecc-setting-panel.php';
				require_once TECC_PLUGIN_DIR . 'includes/tecc-feedback-notice.php';
				new teccFeedbackNotice();
			}
			require_once TECC_PLUGIN_DIR . 'includes/tecc-shortcode.php';
			new CountdownShortcode();
			require_once TECC_PLUGIN_DIR . 'includes/tecc-functions.php';

		}

		/*** Add links in plugin list page */
		public function tecc_settings_page( $links ) {
			$links[] = '<a style="font-weight:bold" href="' . esc_url( get_admin_url( null, 'admin.php?page=countdown_for_the_events_calendar' ) ) . '">' . __( 'Settings', 'tecc' ) . '</a>';
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
