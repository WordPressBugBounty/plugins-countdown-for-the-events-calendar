<?php
//phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, WordPress.Security.NonceVerification.Recommended, WordPress.Security.EscapeOutput.OutputNotEscaped
//phpcs:disable PluginCheck.CodeAnalysis.SettingSanitization.register_settingMissing
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'admin_menu', 'tecc_add_admin_menu', 50 );
add_action( 'admin_init', 'tecc_settings_init' );
add_action( 'admin_init', 'tecc_checkbox_setting' );
add_action( 'admin_head', 'tecc_enqueue_color_picker' );
add_action('wp_ajax_cpfm_save_usage_data_sharing', 'cpfm_save_usage_data_sharing_callback');
// add_action( 'all_admin_notices', 'tecc_display_header', 1 );

function tecc_enqueue_color_picker() {
	$screen = get_current_screen();
	if ( $screen->id != 'events-addons_page_countdown_for_the_events_calendar' ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'tecc-b-color-picker-script', TECC_JS_DIR . '/jquery-custom.js', array( 'wp-color-picker' ), TECC_VERSION_CURRENT, true );
	wp_enqueue_script( 'setting-panel-js', TECC_JS_DIR . '/settings-panel.js', array( 'jquery' ), TECC_VERSION_CURRENT, true );

}


function tecc_add_admin_menu() {
	add_submenu_page( 'cool-plugins-events-addon', 'Countdown for the events calendar', 'Event Countdown', 'manage_options', 'countdown_for_the_events_calendar', 'tecc_options_page', 50 );
}

 /**
* Display header on countdown for the events calendar admin pages
*/
function tecc_display_header() {
	global $current_screen;
	
	// Check if we're on Event Countdown submenu/settings page or post type pages
	$is_tecc_page = false;
	
	// Event Countdown submenu (Events Addons > Event Countdown)
	if ( $current_screen && isset( $current_screen->id ) && $current_screen->id === 'events-addons_page_countdown_for_the_events_calendar' ) {
		$is_tecc_page = true;
	}

	if ( $is_tecc_page) {
		// Add CSS to position header at top
		?>
		<div class="ect-dashboard-wrapper">
		<?php
		// Include the header
		$header_file = TECC_PLUGIN_DIR . 'admin/events-addon-page/includes/dashboard-header.php';
		if ( file_exists( $header_file ) ) {
			$prefix = 'ect';
			$show_wrapper = false;
			include $header_file;
		}
		?>
		</div>
		<?php
	}
}
function tecc_sanitize_settings( $input ) {

	if ( ! is_array( $input ) ) {
		return array();
	}

	$sanitized = array();

	foreach ( $input as $key => $value ) {

		$key = sanitize_key( $key );

		if ( is_array( $value ) ) {
			$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
		} else {
			$sanitized[ $key ] = sanitize_text_field( $value );
		}
	}

	return $sanitized;
}

