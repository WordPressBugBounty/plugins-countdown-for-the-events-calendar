<?php

if ( ! class_exists( 'teccFeedbackNotice' ) ) {
	class teccFeedbackNotice {
		/**
		 * The Constructor
		 */
		public function __construct() {
			// register actions

			if ( is_admin() ) {
				add_action( 'admin_notices', array( $this, 'tecc_admin_notice_for_reviews' ) );
				add_action( 'admin_print_scripts', array( $this, 'load_script' ) );
				add_action( 'wp_ajax_tecc_dismiss_notice', array( $this, 'tecc_dismiss_review_notice' ) );
			}
		}

		/**
		 * Load script to dismiss notices.
		 *
		 * @return void
		 */
		public function load_script() {
			wp_register_script( 'tecc-feedback-notice-script', TECC_JS_DIR . '/tecc-admin-feedback-notice.js', array( 'jquery' ), TECC_VERSION_CURRENT, true );
			wp_enqueue_script( 'tecc-feedback-notice-script' );
			wp_register_style( 'tecc-feedback-notice-styles', TECC_CSS_URL . '/tecc-admin-feedback-notice.css', array(), TECC_VERSION_CURRENT, null, 'all' );
			wp_enqueue_style( 'tecc-feedback-notice-styles' );
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
				$content = wp_kses_post( $this->create_notice_content() );
				printf( '%s', $content );
			}
		}

		// generated review notice HTML
		function create_notice_content() {

			$ajax_url           = esc_url( admin_url( 'admin-ajax.php' ) );
			$ajax_callback      = esc_attr( 'tecc_dismiss_notice' );
			$wrap_cls           = esc_attr( 'notice notice-info is-dismissible' );
			$img_path           = esc_url( TECC_PLUGIN_URL . 'assets/images/logo.svg' );
			$p_name             = esc_html( 'The Events Calendar Countdown Addon' );
			$like_it_text       = esc_html__( 'Rate Now! ★★★★★', 'tecc' );
			$already_rated_text = esc_html__( 'I already rated it', 'tecc' );
			$not_like_it_text   = esc_html__( 'Not Interested', 'tecc' );
			$p_link             = esc_url( 'https://wordpress.org/support/plugin/countdown-for-the-events-calendar/reviews/#new-post' );
			$nonce              = esc_attr( wp_create_nonce( 'tecc_dismiss_notice_nonce' ) );
			$message_raw = sprintf(
				'Thanks for using <b>%s</b> WordPress plugin. We hope it meets your expectations! <br/>Please give us a quick rating, it works as a boost for us to keep working on more <a href="%s" target="_blank"><strong>Cool Plugins</strong></a>!<br/>',
				$p_name,
				esc_url( 'https://coolplugins.net/?utm_source=tecc_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=review_notice' )
			);

			// allow safe tags in message
			$message = wp_kses_post( $message_raw );

			$html = '
			<div data-ajax-url="%8$s" data-ajax-callback="%9$s" data-nonce="%11$s"
			 class="cool-feedback-notice-wrapper %1$s">
				<div class="logo_container">
					<a href="%5$s"><img src="%2$s" alt="%3$s"></a>
				</div>
				<div class="message_container">%4$s
					<div class="callto_action">
						<ul>
							<li class="love_it"><a href="%5$s" class="like_it_btn button button-primary" target="_blank" title="%6$s">%6$s</a></li>
							<li class="already_rated"><a href="#" class="already_rated_btn button %9$s" title="%7$s">%7$s</a></li>
							<li class="already_rated"><a href="#" class="already_rated_btn button %9$s" title="%10$s">%10$s</a></li>
						</ul>
						<div class="clrfix"></div>
					</div>
				</div>
			</div>';

			return sprintf(
				$html,
				$wrap_cls,          // 1
				$img_path,          // 2
				$p_name,            // 3
				$message,           // 4
				$p_link,            // 5
				$like_it_text,      // 6
				$already_rated_text,// 7
				$ajax_url,          // 8
				$ajax_callback,     // 9
				$not_like_it_text,  // 10
				$nonce              // 11

			);
		}

	} //class end

}



