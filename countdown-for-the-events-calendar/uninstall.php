<?php
/**
 * Uninstall clean-up.
 *
 * Deletes ONLY tecc_-prefixed data. NEVER touches the shared
 * `cpfm_*_cool_events` options (sibling Events Addons share them) or any
 * user-created content. Feedback data already sent to
 * feedback.coolplugins.net is retained per the service's policy; nothing
 * further is transmitted on uninstall.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clear every tecc_ cron first.
wp_clear_scheduled_hook( 'tecc_extra_data_update' );

$tecc_options = array(
	// Settings + v2 additive keys.
	'tecc_settings',
	'tecc_display_rules',
	'tecc_engine_mode',
	'tecc_schema_version',
	'tecc_v2_welcome',
	// Bookkeeping (note: `tecc-installDate` and `tecc-install-date` are two
	// DISTINCT legacy keys — review notice vs feedback site_id hash).
	'tecc-v',
	'tecc-type',
	'tecc-installDate',
	'tecc-install-date',
	'tecc-ratingDiv',
	'tecc-cpfm-data-sharing',
	'tecc_initial_save_version',
);

foreach ( $tecc_options as $tecc_option ) {
	delete_option( $tecc_option );
}

unset( $tecc_options, $tecc_option );