function tecc_settings_init() {

	register_setting(
		'pluginPage',
		'tecc_settings',
		array(
			'sanitize_callback' => 'tecc_sanitize_settings',
		)
	);
	add_settings_section(
		'tecc_pluginPage_section',
		__( 'Create Shortcode for the Event countdown using below mentioned settings', 'countdown-for-the-events-calendar' ),
		'tecc_settings_section_callback',
		'pluginPage'
	);
	add_settings_field(
		'autostart-next-countdown',
		__( 'Autostart countdown of next upcoming event', 'countdown-for-the-events-calendar' ),
		'tecc_select_field_8_render',
		'pluginPage',
		'tecc_pluginPage_section',
		array( 'class' => 'tecc-autostart' )
	);

	add_settings_field(
		'autostart-future-countdown',
		__( 'Autostart countdown of next future event', 'countdown-for-the-events-calendar' ),
		'tecc_select_field_11_render',
		'pluginPage',
		'tecc_pluginPage_section',
		array( 'class' => 'tecc-autostart-future' )
	);

	add_settings_field(
		'future-events-list',
		__( 'Select Events for autostart Countdown', 'countdown-for-the-events-calendar' ),
		'tecc_select_field_7_render',
		'pluginPage',
		'tecc_pluginPage_section',
		array( 'class' => 'tecc-events-list' )
	);

	add_settings_field(
		'event_id',
		__( 'Select an Event', 'countdown-for-the-events-calendar' ),
		'tecc_select_field_0_render',
		'pluginPage',
		'tecc_pluginPage_section',
		array( 'class' => 'tecc-single-event' )
	);

	add_settings_field(
		'backgroundcolor',
		__( 'Countdown Background Color', 'countdown-for-the-events-calendar' ),
		'tecc_text_field_1_render',
		'pluginPage',
		'tecc_pluginPage_section'
	);

	add_settings_field(
		'font-color',
		__( 'Countdown Font Color', 'countdown-for-the-events-calendar' ),
		'tecc_text_field_2_render',
		'pluginPage',
		'tecc_pluginPage_section'
	);

	add_settings_field(
		'show-seconds',
		__( 'Show Seconds in Countdown', 'countdown-for-the-events-calendar' ),
		'tecc_select_field_3_render',
		'pluginPage',
		'tecc_pluginPage_section'
	);
	add_settings_field(
		'show-image',
		__( 'Show Image in Countdown', 'countdown-for-the-events-calendar' ),
		'tecc_select_field_12_render',
		'pluginPage',
		'tecc_pluginPage_section'
	);

	add_settings_field(
		'size',
		__( 'Select Countdown Size', 'countdown-for-the-events-calendar' ),
		'tecc_select_field_4_render',
		'pluginPage',
		'tecc_pluginPage_section'
	);

	add_settings_field(
		'event-start',
		__( 'Display Text When Event Starts', 'countdown-for-the-events-calendar' ),
		'tecc_text_field_5_render',
		'pluginPage',
		'tecc_pluginPage_section',
		array( 'class' => 'tecc-start-text' )
	);

	add_settings_field(
		'event-end',
		__( 'Display Text When Event Ends', 'countdown-for-the-events-calendar' ),
		'tecc_text_field_6_render',
		'pluginPage',
		'tecc_pluginPage_section',
		array( 'class' => 'tecc-end-text' )
	);

	add_settings_field(
		'autostart-text',
		__( 'Display Text When Event Starts (Default is "Event Starts refresh page to see next upcoming event") ', 'countdown-for-the-events-calendar' ),
		'tecc_text_field_10_render',
		'pluginPage',
		'tecc_pluginPage_section',
		array( 'class' => 'tecc-autostart-text' )
	);

	add_settings_field(
		'main-title',
		__( 'Main Title (Default is "Next Upcoming Event")', 'countdown-for-the-events-calendar' ),
		'tecc_text_field_9_render',
		'pluginPage',
		'tecc_pluginPage_section'
	);
	add_settings_field(
		'main-title',
		__( 'Main Title (Default is "Next Upcoming Event")', 'countdown-for-the-events-calendar' ),
		'tecc_text_field_9_render',
		'pluginPage',
		'tecc_pluginPage_section'
	);

}

//phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
function tecc_select_field_0_render() {

	$options = get_option( 'tecc_settings' );
	$events  = tribe_get_events(
		array(
			'posts_per_page' => -1,
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_EventStartDate',
					'value'   => current_time( 'Y-m-d H:i:s' ),
					'compare' => '>',
					'type'    => 'DATETIME',
				),
			),
		)
	);
	?>
	<select name='tecc_settings[event_id]'>    
		<?php
		$saved_event = isset( $options['event_id'] ) ? $options['event_id'] : '';
		if ( is_array( $events ) && array_filter( $events ) != null ) {
			foreach ( $events as $event ) {
				?>
				 <option value="<?php echo esc_attr( $event->ID ); ?>"<?php selected( $saved_event, $event->ID ); ?>><?php echo esc_html( $event->post_title ); ?></option>
				<?php
			}
		} else {
			?>
			<option value="0"><?php esc_html_e( 'No Future Event found.', 'countdown-for-the-events-calendar' ); ?></option>
			<?php
		}
		?>
		 
	</select>
	<?php
}


