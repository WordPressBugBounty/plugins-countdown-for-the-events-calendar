<?php
/**
 * Event source resolver: normalized $args -> 0..N occurrences.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Render;

use CoolPlugins\Countdown\Tec\Tec;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Render\Event_Source' ) ) {

	/**
	 * Resolves which event(s) a countdown shows.
	 *
	 * @since 2.0.0
	 */
	final class Event_Source {

		/**
		 * Resolve events for the given args.
		 *
		 * @since 2.0.0
		 * @param array $args Normalized args (Args::normalize()).
		 * @return \WP_Post[] 0..N visible events/occurrences.
		 */
		public static function resolve( array $args ) {
			$ongoing = ! empty( $args['ongoing'] );

			switch ( $args['source'] ) {

				case 'selected':
					// Multiple explicit ids (Display Rules / carousel): each
					// resolves to its next occurrence, order preserved.
					if ( ! empty( $args['event_ids'] ) ) {
						$events = array();
						foreach ( $args['event_ids'] as $event_id ) {
							$event = Tec::selected( $event_id, $ongoing );
							if ( $event ) {
								$events[] = $event;
							}
							if ( count( $events ) >= $args['limit'] ) {
								break;
							}
						}

						return $events;
					}

					$event = Tec::selected( $args['event_id'], $ongoing );

					return $event ? array( $event ) : array();

				case 'next-upcoming':
					return Tec::upcoming( $args['limit'], array(), $ongoing );

				case 'filtered':
					return Tec::upcoming( $args['limit'], $args['filter'], $ongoing );

				case 'legacy-list':
					return self::legacy_list( $args['event_ids'] );

				case 'next-in-context':
					return self::next_in_context( $args, $ongoing );
			}

			return array();
		}

		/**
		 * Context-aware source:
		 *  - single TEC event page  -> that event's next occurrence
		 *  - category/tag archive   -> next upcoming in that taxonomy
		 *  - anything else          -> site-wide next upcoming
		 *  - still nothing          -> render nothing (caller's auto flag)
		 *
		 * @since 2.0.0
		 * @param array $args Normalized args (context injected by Locations).
		 * @return \WP_Post[]
		 */
		private static function next_in_context( array $args, $ongoing = true ) {
			$context = isset( $args['context'] ) && is_array( $args['context'] ) ? $args['context'] : array();

			if ( ! empty( $context['single_event_id'] ) ) {
				$event = Tec::selected( (int) $context['single_event_id'], $ongoing );

				return $event ? array( $event ) : array();
			}

			if ( ! empty( $context['tec_categories'] ) ) {
				return Tec::upcoming( $args['limit'], array( 'category' => (array) $context['tec_categories'] ), $ongoing );
			}

			if ( ! empty( $context['tec_tags'] ) ) {
				return Tec::upcoming( $args['limit'], array( 'tag' => (array) $context['tec_tags'] ), $ongoing );
			}

			return Tec::upcoming( $args['limit'], array(), $ongoing );
		}

		/**
		 * Legacy-list source: among the EXPLICIT ids, pick the soonest
		 * occurrence with start > now — strictly-future only, limit 1, exact
		 * legacy semantics. All-past -> no event (never the old empty-ID
		 * leftover bug).
		 *
		 * @since 2.0.0
		 * @param int[] $event_ids Explicit legacy list ids.
		 * @return \WP_Post[] Zero or one event.
		 */
		private static function legacy_list( array $event_ids ) {
			if ( empty( $event_ids ) || ! Tec::available() ) {
				return array();
			}

			$now  = time();
			$best = null;

			foreach ( $event_ids as $event_id ) {
				$event = Tec::selected( $event_id );
				if ( ! $event ) {
					continue;
				}

				$start = Tec::start_epoch( $event );
				if ( null === $start || $start <= $now ) {
					continue; // Legacy picked strictly-future starts only.
				}

				if ( null === $best || $start < $best[0] ) {
					$best = array( $start, $event );
				}
			}

			return $best ? array( $best[1] ) : array();
		}
	}
}
