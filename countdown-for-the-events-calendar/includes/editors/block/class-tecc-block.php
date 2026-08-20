<?php
/**
 * Gutenberg block registration: tecc/countdown (plan Phase 3).
 *
 * DYNAMIC block: the editor bundle (src/ -> build/ via @wordpress/scripts,
 * §0.1) only provides controls + ServerSideRender; the front end renders
 * through render_callback -> Args::from_block() -> the shared Renderer, so
 * block output is identical to the equivalent shortcode by construction.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Editors\Block;

use CoolPlugins\Countdown\Render\Args;
use CoolPlugins\Countdown\Render\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Editors\Block\Block' ) ) {

	/**
	 * Registers the block from the compiled build/ metadata.
	 *
	 * @since 2.0.0
	 */
	final class Block {

		/**
		 * Hook registration. Called from the Plugin bootstrap.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function init() {
			add_action( 'init', array( __CLASS__, 'register' ), 10 );
			// The inspector sidebar lives in the OUTER editor document.
			add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_inspector_assets' ) );
			// Block previews render INSIDE the editor iframe (WP 6.3+), which only
			// enqueue_block_assets reaches - enqueue_block_editor_assets lands in
			// the outer document and never styles the preview.
			add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_preview_assets' ) );
		}

		/**
		 * Outer editor document: the inspector's RangeControl touch-action fix.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function enqueue_inspector_assets() {
			wp_enqueue_style( 'countdown-css' );
		}

		/**
		 * Render styles for the block preview inside the editor iframe.
		 *
		 * enqueue_block_assets fires for the front end too, so the front end is
		 * excluded here: it gets exactly what it needs from Assets::enqueue() at
		 * render time. Declaring these in block.json instead would load them on
		 * EVERY front-end page under a classic theme, block present or not.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function enqueue_preview_assets() {
			if ( ! is_admin() ) {
				return;
			}

			wp_enqueue_style( 'tecc-tokens' );
			wp_enqueue_style( 'tecc-templates' );
			wp_enqueue_style( 'countdown-css' );
		}

		/**
		 * Register the block type from build/block.json.
		 *
		 * Guarded: a missing build/ directory (dev checkout without a build)
		 * or an old WP must never fatal — the block is simply absent.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		public static function register() {
			if ( ! function_exists( 'register_block_type' ) ) {
				return;
			}

			$metadata = TECC_PLUGIN_DIR . 'build/block.json';
			if ( ! file_exists( $metadata ) ) {
				return;
			}

			register_block_type(
				$metadata,
				array(
					'render_callback' => array( __CLASS__, 'render' ),
				)
			);

			// JS i18n for the editor bundle (handle generated from block name).
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'tecc-countdown-editor-script', 'countdown-for-the-events-calendar', TECC_PLUGIN_DIR . 'languages' );
			}

			// The ONE canonical starter-preset catalogue (6 layouts x Light/Dark),
			// shared with the settings panel + Elementor. The editor maps its
			// tecc_settings keys onto block attributes (see edit.js applyPreset).
			if ( class_exists( 'CoolPlugins\Countdown\Render\Presets' ) ) {
				wp_localize_script(
					'tecc-countdown-editor-script',
					'teccPresets',
					\CoolPlugins\Countdown\Render\Presets::for_js()
				);
			}
		}

		/**
		 * Dynamic render callback -> the ONE shared renderer.
		 *
		 * @since 2.0.0
		 * @param array $attributes Block attributes (block.json schema).
		 * @return string
		 */
		public static function render( $attributes ) {
			return Renderer::render( Args::from_block( is_array( $attributes ) ? $attributes : array() ) );
		}
	}
}