function tecc_text_field_1_render() {

	$options = get_option( 'tecc_settings' );
	?>
	<input type='text' name='tecc_settings[backgroundcolor]' value="<?php echo isset( $options['backgroundcolor'] ) ? esc_attr( $options['backgroundcolor'] ) : '#2a86f7'; ?>" class="wp-color-picker-field" data-default-color ="#4395cb">
	<?php
}


function tecc_text_field_2_render() {

	$options = get_option( 'tecc_settings' );
	?>
	<input type='text' name='tecc_settings[font-color]' value="<?php echo isset( $options['font-color'] ) ? esc_attr( $options['font-color'] ) : '#ffffff'; ?>" class="wp-color-picker-field" data-default-color ="#ffffff">
	<?php

}


function tecc_select_field_3_render() {

	$options       = get_option( 'tecc_settings' );
	 $show_seconds = isset( $options['show-seconds'] ) ? sanitize_key( wp_unslash( $options['show-seconds'] ) ) : 'yes';
	?>
	<select name='tecc_settings[show-seconds]'>
	
		<option value="yes" <?php selected( $show_seconds, 'yes' ); ?> >Yes</option>
		<option value="no" <?php selected( $show_seconds, 'no' ); ?> >No</option>
	</select>
	<?php

}

function tecc_select_field_12_render() {

	$options       = get_option( 'tecc_settings' );
	 $show_image = isset( $options['show-image'] ) ? sanitize_key( wp_unslash( $options['show-image'] ) ) : 'no';
	?>
	<select name='tecc_settings[show-image]'>
	
		<option value="yes" <?php selected( $show_image, 'yes' ); ?> >Yes</option>
		<option value="no" <?php selected( $show_image, 'no' ); ?> >No</option>
	</select>
	<?php

}


function tecc_select_field_4_render() {

	$options = get_option( 'tecc_settings' );
	$size    = isset( $options['size'] ) ? sanitize_key( wp_unslash( $options['size'] ) ) : 'medium';
	?>
	<select name='tecc_settings[size]'>
		<option value="large" <?php selected( $size, 'large' ); ?>>Large</option>
		<option value="medium" <?php selected( $size, 'medium' ); ?>>Medium</option>
		<option value="small" <?php selected( $size, 'small' ); ?>>Small</option>
	</select>
	<?php

}


function tecc_text_field_5_render() {

	$options = get_option( 'tecc_settings' );
	printf(
		'<input type="text" name="tecc_settings[event-start]" value="%s" />',
		isset( $options['event-start'] ) ? esc_attr( $options['event-start'] ) : ''
	);
}

function tecc_text_field_6_render() {
	$options = get_option( 'tecc_settings' );
	printf(
		'<input type="text" name="tecc_settings[event-end]" value="%s" />',
		isset( $options['event-end'] ) ? esc_attr( $options['event-end'] ) : ''
	);
}

function tecc_select_field_8_render() {

	$options   = get_option( 'tecc_settings' );
	$autostart = isset( $options['autostart-next-countdown'] ) ? sanitize_key( wp_unslash( $options['autostart-next-countdown'] ) ) : 'no';
	?>
	<select name='tecc_settings[autostart-next-countdown]'>
		<option value="no" <?php selected( $autostart, 'no' ); ?>>No</option>
		<option value="yes"  <?php selected( $autostart, 'yes' ); ?>>Yes</option>
	</select>
	<?php

}

