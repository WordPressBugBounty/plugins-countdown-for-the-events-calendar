<?php
/**
 * Settings panel view — Events Addons → Event Countdown.
 *
 * Editor-style shell: shared Events Addons header + hero, a full-width 2-tab
 * nav (Shortcode Generator / Sitewide Display) and a two-column editor
 * (left settings card, right shortcode + live preview). All settings stay
 * inside ONE options.php form with a single nonce — the merging sanitizer and
 * every input name/class are preserved.
 *
 * @var array  $tecc_settings             Current `tecc_settings` option (legacy + v2 keys).
 * @var array  $tecc_events               Upcoming events: array of [ 'id' => int, 'title' => string ].
 * @var bool   $tecc_data_sharing_visible Whether the usage-data-sharing block renders.
 * @var string $tecc_data_sharing         'yes' or 'no'.
 * @var array  $tecc_rules                Stored display rules (for the sitewide badge + builder).
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tecc_settings = ( isset( $tecc_settings ) && is_array( $tecc_settings ) ) ? $tecc_settings : array();
$tecc_rules    = ( isset( $tecc_rules ) && is_array( $tecc_rules ) ) ? $tecc_rules : array();

// --- Read helpers (prefill a control from stored settings with a default). ---
$tecc_val = function ( $key, $default ) use ( $tecc_settings ) {
	return ( isset( $tecc_settings[ $key ] ) && '' !== $tecc_settings[ $key ] ) ? (string) $tecc_settings[ $key ] : $default;
};
$tecc_enum = function ( $key, $allowed, $default ) use ( $tecc_settings ) {
	return ( isset( $tecc_settings[ $key ] ) && in_array( $tecc_settings[ $key ], $allowed, true ) ) ? $tecc_settings[ $key ] : $default;
};

/**
 * Render a yes/no toggle SWITCH that preserves the legacy 'yes'/'no' string
 * contract without JavaScript: a hidden 'no' input is overridden by the
 * checkbox's 'yes' only when checked (same name, later value wins on POST).
 * The checkbox change bubbles to the form so the live preview refreshes.
 *
 * @param string $key     tecc_settings key.
 * @param bool   $checked Whether it is currently 'yes'.
 * @param string $label   Visible label.
 * @param string $desc    Optional helper description.
 * @return void
 */
$tecc_switch = function ( $key, $checked, $label, $desc = '' ) {
	$id = 'tecc-sw-' . sanitize_key( $key );
	?>
	<div class="tecc-switch-field">
		<label class="tecc-switch" for="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="tecc_settings[<?php echo esc_attr( $key ); ?>]" value="no" />
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" class="tecc-switch__input" name="tecc_settings[<?php echo esc_attr( $key ); ?>]" value="yes" <?php checked( (bool) $checked ); ?> />
			<span class="tecc-switch__track" aria-hidden="true"><span class="tecc-switch__thumb"></span></span>
			<span class="tecc-switch__text"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php if ( '' !== $desc ) : ?>
			<p class="description tecc-switch__desc"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>
	</div>
	<?php
};

/**
 * Emit a range slider + live <output> readout, for use INSIDE an existing
 * .tecc-design-field label wrapper. Reuses initRanges() in the settings JS.
 *
 * @param string $key   tecc_settings key.
 * @param string $value Current value.
 * @param int    $min   Slider minimum.
 * @param int    $max   Slider maximum.
 * @return void
 */
$tecc_range = function ( $key, $value, $min, $max ) {
	$out = 'tecc-rng-' . sanitize_key( $key ) . '-out';
	?>
	<span class="tecc-range-field">
		<input type="range" class="tecc-range" name="tecc_settings[<?php echo esc_attr( $key ); ?>]" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="1" value="<?php echo esc_attr( $value ); ?>" data-tecc-range="<?php echo esc_attr( $out ); ?>" />
		<output id="<?php echo esc_attr( $out ); ?>" class="tecc-range__value"><?php echo esc_html( $value ); ?></output>
	</span>
	<?php
};

// --- Categories (the controller may not pass $tecc_categories — guard it). ---
$tecc_categories = ( isset( $tecc_categories ) && is_array( $tecc_categories ) ) ? $tecc_categories : array();
$tecc_cats_csv   = isset( $tecc_settings['categories'] ) ? (string) $tecc_settings['categories'] : '';

// --- Source values (derive the UI-only radio state from the stored keys). ---
$tecc_next     = ( isset( $tecc_settings['autostart-next-countdown'] ) && 'yes' === $tecc_settings['autostart-next-countdown'] ) ? 'yes' : 'no';
$tecc_future   = ( isset( $tecc_settings['autostart-future-countdown'] ) && 'yes' === $tecc_settings['autostart-future-countdown'] ) ? 'yes' : 'no';
$tecc_list_ids = ( isset( $tecc_settings['future-events-list'] ) && is_array( $tecc_settings['future-events-list'] ) ) ? array_map( 'absint', $tecc_settings['future-events-list'] ) : array();
$tecc_event_id = isset( $tecc_settings['event_id'] ) ? absint( $tecc_settings['event_id'] ) : 0;

// The chosen source is stored explicitly (source-ui) so the radio round-trips
// exactly — several source fields can be populated at once (a saved category +
// leftover event chips), and a derived guess used to flip to "Specific events".
$tecc_stored_source = isset( $tecc_settings['source-ui'] ) ? (string) $tecc_settings['source-ui'] : '';
if ( in_array( $tecc_stored_source, array( 'upcoming', 'events', 'category' ), true ) ) {
	$tecc_source = $tecc_stored_source;
} elseif ( 'yes' === $tecc_next && ! empty( $tecc_list_ids ) ) {
	// Legacy fallback (options saved before source-ui existed).
	$tecc_source = 'events';
} elseif ( '' !== trim( $tecc_cats_csv ) ) {
	$tecc_source = 'category';
} elseif ( $tecc_event_id > 0 ) {
	$tecc_source = 'events';
} else {
	// Unconfigured -> 'upcoming' so first open shows a working countdown —
	// matches the renderer's own unrecognised-source fallback.
	$tecc_source = 'upcoming';
}

