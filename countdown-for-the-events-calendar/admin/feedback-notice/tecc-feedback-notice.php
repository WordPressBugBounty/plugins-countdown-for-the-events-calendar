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
				add_action( 'ect_display_admin_notices', array( $this, 'tecc_admin_notice_for_reviews' ) );
				add_action( 'wp_ajax_tecc_dismiss_notice', array( $this, 'tecc_dismiss_review_notice' ) );
			}
		}
		// ajax callback for review notice
		public function tecc_dismiss_review_notice() {
			check_ajax_referer( 'tecc_dismiss_notice_nonce', 'security' );

			if ( ! current_user_can( 'update_plugins' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}
			
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

	} //class end

}



