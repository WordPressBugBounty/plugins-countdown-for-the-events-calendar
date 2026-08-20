<?php
/**
 * THE shared renderer — every output method (shortcode, block,
 * Elementor, Display Rules, carousel) funnels here.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Render;

use CoolPlugins\Countdown\Tec\Tec;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Render\Renderer' ) ) {

	/**
	 * Resolves the event(s) and emits markup through a template partial.
	 *
	 * @since 2.0.0
	 */
	final class Renderer {

		/**
		 * Render a countdown instance.
		 *
		 * @since 2.0.0
		 * @param array $args Args from any adapter (normalized here).
		 * @return string HTML ('' when TEC is absent or an auto rule matches nothing).
		 */
		public static function render( array $args ) {
			if ( ! Tec::available() ) {
				return ''; // Hard TEC gate — degrade, never fatal.
			}

			$args   = Args::normalize( $args );
			$events = Event_Source::resolve( $args );
			$view   = self::build_view( $args, $events );

			if ( empty( $view['events'] ) ) {
				// Explicit placement -> empty state; auto Display Rule -> ''.
				return $args['auto'] ? '' : self::empty_state();
			}

			Assets::enqueue( $args, count( $view['events'] ) );

			return self::load_template( $args['template'], $view );
		}

		/**
		 * The legacy-faithful no-event message.
		 *
		 * @since 2.0.0
		 * @return string
		 */
		private static function empty_state() {
			return '<div class="tecc-no-event-msz">' . esc_html__( 'There is no upcoming event', 'countdown-for-the-events-calendar' ) . '</div>';
		}

		/**
		 * Build the template view model. Events without a resolvable start
		 * epoch are dropped (visibility/consistency guard).
		 *
		 * @since 2.0.0
		 * @param array $args   Normalized args.
		 * @param array $events Resolved WP_Post events.
		 * @return array The $tecc_view consumed by template partials.
		 */
		private static function build_view( array $args, array $events ) {
			$now  = time();
			$rows = array();

			foreach ( $events as $event ) {
				$start = Tec::start_epoch( $event );
				$end   = Tec::end_epoch( $event );

				if ( null === $start || null === $end ) {
					continue;
				}

				if ( $now < $start ) {
					$state = 'before';
				} elseif ( $now < $end ) {
					$state = 'during';
				} else {
					$state = 'after';
				}

				$rows[] = array(
					'id'      => (int) $event->ID,
					'title'   => (string) $event->post_title,
					'link'    => Tec::link( $event ),
					'start'   => (int) $start,
					'end'     => (int) $end,
					'state'   => $state,
					'date'    => Tec::formatted_start_date( $event->ID ),
					'venue'   => $args['show_venue'] ? Tec::venue_address( $event->ID ) : '',
					'cost'    => $args['show_cost'] ? Tec::cost( $event->ID ) : '',
					'image'   => $args['show_image'] ? Tec::featured_image_html( $event->ID ) : '',
					'all_day' => Tec::is_all_day( $event ),
				);

				if ( count( $rows ) >= $args['limit'] ) {
					break;
				}
			}

			// Combine legacy token vars (classic/minimal) + the design vars
			// (card/banner) into one style attribute — each template reads only
			// the properties it needs.
			$style_attr  = Args::style_attr( $args['tokens'] );
			$design_attr = Args::design_style_attr( $args['design'] );
			if ( '' !== $style_attr && '' !== $design_attr ) {
				$style_attr .= ';' . $design_attr;
			} else {
				$style_attr .= $design_attr;
			}

			return array(
				'args'            => $args,
				'instance_id'     => $args['instance_id'],
				'style_attr'      => $style_attr,
				'design_classes'  => Args::design_classes( $args ),
				'ongoing'         => ! empty( $args['ongoing'] ),
				'events'          => $rows,
				'labels'          => $args['labels'],
				'next_json'       => self::next_json( $args, $rows ),
			);
		}

		/**
		 * Bake the next occurrences for client-side roll-over (non-optional
		 * for auto-upcoming sources so a timer whose event ends advances
		 * without a page reload, even behind a full-page cache).
		 *
		 * @since 2.0.0
		 * @param array $args Normalized args.
		 * @param array $rows Resolved view events.
		 * @return string JSON array or ''.
		 */
		private static function next_json( array $args, array $rows ) {
			$auto_sources = array( 'next-upcoming', 'next-in-context', 'filtered' );

			if ( empty( $rows ) || 1 !== $args['limit'] || ! in_array( $args['source'], $auto_sources, true ) ) {
				return '';
			}

			// The rollover queue must be drawn from the SAME pool the visible
			// event came from. next-in-context resolves its event from the page
			// context (Event_Source::next_in_context), NOT the rule filter (which
			// is empty for that source) — so mirror that here, or a single-event
			// / taxonomy-scoped display would roll over to unrelated site-wide
			// events when it ends.
			$queue_filter = $args['filter'];
			if ( 'next-in-context' === $args['source'] ) {
				$context = isset( $args['context'] ) && is_array( $args['context'] ) ? $args['context'] : array();
				if ( ! empty( $context['single_event_id'] ) ) {
					return ''; // A specific event never rolls over (mirrors 'selected').
				}
				if ( ! empty( $context['tec_categories'] ) ) {
					$queue_filter = array( 'category' => (array) $context['tec_categories'] );
				} elseif ( ! empty( $context['tec_tags'] ) ) {
					$queue_filter = array( 'tag' => (array) $context['tec_tags'] );
				} else {
					return ''; // No context to scope by -> no meaningful queue.
				}
			}

			$queue   = array();
			$current = $rows[0]['id'];

			// Honor show-ongoing so the rollover queue never surfaces an
			// in-progress event the instance was configured to hide.
			foreach ( Tec::upcoming( 5, $queue_filter, ! empty( $args['ongoing'] ) ) as $event ) {
				if ( (int) $event->ID === $current ) {
					continue;
				}

				$start = Tec::start_epoch( $event );
				$end   = Tec::end_epoch( $event );
				if ( null === $start || null === $end || $end <= $rows[0]['end'] ) {
					continue;
				}

				$queue[] = array(
					's'     => (int) $start,
					'e'     => (int) $end,
					'title' => sanitize_text_field( (string) $event->post_title ),
					'link'  => esc_url_raw( Tec::link( $event ) ),
					// Rollover must know if the NEXT occurrence is all-day so
					// data-tecc-allday stays accurate (units stay visible either way).
					'a'     => Tec::is_all_day( $event ) ? 1 : 0,
				);
			}

			if ( empty( $queue ) ) {
				return '';
			}

			$json = wp_json_encode( $queue );

			return is_string( $json ) ? $json : '';
		}

		/**
		 * Load a template partial with the view in scope.
		 *
		 * @since 2.0.0
		 * @param string $template Template id (already enum-validated).
		 * @param array  $tecc_view View model.
		 * @return string
		 */
		private static function load_template( $template, array $tecc_view ) {
			$file = TECC_PLUGIN_DIR . 'includes/templates/' . sanitize_key( $template ) . '.php';

			if ( ! file_exists( $file ) ) {
				$file = TECC_PLUGIN_DIR . 'includes/templates/classic.php';
			}

			if ( ! file_exists( $file ) ) {
				return '';
			}
			require_once TECC_PLUGIN_DIR . 'includes/templates/tecc-template-helpers.php';
			ob_start();
			include $file;

			return (string) ob_get_clean();
		}
	}
}
