<?php
/**
 * Classic template — BYTE-FAITHFUL to the v1.x DOM.
 *
 * Sites' custom CSS and the untouched legacy countdown.css key on these
 * exact ids, classes and parent>child combinators:
 *   .tecc-wrapper#tecc-{event_ID}
 *   .tec-countdown-timer.tec-{size}-box > .tecc-section > span.tecc-amount/.tecc-word
 *   .eventstart_msz / .eventend_msz (underscores), .event-date-location, …
 * Do NOT rename or restructure. The wrapper id stays tecc-{event_ID} (the
 * documented exception); the render-unique instance id rides on the
 * data attribute instead.
 *
 * In scope: $tecc_view (see Renderer::build_view()).
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tecc_args   = $tecc_view['args'];
$tecc_event  = $tecc_view['events'][0];
$tecc_labels = $tecc_view['labels'];

$tecc_remaining = max( 0, $tecc_event['start'] - time() );
$tecc_days      = (int) floor( $tecc_remaining / DAY_IN_SECONDS );
$tecc_hours     = (int) floor( ( $tecc_remaining % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
$tecc_minutes   = (int) floor( ( $tecc_remaining % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
$tecc_seconds   = (int) ( $tecc_remaining % MINUTE_IN_SECONDS );

// State-message classes for the timer runtime (classic keeps the legacy
// underscore classes).
$tecc_labels_json = wp_json_encode(
	array(
		'now'        => $tecc_labels['now'],
		'ended'      => $tecc_labels['ended'],
		'nowClass'   => 'eventstart_msz',
		'endedClass' => 'eventend_msz',
	)
);
if ( ! is_string( $tecc_labels_json ) ) {
	$tecc_labels_json = '{}';
}

// The ticking box, buffered once: echoed directly in the 'before' state and
// embedded in an inert <template data-tecc-box> for during/after states with
// a roll-over queue — so a full-page cache snapshot taken mid-event still
// lets the client restore the box when it advances to the next occurrence.
ob_start();
?>
			<div class="tec-countdown-timer tec-<?php echo esc_attr( $tecc_args['size'] ); ?>-box" aria-live="off">
				<div class="tecc-section tecc-days-section" data-tecc-unit-wrap="days">
					<span class="tecc-amount" data-tecc-unit="days"><?php echo esc_html( sprintf( '%02d', $tecc_days ) ); ?></span>
					<span class="tecc-word"><?php esc_html_e( 'days', 'countdown-for-the-events-calendar' ); ?></span>
				</div>
				<div class="tecc-section tecc-hours-section" data-tecc-unit-wrap="hours">
					<span class="tecc-amount" data-tecc-unit="hours"><?php echo esc_html( sprintf( '%02d', $tecc_hours ) ); ?></span>
					<span class="tecc-word"><?php esc_html_e( 'hours', 'countdown-for-the-events-calendar' ); ?></span>
				</div>
				<div class="tecc-section tecc-minutes-section" data-tecc-unit-wrap="minutes">
					<span class="tecc-amount" data-tecc-unit="minutes"><?php echo esc_html( sprintf( '%02d', $tecc_minutes ) ); ?></span>
					<span class="tecc-word"><?php esc_html_e( 'min', 'countdown-for-the-events-calendar' ); ?></span>
				</div>
				<?php if ( $tecc_args['show_seconds'] ) : ?>
				<div class="tecc-section tecc-seconds-section" data-tecc-unit-wrap="seconds">
					<span class="tecc-amount" data-tecc-unit="seconds"><?php echo esc_html( sprintf( '%02d', $tecc_seconds ) ); ?></span>
					<span class="tecc-word"><?php esc_html_e( 'sec', 'countdown-for-the-events-calendar' ); ?></span>
				</div>
				<?php endif; ?>
			</div>
<?php
$tecc_box_html = ob_get_clean();
?>
<div class="tecc-wrapper" id="tecc-<?php echo esc_attr( $tecc_event['id'] ); ?>"
	data-tecc="1"
	data-tecc-instance="<?php echo esc_attr( $tecc_view['instance_id'] ); ?>"
	data-tecc-start="<?php echo esc_attr( $tecc_event['start'] ); ?>"
	data-tecc-end="<?php echo esc_attr( $tecc_event['end'] ); ?>"
	data-tecc-state="<?php echo esc_attr( $tecc_event['state'] ); ?>"
	data-tecc-labels="<?php echo esc_attr( $tecc_labels_json ); ?>"
	<?php if ( '' !== $tecc_view['next_json'] ) : ?>
	data-tecc-next="<?php echo esc_attr( $tecc_view['next_json'] ); ?>"
	<?php endif; ?>
	<?php if ( '' !== $tecc_view['style_attr'] ) : ?>
	style="<?php echo esc_attr( $tecc_view['style_attr'] ); ?>"
	<?php endif; ?>
>
	<div class="tecc-event-info">
		<h2 class="tecc-up-event" data-tecc-upcoming-only<?php echo 'before' === $tecc_event['state'] ? '' : ' hidden'; ?>><?php echo esc_html( $tecc_labels['title'] ); ?></h2>
		<?php if ( $tecc_args['show_image'] && '' !== $tecc_event['image'] ) : ?>
		<div class="tecc-image-wrapper"><?php echo wp_kses_post( $tecc_event['image'] ); ?></div>
		<?php endif; ?>
		<a href="<?php echo esc_url( $tecc_event['link'] ); ?>" data-tecc-link><h3 class="tecc-title" data-tecc-title><?php echo esc_html( $tecc_event['title'] ); ?></h3></a>
		<div class="event-date-location">
			<span class="tecc-date"><?php echo esc_html( $tecc_event['date'] ); ?></span><?php if ( '' !== $tecc_event['venue'] ) : ?><span class="tecc-location"> -</span><?php echo esc_html( $tecc_event['venue'] ); ?><?php endif; ?>
		</div>
	</div>
	<div class="tecc-timer-wrapper">
		<div class="tecc-date-timer" data-tecc-region>
			<?php if ( 'before' === $tecc_event['state'] ) : ?>
			<?php echo $tecc_box_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from fully escaped parts. ?>
			<?php elseif ( 'during' === $tecc_event['state'] ) : ?>
			<div class="eventstart_msz"><?php echo esc_html( $tecc_labels['now'] ); ?></div>
			<?php else : ?>
			<div class="eventend_msz"><?php echo esc_html( $tecc_labels['ended'] ); ?></div>
			<?php endif; ?>
			<?php if ( 'before' !== $tecc_event['state'] && '' !== $tecc_view['next_json'] ) : ?>
			<template data-tecc-box><?php echo $tecc_box_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from fully escaped parts. ?></template>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $tecc_args['show_button'] ) : ?>
	<div class="tecc-event-detail">
		<a class="tecc-event-button" href="<?php echo esc_url( $tecc_event['link'] ); ?>" data-tecc-link><?php echo esc_html( $tecc_labels['button'] ); ?></a>
	</div>
	<?php endif; ?>
</div>
