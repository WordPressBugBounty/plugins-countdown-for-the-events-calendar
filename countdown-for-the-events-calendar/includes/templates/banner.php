<?php
/**
 * Countdown template partial: Banner (flexible design engine).
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

$ctx   = tecc_tpl_design_context( $tecc_view );
$event = tecc_tpl_event_timer( $tecc_view['events'][0], $ctx );

$classes = trim( 'tecc-cd tecc-cd--banner ' . $ctx['width_class'] . ' ' . $ctx['design_class'] );
?>
<div
	id="<?php echo esc_attr( $ctx['instance_id'] ); ?>"
	class="<?php echo esc_attr( $classes ); ?>"
	<?php echo tecc_tpl_wrapper_attrs( $event, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>
>
	<div class="tecc-cd__banner-inner">
	<?php if ( $ctx['show_image'] && '' !== $event['image'] ) : ?>
		<div class="tecc-cd__media"><?php echo wp_kses_post( $event['image'] ); ?></div>
	<?php endif; ?>

	<div class="tecc-cd__body">
		<div class="tecc-cd__banner-main">
		<?php if ( $ctx['show_status'] && '' !== $event['kicker'] ) : ?>
			<span class="tecc-cd__kicker" data-tecc-kicker><?php echo esc_html( $event['kicker'] ); ?></span>
		<?php endif; ?>

		<?php echo tecc_tpl_title_html( $event['title'], $event['link'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>

		<?php if ( $ctx['show_divider'] ) : ?>
		<span class="tecc-cd__accent" aria-hidden="true"></span>
		<?php endif; ?>

		<?php echo tecc_tpl_meta_html( $event, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>

		</div><!-- /.tecc-cd__banner-main -->

		<div class="tecc-cd__banner-aside">
		<div class="tecc-cd__banner-timer">
		<?php if ( $ctx['show_timer_label'] ) : ?>
		<span class="tecc-cd__endsin" data-tecc-endsin<?php echo $event['endsin_hidden'] ? ' hidden' : ''; ?>><?php echo esc_html( $event['timer_label'] ); ?></span>
		<?php endif; ?>

		<?php echo tecc_tpl_region_html( $event, $ctx, $ctx['next_json'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>
		</div><!-- /.tecc-cd__banner-timer -->

		<?php if ( $ctx['show_button'] && '' !== $event['link'] ) : ?>
			<a class="tecc-cd__button" href="<?php echo esc_url( $event['link'] ); ?>" data-tecc-link><?php echo esc_html( $ctx['label_button'] ); ?></a>
		<?php endif; ?>
		</div><!-- /.tecc-cd__banner-aside -->
	</div>
	</div><!-- /.tecc-cd__banner-inner -->
</div>