function tecc_select_field_11_render() {

	$options   = get_option( 'tecc_settings' );
	$autostart = isset( $options['autostart-future-countdown'] ) ? sanitize_key( wp_unslash( $options['autostart-future-countdown'] ) ) : 'no';
	?>
	<select name='tecc_settings[autostart-future-countdown]'>
		<option value="no" <?php selected( $autostart, 'no' ); ?>>No</option>
		<option value="yes"  <?php selected( $autostart, 'yes' ); ?>>Yes</option>
	</select>
	<?php

}

function autostart() {
	$options   = get_option( 'tecc_settings' );
	$autostart = isset( $options['autostart-next-countdown'] ) ? sanitize_key( wp_unslash( $options['autostart-next-countdown'] ) ) : 'no';
	return $autostart;
}

function tecc_select_field_7_render() {

	$options = get_option( 'tecc_settings' );
	$events  = tribe_get_events(
		array(
			'posts_per_page' => -1,
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_EventStartDate',
					'value'   => current_time( 'Y-m-d H:i:s' ),
					'compare' => '>',
					'type'    => 'DATETIME',
				),
			),
		)
	);

	$saved_event = isset( $options['event_id'] ) ? $options['event_id'] : '';
	if ( is_array( $events ) && array_filter( $events ) != null ) {
		?>
		<ul class="main">
			<li><input type="checkbox" id="select_all" /> Select/Deselect all</li>
			<ul>		
				<?php
				foreach ( $events as $event ) {
					$checked         = '';
					$selected_events = isset( $options['future-events-list'] ) ? array_map( 'absint', (array) $options['future-events-list'] ) : array();
					if ( is_array( $selected_events ) ) {
						if ( in_array( $event->ID, $selected_events ) ) {
							$checked = 'checked';
						}
					}
					?>
					<li>
						<input class="tecc-checkbox" type='checkbox' name='tecc_settings[future-events-list][]' value="<?php echo esc_attr( $event->ID ); ?>" <?php echo esc_attr( $checked ); ?> ><label><?php echo esc_html( $event->post_title ); ?></label>
					</li>
					<?php
				}
				?>
			</ul>
		</ul>	
		<?php
	} else {
		?>
		<option value="0"><?php esc_html_e( 'No Future Event found.', 'countdown-for-the-events-calendar' ); ?></option>
		<?php
	}
	?>
	 
	<?php
}

function tecc_text_field_9_render() {
	$options = get_option( 'tecc_settings' );
	printf(
		'<input type="text" name="tecc_settings[main-title]" value="%s" />',
		isset( $options['main-title'] ) ? esc_attr( $options['main-title'] ) : ''
	);
}

function tecc_text_field_10_render() {
	$options = get_option( 'tecc_settings' );
	printf(
		'<input type="text" name="tecc_settings[autostart-text]" value="%s" />',
		isset( $options['autostart-text'] ) ? esc_attr( $options['autostart-text'] ) : ''
	);
}

