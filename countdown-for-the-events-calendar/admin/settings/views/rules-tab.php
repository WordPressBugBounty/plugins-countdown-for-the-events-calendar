<?php
/**
 * Sitewide Display builder — rule list + the template the JS clones per rule.
 *
 * Rendered inside the settings panel form (posts to options.php). The builder
 * JS (assets/js/tecc-admin-rules.js) reads/writes ONE hidden field named
 * `tecc_display_rules` containing the JSON-encoded rule list; the server-side
 * sanitizer (includes/display/class-tecc-rules.php) validates everything.
 *
 * Each rule edits in a 3-tab card (Display Conditions | Event Source | Styles).
 * Every serializer class hook (.tecc-rule-*, .tecc-condition-*) is preserved so
 * serializeRule() keeps working — the sub-tabs only group existing controls.
 *
 * @var array $tecc_rules  Stored rules (validated shape — lists are arrays).
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tecc_rules  = ( isset( $tecc_rules ) && is_array( $tecc_rules ) ) ? $tecc_rules : array();

// Five floating locations. The label notes the layout each one forces
// (banner across the bars/middle, card in the corners) since there is no
// separate template control for a display.
$tecc_rule_locations = array(
	'top-bar'       => __( 'Top bar (banner)', 'countdown-for-the-events-calendar' ),
	'footer-bar'    => __( 'Footer bar (banner)', 'countdown-for-the-events-calendar' ),
	'footer-middle' => __( 'Footer middle (banner)', 'countdown-for-the-events-calendar' ),
	'footer-left'   => __( 'Footer left (card)', 'countdown-for-the-events-calendar' ),
	'footer-right'  => __( 'Footer right (card)', 'countdown-for-the-events-calendar' ),
);

$tecc_rule_sources = array(
	'next-upcoming'   => __( 'Next upcoming event', 'countdown-for-the-events-calendar' ),
	'next-in-context' => __( 'Next event in page context', 'countdown-for-the-events-calendar' ),
	'filtered'        => __( 'Filtered by category', 'countdown-for-the-events-calendar' ),
	'selected'        => __( 'Specific events', 'countdown-for-the-events-calendar' ),
);

// Displays offer just Light / Dark; the layout comes from the location.
$tecc_rule_styles = array(
	'light' => __( 'Light', 'countdown-for-the-events-calendar' ),
	'dark'  => __( 'Dark', 'countdown-for-the-events-calendar' ),
);

/*
 * Condition types. Keys map to the server whitelist; 'has_value' marks the
 * types whose row shows a value input.
 */
$tecc_rule_conditions = array(
	'entire_site'    => array(
		'label'     => __( 'Entire site', 'countdown-for-the-events-calendar' ),
		'has_value' => false,
	),
	'front_page'     => array(
		'label'     => __( 'Front page', 'countdown-for-the-events-calendar' ),
		'has_value' => false,
	),
	'blog'           => array(
		'label'     => __( 'Blog page', 'countdown-for-the-events-calendar' ),
		'has_value' => false,
	),
	'all_tec_pages'  => array(
		'label'     => __( 'All calendar pages', 'countdown-for-the-events-calendar' ),
		'has_value' => false,
	),
	'single_event'   => array(
		'label'     => __( 'Single event page', 'countdown-for-the-events-calendar' ),
		'has_value' => false,
	),
	'event_archive'  => array(
		'label'     => __( 'Event archive', 'countdown-for-the-events-calendar' ),
		'has_value' => false,
	),
	'post_type'      => array(
		'label'     => __( 'Post type (slug)', 'countdown-for-the-events-calendar' ),
		'has_value' => true,
	),
	'page_id'        => array(
		'label'     => __( 'Page IDs (comma-separated)', 'countdown-for-the-events-calendar' ),
		'has_value' => true,
	),
	'post_id'        => array(
		'label'     => __( 'Post IDs (comma-separated)', 'countdown-for-the-events-calendar' ),
		'has_value' => true,
	),
	'tec_category'   => array(
		'label'     => __( 'Event category (slugs)', 'countdown-for-the-events-calendar' ),
		'has_value' => true,
	),
	'tec_tag'        => array(
		'label'     => __( 'Event tag (slugs)', 'countdown-for-the-events-calendar' ),
		'has_value' => true,
	),
	'tec_venue'      => array(
		'label'     => __( 'Venue IDs (comma-separated)', 'countdown-for-the-events-calendar' ),
		'has_value' => true,
	),
	'url_contains'   => array(
		'label'     => __( 'URL contains', 'countdown-for-the-events-calendar' ),
		'has_value' => true,
	),
	'user_logged_in' => array(
		'label'     => __( 'User is logged in', 'countdown-for-the-events-calendar' ),
		'has_value' => false,
	),
	'is_mobile'      => array(
		'label'     => __( 'Mobile device', 'countdown-for-the-events-calendar' ),
		'has_value' => false,
	),
);

