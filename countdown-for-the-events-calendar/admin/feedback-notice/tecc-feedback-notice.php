<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'teccFeedbackNotice' ) ) {
	class teccFeedbackNotice {
		/**
		 * The Constructor
		 */
		public function __construct() {
			// register actions

			if ( is_admin() ) {
				add_action('admin_enqueue_scripts', array($this, 'add_notice_positioning_inline'), 20);
				add_action( 'admin_notices', array( $this, 'tecc_admin_notice_for_reviews' ) );
				add_action( 'wp_ajax_tecc_dismiss_notice', array( $this, 'tecc_dismiss_review_notice' ) );
			}
		}
		// ajax callback for review notice
		public function tecc_dismiss_review_notice() {
			check_ajax_referer( 'tecc_dismiss_notice_nonce', 'security' );
			update_option( 'tecc-ratingDiv', 'yes' );
			wp_send_json_success();
		}
		// admin notice
		public function tecc_admin_notice_for_reviews() {

			if ( ! current_user_can( 'update_plugins' ) ) {
				return;
			}
			 // get installation dates and rated settings
			 $installation_date = get_option( 'tecc-installDate' );
			 $alreadyRated      = get_option( 'tecc-ratingDiv' ) != false ? get_option( 'tecc-ratingDiv' ) : 'no';

			 // check user already rated
			if ( $alreadyRated == 'yes' ) {
				return;
			}

			// grab plugin installation date and compare it with current date
			$display_date = gmdate( 'Y-m-d h:i:s' );
			$install_date = new DateTime( $installation_date );
			$current_date = new DateTime( $display_date );
			$difference   = $install_date->diff( $current_date );
			$diff_days    = $difference->days;

			// check if installation days is greator then week
			if ( isset( $diff_days ) && $diff_days >= 3 ) {
				wp_enqueue_style( 'tecc-feedback-notice-styles', TECC_PLUGIN_URL . 'admin/feedback-notice/css/tecc-admin-feedback-notice.css', array(), TECC_VERSION_CURRENT, null, 'all' );
				wp_enqueue_script( 'tecc-feedback-notice-script', TECC_PLUGIN_URL . 'admin/feedback-notice/js/tecc-admin-feedback-notice.js', array( 'jquery' ), TECC_VERSION_CURRENT, true );
				$content = wp_kses_post( $this->create_notice_content() );
				printf( '%s', $content );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		// generated review notice HTML
		function create_notice_content() {
		$ajax_url           = esc_url( admin_url( 'admin-ajax.php' ) );
		$ajax_callback      = 'tecc_dismiss_notice';
		$wrap_cls           = 'notice notice-info is-dismissible ect-required-plugin-notice';
		$p_name             = esc_html( 'The Events Calendar Countdown Addon' );
			$like_it_text       = esc_html__( 'Rate Now! ★★★★★', 'countdown-for-the-events-calendar' );
			$already_rated_text = esc_html__( 'Already Reviewed', 'countdown-for-the-events-calendar' );
			$not_like_it_text   = esc_html__( 'Not Interested', 'countdown-for-the-events-calendar' );
			$p_link             = esc_url( 'https://wordpress.org/support/plugin/countdown-for-the-events-calendar/reviews/#new-post' );
			$nonce              = wp_create_nonce( 'tecc_dismiss_notice_nonce' );
		
			$message = sprintf(
				wp_kses_post(
					'Thanks for using <b>%s</b> WordPress plugin. We hope it meets your expectations! <br/>Please give us a quick rating, it works as a boost for us to keep working on more <a href="%s" target="_blank"><strong>Cool Plugins</strong></a>!<br/>'
				),
				$p_name,
				esc_url( 'https://coolplugins.net/?utm_source=tecc_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=review_notice' )
			);
		
			$html = '
			<div data-ajax-url="%7$s" data-ajax-callback="%8$s" data-nonce="%9$s" class="cool-feedback-notice-wrapper %1$s">
				<div class="message_container">%2$s
					<div class="callto_action">
						<ul>
							<li class="love_it"><a href="%3$s" class="like_it_btn button button-primary" target="_blank" title="%4$s">%4$s</a></li>
							<li class="already_rated"><a href="#" class="already_rated_btn button %8$s" title="%5$s">%5$s</a></li>
							<li class="already_rated"><a href="#" class="already_rated_btn button %8$s" title="%6$s">%6$s</a></li>
						</ul>
						<div class="clrfix"></div>
					</div>
				</div>
			</div>';
		
			return sprintf(
				$html,
				esc_attr( $wrap_cls ),          // %1$s
				$message,                       // %2$s
				$p_link,                        // %3$s
				esc_html( $like_it_text ),      // %4$s
				esc_html( $already_rated_text ),// %5$s
				esc_html( $not_like_it_text ),  // %6$s
				esc_url( $ajax_url ),           // %7$s
				esc_attr( $ajax_callback ),     // %8$s
				esc_attr( $nonce )              // %9$s
			);
		}

		 /**
         * Check if we're on the plugin admin pages
         *
         * @since 1.0.0
         *
         * @return bool
         */
        private function is_ect_plugin_page() {
            $screen = get_current_screen();
            if ( empty( $screen ) ) {
                return false;
            }
            
            // Check if we're on plugin pages that use the header
            $plugin_pages = array(
                'toplevel_page_cool-plugins-events-addon',
                'events-addons_page_tribe-events-shortcode-template-settings',
                'events-addons_page_cool-events-registration',
            );
            
            return in_array( $screen->id, $plugin_pages, true );
        }

        /**
         * Add inline CSS and JavaScript for notice positioning on plugin pages
         *
         * @since 1.0.0
         *
         * @return void
         */
        public function add_notice_positioning_inline() {
            if ( ! $this->is_ect_plugin_page() ) {
                return;
            }

            // Ensure jQuery is enqueued
            wp_enqueue_script( 'jquery' );

            // Add inline CSS
            $css = "
			/* Notice positioning for plugin pages */
			body.toplevel_page_cool-plugins-events-addon .notice,
			body.toplevel_page_cool-plugins-events-addon .error,
			body.toplevel_page_cool-plugins-events-addon .updated,
			body.toplevel_page_cool-plugins-events-addon .notice-error,
			body.toplevel_page_cool-plugins-events-addon .notice-warning,
			body.toplevel_page_cool-plugins-events-addon .notice-info,
			body.toplevel_page_cool-plugins-events-addon .notice-success,
			body.events-addons_page_tribe-events-shortcode-template-settings .notice,
			body.events-addons_page_tribe-events-shortcode-template-settings .error,
			body.events-addons_page_tribe-events-shortcode-template-settings .updated,
			body.events-addons_page_tribe-events-shortcode-template-settings .notice-error,
			body.events-addons_page_tribe-events-shortcode-template-settings .notice-warning,
			body.events-addons_page_tribe-events-shortcode-template-settings .notice-info,
			body.events-addons_page_tribe-events-shortcode-template-settings .notice-success,
			body.events-addons_page_cool-events-registration .notice,
			body.events-addons_page_cool-events-registration .error,
			body.events-addons_page_cool-events-registration .updated,
			body.events-addons_page_cool-events-registration .notice-error,
			body.events-addons_page_cool-events-registration .notice-warning,
			body.events-addons_page_cool-events-registration .notice-info,
			body.events-addons_page_cool-events-registration .notice-success {
				display: none !important;
				margin-left: 2rem;
			}

			/* Keep inline notices inside license box visible (do NOT move them) */
			body.toplevel_page_cool-plugins-events-addon [class*=\"license-box\"] .notice,
			body.toplevel_page_cool-plugins-events-addon [class*=\"license-box\"] .error,
			body.toplevel_page_cool-plugins-events-addon [class*=\"license-box\"] .updated,
			body.toplevel_page_cool-plugins-events-addon [class*=\"license-box\"] .notice-error,
			body.toplevel_page_cool-plugins-events-addon [class*=\"license-box\"] .notice-warning,
			body.toplevel_page_cool-plugins-events-addon [class*=\"license-box\"] .notice-info,
			body.toplevel_page_cool-plugins-events-addon [class*=\"license-box\"] .notice-success,
			body.events-addons_page_tribe-events-shortcode-template-settings [class*=\"license-box\"] .notice,
			body.events-addons_page_tribe-events-shortcode-template-settings [class*=\"license-box\"] .error,
			body.events-addons_page_tribe-events-shortcode-template-settings [class*=\"license-box\"] .updated,
			body.events-addons_page_tribe-events-shortcode-template-settings [class*=\"license-box\"] .notice-error,
			body.events-addons_page_tribe-events-shortcode-template-settings [class*=\"license-box\"] .notice-warning,
			body.events-addons_page_tribe-events-shortcode-template-settings [class*=\"license-box\"] .notice-info,
			body.events-addons_page_tribe-events-shortcode-template-settings [class*=\"license-box\"] .notice-success,
			body.events-addons_page_cool-events-registration [class*=\"license-box\"] .notice,
			body.events-addons_page_cool-events-registration [class*=\"license-box\"] .error,
			body.events-addons_page_cool-events-registration [class*=\"license-box\"] .updated,
			body.events-addons_page_cool-events-registration [class*=\"license-box\"] .notice-error,
			body.events-addons_page_cool-events-registration [class*=\"license-box\"] .notice-warning,
			body.events-addons_page_cool-events-registration [class*=\"license-box\"] .notice-info,
			body.events-addons_page_cool-events-registration [class*=\"license-box\"] .notice-success {
				display: block !important;
				margin-left: 0;
				margin-right: 0;
				width: auto;
			}

			/* Show notices after they are moved */
			body.toplevel_page_cool-plugins-events-addon .ect-moved-notice,
			body.events-addons_page_tribe-events-shortcode-template-settings .ect-moved-notice,
			body.events-addons_page_cool-events-registration .ect-moved-notice {
				display: block !important;
				margin-left: 2rem;
				margin-right: 2rem;
				width: auto;
			}
			";
            
            // Register and enqueue a style handle for notice positioning if not already done
            if ( ! wp_style_is( 'ect-notice-positioning', 'registered' ) ) {
                wp_register_style( 'ect-notice-positioning',null, null, TECC_VERSION_CURRENT, false );
            }
            wp_enqueue_style( 'ect-notice-positioning' );
            wp_add_inline_style( 'ect-notice-positioning', $css );

            // Add inline JavaScript
            $js = "
			jQuery(document).ready(function($) {
				// Wait for the page to load
				setTimeout(function() {
					// Move ONLY top admin notices (page top) - do not touch inline/content notices
					// Also: jis notice me yeh text aaye, usko move mat karo (neeche hi rahe)
					var skipText = 'to continue receiving updates and priority support.';
					var topNotices = $('#wpbody-content').find(
						'> .notice, > .error, > .updated, > .notice-error, > .notice-warning, > .notice-info, > .notice-success,' +
						'> .wrap > .notice, > .wrap > .error, > .wrap > .updated, > .wrap > .notice-error, > .wrap > .notice-warning, > .wrap > .notice-info, > .wrap > .notice-success'
					);

					var noticesToMove = topNotices.filter(function() {
						var txt = $(this).text() || '';
						return txt.indexOf(skipText) === -1;
					});

					if (noticesToMove.length > 0) {
						var headerContainer = $('.ect-top-header');
						if (headerContainer.length > 0) {
							noticesToMove.detach().insertAfter(headerContainer);
							noticesToMove.addClass('ect-moved-notice');
						}
					}
				}, 100);
			});
			";
            wp_add_inline_script( 'jquery', $js );
        }

	} //class end

}



