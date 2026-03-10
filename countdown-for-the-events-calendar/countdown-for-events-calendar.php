<?php
/*
Plugin Name:Event Countdown for The Events Calendar
Plugin URI:https://eventscalendaraddons.com/
Description:Event Countdown for The Events Calendar provides the ability to create Beautiful Countdown for <a href="http://wordpress.org/plugins/the-events-calendar/">The Events Calendar (by Modern Tribe)</a> events with just a few clicks.
Version:1.5.2
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
	define( 'TECC_VERSION_CURRENT', '1.5.2' );
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
			add_action('admin_print_scripts', [$this, 'ect_hide_unrelated_notices']);
		}

		public function ect_hide_unrelated_notices(){ 
			
			// phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded
            $events_pages = false;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking page parameter to conditionally hide notices, no data processing
            if (isset($_GET['page'])) {
				
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking page parameter to conditionally hide notices, no data processing
				$page_param = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

				$allowed_pages = array(
					'cool-plugins-events-addon',
					'cool-events-registration',
					'tribe-events-shortcode-template-settings',
					'tribe_events-events-template-settings',
					'countdown_for_the_events_calendar',
					'esas-speaker-sponsor-settings',
					'esas_speaker',
					'esas_sponsor',
					'ewpe',
					'epta'
				);

				if (in_array($page_param, $allowed_pages, true)) {
					$events_pages = true;
				}
            }
			$is_post_type_page = false;

			$current_screen = get_current_screen();
			
			if ( $current_screen && ! empty( $current_screen->post_type ) ) {
			
				$allowed_post_types = array(
					'esas_speaker',
					'esas_sponsor',
					'epta',
					'ewpe'
				);
			
				if ( in_array( $current_screen->post_type, $allowed_post_types, true ) ) {
					$is_post_type_page = true;
				}
			}
            if ($events_pages) {
                global $wp_filter;
                // Define rules to remove callbacks.
                $rules = [
                    'user_admin_notices' => [], // remove all callbacks.
                    'admin_notices'      => [],
                    'all_admin_notices'  => [],
                    'admin_footer'       => [
                        'render_delayed_admin_notices', // remove this particular callback.
                    ],
                ];
                $notice_types = array_keys($rules);
                foreach ($notice_types as $notice_type) {
                    if (empty($wp_filter[$notice_type]) || empty($wp_filter[$notice_type]->callbacks) || ! is_array($wp_filter[$notice_type]->callbacks)) {
                        continue;
                    }
                    $remove_all_filters = empty($rules[$notice_type]);
                    foreach ($wp_filter[$notice_type]->callbacks as $priority => $hooks) {
                        foreach ($hooks as $name => $arr) {
                            if (is_object($arr['function']) && is_callable($arr['function'])) {
                                if ($remove_all_filters) {
                                    unset($wp_filter[$notice_type]->callbacks[$priority][$name]);
                                }
                                continue;
                            }
                            $class = ! empty($arr['function'][0]) && is_object($arr['function'][0]) ? strtolower(get_class($arr['function'][0])) : '';
                            // Remove all callbacks except WPForms notices.
                            if ($remove_all_filters && strpos($class, 'wpforms') === false) {
                                unset($wp_filter[$notice_type]->callbacks[$priority][$name]);
                                continue;
                            }
                            $cb = is_array($arr['function']) ? $arr['function'][1] : $arr['function'];
                            // Remove a specific callback.
                            if (! $remove_all_filters) {
                                if (in_array($cb, $rules[$notice_type], true)) {
                                    unset($wp_filter[$notice_type]->callbacks[$priority][$name]);
                                }
                                continue;
                            }
                        }
                    }
                }
            }

			// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			if (!$events_pages && !$is_post_type_page) {

				// ✅ GLOBAL LOCK SYSTEM
				if (!defined('ECT_ADMIN_NOTICE_HOOKED')) {

					define('ECT_ADMIN_NOTICE_HOOKED', true);

					add_action(
						'admin_notices',
						array($this, 'ect_dash_admin_notices'),
						PHP_INT_MAX
					);
				}
			}
        }

		public function ect_dash_admin_notices() {

			// ✅ Double render protection
			if (defined('ECT_ADMIN_NOTICE_RENDERED')) {
				return;
			}

			define('ECT_ADMIN_NOTICE_RENDERED', true);

			do_action('ect_display_admin_notices');
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