?>

<p class="tecc-rules-intro">
	<?php esc_html_e( 'Show a countdown automatically in a site location on chosen pages.', 'countdown-for-the-events-calendar' ); ?>
</p>
<p class="tecc-rules-intro description">
	<?php esc_html_e( 'One display per location; the lower priority number wins.', 'countdown-for-the-events-calendar' ); ?>
</p>

<input type="hidden" name="tecc_display_rules" id="tecc-rules-json" value="<?php echo esc_attr( wp_json_encode( $tecc_rules ) ); ?>" />

<div id="tecc-rules-app" data-tecc-rules-app></div>

<p class="tecc-rules-actions">
	<button type="button" id="tecc-add-rule" class="button button-secondary">
		<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
		<?php esc_html_e( 'Add sitewide display', 'countdown-for-the-events-calendar' ); ?>
	</button>
	<button type="submit" class="button button-primary tecc-save-sitewide">
		<span class="dashicons dashicons-saved" aria-hidden="true"></span>
		<?php esc_html_e( 'Save sitewide displays', 'countdown-for-the-events-calendar' ); ?>
	</button>
</p>

<template id="tecc-rule-template">
	<div class="tecc-rule-card">
		<div class="tecc-rule-header">
			<span class="tecc-rule-move">
				<button type="button" class="tecc-rule-up button-link" aria-label="<?php esc_attr_e( 'Move display up', 'countdown-for-the-events-calendar' ); ?>">&#9650;</button>
				<button type="button" class="tecc-rule-down button-link" aria-label="<?php esc_attr_e( 'Move display down', 'countdown-for-the-events-calendar' ); ?>">&#9660;</button>
			</span>
			<label class="tecc-rule-enabled-wrap">
				<input type="checkbox" class="tecc-rule-enabled" checked="checked" />
				<span class="screen-reader-text"><?php esc_html_e( 'Display enabled', 'countdown-for-the-events-calendar' ); ?></span>
			</label>
			<span class="tecc-rule-summary"></span>
			<button type="button" class="tecc-rule-preview button-link"><?php esc_html_e( 'Preview', 'countdown-for-the-events-calendar' ); ?></button>
			<button type="button" class="tecc-rule-toggle button-link" aria-expanded="true">
				<?php esc_html_e( 'Expand / collapse', 'countdown-for-the-events-calendar' ); ?>
			</button>
			<button type="button" class="tecc-rule-delete button-link">
				<?php esc_html_e( 'Delete', 'countdown-for-the-events-calendar' ); ?>
			</button>
		</div>

		<div class="tecc-rule-body">
			<nav class="tecc-rule-subnav" role="tablist" aria-label="<?php esc_attr_e( 'Display settings', 'countdown-for-the-events-calendar' ); ?>">
				<button type="button" role="tab" class="tecc-rule-subtab-button is-active" data-tecc-rule-subtab="conditions" aria-selected="true"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><span><?php esc_html_e( 'Conditions', 'countdown-for-the-events-calendar' ); ?></span></button>
				<button type="button" role="tab" class="tecc-rule-subtab-button" data-tecc-rule-subtab="source" aria-selected="false"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><span><?php esc_html_e( 'Event Source', 'countdown-for-the-events-calendar' ); ?></span></button>
				<button type="button" role="tab" class="tecc-rule-subtab-button" data-tecc-rule-subtab="styles" aria-selected="false"><span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span><span><?php esc_html_e( 'Styles', 'countdown-for-the-events-calendar' ); ?></span></button>
			</nav>

			<!-- Display Conditions -->
			<div class="tecc-rule-subpanel is-active" data-tecc-rule-subtab-panel="conditions" role="tabpanel">
				<div class="tecc-rule-grid">
					<div class="tecc-rule-field">
						<label>
							<span class="tecc-rule-label"><?php esc_html_e( 'Location', 'countdown-for-the-events-calendar' ); ?></span>
							<select class="tecc-rule-location">
								<?php foreach ( $tecc_rule_locations as $tecc_loc_key => $tecc_loc_label ) : ?>
									<option value="<?php echo esc_attr( $tecc_loc_key ); ?>"><?php echo esc_html( $tecc_loc_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<p class="description"><?php esc_html_e( 'Floats over the page. Within a location, the highest in the list wins.', 'countdown-for-the-events-calendar' ); ?></p>
					</div>
				</div>

				<div class="tecc-rule-field">
					<label>
						<span class="tecc-rule-label"><?php esc_html_e( 'When a visitor closes it', 'countdown-for-the-events-calendar' ); ?></span>
						<select class="tecc-rule-close-action">
							<option value="event"><?php esc_html_e( 'Hide until the next event', 'countdown-for-the-events-calendar' ); ?></option>
							<option value="session"><?php esc_html_e( 'Hide for this browsing session', 'countdown-for-the-events-calendar' ); ?></option>
							<option value="week"><?php esc_html_e( 'Hide for 7 days', 'countdown-for-the-events-calendar' ); ?></option>
							<option value="forever"><?php esc_html_e( 'Hide permanently', 'countdown-for-the-events-calendar' ); ?></option>
						</select>
					</label>
					<p class="description"><?php esc_html_e( 'Hides it in that visitor’s browser only, not for everyone.', 'countdown-for-the-events-calendar' ); ?></p>
				</div>
				<hr class="tecc-rule-sep" />

				<div class="tecc-rule-conditions">
					<div class="tecc-rule-conditions-group" data-tecc-bucket="include">
						<span class="tecc-rule-label"><?php esc_html_e( 'Show on', 'countdown-for-the-events-calendar' ); ?></span>
						<div class="tecc-rule-condition-list"></div>
						<button type="button" class="tecc-rule-add-include button button-small">
							<?php esc_html_e( 'Add condition', 'countdown-for-the-events-calendar' ); ?>
						</button>
					</div>
					<div class="tecc-rule-conditions-group" data-tecc-bucket="exclude">
						<span class="tecc-rule-label"><?php esc_html_e( 'Hide on', 'countdown-for-the-events-calendar' ); ?></span>
						<div class="tecc-rule-condition-list"></div>
						<button type="button" class="tecc-rule-add-exclude button button-small">
							<?php esc_html_e( 'Add condition', 'countdown-for-the-events-calendar' ); ?>
						</button>
					</div>

					<template class="tecc-condition-template">
						<div class="tecc-condition-row">
							<select class="tecc-condition-type">
								<?php foreach ( $tecc_rule_conditions as $tecc_cond_key => $tecc_cond ) : ?>
									<option value="<?php echo esc_attr( $tecc_cond_key ); ?>" data-has-value="<?php echo esc_attr( $tecc_cond['has_value'] ? '1' : '0' ); ?>"><?php echo esc_html( $tecc_cond['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" class="tecc-condition-value" placeholder="<?php esc_attr_e( 'Value', 'countdown-for-the-events-calendar' ); ?>" />
							<button type="button" class="tecc-condition-remove button-link" aria-label="<?php esc_attr_e( 'Remove condition', 'countdown-for-the-events-calendar' ); ?>">&times;</button>
						</div>
					</template>
				</div>

			</div><!-- /conditions -->

			<!-- Event Source -->
			<div class="tecc-rule-subpanel" data-tecc-rule-subtab-panel="source" role="tabpanel">
				<div class="tecc-rule-field">
					<label>
						<span class="tecc-rule-label"><?php esc_html_e( 'Event source', 'countdown-for-the-events-calendar' ); ?></span>
							<select class="tecc-rule-source">
								<?php foreach ( $tecc_rule_sources as $tecc_src_key => $tecc_src_label ) : ?>
									<option value="<?php echo esc_attr( $tecc_src_key ); ?>"><?php echo esc_html( $tecc_src_label ); ?></option>
								<?php endforeach; ?>
							</select>
					</label>
				</div>

				<!-- Source-specific input sits directly under the source select. -->
				<div class="tecc-rule-filtered tecc-rule-subsection">
					<div class="tecc-rule-field">
						<label>
							<span class="tecc-rule-label"><?php esc_html_e( 'Category IDs or slugs (comma-separated)', 'countdown-for-the-events-calendar' ); ?></span>
							<input type="text" class="tecc-rule-categories regular-text" placeholder="<?php esc_attr_e( 'e.g. concerts, 12, workshops', 'countdown-for-the-events-calendar' ); ?>" />
						</label>
					</div>
				</div>

				<div class="tecc-rule-selected tecc-rule-subsection">
					<div class="tecc-rule-field">
						<label>
							<span class="tecc-rule-label"><?php esc_html_e( 'Event IDs (comma-separated)', 'countdown-for-the-events-calendar' ); ?></span>
							<input type="text" class="tecc-rule-event-ids regular-text" placeholder="<?php esc_attr_e( 'e.g. 123, 456', 'countdown-for-the-events-calendar' ); ?>" />
						</label>
					</div>
				</div>

				<div class="tecc-rule-field tecc-rule-carousel-field">
					<label class="tecc-rule-switch-inline">
						<input type="checkbox" class="tecc-rule-carousel" />
						<span class="tecc-rule-label"><?php esc_html_e( 'Carousel — show multiple events', 'countdown-for-the-events-calendar' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Corner-card locations only; bars and middle show one event.', 'countdown-for-the-events-calendar' ); ?></p>
				</div>

				<div class="tecc-rule-field tecc-rule-limit-field">
					<label>
						<span class="tecc-rule-label"><?php esc_html_e( 'Number of events', 'countdown-for-the-events-calendar' ); ?></span>
						<span class="tecc-range-field">
							<input type="range" class="tecc-rule-limit tecc-range" min="2" max="5" step="1" value="2" />
							<output class="tecc-range__value tecc-rule-limit-out">2</output>
						</span>
					</label>
				</div>

				<p class="description tecc-rule-context-note"><?php esc_html_e( 'Page context applies on your live site; the preview shows a fallback.', 'countdown-for-the-events-calendar' ); ?></p>
			</div><!-- /source -->

			<!-- Styles -->
			<div class="tecc-rule-subpanel" data-tecc-rule-subtab-panel="styles" role="tabpanel">
				<div class="tecc-rule-grid">
					<div class="tecc-rule-field">
						<label>
							<span class="tecc-rule-label"><?php esc_html_e( 'Appearance', 'countdown-for-the-events-calendar' ); ?></span>
							<select class="tecc-rule-style">
								<?php foreach ( $tecc_rule_styles as $tecc_sty_key => $tecc_sty_label ) : ?>
									<option value="<?php echo esc_attr( $tecc_sty_key ); ?>"><?php echo esc_html( $tecc_sty_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
					<div class="tecc-rule-field">
						<label>
							<span class="tecc-rule-label"><?php esc_html_e( 'Timer style', 'countdown-for-the-events-calendar' ); ?></span>
							<select class="tecc-rule-cd-style">
								<option value="box"><?php esc_html_e( 'Boxes', 'countdown-for-the-events-calendar' ); ?></option>
								<option value="ring"><?php esc_html_e( 'Rings', 'countdown-for-the-events-calendar' ); ?></option>
								<option value="inline"><?php esc_html_e( 'Inline', 'countdown-for-the-events-calendar' ); ?></option>
							</select>
						</label>
					</div>

				</div>

				<hr class="tecc-rule-sep" />

				<?php
				// Real colour pickers, seeded by the Light/Dark choice above (the
				// builder JS re-seeds them on change). data-tecc-design keys map to
				// the renderer design colours; serialized into rule.design.
				$tecc_rule_design_colors = array(
					'main'        => __( 'Accent', 'countdown-for-the-events-calendar' ),
					'alt'         => __( 'On-accent text', 'countdown-for-the-events-calendar' ),
					'title_color' => __( 'Title', 'countdown-for-the-events-calendar' ),
					'text_color'  => __( 'Body text', 'countdown-for-the-events-calendar' ),
					'bg'          => __( 'Background', 'countdown-for-the-events-calendar' ),
				);
				?>
				<div class="tecc-rule-colors">
					<span class="tecc-rule-label"><?php esc_html_e( 'Colors', 'countdown-for-the-events-calendar' ); ?></span>
					<div class="tecc-rule-colors-row">
						<?php foreach ( $tecc_rule_design_colors as $tecc_dc_key => $tecc_dc_label ) : ?>
							<label class="tecc-rule-color-field">
								<span class="tecc-rule-color-name"><?php echo esc_html( $tecc_dc_label ); ?></span>
								<input type="color" class="tecc-rule-design" data-tecc-design="<?php echo esc_attr( $tecc_dc_key ); ?>" value="#4395cb" />
							</label>
						<?php endforeach; ?>
					</div>

					<p class="description"><?php esc_html_e( 'Switching Light / Dark resets these colours.', 'countdown-for-the-events-calendar' ); ?></p>
				</div>

				<hr class="tecc-rule-sep" />

				<?php
				// Size / spacing / image controls (serialized into rule.design via
				// data-tecc-design, except image which is a top-level position).
				$tecc_rule_dims = array(
					'title_size' => array( __( 'Title size', 'countdown-for-the-events-calendar' ), 8, 80, 20 ),
					'body_size'  => array( __( 'Body size', 'countdown-for-the-events-calendar' ), 8, 48, 14 ),
					'radius'     => array( __( 'Corner radius', 'countdown-for-the-events-calendar' ), 0, 80, 12 ),
					'border'     => array( __( 'Border width', 'countdown-for-the-events-calendar' ), 0, 20, 0 ),
					'padding'    => array( __( 'Padding', 'countdown-for-the-events-calendar' ), 0, 80, 18 ),
				);
				?>
				<div class="tecc-rule-grid tecc-rule-dims">
					<label class="tecc-rule-field">
						<span class="tecc-rule-label"><?php esc_html_e( 'Image', 'countdown-for-the-events-calendar' ); ?></span>
						<select class="tecc-rule-imgpos">
							<option value="none"><?php esc_html_e( 'None', 'countdown-for-the-events-calendar' ); ?></option>
							<option value="top"><?php esc_html_e( 'Top', 'countdown-for-the-events-calendar' ); ?></option>
							<option value="left"><?php esc_html_e( 'Left', 'countdown-for-the-events-calendar' ); ?></option>
							<option value="right"><?php esc_html_e( 'Right', 'countdown-for-the-events-calendar' ); ?></option>
						</select>
					</label>
					<label class="tecc-rule-field">
						<span class="tecc-rule-label"><?php esc_html_e( 'Timer size (px)', 'countdown-for-the-events-calendar' ); ?></span>
						<span class="tecc-range-field">
							<input type="range" class="tecc-rule-cd-size tecc-range" min="10" max="120" step="1" value="28" />
							<output class="tecc-range__value tecc-rule-cd-size-out">28</output>
						</span>
					</label>
					<?php foreach ( $tecc_rule_dims as $tecc_dim_key => $tecc_dim ) : ?>
						<label class="tecc-rule-field">
							<span class="tecc-rule-label"><?php echo esc_html( $tecc_dim[0] ); ?></span>
							<span class="tecc-range-field">
								<input type="range" class="tecc-rule-design tecc-range" data-tecc-design="<?php echo esc_attr( $tecc_dim_key ); ?>" min="<?php echo esc_attr( $tecc_dim[1] ); ?>" max="<?php echo esc_attr( $tecc_dim[2] ); ?>" step="1" value="<?php echo esc_attr( $tecc_dim[3] ); ?>" />
								<output class="tecc-range__value"><?php echo esc_html( $tecc_dim[3] ); ?></output>
							</span>
						</label>
					<?php endforeach; ?>
					<label class="tecc-rule-field">
						<span class="tecc-rule-label"><?php esc_html_e( 'Width', 'countdown-for-the-events-calendar' ); ?></span>
						<input type="text" class="tecc-rule-design" data-tecc-design="width" value="auto" placeholder="<?php esc_attr_e( 'auto, 800 or 70%', 'countdown-for-the-events-calendar' ); ?>" />
					</label>
				</div>
			</div><!-- /styles -->
		</div>
	</div>
</template>
