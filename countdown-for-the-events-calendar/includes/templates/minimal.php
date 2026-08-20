<?php
/**
 * Countdown template partial: Minimal.
 *
 * Compact single-row layout: title + digits inline with a subtle date.
 * No image and no button — those toggles are intentionally ignored here.
 * Included by the shared renderer with a single $tecc_view array in scope.
 * All colors/spacing come from CSS custom properties (design tokens).
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 *
 * @var array $tecc_view {
 *     @type array  $args        Normalized render args.
 *     @type string $instance_id Unique per-render id.
 *     @type string $style_attr  Validated CSS custom-property string.
 *     @type array  $events      1..5 event arrays (Phase 1 renders the first only).
 *     @type array  $labels      Plain-text labels: title, ended, now, button.
 *     @type string $next_json   JSON for client rollover, or ''.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $tecc_view ) || ! is_array( $tecc_view ) || empty( $tecc_view['events'][0] ) || ! is_array( $tecc_view['events'][0] ) ) {
	return;
}

$tecc_args   = ( isset( $tecc_view['args'] ) && is_array( $tecc_view['args'] ) ) ? $tecc_view['args'] : array();
$tecc_event  = $tecc_view['events'][0];
$tecc_labels = ( isset( $tecc_view['labels'] ) && is_array( $tecc_view['labels'] ) ) ? $tecc_view['labels'] : array();

$tecc_instance_id = ( isset( $tecc_view['instance_id'] ) && '' !== $tecc_view['instance_id'] ) ? (string) $tecc_view['instance_id'] : uniqid( 'tecc-' );
$tecc_style_attr  = isset( $tecc_view['style_attr'] ) ? (string) $tecc_view['style_attr'] : '';
$tecc_next_json   = isset( $tecc_view['next_json'] ) ? (string) $tecc_view['next_json'] : '';

$tecc_size  = isset( $tecc_args['size'] ) ? (string) $tecc_args['size'] : 'medium';
$tecc_style = isset( $tecc_args['style'] ) ? (string) $tecc_args['style'] : 'default';

$tecc_show_seconds = ! empty( $tecc_args['show_seconds'] );

$tecc_start = isset( $tecc_event['start'] ) ? (int) $tecc_event['start'] : 0;
$tecc_end   = isset( $tecc_event['end'] ) ? (int) $tecc_event['end'] : 0;
$tecc_state = isset( $tecc_event['state'] ) ? (string) $tecc_event['state'] : 'before';
if ( ! in_array( $tecc_state, array( 'before', 'during', 'after' ), true ) ) {
	$tecc_state = 'before';
}

$tecc_event_title = isset( $tecc_event['title'] ) ? (string) $tecc_event['title'] : '';
$tecc_event_link  = isset( $tecc_event['link'] ) ? (string) $tecc_event['link'] : '';
$tecc_event_date  = isset( $tecc_event['date'] ) ? (string) $tecc_event['date'] : '';

$tecc_label_now   = ( isset( $tecc_labels['now'] ) && '' !== $tecc_labels['now'] ) ? (string) $tecc_labels['now'] : __( 'Happening now', 'countdown-for-the-events-calendar' );
$tecc_label_ended = ( isset( $tecc_labels['ended'] ) && '' !== $tecc_labels['ended'] ) ? (string) $tecc_labels['ended'] : __( 'This event has ended.', 'countdown-for-the-events-calendar' );

$tecc_labels_json = wp_json_encode(
	array(
		'now'        => $tecc_label_now,
		'ended'      => $tecc_label_ended,
		'nowClass'   => 'tecc-cd__msg tecc-cd__msg--now',
		'endedClass' => 'tecc-cd__msg tecc-cd__msg--ended',
	)
);
if ( ! is_string( $tecc_labels_json ) ) {
	$tecc_labels_json = '{}';
}

// Server-computed initial digits so first paint and no-JS pages are correct.
$tecc_remaining = max( 0, $tecc_start - time() );
$tecc_digits    = array(
	'days'    => sprintf( '%02d', floor( $tecc_remaining / 86400 ) ),
	'hours'   => sprintf( '%02d', floor( ( $tecc_remaining % 86400 ) / 3600 ) ),
	'minutes' => sprintf( '%02d', floor( ( $tecc_remaining % 3600 ) / 60 ) ),
	'seconds' => sprintf( '%02d', $tecc_remaining % 60 ),
);

$tecc_units = array(
	'days'    => __( 'days', 'countdown-for-the-events-calendar' ),
	'hours'   => __( 'hours', 'countdown-for-the-events-calendar' ),
	'minutes' => __( 'min', 'countdown-for-the-events-calendar' ),
	'seconds' => __( 'sec', 'countdown-for-the-events-calendar' ),
);
if ( ! $tecc_show_seconds ) {
	unset( $tecc_units['seconds'] );
}

// Digit box markup, buffered once: printed pre-start, and embedded in an
// inert <template data-tecc-box> for during/after so the JS can roll over
// to the next occurrence (data-tecc-next) without a fresh server render.
ob_start();
?>
<div class="tecc-cd__digits" aria-live="off">
	<?php foreach ( $tecc_units as $tecc_unit => $tecc_unit_label ) : ?>
		<div class="tecc-cd__unit" data-tecc-unit-wrap="<?php echo esc_attr( $tecc_unit ); ?>">
			<span class="tecc-cd__num" data-tecc-unit="<?php echo esc_attr( $tecc_unit ); ?>"><?php echo esc_html( $tecc_digits[ $tecc_unit ] ); ?></span>
			<span class="tecc-cd__label"><?php echo esc_html( $tecc_unit_label ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
<?php
$tecc_box_html = ob_get_clean();

$tecc_classes = 'tecc-cd tecc-cd--minimal tecc-cd--' . sanitize_html_class( $tecc_size, 'medium' ) . ' tecc-cd--style-' . sanitize_html_class( $tecc_style, 'default' );
?>
<div
	id="<?php echo esc_attr( $tecc_instance_id ); ?>"
	class="<?php echo esc_attr( $tecc_classes ); ?>"
	data-tecc="1"
	data-tecc-start="<?php echo esc_attr( (string) $tecc_start ); ?>"
	data-tecc-end="<?php echo esc_attr( (string) $tecc_end ); ?>"
	data-tecc-state="<?php echo esc_attr( $tecc_state ); ?>"
	data-tecc-labels="<?php echo esc_attr( $tecc_labels_json ); ?>"
	<?php if ( '' !== $tecc_next_json ) : ?>
	data-tecc-next="<?php echo esc_attr( $tecc_next_json ); ?>"
	<?php endif; ?>
	<?php if ( ! empty( $tecc_event['all_day'] ) ) : ?>
	data-tecc-allday="1"
	<?php endif; ?>
	<?php if ( '' !== $tecc_style_attr ) : ?>
	style="<?php echo esc_attr( $tecc_style_attr ); ?>"
	<?php endif; ?>
>
	<div class="tecc-cd__info">
		<h3 class="tecc-cd__title" data-tecc-title>
			<?php if ( '' !== $tecc_event_link ) : ?>
				<a class="tecc-cd__title-link" href="<?php echo esc_url( $tecc_event_link ); ?>" data-tecc-link><?php echo esc_html( $tecc_event_title ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $tecc_event_title ); ?>
			<?php endif; ?>
		</h3>
		<?php if ( '' !== $tecc_event_date ) : ?>
			<span class="tecc-cd__date"><?php echo esc_html( $tecc_event_date ); ?></span>
		<?php endif; ?>
	</div>

	<div class="tecc-cd__timer" data-tecc-region>
		<?php if ( 'before' === $tecc_state ) : ?>
			<?php echo $tecc_box_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully escaped markup buffered above. ?>
		<?php elseif ( 'during' === $tecc_state ) : ?>
			<div class="tecc-cd__msg tecc-cd__msg--now"><?php echo esc_html( $tecc_label_now ); ?></div>
			<?php if ( '' !== $tecc_next_json ) : ?>
				<template data-tecc-box><?php echo $tecc_box_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully escaped markup buffered above. ?></template>
			<?php endif; ?>
		<?php else : ?>
			<div class="tecc-cd__msg tecc-cd__msg--ended"><?php echo esc_html( $tecc_label_ended ); ?></div>
			<?php if ( '' !== $tecc_next_json ) : ?>
				<template data-tecc-box><?php echo $tecc_box_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully escaped markup buffered above. ?></template>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
