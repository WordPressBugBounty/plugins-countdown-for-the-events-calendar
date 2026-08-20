<?php
/**
 * Countdown template partial: Card (flexible design engine).
 *
 * One event = single card; two or more = carousel. Shared helpers live in
 * tecc-template-helpers.php.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 *
 * @var array $tecc_view View model from Renderer::build_view().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
//phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! isset( $tecc_view ) || ! is_array( $tecc_view ) || empty( $tecc_view['events'][0] ) || ! is_array( $tecc_view['events'][0] ) ) {
	return;
}

$ctx = tecc_tpl_design_context( $tecc_view );

$events = array();
foreach ( $tecc_view['events'] as $maybe ) {
	if ( is_array( $maybe ) ) {
		$events[] = $maybe;
	}
}
$is_carousel = count( $events ) > 1;
if ( $is_carousel ) {
	$ctx['next_json'] = ''; // Carousel already shows multiple events — no rollover queue.
}

$cards = array();
foreach ( $events as $row ) {
	$event = tecc_tpl_event_timer( $row, $ctx );

	ob_start();
	?>
	<?php if ( $ctx['show_image'] && '' !== $event['image'] ) : ?>
		<div class="tecc-cd__media"><?php echo wp_kses_post( $event['image'] ); ?></div>
	<?php endif; ?>

	<div class="tecc-cd__body">
		<?php if ( $ctx['show_status'] && '' !== $event['kicker'] ) : ?>
			<span class="tecc-cd__kicker" data-tecc-kicker><?php echo esc_html( $event['kicker'] ); ?></span>
		<?php endif; ?>

		<?php echo tecc_tpl_title_html( $event['title'], $event['link'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>

		<?php echo tecc_tpl_meta_html( $event, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>

		<?php if ( $ctx['show_divider'] ) : ?>
			<div class="tecc-cd__divider" role="separator"></div>
		<?php endif; ?>

		<?php if ( $ctx['show_timer_label'] ) : ?>
		<span class="tecc-cd__endsin" data-tecc-endsin<?php echo $event['endsin_hidden'] ? ' hidden' : ''; ?>><?php echo esc_html( $event['timer_label'] ); ?></span>
		<?php endif; ?>

		<?php echo tecc_tpl_region_html( $event, $ctx, $ctx['next_json'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>

		<?php if ( $ctx['show_button'] && '' !== $event['link'] ) : ?>
			<a class="tecc-cd__button" href="<?php echo esc_url( $event['link'] ); ?>" data-tecc-link><?php echo esc_html( $ctx['label_button'] ); ?></a>
		<?php endif; ?>
	</div>
	<?php
	$cards[] = array(
		'html'  => ob_get_clean(),
		'event' => $event,
	);
}

if ( empty( $cards ) ) {
	return;
}

if ( ! $is_carousel ) :
	$card    = $cards[0];
	$classes = trim( 'tecc-cd tecc-cd--card ' . $ctx['width_class'] . ' ' . $ctx['design_class'] );
	?>
<div
	id="<?php echo esc_attr( $ctx['instance_id'] ); ?>"
	class="<?php echo esc_attr( $classes ); ?>"
	<?php echo tecc_tpl_wrapper_attrs( $card['event'], $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>
>
<?php echo $card['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped helpers above. ?>
</div>
<?php else : ?>
<?php
	$carousel_classes = trim( 'tecc-cd tecc-cd--carousel ' . $ctx['width_class'] . ' ' . $ctx['design_class'] );
	$card_classes     = trim( 'tecc-cd tecc-cd--card ' . $ctx['design_class'] );
	$slide_count      = count( $cards );
	$carousel_label   = ( '' !== $ctx['label_title'] ) ? $ctx['label_title'] : __( 'Upcoming events', 'countdown-for-the-events-calendar' );
?>
<div
	id="<?php echo esc_attr( $ctx['instance_id'] ); ?>"
	class="<?php echo esc_attr( $carousel_classes ); ?>"
	role="region"
	aria-roledescription="carousel"
	aria-label="<?php echo esc_attr( $carousel_label ); ?>"
	data-tecc-carousel
	<?php if ( '' !== $ctx['style_attr'] ) : ?>
	style="<?php echo esc_attr( $ctx['style_attr'] ); ?>"
	<?php endif; ?>
>
	<div class="tecc-cd__viewport" data-tecc-viewport>
		<div class="tecc-cd__track" data-tecc-track>
			<?php foreach ( $cards as $i => $card ) : ?>
				<?php
				$slide_label = sprintf(
					/* translators: 1: slide number, 2: total number of slides. */
					__( '%1$d of %2$d', 'countdown-for-the-events-calendar' ),
					$i + 1,
					$slide_count
				);
				?>
			<div class="tecc-cd__slide" data-tecc-slide role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( $slide_label ); ?>">
				<div
					id="<?php echo esc_attr( $ctx['instance_id'] . '-s' . $i ); ?>"
					class="<?php echo esc_attr( $card_classes ); ?>"
					<?php echo tecc_tpl_wrapper_attrs( $card['event'], $ctx, array( 'next_json' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>
				>
<?php echo $card['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped helpers above. ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="tecc-cd__controls">
		<span class="tecc-cd__nav tecc-cd__nav--prev" data-tecc-prev role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Previous event', 'countdown-for-the-events-calendar' ); ?>">&lsaquo;</span>
		<div class="tecc-cd__dots" data-tecc-dots role="tablist" aria-label="<?php esc_attr_e( 'Choose event', 'countdown-for-the-events-calendar' ); ?>"></div>
		<span class="tecc-cd__nav tecc-cd__nav--next" data-tecc-next role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Next event', 'countdown-for-the-events-calendar' ); ?>">&rsaquo;</span>
	</div>
</div>
<?php endif; ?>