// --- Style / template values. ---
// The picker offers card|banner, but a stored legacy template (classic/minimal,
// still valid via shortcode) must stay selectable — a radio always submits, so
// omitting it would silently rewrite the saved value on the next save.
$tecc_stored_tpl   = isset( $tecc_settings['template'] ) ? (string) $tecc_settings['template'] : '';
$tecc_legacy_tpl   = in_array( $tecc_stored_tpl, array( 'classic', 'minimal' ), true ) ? $tecc_stored_tpl : '';
$tecc_tpl_choices  = $tecc_legacy_tpl ? array( 'card', 'banner', $tecc_legacy_tpl ) : array( 'card', 'banner' );
$tecc_template     = $tecc_enum( 'template', $tecc_tpl_choices, 'card' );
$tecc_cd_style  = $tecc_enum( 'countdown-style', array( 'box', 'ring', 'inline' ), 'box' );
$tecc_no_events = max( 1, min( 5, isset( $tecc_settings['no-of-events'] ) ? absint( $tecc_settings['no-of-events'] ) : 1 ) );

// --- Sitewide status badge (active when >= 1 enabled rule). ---
$tecc_sitewide_active = false;
foreach ( $tecc_rules as $tecc_rule_row ) {
	if ( is_array( $tecc_rule_row ) && ! empty( $tecc_rule_row['enabled'] ) ) {
		$tecc_sitewide_active = true;
		break;
	}
}

// --- Field toggles (yes/no selects) for the Content sub-panel. ---
$tecc_toggles = array(
	'show-image'   => array(
		'label'   => __( 'Show event image', 'countdown-for-the-events-calendar' ),
		'default' => 'no',
	),
	'show-venue'   => array(
		'label'   => __( 'Show venue', 'countdown-for-the-events-calendar' ),
		'default' => 'yes',
	),
	'show-cost'    => array(
		'label'   => __( 'Show cost', 'countdown-for-the-events-calendar' ),
		'default' => 'no',
	),
	'show-button'  => array(
		'label'   => __( 'Show event button', 'countdown-for-the-events-calendar' ),
		'default' => 'yes',
	),
	'show-seconds' => array(
		'label'   => __( 'Show seconds', 'countdown-for-the-events-calendar' ),
		'default' => 'yes',
	),
	'show-divider' => array(
		'label'   => __( 'Show divider', 'countdown-for-the-events-calendar' ),
		'default' => 'no',
	),
	'show-timer-label' => array(
		'label'   => __( 'Show “Begins in” label', 'countdown-for-the-events-calendar' ),
		'default' => 'yes',
	),
);

// --- Countdown-style pills. ---
$tecc_cd_styles = array(
	'box'    => __( 'Boxes', 'countdown-for-the-events-calendar' ),
	'ring'   => __( 'Rings', 'countdown-for-the-events-calendar' ),
	'inline' => __( 'Inline', 'countdown-for-the-events-calendar' ),
);

// --- Preset gallery (the ONE canonical catalogue; the design JS applies the
// selected tile's Light/Dark bundle to the controls). ---
$tecc_preset_layouts = class_exists( 'CoolPlugins\Countdown\Render\Presets' )
	? \CoolPlugins\Countdown\Render\Presets::layouts()
	: array();

/**
 * Print a "browser frame" live preview: a top bar (traffic-light dots, width
 * controls, an animated status pill) over a fixed-height, grid-backed viewport
 * whose stage the JS scales/scrolls. Optionally a shortcode + copy footer in
 * the SAME card (Shortcode Generator tab).
 *
 * @param string $box_id         Preview box element id.
 * @param bool   $with_shortcode Render the shortcode + copy/save footer.
 * @return void
 */
