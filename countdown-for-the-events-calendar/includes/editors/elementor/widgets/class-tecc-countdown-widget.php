<?php
/**
 * Elementor "Event Countdown" widget (free Elementor APIs only).
 *
 * The widget only builds settings; `Args::from_elementor()` normalizes them
 * and the ONE shared renderer emits the markup — identical output to the
 * shortcode, block, and every other editor (guardrail 4).
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Editors\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Widget_Base' ) && ! class_exists( 'CoolPlugins\Countdown\Editors\Elementor\Countdown_Widget' ) ) {

	/**
	 * Event Countdown widget for Elementor.
	 *
	 * @since 2.0.0
	 */
	class Countdown_Widget extends \Elementor\Widget_Base {

		/**
		 * Widget slug.
		 *
		 * @since 2.0.0
		 * @return string
		 */
		public function get_name() {
			return 'tecc-countdown';
		}

		/**
		 * Widget title shown in the Elementor panel.
		 *
		 * @since 2.0.0
		 * @return string
		 */
		public function get_title() {
			return __( 'Event Countdown', 'countdown-for-the-events-calendar' );
		}

		/**
		 * Panel icon (free eicon set).
		 *
		 * @since 2.0.0
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-countdown';
		}

		/**
		 * Panel category (registered by Elementor_Integration).
		 *
		 * @since 2.0.0
		 * @return string[]
		 */
		public function get_categories() {
			return array( 'tecc-events-addons' );
		}

		/**
		 * Search keywords in the widget panel.
		 *
		 * @since 2.0.0
		 * @return string[]
		 */
		public function get_keywords() {
			// Elementor matches the panel search against the title + these, so
			// cover the main term, its variations, and how people actually look
			// for it ("the events calendar", "tec", "tickets", "launch"...).
			return array(
				'event countdown',
				'countdown',
				'countdown timer',
				'event',
				'events',
				'the events calendar',
				'events calendar',
				'tec',
				'timer',
				'event timer',
				'clock',
				'date',
				'upcoming event',
				'next event',
				'tickets',
				'launch',
				'coming soon',
				'days hours minutes',
			);
		}

		/**
		 * Style handles the widget depends on (registered by Render\Assets).
		 *
		 * @since 2.0.0
		 * @return string[]
		 */
		public function get_style_depends() {
			return array( 'tecc-tokens', 'tecc-templates', 'countdown-css' );
		}

		/**
		 * Script handles the widget depends on (registered by Render\Assets).
		 *
		 * @since 2.0.0
		 * @return string[]
		 */
		public function get_script_depends() {
			return array( 'tecc-countdown' );
		}

		/**
		 * Register all panel controls.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		protected function register_controls() {
			$this->register_source_section();
			$this->register_layout_section();
			$this->register_text_section();
			$this->register_design_layout_section();
			$this->register_design_colors_section();
			$this->register_typography_section();
		}

		/**
		 * Content tab: "Event source" section.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private function register_source_section() {
			$this->start_controls_section(
				'tecc_section_source',
				array(
					'label' => __( 'Event source', 'countdown-for-the-events-calendar' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'tecc_source',
				array(
					'label'   => __( 'Event source', 'countdown-for-the-events-calendar' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'next-upcoming',
					'options' => array(
						'next-upcoming' => __( 'Next upcoming event', 'countdown-for-the-events-calendar' ),
						'selected'      => __( 'A specific event', 'countdown-for-the-events-calendar' ),
						'filtered'      => __( 'Filtered by taxonomy', 'countdown-for-the-events-calendar' ),
					),
				)
			);

			$this->add_control(
				'tecc_event_id',
				array(
					'label'       => __( 'Event ID(s)', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'placeholder' => 'e.g. 84, 92, 95',
					'description' => __( 'Comma-separated event IDs — find an ID in the event’s edit URL.', 'countdown-for-the-events-calendar' ),
					'condition'   => array(
						'tecc_source' => 'selected',
					),
				)
			);

			$this->add_control(
				'tecc_categories',
				array(
					'label'       => __( 'Event categories', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'placeholder' => 'category-slug-a, category-slug-b',
					'description' => __( 'Comma-separated category slugs.', 'countdown-for-the-events-calendar' ),
					'condition'   => array(
						'tecc_source' => 'filtered',
					),
				)
			);

			$this->add_control(
				'tecc_tags',
				array(
					'label'       => __( 'Event tags', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'placeholder' => 'tag-slug-a, tag-slug-b',
					'description' => __( 'Comma-separated tag slugs.', 'countdown-for-the-events-calendar' ),
					'condition'   => array(
						'tecc_source' => 'filtered',
					),
				)
			);

			$this->end_controls_section();
		}

		/**
		 * Content tab: "Layout" section.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private function register_layout_section() {
			$this->start_controls_section(
				'tecc_section_layout',
				array(
					'label' => __( 'Layout', 'countdown-for-the-events-calendar' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			// Starter presets: the same six Render\Presets layouts as the block +
			// panel, as a clickable card grid. Clicking SEEDS this widget's own
			// controls (tecc-elementor-presets.js) — nothing is applied at render
			// time, so every control below stays editable.
			// Hidden layout slot the preset seeds (card|banner) - mirrors the
			// block's `template` attribute so Promo Banner really renders as a
			// banner here too. No visible picker: presets are the only writer.
			$this->add_control(
				'tecc_template',
				array(
					'type'    => \Elementor\Controls_Manager::HIDDEN,
					'default' => 'card',
				)
			);

			$this->add_control(
				'tecc_preset_mode',
				array(
					'label'   => __( 'Preset colours', 'countdown-for-the-events-calendar' ),
					'type'    => \Elementor\Controls_Manager::CHOOSE,
					'default' => 'light',
					'toggle'  => false,
					'options' => array(
						'light' => array(
							'title' => __( 'Light', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-light-mode',
						),
						'dark'  => array(
							'title' => __( 'Dark', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-dark-mode',
						),
					),
				)
			);

			$this->add_control(
				'tecc_preset_grid',
				array(
					'label'     => __( 'Starter preset', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::RAW_HTML,
					'raw'       => $this->preset_grid_html(),
					'separator' => 'none',
				)
			);

			// Shown for EVERY state (including no preset chosen) so the behaviour
			// is never a surprise.
			$this->add_control(
				'tecc_preset_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Choosing a preset overwrites the style and layout options below.', 'countdown-for-the-events-calendar' ),
					'content_classes' => 'elementor-descriptor tecc-preset-note',
				)
			);

			$this->add_control(
				'tecc_preset_divider',
				array(
					'type' => \Elementor\Controls_Manager::DIVIDER,
				)
			);

			// No template/style/size pickers — the look is preset- and
			// control-driven; Args::from_elementor supplies the modern default.

			$this->add_control(
				'tecc_no_of_events',
				array(
					'label'       => __( 'Number of events (for carousel view)', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array( 'px' => array( 'min' => 1, 'max' => 5, 'step' => 1 ) ),
					'default'     => array( 'unit' => 'px', 'size' => 1 ),
					'description' => __( '2 or more shows a carousel of upcoming events.', 'countdown-for-the-events-calendar' ),
				)
			);

			$this->add_control(
				'tecc_show_ongoing',
				array(
					'label'     => __( 'Show ongoing events', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Show', 'countdown-for-the-events-calendar' ),
					'label_off' => __( 'Hide', 'countdown-for-the-events-calendar' ),
					'default'   => 'yes',
				)
			);

			$this->add_control(
				'tecc_show_seconds',
				array(
					'label'     => __( 'Show seconds', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Show', 'countdown-for-the-events-calendar' ),
					'label_off' => __( 'Hide', 'countdown-for-the-events-calendar' ),
					'default'   => 'yes',
				)
			);

			$this->add_control(
				'tecc_show_image',
				array(
					'label'     => __( 'Show event image', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Show', 'countdown-for-the-events-calendar' ),
					'label_off' => __( 'Hide', 'countdown-for-the-events-calendar' ),
					'default'   => '',
				)
			);

			$this->add_control(
				'tecc_show_venue',
				array(
					'label'     => __( 'Show venue', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Show', 'countdown-for-the-events-calendar' ),
					'label_off' => __( 'Hide', 'countdown-for-the-events-calendar' ),
					'default'   => 'yes',
				)
			);

			$this->add_control(
				'tecc_show_cost',
				array(
					'label'     => __( 'Show cost', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Show', 'countdown-for-the-events-calendar' ),
					'label_off' => __( 'Hide', 'countdown-for-the-events-calendar' ),
					'default'   => '',
				)
			);

			$this->add_control(
				'tecc_show_divider',
				array(
					'label'     => __( 'Show divider', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Show', 'countdown-for-the-events-calendar' ),
					'label_off' => __( 'Hide', 'countdown-for-the-events-calendar' ),
					'default'   => '',
				)
			);

			$this->add_control(
				'tecc_show_button',
				array(
					'label'     => __( 'Show event button', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Show', 'countdown-for-the-events-calendar' ),
					'label_off' => __( 'Hide', 'countdown-for-the-events-calendar' ),
					'default'   => 'yes',
				)
			);

			$this->end_controls_section();
		}

		/**
		 * Content tab: "Text" section.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private function register_text_section() {
			$this->start_controls_section(
				'tecc_section_text',
				array(
					'label'  => __( 'Text', 'countdown-for-the-events-calendar' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'tecc_main_title',
				array(
					'label'       => __( 'Heading', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'placeholder' => __( 'Next Upcoming Event', 'countdown-for-the-events-calendar' ),
				)
			);

			$this->add_control(
				'tecc_ongoing_title',
				array(
					'label'       => __( 'Ongoing title', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'default'     => '',
					'description' => __( 'Shown while the event is in progress.', 'countdown-for-the-events-calendar' ),
				)
			);

			$this->add_control(
				'tecc_ended_title',
				array(
					'label'       => __( 'Ended title', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'default'     => '',
				)
			);

			$this->add_control(
				'tecc_button_text',
				array(
					'label'       => __( 'Button text', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'default'     => '',
					'placeholder' => __( 'Find out more', 'countdown-for-the-events-calendar' ),
					'condition'   => array( 'tecc_show_button' => 'yes' ),
				)
			);

			$this->end_controls_section();
		}

		/**
		 * Style tab: "Layout" section (canonical design/layout controls).
		 *
		 * Control id = the canonical Args::EDITOR_CONTROLS key prefixed with
		 * `tecc_`, so the block and the widget stay byte-identical.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private function register_design_layout_section() {
			$this->start_controls_section(
				'tecc_section_design_layout',
				array(
					'label' => __( 'Layout', 'countdown-for-the-events-calendar' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_control(
				'tecc_countdown_style',
				array(
					'label'   => __( 'Countdown style', 'countdown-for-the-events-calendar' ),
					'type'    => \Elementor\Controls_Manager::CHOOSE,
					'default' => 'box',
					'toggle'  => false,
					'options' => array(
						'box'    => array(
							'title' => __( 'Boxes', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-checkbox',
						),
						'ring'   => array(
							'title' => __( 'Rings', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-circle-o',
						),
						'inline' => array(
							'title' => __( 'Inline', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-ellipsis-h',
						),
					),
				)
			);

			// Image controls are meaningless with the image hidden — gate them on
			// the Content-tab switcher (Elementor conditions are model-based, so
			// they work across tabs).
			$this->add_control(
				'tecc_image_position',
				array(
					'label'     => __( 'Image position', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'default'   => 'top',
					'toggle'    => false,
					'options'   => array(
						'top'   => array(
							'title' => __( 'Top', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-v-align-top',
						),
						'left'  => array(
							'title' => __( 'Left', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-h-align-left',
						),
						'right' => array(
							'title' => __( 'Right', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'condition' => array( 'tecc_show_image' => 'yes' ),
				)
			);

			$this->add_control(
				'tecc_content_align',
				array(
					'label'   => __( 'Content alignment', 'countdown-for-the-events-calendar' ),
					'type'    => \Elementor\Controls_Manager::CHOOSE,
					'default' => 'left',
					'toggle'  => false,
					'options' => array(
						'left'   => array(
							'title' => __( 'Left', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center' => array(
							'title' => __( 'Center', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-text-align-center',
						),
						'right'  => array(
							'title' => __( 'Right', 'countdown-for-the-events-calendar' ),
							'icon'  => 'eicon-text-align-right',
						),
					),
					// Full re-render (do not use render_type => 'ui'). Alignment is
					// driven by root classes .tecc-cd--align-l|c|r. CSS-only selectors
					// on .tecc-cd__body miss that — editor looks wrong, frontend OK.
				)
			);

			$this->add_control(
				'tecc_image_gap',
				array(
					'label'     => __( 'Inset image', 'countdown-for-the-events-calendar' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Show', 'countdown-for-the-events-calendar' ),
					'label_off' => __( 'Hide', 'countdown-for-the-events-calendar' ),
					'default'   => 'yes',
					'condition' => array( 'tecc_show_image' => 'yes' ),
				)
			);

			// Width as a px/% slider (default 100% = fill the container it's
			// dropped into). Live, so dragging it resizes without a re-render.
			$this->add_control(
				'tecc_width',
				array(
					'label'       => __( 'Width', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( '%', 'px' ),
					'range'       => array(
						'%'  => array( 'min' => 10, 'max' => 100, 'step' => 1 ),
						'px' => array( 'min' => 120, 'max' => 1200, 'step' => 10 ),
					),
					'default'     => array(
						'unit' => '%',
						'size' => 100,
					),
					'render_type' => 'ui',
					'selectors'   => array(
						'{{WRAPPER}} .tecc-cd' => 'max-inline-size: {{SIZE}}{{UNIT}} !important;',
					),
				)
			);

			$this->add_control(
				'tecc_radius',
				array(
					'label'       => __( 'Radius', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array( 'px' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
					'default'     => array( 'unit' => 'px', 'size' => 10 ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-radius: {{SIZE}}{{UNIT}} !important;' ),
				)
			);

			$this->add_control(
				'tecc_padding',
				array(
					'label'       => __( 'Padding', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array( 'px' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
					'default'     => array( 'unit' => 'px', 'size' => 15 ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-pad: {{SIZE}}{{UNIT}} !important;' ),
				)
			);

			// Border + shadow are live too: the renderer adds .tecc-cd--bordered /
			// --shadow server-side, but the same paint can be injected as plain CSS
			// here, so dragging/toggling updates instantly (a 0px border is simply
			// invisible, and the shadow rule only exists while the switch is on).
			$this->add_control(
				'tecc_border',
				array(
					'label'       => __( 'Border', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array( 'px' => array( 'min' => 0, 'max' => 20, 'step' => 1 ) ),
					'default'     => array( 'unit' => 'px', 'size' => 0 ),
					'render_type' => 'ui',
					'selectors'   => array(
						'{{WRAPPER}} .tecc-cd' => 'border-style: solid !important; border-color: var(--tecc-hairline) !important; border-width: {{SIZE}}{{UNIT}} !important;',
					),
				)
			);

			$this->add_control(
				'tecc_shadow',
				array(
					'label'       => __( 'Shadow', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'label_on'    => __( 'Show', 'countdown-for-the-events-calendar' ),
					'label_off'   => __( 'Hide', 'countdown-for-the-events-calendar' ),
					'default'     => '',
					'render_type' => 'ui',
					'selectors'   => array(
						'{{WRAPPER}} .tecc-cd' => 'box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12) !important;',
					),
				)
			);

			$this->end_controls_section();
		}

		/**
		 * Style tab: "Colors" section (canonical design colors).
		 *
		 * Every control defaults to an EMPTY string on purpose: an empty color
		 * means "not set", so the chosen style preset still applies.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private function register_design_colors_section() {
			$this->start_controls_section(
				'tecc_section_design_colors',
				array(
					'label' => __( 'Colors', 'countdown-for-the-events-calendar' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);

			// Each colour drives its --tecc-* token live via selectors (render_type
			// 'ui' = CSS only, no re-render); !important beats the renderer's inline
			// var. Empty colours emit no selector, so the preset still applies.
			$this->add_control(
				'tecc_main_color',
				array(
					'label'       => __( 'Main color', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'default'     => '',
					'alpha'       => false,
					'global'      => array( 'active' => false ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-main: {{VALUE}} !important;' ),
				)
			);

			$this->add_control(
				'tecc_alt_color',
				array(
					'label'       => __( 'Alternate color (on main)', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'default'     => '',
					'alpha'       => false,
					'global'      => array( 'active' => false ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-alt: {{VALUE}} !important;' ),
				)
			);

			$this->add_control(
				'tecc_title_color',
				array(
					'label'       => __( 'Title color', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'default'     => '',
					'alpha'       => false,
					'global'      => array( 'active' => false ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-title-color: {{VALUE}} !important;' ),
				)
			);

			$this->add_control(
				'tecc_text_color',
				array(
					'label'       => __( 'Text color', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'default'     => '',
					'alpha'       => false,
					'global'      => array( 'active' => false ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-text-color: {{VALUE}} !important;' ),
				)
			);

			$this->add_control(
				'tecc_card_bg',
				array(
					'label'       => __( 'Card background', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'default'     => '',
					'alpha'       => false,
					'global'      => array( 'active' => false ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-card-bg: {{VALUE}} !important;' ),
				)
			);

			$this->end_controls_section();
		}

		/**
		 * Style tab: "Typography" section.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		private function register_typography_section() {
			$this->start_controls_section(
				'tecc_section_typography',
				array(
					'label' => __( 'Typography', 'countdown-for-the-events-calendar' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_control(
				'tecc_countdown_size',
				array(
					'label'       => __( 'Countdown size', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array( 'px' => array( 'min' => 10, 'max' => 120, 'step' => 1 ) ),
					'default'     => array( 'unit' => 'px', 'size' => 28 ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-cd-size: {{SIZE}}{{UNIT}} !important;' ),
				)
			);

			$this->add_control(
				'tecc_title_size',
				array(
					'label'       => __( 'Title size', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array( 'px' => array( 'min' => 8, 'max' => 80, 'step' => 1 ) ),
					'default'     => array( 'unit' => 'px', 'size' => 20 ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-title-size: {{SIZE}}{{UNIT}} !important;' ),
				)
			);

			$this->add_control(
				'tecc_body_size',
				array(
					'label'       => __( 'Body size', 'countdown-for-the-events-calendar' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array( 'px' => array( 'min' => 8, 'max' => 48, 'step' => 1 ) ),
					'default'     => array( 'unit' => 'px', 'size' => 14 ),
					'render_type' => 'ui',
					'selectors'   => array( '{{WRAPPER}} .tecc-cd' => '--tecc-body-size: {{SIZE}}{{UNIT}} !important;' ),
				)
			);

			$this->end_controls_section();
		}

		/**
		 * Build the clickable preset card grid (RAW_HTML control body).
		 *
		 * Each card carries data-tecc-preset="<id>" and a small colour preview;
		 * tecc-elementor-presets.js turns a click into a seed of this widget's
		 * own controls. Escaped here because RAW_HTML is printed as-is.
		 *
		 * @since 2.1.0
		 * @return string
		 */
		private function preset_grid_html() {
			if ( ! class_exists( 'CoolPlugins\Countdown\Render\Presets' ) ) {
				return '';
			}

			$html = '<div class="tecc-preset-grid" data-tecc-preset-grid>';

			// Same shape as the settings panel's preset tiles: title + one-line
			// description, no preview skeleton.
			foreach ( \CoolPlugins\Countdown\Render\Presets::layouts() as $id => $def ) {
				$label   = isset( $def['label'] ) ? $def['label'] : $id;
				$tagline = isset( $def['tagline'] ) ? $def['tagline'] : '';
				$hint    = isset( $def['hint'] ) ? $def['hint'] : $tagline;

				$html .= '<button type="button" class="tecc-preset-card" data-tecc-preset="' . esc_attr( $id ) . '"'
					. ' title="' . esc_attr( $hint ) . '">'
					. '<span class="tecc-preset-card__label">' . esc_html( $label ) . '</span>';

				if ( '' !== $tagline ) {
					$html .= '<span class="tecc-preset-card__desc">' . esc_html( $tagline ) . '</span>';
				}

				$html .= '</button>';
			}

			$html .= '</div>';

			return $html;
		}

		/**
		 * Server-side render through the ONE shared renderer (guardrail 4).
		 *
		 * @since 2.0.0
		 * @return void
		 */
		protected function render() {
			if ( ! class_exists( 'CoolPlugins\Countdown\Render\Renderer' )
				|| ! is_callable( array( 'CoolPlugins\Countdown\Render\Args', 'from_elementor' ) ) ) {
				return;
			}

			echo \CoolPlugins\Countdown\Render\Renderer::render( \CoolPlugins\Countdown\Render\Args::from_elementor( $this->get_settings_for_display() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fully escaped by the renderer.
		}

		/**
		 * No JS content template: the widget is server-rendered, so the
		 * editor preview falls back to a live server render.
		 *
		 * @since 2.0.0
		 * @return void
		 */
		protected function content_template() {
			// Intentionally empty — server-rendered widget.
		}
	}
}