function tecc_checkbox_setting(){
	register_setting( 'settingPage', 'tecc_settings', array( 'sanitize_callback' => 'tecc_sanitize_settings' ) );
	add_settings_section(
		'tecc_settingPage_section',
		'',
		'',
		'settingPage'
	);
	$option = get_option( 'cpfm_opt_in_choice_cool_events' );
	if( $option == 'yes' || $option == 'no' ) {
		add_settings_field(
			'tecc-cpfm-data-sharing',
			__( 'Usage Data Sharing', 'countdown-for-the-events-calendar' ),
			'tecc_select_field_13_render',
			'settingPage',
			'tecc_settingPage_section',
			array( 'class' => 'tecc-data-sharing' )
		);
	}
}
function tecc_select_field_13_render() {

	$option = get_option( 'cpfm_opt_in_choice_cool_events' );
	add_option( 'tecc-cpfm-data-sharing', $option );
	$options = get_option( 'tecc-cpfm-data-sharing' );
	
	// Sirf pehli baar 'cpfm_opt_in_choice_cool_events' ki value se set karo agar 'tecc-cpfm-data-sharing' set nahi hai
	if ( $options === false && ( $option === 'yes' || $option === 'no' ) ) {
		add_option( 'tecc-cpfm-data-sharing', $option );
		$final_value = $option;
	} else {
		$final_value = $options;
	}

	// Checkbox check logic
	$checked = $final_value === 'yes' ? 'checked' : '';
	?>
	<input type="checkbox" id="tecc-cpfm-data-sharing" <?php echo esc_attr( $checked); ?>>
		Help us make this plugin more compatible with your site by sharing non-sensitive site data. <a href="#" class="cpfm-see-terms tecc-see-terms">[See terms]</a>
		<div id="termsBox" class="tecc-terms-box" style="display: none; padding-left: 20px; margin-top: 10px; font-size: 12px; color: #999;">
			<p><?php esc_html_e("Opt in to receive email updates about security improvements, new features, helpful tutorials, and occasional special offers. We'll collect:", 'countdown-for-the-events-calendar'); ?><a href='https://my.coolplugins.net/terms/usage-tracking/' target='_blank'> Click Here</a></p>
			<ul style="list-style-type: auto; padding-left: 20px;">
				<li><?php esc_html_e("Your website home URL and WordPress admin email.", 'countdown-for-the-events-calendar'); ?></li>
				<li><?php esc_html_e("To check plugin compatibility, we will collect the following: list of active plugins and themes, server type, MySQL version, WordPress version, memory limit, site language and database prefix.", 'countdown-for-the-events-calendar'); ?></li>
			</ul>
		</div>
	<?php
}


function cpfm_save_usage_data_sharing_callback() {
	if ( ! current_user_can( 'manage_options' ) ) { 
		wp_send_json_error( __( 'You do not have sufficient permissions to access this page.', 'countdown-for-the-events-calendar' ) ); 
	}
	check_ajax_referer('cpfm_nonce_action', 'nonce');

	$choice = isset($_POST['opt_in']) && sanitize_key(wp_unslash($_POST['opt_in'])) === 'yes' ? 'yes' : 'no';
	
	update_option('tecc-cpfm-data-sharing', $choice);

	if ($choice === 'yes') {
		TECC_cronjob::tecc_send_data();
	} else {
		if (wp_next_scheduled('tecc_extra_data_update')) {
			wp_clear_scheduled_hook('tecc_extra_data_update');
		}
	}

	wp_send_json_success('Saved');
}

function tecc_settings_section_callback() {
	echo '<h3>' . esc_html__( 'Countdown Settings', 'countdown-for-the-events-calendar' ) . '</h3>';
}


