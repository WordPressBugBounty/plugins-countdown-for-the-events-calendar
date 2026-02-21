<?php
if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('TECC_cronjob')) {
    class TECC_cronjob
    {

        public function __construct() {
          // Register cron jobs
            add_filter('cron_schedules', array($this, 'tecc_cron_schedules'));
            add_action('tecc_extra_data_update', array($this, 'tecc_cron_extra_data_autoupdater'));
        }
        
        function tecc_cron_extra_data_autoupdater() {
                if (class_exists('TECC_cronjob')) {
                    TECC_cronjob::tecc_send_data();
                }
        }
           
       static public function tecc_send_data() {
                   
            $feedback_url = TECC_FEEDBACK_API . 'wp-json/coolplugins-feedback/v1/site';
            require_once TECC_PLUGIN_DIR . 'admin/feedback/admin-feedback-form.php';

            if (!defined('TECC_PLUGIN_DIR')  || !class_exists('\TECC\feedback\tecc_feedback') ) {
                return;
            }
            
            $extra_data         = new \TECC\feedback\tecc_feedback();
            $extra_data_details = $extra_data->cpfm_get_user_info();
            
            $server_info    = $extra_data_details['server_info'];
            $extra_details  = $extra_data_details['extra_details'];
            $site_url       = get_site_url();
            $install_date   = get_option('tecc-install-date');
            $uni_id         = '27';
            $site_id        = $site_url . '-' . $install_date . '-' . $uni_id;
            $initial_version = get_option('tecc_initial_save_version');
            $initial_version = is_string($initial_version) ? sanitize_text_field($initial_version) : 'N/A';
            $plugin_version = defined('TECC_VERSION_CURRENT') ? TECC_VERSION_CURRENT : 'N/A';
            $admin_email    = sanitize_email(get_option('admin_email') ?: 'N/A');
            
            $post_data = array(

                'site_id'           => md5($site_id),
                'plugin_version'    => $plugin_version,
                'plugin_name'       => 'The Events Calendar Countdown Addon',
                'plugin_initial'    => $initial_version,
                'email'             => $admin_email,
                'site_url'          => esc_url_raw($site_url),
                'server_info'       => $server_info,
                'extra_details'     => $extra_details,
            );
            
            $response = wp_remote_post($feedback_url, array(

                'method'    => 'POST',
                'timeout'   => 30,
                'headers'   => array(
                    'Content-Type' => 'application/json',
                ),
                'body'      => wp_json_encode($post_data),
            ));
            
            if (is_wp_error($response)) {
                return;
            }
            
            $response_body  = wp_remote_retrieve_body($response);
            $decoded        = json_decode($response_body, true);
            
            if (!wp_next_scheduled('tecc_extra_data_update')) {

                wp_schedule_event(time(), 'every_30_days', 'tecc_extra_data_update');
            }
        }
          
        /**
         * Cron status schedule(s).
         */
        public function tecc_cron_schedules($schedules)
        {
            // 30days schedule for update information
            if (!isset($schedules['every_30_days'])) {

                $schedules['every_30_days'] = array(
                    'interval' => 30 * 24 * 60 * 60, // 2,592,000 seconds
                    'display'  => __('Once every 30 days', 'countdown-for-the-events-calendar'),
                );
            }

            return $schedules;
        }

    }

    $cron_init = new TECC_cronjob();//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
}
