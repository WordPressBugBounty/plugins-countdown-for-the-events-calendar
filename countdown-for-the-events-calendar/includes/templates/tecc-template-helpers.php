<?php
/**
 * Shared helpers for the design-engine templates (card + banner).
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'tecc_tpl_design_context' ) ) {

	/**
	 * Shared flags, labels, units and width class for card/banner.
	 *
	 * @param array $view Renderer view model.
	 * @return array
	 */
	function tecc_tpl_design_context( array $view ) {
		$args   = ( isset( $view['args'] ) && is_array( $view['args'] ) ) ? $view['args'] : array();
		$labels = ( isset( $view['labels'] ) && is_array( $view['labels'] ) ) ? $view['labels'] : array();

		$cd_style = ( isset( $args['countdown_style'] ) && in_array( $args['countdown_style'], array( 'box', 'ring', 'inline' ), true ) )
			? (string) $args['countdown_style']
			: 'box';

		$label_title     = isset( $labels['title'] ) ? (string) $labels['title'] : '';
		$label_ongoing   = isset( $labels['ongoing'] ) ? (string) $labels['ongoing'] : '';
		$label_now       = ( isset( $labels['now'] ) && '' !== $labels['now'] ) ? (string) $labels['now'] : __( 'Happening now', 'countdown-for-the-events-calendar' );
		$label_ended     = ( isset( $labels['ended'] ) && '' !== $labels['ended'] ) ? (string) $labels['ended'] : __( 'This event has ended.', 'countdown-for-the-events-calendar' );
		$label_ends_in   = isset( $labels['ends_in'] ) ? (string) $labels['ends_in'] : '';
		$label_begins_in = ( isset( $labels['begins_in'] ) && '' !== $labels['begins_in'] ) ? (string) $labels['begins_in'] : __( 'Begins in', 'countdown-for-the-events-calendar' );
		$label_button    = ( isset( $labels['button'] ) && '' !== $labels['button'] ) ? (string) $labels['button'] : __( 'Find out more', 'countdown-for-the-events-calendar' );

		$labels_json = wp_json_encode(
			array(
				'title'      => $label_title,
				'ongoing'    => $label_ongoing,
				'ended'      => $label_ended,
				'now'        => $label_now,
				'beginsIn'   => $label_begins_in,
				'endsIn'     => $label_ends_in,
				'nowClass'   => 'tecc-cd__msg tecc-cd__msg--now',
				'endedClass' => 'tecc-cd__msg tecc-cd__msg--ended',
			)
		);
		if ( ! is_string( $labels_json ) ) {
			$labels_json = '{}';
		}

		$cd_size = isset( $args['design']['countdown_size'] ) ? (int) $args['design']['countdown_size'] : 28;
		$short   = ( 'inline' === $cd_style ) || ( $cd_size > 0 && $cd_size <= 20 );
		if ( $short ) {
			$units = array(
				'days'    => _x( 'D', 'days unit abbreviation', 'countdown-for-the-events-calendar' ),
				'hours'   => _x( 'H', 'hours unit abbreviation', 'countdown-for-the-events-calendar' ),
				'minutes' => _x( 'M', 'minutes unit abbreviation', 'countdown-for-the-events-calendar' ),
				'seconds' => _x( 'S', 'seconds unit abbreviation', 'countdown-for-the-events-calendar' ),
			);
		} else {
			$units = array(
				'days'    => __( 'days', 'countdown-for-the-events-calendar' ),
				'hours'   => __( 'hours', 'countdown-for-the-events-calendar' ),
				'minutes' => __( 'min', 'countdown-for-the-events-calendar' ),
				'seconds' => __( 'sec', 'countdown-for-the-events-calendar' ),
			);
		}
		if ( empty( $args['show_seconds'] ) ) {
			unset( $units['seconds'] );
		}

		$width = isset( $args['design']['width'] ) ? $args['design']['width'] : 'auto';

		return array(
			'instance_id'       => ( isset( $view['instance_id'] ) && '' !== $view['instance_id'] ) ? (string) $view['instance_id'] : uniqid( 'tecc-' ),
			'style_attr'        => isset( $view['style_attr'] ) ? (string) $view['style_attr'] : '',
			'next_json'         => isset( $view['next_json'] ) ? (string) $view['next_json'] : '',
			'design_class'      => isset( $view['design_classes'] ) ? (string) $view['design_classes'] : '',
			'ongoing'           => ! empty( $view['ongoing'] ),
			'cd_style'          => $cd_style,
			'show_image'        => ! empty( $args['show_image'] ),
			'show_venue'        => ! empty( $args['show_venue'] ),
			'show_button'       => ! empty( $args['show_button'] ),
			'show_cost'         => ! empty( $args['show_cost'] ),
			'show_divider'      => ! empty( $args['show_divider'] ),
			'show_timer_label'  => isset( $args['show_timer_label'] ) ? ! empty( $args['show_timer_label'] ) : true,
			'show_status'       => isset( $args['show_status'] ) ? ! empty( $args['show_status'] ) : true,
			'width_class'       => ( 'auto' === $width ) ? 'tecc-cd--w-auto' : 'tecc-cd--w-fixed',
			'label_title'       => $label_title,
			'label_ongoing'     => $label_ongoing,
			'label_now'         => $label_now,
			'label_ended'       => $label_ended,
			'label_ends_in'     => $label_ends_in,
			'label_begins_in'   => $label_begins_in,
			'label_button'      => $label_button,
			'labels_json'       => $labels_json,
			'units'             => $units,
			'now'               => time(),
		);
	}

	/**
	 * Per-event timer facts and buffered countdown box HTML.
	 *
	 * @param array $event Event row from the view.
	 * @param array $ctx   From tecc_tpl_design_context().
	 * @return array
	 */
	function tecc_tpl_event_timer( array $event, array $ctx ) {
		$start = isset( $event['start'] ) ? (int) $event['start'] : 0;
		$end   = isset( $event['end'] ) ? (int) $event['end'] : 0;
		$state = isset( $event['state'] ) ? (string) $event['state'] : 'before';
		if ( ! in_array( $state, array( 'before', 'during', 'after' ), true ) ) {
			$state = 'before';
		}

		$now = isset( $ctx['now'] ) ? (int) $ctx['now'] : time();
		if ( 'before' === $state ) {
			$total_days = max( 1, (int) ceil( ( $start - $now ) / 86400 ) );
		} else {
			$total_days = max( 1, (int) ceil( ( $end - $start ) / 86400 ) );
		}

		$target    = ( 'before' === $state ) ? $start : $end;
		$remaining = max( 0, $target - $now );
		$raw       = array(
			'days'    => (int) floor( $remaining / 86400 ),
			'hours'   => (int) floor( ( $remaining % 86400 ) / 3600 ),
			'minutes' => (int) floor( ( $remaining % 3600 ) / 60 ),
			'seconds' => (int) ( $remaining % 60 ),
		);
		$digits = array(
			'days'    => sprintf( '%02d', $raw['days'] ),
			'hours'   => sprintf( '%02d', $raw['hours'] ),
			'minutes' => sprintf( '%02d', $raw['minutes'] ),
			'seconds' => sprintf( '%02d', $raw['seconds'] ),
		);
		$ring = array(
			'days'    => sprintf( '%.4f', min( 1, $raw['days'] / $total_days ) ),
			'hours'   => sprintf( '%.4f', $raw['hours'] / 24 ),
			'minutes' => sprintf( '%.4f', $raw['minutes'] / 60 ),
			'seconds' => sprintf( '%.4f', $raw['seconds'] / 60 ),
		);

		$cd_style = isset( $ctx['cd_style'] ) ? $ctx['cd_style'] : 'box';
		$units    = ( isset( $ctx['units'] ) && is_array( $ctx['units'] ) ) ? $ctx['units'] : array();
		$ongoing  = ! empty( $ctx['ongoing'] );

		ob_start();
		?>
<div class="tecc-cd__timer" aria-live="off">
	<?php foreach ( $units as $unit => $unit_label ) : ?>
	<div class="tecc-cd__unit" data-tecc-unit-wrap="<?php echo esc_attr( $unit ); ?>">
		<?php if ( 'ring' === $cd_style ) : ?>
		<span class="tecc-cd__ring" data-tecc-ring="<?php echo esc_attr( $unit ); ?>" style="--tecc-ring:<?php echo esc_attr( $ring[ $unit ] ); ?>">
			<span class="tecc-cd__num" data-tecc-unit="<?php echo esc_attr( $unit ); ?>"><?php echo esc_html( $digits[ $unit ] ); ?></span>
		</span>
		<?php else : ?>
		<span class="tecc-cd__num" data-tecc-unit="<?php echo esc_attr( $unit ); ?>"><?php echo esc_html( $digits[ $unit ] ); ?></span>
		<?php endif; ?>
		<span class="tecc-cd__label"><?php echo esc_html( $unit_label ); ?></span>
	</div>
	<?php endforeach; ?>
</div>
		<?php
		$box_html = ob_get_clean();

		if ( 'before' === $state ) {
			$kicker      = isset( $ctx['label_title'] ) ? (string) $ctx['label_title'] : '';
			$timer_label = isset( $ctx['label_begins_in'] ) ? (string) $ctx['label_begins_in'] : '';
		} elseif ( 'during' === $state ) {
			$kicker      = isset( $ctx['label_ongoing'] ) ? (string) $ctx['label_ongoing'] : '';
			$timer_label = $ongoing && isset( $ctx['label_ends_in'] ) ? (string) $ctx['label_ends_in'] : '';
		} else {
			$kicker      = isset( $ctx['label_ended'] ) ? (string) $ctx['label_ended'] : '';
			$timer_label = '';
		}

		return array(
			'start'          => $start,
			'end'            => $end,
			'state'          => $state,
			'title'          => isset( $event['title'] ) ? (string) $event['title'] : '',
			'link'           => isset( $event['link'] ) ? (string) $event['link'] : '',
			'date'           => isset( $event['date'] ) ? (string) $event['date'] : '',
			'venue'          => isset( $event['venue'] ) ? (string) $event['venue'] : '',
			'cost'           => isset( $event['cost'] ) ? (string) $event['cost'] : '',
			'image'          => isset( $event['image'] ) ? (string) $event['image'] : '',
			'all_day'        => ! empty( $event['all_day'] ),
			'total_days'     => $total_days,
			'box_html'       => $box_html,
			'kicker'         => $kicker,
			'show_box_live'  => ( 'before' === $state ) || ( 'during' === $state && $ongoing ),
			'timer_label'    => $timer_label,
			'endsin_hidden'  => ( '' === $timer_label ),
		);
	}

	/**
	 * Linked event title markup.
	 *
	 * @param string $title Event title.
	 * @param string $link  Event URL.
	 * @return string
	 */
	function tecc_tpl_title_html( $title, $link ) {
		ob_start();
		?>
		<h3 class="tecc-cd__title">
			<?php if ( '' !== $link ) : ?>
				<a class="tecc-cd__title-link" href="<?php echo esc_url( $link ); ?>" data-tecc-title data-tecc-link><?php echo esc_html( $title ); ?></a>
			<?php else : ?>
				<span data-tecc-title><?php echo esc_html( $title ); ?></span>
			<?php endif; ?>
		</h3>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Date / venue / cost meta row.
	 *
	 * @param array $event From tecc_tpl_event_timer().
	 * @param array $ctx   From tecc_tpl_design_context().
	 * @return string
	 */
	function tecc_tpl_meta_html( array $event, array $ctx ) {
		ob_start();
		?>
		<div class="tecc-cd__meta">
			<?php if ( '' !== $event['date'] ) : ?>
				<span class="tecc-cd__meta-item tecc-cd__date"><svg class="tecc-cd__icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><rect x="2" y="3" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M2 6.2h12M5.4 1.6v2.6M10.6 1.6v2.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg><span><?php echo esc_html( $event['date'] ); ?></span></span>
			<?php endif; ?>
			<?php if ( ! empty( $ctx['show_venue'] ) && '' !== $event['venue'] ) : ?>
				<span class="tecc-cd__meta-item tecc-cd__venue"><svg class="tecc-cd__icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M8 14.5S12.5 10 12.5 6.3A4.5 4.5 0 0 0 3.5 6.3C3.5 10 8 14.5 8 14.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><circle cx="8" cy="6.2" r="1.6" stroke="currentColor" stroke-width="1.3"/></svg><span><?php echo esc_html( $event['venue'] ); ?></span></span>
			<?php endif; ?>
			<?php if ( ! empty( $ctx['show_cost'] ) && '' !== $event['cost'] ) : ?>
				<span class="tecc-cd__meta-item tecc-cd__cost"><svg class="tecc-cd__icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M2 5.6A1.6 1.6 0 0 1 3.6 4h8.8A1.6 1.6 0 0 1 14 5.6a1.4 1.4 0 0 0 0 2.8v2A1.6 1.6 0 0 1 12.4 12H3.6A1.6 1.6 0 0 1 2 10.4v-2a1.4 1.4 0 0 0 0-2.8Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg><span><?php echo esc_html( $event['cost'] ); ?></span></span>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Live countdown region + optional rollover &lt;template&gt;.
	 *
	 * @param array  $event     From tecc_tpl_event_timer().
	 * @param array  $ctx       From tecc_tpl_design_context().
	 * @param string $next_json Rollover JSON ('' to omit template).
	 * @return string
	 */
	function tecc_tpl_region_html( array $event, array $ctx, $next_json = '' ) {
		ob_start();
		?>
		<div class="tecc-cd__region" data-tecc-region>
			<?php if ( ! empty( $event['show_box_live'] ) ) : ?>
				<?php echo $event['box_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in tecc_tpl_event_timer(). ?>
			<?php elseif ( 'during' === $event['state'] ) : ?>
				<div class="tecc-cd__msg tecc-cd__msg--now"><?php echo esc_html( $ctx['label_now'] ); ?></div>
			<?php else : ?>
				<div class="tecc-cd__msg tecc-cd__msg--ended"><?php echo esc_html( $ctx['label_ended'] ); ?></div>
			<?php endif; ?>
			<?php if ( empty( $event['show_box_live'] ) && '' !== $next_json ) : ?>
				<template data-tecc-box><?php echo $event['box_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in tecc_tpl_event_timer(). ?></template>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * data-* / style attributes for a countdown instance wrapper.
	 *
	 * @param array $event From tecc_tpl_event_timer().
	 * @param array $ctx   From tecc_tpl_design_context().
	 * @param array $extra Optional overrides: next_json, style_attr.
	 * @return string Attribute HTML (no leading space on first attr — caller adds spacing).
	 */
	function tecc_tpl_wrapper_attrs( array $event, array $ctx, array $extra = array() ) {
		$next  = array_key_exists( 'next_json', $extra ) ? (string) $extra['next_json'] : (string) $ctx['next_json'];
		$style = array_key_exists( 'style_attr', $extra ) ? (string) $extra['style_attr'] : (string) $ctx['style_attr'];

		ob_start();
		?>
        data-tecc="1"
        data-tecc-start="<?php echo esc_attr( (string) $event['start'] ); ?>"
        data-tecc-end="<?php echo esc_attr( (string) $event['end'] ); ?>"
        data-tecc-state="<?php echo esc_attr( $event['state'] ); ?>"
        data-tecc-labels="<?php echo esc_attr( $ctx['labels_json'] ); ?>"
        data-tecc-total-days="<?php echo esc_attr( (string) $event['total_days'] ); ?>"
        <?php if ( ! empty( $ctx['ongoing'] ) ) : ?>
        data-tecc-ongoing="1"
        <?php endif; ?>
        <?php if ( '' !== $next ) : ?>
        data-tecc-next="<?php echo esc_attr( $next ); ?>"
        <?php endif; ?>
        <?php if ( ! empty( $event['all_day'] ) ) : ?>
        data-tecc-allday="1"
        <?php endif; ?>
        <?php if ( '' !== $style ) : ?>
        style="<?php echo esc_attr( $style ); ?>"
        <?php endif; ?>
		<?php
		return trim( (string) ob_get_clean() );
	}
}