function tecc_options_page() {
	// check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( function_exists( 'tecc_display_header' ) ) {
		tecc_display_header();
	}

	// add error/update messages
	// check if the user have submitted the settings
	// WordPress will add the "settings-updated" $_GET parameter to the url
	if ( isset( $_GET['settings-updated'] ) && sanitize_key( $_GET['settings-updated'] ) ) {
		// add settings saved message with the class of "updated"
		add_settings_error( 'wporg_messages', 'wporg_message', __( 'Shortcode generated', 'countdown-for-the-events-calendar' ), 'updated' );
		// show error/update messages
		settings_errors( 'wporg_messages' );
	}
	?>

	 <div class="wrap tecc-from-wrapper">
		 <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>        
		<form action='options.php' method='post' class="tecc-form">
			<?php
			settings_fields( 'pluginPage' );
			do_settings_sections( 'pluginPage' );
			submit_button( 'Generate Shortcode' );
			settings_fields( 'settingPage' );
			do_settings_sections( 'settingPage' );
			?>
		</form>
		<div class="tecc-shortcode-wrapper">
		<?php
		if ( isset( $_GET['settings-updated'] ) && sanitize_key( $_GET['settings-updated'] ) ) {
			$options = get_option( 'tecc_settings' );
			$b       = 0;
			$k       = isset( $options['future-events-list'] ) && ! empty( $options['future-events-list'] ) ? $options['future-events-list'] : '';
			if ( $k ) {
				foreach ( $k as $eventID ) {
					if ( $b <= 0 ) {
						$k = $eventID;
					} else {
						$k .= ',' . $eventID;

					}
					$b++;
				}
			}
			if ( isset( $options['event_id'] ) && ! empty( $options['event_id'] ) && $options['event_id'] != 0 ) {
				$dynamic_attr    = '';
				$event_id        = isset( $options['event_id'] ) ? absint( wp_unslash( $options['event_id'] ) ) : 0;
				$backgroundcolor = isset( $options['backgroundcolor'] ) ? sanitize_hex_color( wp_unslash( $options['backgroundcolor'] ) ) : '';
				$font_color      = isset( $options['font-color'] ) ? sanitize_hex_color( wp_unslash( $options['font-color'] ) ) : '';
                $show_seconds    = isset( $options['show-seconds'] ) ? sanitize_key( wp_unslash( $options['show-seconds'] ) ) : '';
                $show_image      = isset( $options['show-image'] ) ? sanitize_key( wp_unslash( $options['show-image'] ) ) : '';
                $size            = isset( $options['size'] ) ? sanitize_key( wp_unslash( $options['size'] ) ) : '';
                $event_start     = isset( $options['event-start'] ) ? sanitize_text_field( wp_unslash( $options['event-start'] ) ) : '';
                $event_end       = isset( $options['event-end'] ) ? sanitize_text_field( wp_unslash( $options['event-end'] ) ) : '';
                $autostart_next_countdown = isset( $options['autostart-next-countdown'] ) ? sanitize_key( wp_unslash( $options['autostart-next-countdown'] ) ) : '';
                $autostart_text = isset( $options['autostart-text'] ) ? sanitize_text_field( wp_unslash( $options['autostart-text'] ) ) : '';
                $autostart_future_countdown = isset( $options['autostart-future-countdown'] ) ? sanitize_key( wp_unslash( $options['autostart-future-countdown'] ) ) : '';
                $future_events_list = isset( $k ) ? absint( $k ) : 0;
                $main_title      = isset( $options['main-title'] ) ? sanitize_text_field( wp_unslash( $options['main-title'] ) ) : '';

				$dynamic_attr = sprintf(
					'[events-calendar-countdown id="%s" backgroundcolor="%s" font-color="%s" show-seconds="%s" show-image="%s" size="%s" event-start="%s" event-end="%s" autostart-next-countdown="%s" autostart-text="%s" autostart-future-countdown="%s" future-events-list="%s" main-title="%s"]',
					esc_attr( $event_id ),
					esc_attr( $backgroundcolor ),
					esc_attr( $font_color ),
					esc_attr( $show_seconds ),
					esc_attr( $show_image ),
					esc_attr( $size ),
					esc_attr( $event_start ),
					esc_attr( $event_end ),
					esc_attr( $autostart_next_countdown ),
					esc_attr( $autostart_text ),
					esc_attr( $autostart_future_countdown ),
					esc_attr( $future_events_list ),
					esc_attr( $main_title )
				);

				echo '<h3>' . esc_html__( 'Shortcode Preview', 'countdown-for-the-events-calendar' ) . '</h3>';
				echo do_shortcode( $dynamic_attr );
				$prefix = '_tec_';
				echo '<h2>' . esc_html__( 'Countdown for the events calendar Shortcode :', 'countdown-for-the-events-calendar' ) . '</h2>';
				echo ' <p style="font-size:18px">Paste this shortcode anywhere in page where you want to display Event Countdown
	            </p>';
				echo '<code>' . esc_html( $dynamic_attr ) . '</code>';

			} else {
				echo '<h3 style="color:red">' . esc_html__( 'There is no upcoming event. Please add atleast one upcoming event to generate countdown.', 'countdown-for-the-events-calendar' ) . '</h3>';
			}
		}
		?>
		</div>
	</div>

	
	<?php
}