$tecc_render_preview_frame = function ( $box_id, $with_shortcode = false, $default_mode = '1280' ) {
	?>
	<div class="tecc-preview-frame">
		<div class="tecc-preview-topbar">
			<span class="tecc-preview-dots" aria-hidden="true"><i></i><i></i><i></i></span>
			<?php if ( $with_shortcode ) : ?>
				<?php // Same copy action as the button below; confirms in place, then reverts. ?>
				<button type="button" class="tecc-preview-copy" data-tecc-preview-copy>
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<span data-tecc-copy-label><?php esc_html_e( 'Copy shortcode', 'countdown-for-the-events-calendar' ); ?></span>
				</button>
			<?php endif; ?>
			<div class="tecc-preview-widths" role="group" aria-label="<?php esc_attr_e( 'Preview mode', 'countdown-for-the-events-calendar' ); ?>">
				<?php
				// Default mode is per-frame (shortcode opens pixel-true, sitewide zoomed
				// out); JS reads the active button. Cast both sides — PHP turns the
				// '1280' array key into an int.
				foreach ( array(
					'fit'  => __( 'Zoomed out', 'countdown-for-the-events-calendar' ),
					'1280' => __( 'Desktop (Real View)', 'countdown-for-the-events-calendar' ),
				) as $tecc_pw_key => $tecc_pw_label ) :
					?>
					<button type="button" class="tecc-pw-btn<?php echo ( (string) $tecc_pw_key === (string) $default_mode ) ? ' is-active' : ''; ?>" data-tecc-pw="<?php echo esc_attr( $tecc_pw_key ); ?>"><?php echo esc_html( $tecc_pw_label ); ?></button>
					<?php
				endforeach;
				?>
			</div>
			<span class="tecc-preview-status" data-tecc-preview-status>
				<span class="tecc-preview-dot" aria-hidden="true"></span>
				<span class="tecc-preview-status-text"><?php esc_html_e( 'Live preview', 'countdown-for-the-events-calendar' ); ?></span>
			</span>
		</div>
		<div class="tecc-preview-viewport" data-tecc-preview-viewport>
			<div class="tecc-preview-scaler" data-tecc-preview-scaler>
				<div id="<?php echo esc_attr( $box_id ); ?>" class="tecc-preview-box" aria-busy="false">
					<p class="tecc-preview-placeholder"><?php esc_html_e( 'Loading preview…', 'countdown-for-the-events-calendar' ); ?></p>
				</div>
			</div>
			<div class="tecc-preview-overlay" aria-hidden="true">
				<span class="tecc-preview-spinner" aria-hidden="true"></span>
				<span class="tecc-preview-overlay-text"><?php esc_html_e( 'Updating preview…', 'countdown-for-the-events-calendar' ); ?></span>
			</div>
		</div>
		<?php if ( $with_shortcode ) : ?>
			<div class="tecc-preview-shortcode">
				<code id="tecc-shortcode-output" class="tecc-shortcode-output"></code>
				<div class="tecc-copy-row">
					<button type="button" class="button button-primary" id="tecc-copy-shortcode"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span><span><?php esc_html_e( 'Copy shortcode', 'countdown-for-the-events-calendar' ); ?></span></button>
					<button type="submit" class="button button-secondary tecc-save-default" title="<?php esc_attr_e( 'Remember these settings so the generator opens with them next time', 'countdown-for-the-events-calendar' ); ?>"><span class="dashicons dashicons-saved" aria-hidden="true"></span><span><?php esc_html_e( 'Save these settings', 'countdown-for-the-events-calendar' ); ?></span></button>
					<span class="tecc-copy-feedback" aria-live="polite"></span>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
};
?>
<div class="wrap eca-admin-wrap tecc-settings-wrap">
	<div class="eca-admin-page">

		<?php // Header chrome mirrors the shared dashboard so navigation between addons feels seamless. ?>
		<header class="eca-admin-header">
			<div class="eca-admin-header__left">
				<a class="eca-admin-brand tecc-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=cool-plugins-events-addon' ) ); ?>">
					<span class="eca-admin-brand__logo" aria-hidden="true">
						<span class="dashicons dashicons-calendar-alt"></span>
					</span>
					<span class="tecc-brand__stack">
						<span class="eca-admin-brand__name"><?php esc_html_e( 'Events Calendar Addons', 'countdown-for-the-events-calendar' ); ?></span>
						<span class="tecc-brand__page">
							<span class="tecc-brand__arrow" aria-hidden="true">&rarr;</span>
							<?php esc_html_e( 'Event Countdown', 'countdown-for-the-events-calendar' ); ?>
						</span>
					</span>
				</a>
			</div>
			<div class="eca-admin-header__right">
				<a href="<?php echo esc_url( TECC_DOCS_URL ); ?>" target="_blank" rel="noopener" class="eca-btn-primary">
					<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Docs', 'countdown-for-the-events-calendar' ); ?></span>
				</a>
				<a href="<?php echo esc_url( TECC_SUPPORT_URL ); ?>" target="_blank" rel="noopener" class="eca-btn-secondary">
					<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Support', 'countdown-for-the-events-calendar' ); ?></span>
				</a>
			</div>
		</header>

		<main class="eca-admin-main tecc-settings-shell">

			<?php // Notices render here: WP would print them after the first h1, i.e. mid-panel on this full-bleed layout. ?>
			<div class="tecc-notices">
				<?php do_action( 'tecc_admin_notices' ); ?>
			</div>

			<section class="eca-hero eca-hero--compact tecc-hero">
				<div class="eca-hero__inner tecc-hero__inner">
					<div class="tecc-hero__lead">
						<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
						<p><?php esc_html_e( 'Build a countdown for any page, or show one sitewide automatically.', 'countdown-for-the-events-calendar' ); ?></p>
					</div>
					<?php
					$tecc_el_active = ! empty( $tecc_elementor_active );
					?>
					<aside class="tecc-hero__card" aria-label="<?php esc_attr_e( 'Ways to add the countdown', 'countdown-for-the-events-calendar' ); ?>">
						<span class="tecc-hero__card-icon" aria-hidden="true">
							<span class="dashicons <?php echo $tecc_el_active ? 'dashicons-admin-page' : 'dashicons-block-default'; ?>"></span>
						</span>
						<div class="tecc-hero__card-body">
							<?php if ( $tecc_el_active ) : ?>
								<h2 class="tecc-hero__card-title"><?php esc_html_e( 'Using Elementor? Skip the shortcode', 'countdown-for-the-events-calendar' ); ?></h2>
								<p class="tecc-hero__card-text"><?php esc_html_e( 'Drop the Event Countdown widget into any Elementor layout.', 'countdown-for-the-events-calendar' ); ?></p>
							<?php else : ?>
								<h2 class="tecc-hero__card-title"><?php esc_html_e( 'Prefer the block editor?', 'countdown-for-the-events-calendar' ); ?></h2>
								<p class="tecc-hero__card-text"><?php esc_html_e( 'Add the Event Countdown block to any page or post — no shortcode needed.', 'countdown-for-the-events-calendar' ); ?></p>
							<?php endif; ?>
						</div>
					</aside>
				</div>
			</section>

			<nav class="tecc-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'countdown-for-the-events-calendar' ); ?>">
				<button type="button" role="tab" id="tecc-tab-shortcode" aria-controls="tecc-panel-shortcode" aria-selected="true" class="tecc-tab-button is-active" data-tecc-tab="shortcode">
					<span class="tecc-tab-text">
						<span class="tecc-tab-title"><?php esc_html_e( 'Shortcode Generator', 'countdown-for-the-events-calendar' ); ?></span>
						<span class="tecc-tab-desc"><?php esc_html_e( 'Add a countdown to any page', 'countdown-for-the-events-calendar' ); ?></span>
					</span>
				</button>
				<button type="button" role="tab" id="tecc-tab-sitewide" aria-controls="tecc-panel-sitewide" aria-selected="false" class="tecc-tab-button" data-tecc-tab="sitewide">
					<span class="tecc-tab-text">
						<span class="tecc-tab-title"><?php esc_html_e( 'Floating Event Countdown', 'countdown-for-the-events-calendar' ); ?></span>
						<span class="tecc-tab-desc"><?php esc_html_e( 'Show it automatically, sitewide', 'countdown-for-the-events-calendar' ); ?></span>
					</span>
					<span class="tecc-badge<?php echo $tecc_sitewide_active ? ' is-active' : ' is-inactive'; ?>" id="tecc-sitewide-badge" data-state="<?php echo $tecc_sitewide_active ? 'active' : 'inactive'; ?>">
						<?php echo $tecc_sitewide_active ? esc_html__( 'Active', 'countdown-for-the-events-calendar' ) : esc_html__( 'Inactive', 'countdown-for-the-events-calendar' ); ?>
					</span>
				</button>
			</nav>

			<?php // WP redirects with settings-updated=true after an options.php save; our
			// screen suppresses foreign notices, so show our own confirmation. ?>
			<?php if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag set by WordPress. ?>
				<div class="tecc-saved-notice" role="status" data-tecc-saved-notice>
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Settings saved.', 'countdown-for-the-events-calendar' ); ?></span>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php" class="tecc-settings-form">
				<?php settings_fields( 'tecc_settings_group' ); ?>

				<!-- ============================= SHORTCODE ============================= -->
				<div class="tecc-tab-panel is-active" data-tecc-tab-panel="shortcode" id="tecc-panel-shortcode" role="tabpanel" aria-labelledby="tecc-tab-shortcode" tabindex="0">
					<div class="tecc-editor-columns">

						<div class="tecc-editor-main">
							<nav class="tecc-subtab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Countdown settings', 'countdown-for-the-events-calendar' ); ?>">
								<button type="button" role="tab" id="tecc-subtab-content" aria-controls="tecc-subpanel-content" aria-selected="true" class="tecc-subtab-button is-active" data-tecc-subtab="content"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><span><?php esc_html_e( 'Content &amp; Source', 'countdown-for-the-events-calendar' ); ?></span></button>
								<button type="button" role="tab" id="tecc-subtab-styles" aria-controls="tecc-subpanel-styles" aria-selected="false" class="tecc-subtab-button" data-tecc-subtab="styles"><span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span><span><?php esc_html_e( 'Design &amp; Styles', 'countdown-for-the-events-calendar' ); ?></span></button>
							</nav>

							<!-- Content & Source -->
							<div class="tecc-subtab-panel is-active" data-tecc-subtab-panel="content" id="tecc-subpanel-content" role="tabpanel" aria-labelledby="tecc-subtab-content">

								<div class="tecc-form-row">
									<span class="tecc-field-label"><?php esc_html_e( 'Countdown source', 'countdown-for-the-events-calendar' ); ?></span>
									<div class="tecc-source-radios">
										<label>
											<input type="radio" name="tecc_source_ui" value="upcoming" <?php checked( $tecc_source, 'upcoming' ); ?> />
											<?php esc_html_e( 'Upcoming event (automatic)', 'countdown-for-the-events-calendar' ); ?>
										</label>
										<label>
											<input type="radio" name="tecc_source_ui" value="events" <?php checked( $tecc_source, 'events' ); ?> />
											<?php esc_html_e( 'Specific events', 'countdown-for-the-events-calendar' ); ?>
										</label>
										<label>
											<input type="radio" name="tecc_source_ui" value="category" <?php checked( $tecc_source, 'category' ); ?> />
											<?php esc_html_e( 'Events in a category (Upcoming)', 'countdown-for-the-events-calendar' ); ?>
										</label>
									</div>
									<input type="hidden" name="tecc_settings[autostart-next-countdown]" value="<?php echo esc_attr( $tecc_next ); ?>" />
									<input type="hidden" name="tecc_settings[autostart-future-countdown]" value="<?php echo esc_attr( $tecc_future ); ?>" />
									<input type="hidden" name="tecc_settings[source-ui]" value="<?php echo esc_attr( $tecc_source ); ?>" data-tecc-source-ui />
								</div>

								<?php
								// Label maps for the pre-selected chips (the JS resolves
								// newly searched ones from the /source endpoint).
								$tecc_ev_titles = array();
								foreach ( $tecc_events as $tecc_ev ) {
									$tecc_ev_titles[ (int) $tecc_ev['id'] ] = isset( $tecc_ev['title'] ) ? (string) $tecc_ev['title'] : ( '#' . (int) $tecc_ev['id'] );
								}
								$tecc_cat_names = array();
								foreach ( $tecc_categories as $tecc_cat ) {
									$tecc_cat_key = isset( $tecc_cat['id'] ) ? (string) $tecc_cat['id'] : '';
									$tecc_cat_names[ $tecc_cat_key ] = isset( $tecc_cat['name'] ) ? (string) $tecc_cat['name'] : '';
								}
								$tecc_cat_tokens = array_filter( array_map( 'trim', explode( ',', $tecc_cats_csv ) ), 'strlen' );

								// Pre-selected event chips = the stored list UNION the legacy
								// single event_id, so a legacy selection shows as a chip and
								// migrates into future-events-list on the next save (never
								// silently wiped).
								$tecc_event_chip_ids = $tecc_list_ids;
								if ( $tecc_event_id > 0 && ! in_array( $tecc_event_id, $tecc_event_chip_ids, true ) ) {
									$tecc_event_chip_ids[] = $tecc_event_id;
								}
								?>
								<div class="tecc-form-row" data-tecc-source-vis="events">
									<span class="tecc-field-label"><?php esc_html_e( 'Choose events', 'countdown-for-the-events-calendar' ); ?></span>
									<input type="hidden" name="tecc_settings[future-events-list-marker]" value="1" />
									<div class="tecc-ts" data-tecc-ts="events">
										<div class="tecc-ts-control">
											<span class="tecc-ts-chips" data-tecc-ts-chips>
												<?php foreach ( $tecc_event_chip_ids as $tecc_sel_id ) : ?>
													<?php
													$tecc_sel_id = (int) $tecc_sel_id;
													if ( ! $tecc_sel_id ) {
														continue;
													}
													$tecc_sel_label = isset( $tecc_ev_titles[ $tecc_sel_id ] ) ? $tecc_ev_titles[ $tecc_sel_id ] : ( '#' . $tecc_sel_id );
													?>
													<span class="tecc-ts-chip" data-value="<?php echo esc_attr( $tecc_sel_id ); ?>">
														<span class="tecc-ts-chip-label"><?php echo esc_html( $tecc_sel_label ); ?></span>
														<button type="button" class="tecc-ts-remove" aria-label="<?php esc_attr_e( 'Remove', 'countdown-for-the-events-calendar' ); ?>">&times;</button>
														<input type="hidden" name="tecc_settings[future-events-list][]" value="<?php echo esc_attr( $tecc_sel_id ); ?>" />
													</span>
												<?php endforeach; ?>
											</span>
											<input type="text" class="tecc-ts-input" data-tecc-ts-input placeholder="<?php esc_attr_e( 'Search events…', 'countdown-for-the-events-calendar' ); ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list" />
										</div>
										<ul class="tecc-ts-menu" data-tecc-ts-menu role="listbox" hidden></ul>
									</div>
									<p class="description"><?php esc_html_e( 'The soonest selected event is used. Raise Number of events for a carousel.', 'countdown-for-the-events-calendar' ); ?></p>
								</div>

								<div class="tecc-form-row" data-tecc-source-vis="category">
									<span class="tecc-field-label"><?php esc_html_e( 'Categories', 'countdown-for-the-events-calendar' ); ?></span>
									<div class="tecc-ts" data-tecc-ts="category">
										<input type="hidden" name="tecc_settings[categories]" value="<?php echo esc_attr( $tecc_cats_csv ); ?>" data-tecc-ts-value />
										<div class="tecc-ts-control">
											<span class="tecc-ts-chips" data-tecc-ts-chips>
												<?php foreach ( $tecc_cat_tokens as $tecc_tok ) : ?>
													<?php $tecc_tok_label = ( isset( $tecc_cat_names[ $tecc_tok ] ) && '' !== $tecc_cat_names[ $tecc_tok ] ) ? $tecc_cat_names[ $tecc_tok ] : $tecc_tok; ?>
													<span class="tecc-ts-chip" data-value="<?php echo esc_attr( $tecc_tok ); ?>">
														<span class="tecc-ts-chip-label"><?php echo esc_html( $tecc_tok_label ); ?></span>
														<button type="button" class="tecc-ts-remove" aria-label="<?php esc_attr_e( 'Remove', 'countdown-for-the-events-calendar' ); ?>">&times;</button>
													</span>
												<?php endforeach; ?>
											</span>
											<input type="text" class="tecc-ts-input" data-tecc-ts-input placeholder="<?php esc_attr_e( 'Search categories…', 'countdown-for-the-events-calendar' ); ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list" />
										</div>
										<ul class="tecc-ts-menu" data-tecc-ts-menu role="listbox" hidden></ul>
									</div>
									<p class="description"><?php esc_html_e( 'Events in any of the selected categories are used.', 'countdown-for-the-events-calendar' ); ?></p>
								</div>

								<div class="tecc-form-row">
									<label class="tecc-field-label" for="tecc-no-of-events"><?php esc_html_e( 'Number of events (for carousel view)', 'countdown-for-the-events-calendar' ); ?></label>
									<div class="tecc-range-field">
										<input type="range" id="tecc-no-of-events" class="tecc-range" name="tecc_settings[no-of-events]" min="1" max="5" step="1" value="<?php echo esc_attr( $tecc_no_events ); ?>" data-tecc-range="tecc-no-of-events-out" />
										<output id="tecc-no-of-events-out" class="tecc-range__value" for="tecc-no-of-events"><?php echo esc_html( $tecc_no_events ); ?></output>
									</div>
									<p class="description"><?php esc_html_e( '2 or more rotates the events as a carousel.', 'countdown-for-the-events-calendar' ); ?></p>
								</div>

								<div class="tecc-form-row">
									<?php $tecc_switch( 'show-ongoing', 'yes' === $tecc_enum( 'show-ongoing', array( 'yes', 'no' ), 'yes' ), __( 'Show ongoing events', 'countdown-for-the-events-calendar' ), __( 'Counts down events already in progress instead of hiding them.', 'countdown-for-the-events-calendar' ) ); ?>
								</div>

								<hr class="tecc-sep" />

								<div class="tecc-form-row">
									<?php $tecc_switch( 'show-status', 'yes' === $tecc_enum( 'show-status', array( 'yes', 'no' ), 'yes' ), __( 'Show status label above event title', 'countdown-for-the-events-calendar' ), __( 'Turning it off also hides the three status labels below.', 'countdown-for-the-events-calendar' ) ); ?>
								</div>

								<div class="tecc-status-fields" data-tecc-status-fields>
								<div class="tecc-form-row">
									<label class="tecc-field-label" for="tecc-main-title"><?php esc_html_e( 'Status label — before it starts', 'countdown-for-the-events-calendar' ); ?></label>
									<input type="text" id="tecc-main-title" class="regular-text" name="tecc_settings[main-title]" value="<?php echo esc_attr( $tecc_val( 'main-title', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Next Event', 'countdown-for-the-events-calendar' ); ?>" />
								</div>

								<div class="tecc-form-row">
									<label class="tecc-field-label" for="tecc-ongoing-title"><?php esc_html_e( 'Status label — while it’s happening', 'countdown-for-the-events-calendar' ); ?></label>
									<input type="text" id="tecc-ongoing-title" class="regular-text" name="tecc_settings[ongoing-title]" value="<?php echo esc_attr( $tecc_val( 'ongoing-title', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Event in Progress', 'countdown-for-the-events-calendar' ); ?>" />
								</div>

								<div class="tecc-form-row">
									<label class="tecc-field-label" for="tecc-ended-title"><?php esc_html_e( 'Status label — after it ends', 'countdown-for-the-events-calendar' ); ?></label>
									<input type="text" id="tecc-ended-title" class="regular-text" name="tecc_settings[ended-title]" value="<?php echo esc_attr( $tecc_val( 'ended-title', '' ) ); ?>" placeholder="<?php esc_attr_e( 'This Event Has Ended', 'countdown-for-the-events-calendar' ); ?>" />
								</div>
								</div>

								<hr class="tecc-sep" />

								<div class="tecc-form-row">
									<span class="tecc-field-label"><?php esc_html_e( 'Display elements', 'countdown-for-the-events-calendar' ); ?></span>
									<div class="tecc-switch-grid">
										<?php foreach ( $tecc_toggles as $tecc_toggle_key => $tecc_toggle ) : ?>
											<?php $tecc_switch( $tecc_toggle_key, 'yes' === $tecc_enum( $tecc_toggle_key, array( 'yes', 'no' ), $tecc_toggle['default'] ), $tecc_toggle['label'] ); ?>
										<?php endforeach; ?>
									</div>
								</div>

								<div class="tecc-form-row" data-tecc-button-field>
									<label class="tecc-field-label" for="tecc-button-text"><?php esc_html_e( 'Button text', 'countdown-for-the-events-calendar' ); ?></label>
									<input type="text" id="tecc-button-text" class="regular-text" name="tecc_settings[button-text]" value="<?php echo esc_attr( $tecc_val( 'button-text', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Find out more', 'countdown-for-the-events-calendar' ); ?>" />
									<p class="description"><?php esc_html_e( 'Shown only while the event button is on. Try Learn More or Register.', 'countdown-for-the-events-calendar' ); ?></p>
								</div>

							</div><!-- /content -->

							<!-- Styles & Templates -->
							<div class="tecc-subtab-panel" data-tecc-subtab-panel="styles" id="tecc-subpanel-styles" role="tabpanel" aria-labelledby="tecc-subtab-styles">

								<div class="tecc-presets" data-tecc-presets>
									<div class="tecc-presets-head">
										<span class="tecc-field-label"><?php esc_html_e( 'Start from a preset, then customize', 'countdown-for-the-events-calendar' ); ?></span>
										<div class="tecc-mode-toggle" role="radiogroup" aria-label="<?php esc_attr_e( 'Preset colour mode', 'countdown-for-the-events-calendar' ); ?>">
											<button type="button" class="tecc-mode-btn is-active" role="radio" aria-checked="true" data-tecc-preset-mode="light">
												<span class="dashicons dashicons-sun" aria-hidden="true"></span>
												<span><?php esc_html_e( 'Light', 'countdown-for-the-events-calendar' ); ?></span>
											</button>
											<button type="button" class="tecc-mode-btn" role="radio" aria-checked="false" data-tecc-preset-mode="dark">
												<span class="dashicons dashicons-moon" aria-hidden="true"></span>
												<span><?php esc_html_e( 'Dark', 'countdown-for-the-events-calendar' ); ?></span>
											</button>
										</div>
									</div>
									<div class="tecc-preset-gallery" data-tecc-preset-gallery>
										<?php foreach ( $tecc_preset_layouts as $tecc_preset_id => $tecc_preset_def ) : ?>
											<button type="button" class="tecc-preset-tile" data-tecc-preset="<?php echo esc_attr( $tecc_preset_id ); ?>" title="<?php echo esc_attr( isset( $tecc_preset_def['hint'] ) ? $tecc_preset_def['hint'] : '' ); ?>">
												<span class="tecc-preset-name"><?php echo esc_html( isset( $tecc_preset_def['label'] ) ? $tecc_preset_def['label'] : $tecc_preset_id ); ?></span>
												<?php if ( ! empty( $tecc_preset_def['tagline'] ) ) : ?>
													<span class="tecc-preset-desc"><?php echo esc_html( $tecc_preset_def['tagline'] ); ?></span>
												<?php endif; ?>
											</button>
										<?php endforeach; ?>
									</div>
								</div>

								<?php // template is chosen by the preset, not the user; keep it saving. ?>
								<input type="hidden" name="tecc_settings[template]" value="<?php echo esc_attr( $tecc_template ); ?>" data-tecc-template-field />

								<div class="tecc-form-row">
									<span class="tecc-field-label"><?php esc_html_e( 'Countdown style', 'countdown-for-the-events-calendar' ); ?></span>
									<div class="tecc-pill-group">
										<?php foreach ( $tecc_cd_styles as $tecc_cds_key => $tecc_cds_label ) : ?>
											<label class="tecc-choice-pill<?php echo ( $tecc_cds_key === $tecc_cd_style ) ? ' is-selected' : ''; ?>">
												<input type="radio" name="tecc_settings[countdown-style]" value="<?php echo esc_attr( $tecc_cds_key ); ?>" <?php checked( $tecc_cd_style, $tecc_cds_key ); ?> />
												<span><?php echo esc_html( $tecc_cds_label ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>

								</div>

								<hr class="tecc-sep" />

								<div class="tecc-form-row">
									<span class="tecc-field-label"><?php esc_html_e( 'Colors', 'countdown-for-the-events-calendar' ); ?></span>
									<div class="tecc-color-row">
										<label class="tecc-color-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Main / accent', 'countdown-for-the-events-calendar' ); ?></span>
											<input type="color" name="tecc_settings[main-color]" value="<?php echo esc_attr( $tecc_val( 'main-color', '#4395cb' ) ); ?>" />
										</label>
										<label class="tecc-color-field">
											<span class="tecc-field-label"><?php esc_html_e( 'On-accent text', 'countdown-for-the-events-calendar' ); ?></span>
											<input type="color" name="tecc_settings[alternate-color]" value="<?php echo esc_attr( $tecc_val( 'alternate-color', '#ffffff' ) ); ?>" />
										</label>
										<label class="tecc-color-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Title', 'countdown-for-the-events-calendar' ); ?></span>
											<input type="color" name="tecc_settings[title-color]" value="<?php echo esc_attr( $tecc_val( 'title-color', '#1d2327' ) ); ?>" />
										</label>
										<label class="tecc-color-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Body text', 'countdown-for-the-events-calendar' ); ?></span>
											<input type="color" name="tecc_settings[text-color]" value="<?php echo esc_attr( $tecc_val( 'text-color', '#50575e' ) ); ?>" />
										</label>
										<?php
										$tecc_bg             = $tecc_val( 'bg-color', '#ffffff' );
										$tecc_bg_transparent = ( 'transparent' === $tecc_bg );
										?>
										<label class="tecc-color-field tecc-color-field--bg">
											<span class="tecc-field-label"><?php esc_html_e( 'Card background', 'countdown-for-the-events-calendar' ); ?></span>
											<span class="tecc-bg-controls">
												<input type="color" data-tecc-bg-picker value="<?php echo esc_attr( $tecc_bg_transparent ? '#ffffff' : $tecc_bg ); ?>"<?php echo $tecc_bg_transparent ? ' disabled' : ''; ?> />
												<input type="hidden" name="tecc_settings[bg-color]" value="<?php echo esc_attr( $tecc_bg ); ?>" data-tecc-bg-value />
												<span class="tecc-bg-transparent">
													<input type="checkbox" data-tecc-bg-transparent <?php checked( $tecc_bg_transparent ); ?> />
													<?php esc_html_e( 'Transparent', 'countdown-for-the-events-calendar' ); ?>
												</span>
											</span>
										</label>
									</div>

								</div>

								<hr class="tecc-sep" />

								<div class="tecc-form-row">
									<span class="tecc-field-label"><?php esc_html_e( 'Sizes', 'countdown-for-the-events-calendar' ); ?></span>
									<div class="tecc-design-grid">
										<label class="tecc-design-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Countdown size (px)', 'countdown-for-the-events-calendar' ); ?></span>
											<?php $tecc_range( 'countdown-size', $tecc_val( 'countdown-size', '28' ), 10, 120 ); ?>
										</label>
										<label class="tecc-design-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Title size (px)', 'countdown-for-the-events-calendar' ); ?></span>
											<?php $tecc_range( 'title-size', $tecc_val( 'title-size', '20' ), 8, 80 ); ?>
										</label>
										<label class="tecc-design-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Body size (px)', 'countdown-for-the-events-calendar' ); ?></span>
											<?php $tecc_range( 'body-size', $tecc_val( 'body-size', '14' ), 8, 48 ); ?>
										</label>
									</div>

								</div>

								<hr class="tecc-sep" />

								<div class="tecc-form-row">
									<span class="tecc-field-label"><?php esc_html_e( 'Layout', 'countdown-for-the-events-calendar' ); ?></span>
									<div class="tecc-design-grid">
										<?php $tecc_image_position = $tecc_enum( 'image-position', array( 'top', 'left', 'right' ), 'top' ); ?>
										<label class="tecc-design-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Image position', 'countdown-for-the-events-calendar' ); ?></span>
											<select name="tecc_settings[image-position]">
												<option value="top" <?php selected( $tecc_image_position, 'top' ); ?>><?php esc_html_e( 'Top', 'countdown-for-the-events-calendar' ); ?></option>
												<option value="left" <?php selected( $tecc_image_position, 'left' ); ?>><?php esc_html_e( 'Left', 'countdown-for-the-events-calendar' ); ?></option>
												<option value="right" <?php selected( $tecc_image_position, 'right' ); ?>><?php esc_html_e( 'Right', 'countdown-for-the-events-calendar' ); ?></option>
											</select>
										</label>
										<?php $tecc_content_align = $tecc_enum( 'content-align', array( 'left', 'center', 'right' ), 'left' ); ?>
										<label class="tecc-design-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Content alignment', 'countdown-for-the-events-calendar' ); ?></span>
											<select name="tecc_settings[content-align]">
												<option value="left" <?php selected( $tecc_content_align, 'left' ); ?>><?php esc_html_e( 'Left', 'countdown-for-the-events-calendar' ); ?></option>
												<option value="center" <?php selected( $tecc_content_align, 'center' ); ?>><?php esc_html_e( 'Center', 'countdown-for-the-events-calendar' ); ?></option>
												<option value="right" <?php selected( $tecc_content_align, 'right' ); ?>><?php esc_html_e( 'Right', 'countdown-for-the-events-calendar' ); ?></option>
											</select>
										</label>
										<div class="tecc-design-field">
											<?php $tecc_switch( 'image-gap', 'yes' === $tecc_enum( 'image-gap', array( 'yes', 'no' ), 'yes' ), __( 'Inset image', 'countdown-for-the-events-calendar' ) ); ?>
										</div>
										<div class="tecc-design-field">
											<?php $tecc_switch( 'shadow', 'yes' === $tecc_enum( 'shadow', array( 'yes', 'no' ), 'no' ), __( 'Shadow', 'countdown-for-the-events-calendar' ) ); ?>
										</div>
										<label class="tecc-design-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Corner radius (px)', 'countdown-for-the-events-calendar' ); ?></span>
											<?php $tecc_range( 'radius', $tecc_val( 'radius', '10' ), 0, 80 ); ?>
										</label>
										<label class="tecc-design-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Border width (px, 0 = none)', 'countdown-for-the-events-calendar' ); ?></span>
											<?php $tecc_range( 'border', $tecc_val( 'border', '0' ), 0, 20 ); ?>
										</label>
										<label class="tecc-design-field">
											<span class="tecc-field-label"><?php esc_html_e( 'Padding (px)', 'countdown-for-the-events-calendar' ); ?></span>
											<?php $tecc_range( 'padding', $tecc_val( 'padding', '15' ), 0, 80 ); ?>
										</label>
										<label class="tecc-design-field tecc-design-field--wide">
											<span class="tecc-field-label"><?php esc_html_e( 'Width', 'countdown-for-the-events-calendar' ); ?></span>
											<input type="text" name="tecc_settings[width]" value="<?php echo esc_attr( $tecc_val( 'width', 'auto' ) ); ?>" placeholder="<?php esc_attr_e( 'auto, 800, 70%, 100%', 'countdown-for-the-events-calendar' ); ?>" />
											<span class="description tecc-field-hint"><?php esc_html_e( 'Shortcode default caps at 480px; use 100% to fill the container.', 'countdown-for-the-events-calendar' ); ?></span>
										</label>
									</div>
								</div>

							</div><!-- /styles -->
						</div><!-- /.tecc-editor-main -->

						<div class="tecc-editor-side">
							<?php $tecc_render_preview_frame( 'tecc-preview', true ); ?>
							<p class="tecc-preview-note description"><?php esc_html_e( 'Copy the shortcode to paste anywhere. Saving keeps these settings for next time; it does not change countdowns already on your site.', 'countdown-for-the-events-calendar' ); ?></p>

							<?php
							// Standing review invitation; close snoozes 24h in this browser
							// only, never writes the saved review state.
							if ( class_exists( 'CPFM_Review' ) ) {
								CPFM_Review::cpfm_render_inline( 'tecc', array( 'style' => 'card', 'hours' => 24, 'persistent' => true ) );
							}
							?>
						</div><!-- /.tecc-editor-side -->

					</div><!-- /.tecc-editor-columns -->
				</div><!-- /shortcode -->

				<!-- ============================= SITEWIDE ============================= -->
				<div class="tecc-tab-panel" data-tecc-tab-panel="sitewide" id="tecc-panel-sitewide" role="tabpanel" aria-labelledby="tecc-tab-sitewide" tabindex="0">
					<div class="tecc-editor-columns">

						<div class="tecc-editor-main">
							<?php
							$tecc_rules_tab = TECC_PLUGIN_DIR . 'admin/settings/views/rules-tab.php';
							if ( file_exists( $tecc_rules_tab ) ) {
								include $tecc_rules_tab;
							}
							?>
						</div>

						<div class="tecc-editor-side">
							<?php $tecc_render_preview_frame( 'tecc-rules-preview', false, 'fit' ); ?>
							<p class="tecc-preview-note description"><?php esc_html_e( 'Location and page conditions apply on your live site only.', 'countdown-for-the-events-calendar' ); ?></p>

							<?php
							// Same standing invitation as the Shortcode tab.
							if ( class_exists( 'CPFM_Review' ) ) {
								CPFM_Review::cpfm_render_inline( 'tecc', array( 'style' => 'card', 'hours' => 24, 'persistent' => true ) );
							}
							?>
						</div>

					<?php if ( $tecc_data_sharing_visible ) : ?>
						<footer class="tecc-page-footer tecc-sitewide-consent">
							<div class="tecc-data-sharing">
								<label class="tecc-data-sharing__opt">
									<input type="checkbox" id="tecc-cpfm-data-sharing" <?php checked( 'yes', $tecc_data_sharing ); ?> />
									<span><?php esc_html_e( 'Help us make this plugin more compatible with your site by sharing non-sensitive site data. ', 'countdown-for-the-events-calendar' ); ?></span>
								</label>
								<a href="#" class="cpfm-see-terms tecc-see-terms">[<?php esc_html_e( 'See terms', 'countdown-for-the-events-calendar' ); ?>]</a>
								<?php // The AJAX handler writes ONLY this plugin's override (tecc-cpfm-data-sharing) — the copy must match that scope. ?>
								<p class="description tecc-data-sharing__scope"><?php esc_html_e( 'This setting controls usage data sharing for Event Countdown only. Other Events Addons keep their own choice.', 'countdown-for-the-events-calendar' ); ?></p>
								<div id="termsBox" class="tecc-terms-box" style="display: none;">
									<p><?php esc_html_e( "Opt in to receive email updates about security improvements, new features, helpful tutorials, and occasional special offers. We'll collect:", 'countdown-for-the-events-calendar' ); ?><a href="https://my.coolplugins.net/terms/usage-tracking/" target="_blank" rel="noopener"> <?php esc_html_e( 'Click Here', 'countdown-for-the-events-calendar' ); ?></a></p>
									<ul>
										<li><?php esc_html_e( 'Your website home URL and WordPress admin email.', 'countdown-for-the-events-calendar' ); ?></li>
										<?php // Same msgid as the shared CPFM notice so one translation covers both surfaces, and so the two can never disclose different things. ?>
										<li><?php esc_html_e( 'To check plugin compatibility, we will collect the following: list of active plugins and themes, PHP, MySQL and WordPress versions, memory limit, whether the site is multisite, and the site language. ', 'countdown-for-the-events-calendar' ); ?></li>
									</ul>
								</div>
							</div>
						</footer>
					<?php endif; ?>
				</div><!-- /sitewide -->

			</form>

		</main>
	</div><!-- /.eca-admin-page -->
</div><!-- /.tecc-settings-wrap -->
