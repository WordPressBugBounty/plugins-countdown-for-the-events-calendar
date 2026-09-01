<?php
/*
Plugin Name:Event Countdown for The Events Calendar
Plugin URI:https://eventscalendaraddons.com/
Description:Display upcoming events with countdown timer using a shortcode, Elementor widget, Gutenberg block or sitewide floating header/footer bar, built for The Events Calendar.
Version:2.0.1
License:GPLv2 or later
Author:Cool Plugins
Author URI:https://coolplugins.net/?utm_source=tecc_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=plugins_list
License URI:https://www.gnu.org/licenses/gpl-2.0.html
Requires at least:6.3
Requires PHP:7.2
Domain Path: /languages
Text Domain: countdown-for-the-events-calendar
Requires Plugins: the-events-calendar
*/

if (!defined('ABSPATH')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

if (!defined('TECC_VERSION_CURRENT')) {
	define('TECC_VERSION_CURRENT', '2.0.1');
}
if (!defined('TECC_PLUGIN_FILE')) {
	define('TECC_PLUGIN_FILE', __FILE__);
}
if (!defined('TECC_PLUGIN_URL')) {
	define('TECC_PLUGIN_URL', plugin_dir_url(TECC_PLUGIN_FILE));
}
if (!defined('TECC_PLUGIN_DIR')) {
	define('TECC_PLUGIN_DIR', plugin_dir_path(TECC_PLUGIN_FILE));
}
if (!defined('TECC_JS_DIR')) {
	define('TECC_JS_DIR', TECC_PLUGIN_URL . 'assets/js');
}
if (!defined('TECC_CSS_URL')) {
	define('TECC_CSS_URL', TECC_PLUGIN_URL . 'assets/css');
}
if (!defined('TECC_FEEDBACK_API')) {
	define('TECC_FEEDBACK_API', 'https://feedback.coolplugins.net/');
}
if (!defined('TECC_SUPPORT_URL')) {
	define('TECC_SUPPORT_URL', 'https://eventscalendaraddons.com/support/?utm_source=tecc_plugin&utm_medium=inside&utm_campaign=support&utm_content=header');
}
if (!defined('TECC_DOCS_URL')) {
	// Placeholder until the per-plugin docs page ships (later: .../docs/event-countdown-for-the-events-calendar/).
	define('TECC_DOCS_URL', 'https://eventscalendaraddons.com/docs/?utm_source=tecc_plugin&utm_medium=inside&utm_campaign=docs&utm_content=header');
}
if (!defined('TECC_MIN_TEC_VERSION')) {
	define('TECC_MIN_TEC_VERSION', '6.0.0');
}

// Guarded bootstrap: nothing runs before plugins_loaded; activation hooks must
// register at file scope (the activation request includes this file after
// plugins_loaded fired).
if (!class_exists('CoolPlugins\Countdown\Autoloader')) {
	require_once TECC_PLUGIN_DIR . 'includes/class-tecc-autoloader.php';
}

if (class_exists('CoolPlugins\Countdown\Autoloader')) {
	CoolPlugins\Countdown\Autoloader::register();

	register_activation_hook(__FILE__, array('CoolPlugins\Countdown\Plugin', 'activate'));
	register_deactivation_hook(__FILE__, array('CoolPlugins\Countdown\Plugin', 'deactivate'));

	add_action('plugins_loaded', array('CoolPlugins\Countdown\Plugin', 'instance'), 5);
}
