<?php
/**
 * The Events Calendar compatibility layer — the ONLY place that
 * knows TEC internals.
 *
 * Contract (verified against live TEC 6.17 / Custom Tables V1):
 * - Query ONLY through the occurrence-aware ORM (`tribe_events()`); never a
 *   raw `_EventStartDate` meta_query (misses recurring occurrences).
 * - Epochs come from the true-UTC values: the decorated post's
 *   `dates->start_utc` when present, else `_EventStartDateUTC` meta parsed
 *   as UTC. Forbidden: current_time('timestamp'), strtotime() on local
 *   strings, tribe_get_start_date(...,'U').
 * - Visibility guard: only published, non-password tribe_events
 *   are ever surfaced.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Tec;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Tec\Tec' ) ) {

	/**
	 * TEC availability gate + occurrence-aware queries + UTC epoch helpers.
	 *
	 * @since 2.0.0
	 */
	final class Tec {

		/**
		 * Whether a compatible The Events Calendar is active.
		 *
		 * Hard gate for every TEC call (guardrail 1): degrade to nothing,
		 * never fatal, when TEC is absent or older than TECC_MIN_TEC_VERSION.
		 *
		 * @since 2.0.0
		 * @return bool
		 */
		public static function available() {
			if ( ! class_exists( 'Tribe__Events__Main' ) || ! function_exists( 'tribe_events' ) || ! function_exists( 'tribe_get_events' ) ) {
				return false;
			}

			if ( defined( 'TECC_MIN_TEC_VERSION' ) && defined( 'Tribe__Events__Main::VERSION' ) ) {
				return version_compare( \Tribe__Events__Main::VERSION, TECC_MIN_TEC_VERSION, '>=' );
			}

			return true;
		}

		/**
		 * Next upcoming (or currently running) occurrences, soonest first.
		 *
		 * Uses `ends_after now` so an in-progress event is surfaced — the
		 * tri-state timer needs it for the "Happening now" state.
		 *
		 * @since 2.0.0
		 * @param int   $limit  Max occurrences (1–5).
		 * @param array $filter Optional {category:[],tag:[]} slug lists.
		 * @return \WP_Post[] Visible occurrences (possibly empty).
		 */
		public static function upcoming( $limit = 1, array $filter = array(), $ongoing = true ) {
			if ( ! self::available() ) {
				return array();
			}

			$limit = max( 1, min( 5, (int) $limit ) );

			// ongoing: include in-progress events (ends_after now); otherwise
			// only strictly-future events (starts_after now).
			$query = tribe_events()
				->where( $ongoing ? 'ends_after' : 'starts_after', 'now' )
				->order_by( 'event_date', 'ASC' )
				->per_page( $limit );

			if ( ! empty( $filter['category'] ) ) {
				$query = $query->where( 'event_category', self::category_slugs( (array) $filter['category'] ) );
			}
			if ( ! empty( $filter['tag'] ) ) {
				$query = $query->where( 'tag', array_map( 'sanitize_title', (array) $filter['tag'] ) );
			}

			$events = $query->all();

			return array_values( array_filter( (array) $events, array( __CLASS__, 'visible' ) ) );
		}

		/**
		 * Resolve category tokens (ids OR slugs) to slugs for the ORM.
		 *
		 * @since 2.0.0
		 * @param array $tokens Term ids or slugs.
		 * @return string[] Slugs.
		 */
		private static function category_slugs( array $tokens ) {
			$slugs = array();

			foreach ( $tokens as $token ) {
				$token = is_string( $token ) ? trim( $token ) : $token;
				if ( is_numeric( $token ) ) {
					$term = get_term( (int) $token, 'tribe_events_cat' );
					if ( $term && ! is_wp_error( $term ) && isset( $term->slug ) ) {
						$slugs[] = $term->slug;
					}
					continue;
				}
				$slug = sanitize_title( (string) $token );
				if ( '' !== $slug ) {
					$slugs[] = $slug;
				}
			}

			return array_values( array_unique( $slugs ) );
		}

		/**
		 * Upcoming published events for admin pickers (id + title only).
		 *
		 * @since 2.0.0
		 * @param int $per_page Max events to list.
		 * @return array[] Arrays of {id:int, title:string}.
		 */
		public static function picker_events( $per_page = 100, $search = '', $include = array() ) {
			if ( ! self::available() ) {
				return array();
			}

			$per_page = max( 1, min( 200, (int) $per_page ) );
			$search   = trim( (string) $search );
			$include  = array_slice( array_values( array_filter( array_map( 'absint', (array) $include ) ) ), 0, 50 );
			$rows     = array();
			$seen     = array();

			// ends_after (not starts_after): an in-progress event must stay
			// selectable, matching upcoming()/selected() semantics.
			$repo = tribe_events()
				->where( 'ends_after', 'now' )
				->order_by( 'event_date', 'ASC' )
				->per_page( $per_page );

			if ( '' !== $search ) {
				$repo = $repo->search( $search );
			}

			foreach ( array_filter( (array) $repo->all(), array( __CLASS__, 'visible' ) ) as $event ) {
				$rows[]              = array(
					'id'    => (int) $event->ID,
					'title' => (string) $event->post_title,
				);
				$seen[ (int) $event->ID ] = true;
			}

			// Always resolve labels for already-selected ids, even a past event
			// the time-window query would drop — otherwise select2 shows a bare
			// id chip for a valid saved selection.
			$missing = array_diff( $include, array_keys( $seen ) );
			if ( ! empty( $missing ) ) {
				$picked = tribe_events()
					->where( 'post__in', $missing )
					->per_page( count( $missing ) )
					->all();
				foreach ( array_filter( (array) $picked, array( __CLASS__, 'visible' ) ) as $event ) {
					if ( empty( $seen[ (int) $event->ID ] ) ) {
						$rows[]                   = array(
							'id'    => (int) $event->ID,
							'title' => (string) $event->post_title,
						);
						$seen[ (int) $event->ID ] = true;
					}
				}
			}

			return $rows;
		}

		/**
		 * Event categories for the admin picker (id + name).
		 *
		 * @since 2.0.0
		 * @return array[] Arrays of {id:int, name:string}.
		 */
		public static function picker_categories( $search = '', $include = array() ) {
			if ( ! taxonomy_exists( 'tribe_events_cat' ) ) {
				return array();
			}

			$search = trim( (string) $search );
			$args   = array(
				'taxonomy'   => 'tribe_events_cat',
				'hide_empty' => false,
				'number'     => 200,
			);
			if ( '' !== $search ) {
				$args['search'] = $search;
			}

			$terms   = get_terms( $args );
			$include = array_slice( array_values( array_filter( array_map( 'absint', (array) $include ) ) ), 0, 50 );

			if ( ! is_array( $terms ) ) {
				return array();
			}

			$rows = array();
			$seen = array();
			foreach ( $terms as $term ) {
				if ( isset( $term->term_id, $term->name ) ) {
					$rows[]                       = array(
						'id'   => (int) $term->term_id,
						'name' => (string) $term->name,
					);
					$seen[ (int) $term->term_id ] = true;
				}
			}

			// Resolve labels for already-selected term ids a search would omit.
			foreach ( array_diff( $include, array_keys( $seen ) ) as $term_id ) {
				$term = get_term( $term_id, 'tribe_events_cat' );
				if ( $term && ! is_wp_error( $term ) && isset( $term->name ) ) {
					$rows[]              = array(
						'id'   => (int) $term_id,
						'name' => (string) $term->name,
					);
					$seen[ $term_id ] = true;
				}
			}

			return $rows;
		}

		/**
		 * Resolve an explicitly selected event id.
		 *
		 * A recurring event chosen by id must count to its next occurrence
		 * >= now (in-progress counts). If every occurrence is past, the
		 * post itself is returned so the template renders the ended state.
		 *
		 * @since 2.0.0
		 * @param int $event_id Event (or occurrence) id.
		 * @return \WP_Post|null Visible event, or null (never leaks drafts).
		 */
		public static function selected( $event_id, $ongoing = true ) {
			if ( ! self::available() ) {
				return null;
			}

			$event_id = absint( $event_id );
			if ( ! $event_id ) {
				return null;
			}

			$next = tribe_events()
				->where( 'post__in', array( $event_id ) )
				->where( $ongoing ? 'ends_after' : 'starts_after', 'now' )
				->order_by( 'event_date', 'ASC' )
				->per_page( 1 )
				->all();

			if ( ! empty( $next ) && self::visible( $next[0] ) ) {
				return $next[0];
			}

			// All occurrences past: ended state for the event itself.
			$post = get_post( $event_id );

			return ( $post && self::visible( $post ) ) ? $post : null;
		}

		/**
		 * Visibility guard: publish + tribe_events + not
		 * password-protected. A non-qualifying post renders the empty
		 * state — never a leaked title/date.
		 *
		 * @since 2.0.0
		 * @param mixed $post Candidate post.
		 * @return bool
		 */
		public static function visible( $post ) {
			return $post instanceof \WP_Post
				&& 'tribe_events' === $post->post_type
				&& 'publish' === $post->post_status
				&& ! post_password_required( $post );
		}

		/**
		 * Absolute UTC start epoch for an event/occurrence.
		 *
		 * @since 2.0.0
		 * @param \WP_Post|int $event Event post or id.
		 * @return int|null Epoch seconds, or null when unresolvable.
		 */
		public static function start_epoch( $event ) {
			return self::epoch( $event, 'start' );
		}

		/**
		 * Absolute UTC end epoch. Falls back to the start epoch when the
		 * end value is missing (zero-length event, never negative).
		 *
		 * @since 2.0.0
		 * @param \WP_Post|int $event Event post or id.
		 * @return int|null
		 */
		public static function end_epoch( $event ) {
			$end = self::epoch( $event, 'end' );

			if ( null === $end ) {
				return self::epoch( $event, 'start' );
			}

			$start = self::epoch( $event, 'start' );
			if ( null !== $start && $end < $start ) {
				return $start;
			}

			return $end;
		}

		/**
		 * Shared epoch resolution: decorated `dates` object first, UTC meta
		 * second, local meta + event timezone as the last resort.
		 *
		 * @since 2.0.0
		 * @param \WP_Post|int $event Event post or id.
		 * @param string       $which 'start' or 'end'.
		 * @return int|null
		 */
		private static function epoch( $event, $which ) {
			$post = $event instanceof \WP_Post ? $event : get_post( absint( $event ) );
			if ( ! $post ) {
				return null;
			}

			// 1. ORM-decorated post: dates->start_utc / end_utc (DateTime).
			if ( isset( $post->dates ) && is_object( $post->dates ) ) {
				$prop = $which . '_utc';
				if ( isset( $post->dates->{$prop} ) && $post->dates->{$prop} instanceof \DateTimeInterface ) {
					return (int) $post->dates->{$prop}->getTimestamp();
				}
			}

			// 2. True-UTC meta (single events + CT1 occurrence redirection).
			$utc = get_post_meta( $post->ID, 'start' === $which ? '_EventStartDateUTC' : '_EventEndDateUTC', true );
			if ( is_string( $utc ) && '' !== $utc ) {
				$ts = self::parse_utc( $utc, 'UTC' );
				if ( null !== $ts ) {
					return $ts;
				}
			}

			// 3. Local meta + per-event timezone (legacy events without UTC meta).
			$local = get_post_meta( $post->ID, 'start' === $which ? '_EventStartDate' : '_EventEndDate', true );
			if ( is_string( $local ) && '' !== $local ) {
				$tz = get_post_meta( $post->ID, '_EventTimezone', true );
				if ( ! is_string( $tz ) || '' === $tz ) {
					$tz = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
				}

				return self::parse_utc( $local, $tz );
			}

			return null;
		}

		/**
		 * Parse a `Y-m-d H:i:s` string in a given timezone to a UTC epoch.
		 *
		 * @since 2.0.0
		 * @param string $datetime Date-time string.
		 * @param string $timezone Timezone identifier or UTC offset.
		 * @return int|null
		 */
		private static function parse_utc( $datetime, $timezone ) {
			try {
				$dt = new \DateTimeImmutable( $datetime, new \DateTimeZone( $timezone ) );

				return (int) $dt->getTimestamp();
			} catch ( \Exception $e ) {
				return null;
			}
		}

		/**
		 * Whether the event is an all-day event.
		 *
		 * @since 2.0.0
		 * @param \WP_Post|int $event Event post or id.
		 * @return bool
		 */
		public static function is_all_day( $event ) {
			$id = $event instanceof \WP_Post ? $event->ID : absint( $event );

			return $id && in_array( get_post_meta( $id, '_EventAllDay', true ), array( 'yes', '1' ), true );
		}

		/**
		 * Front-end permalink for the event.
		 *
		 * @since 2.0.0
		 * @param \WP_Post|int $event Event post or id.
		 * @return string
		 */
		public static function link( $event ) {
			if ( function_exists( 'tribe_get_event_link' ) ) {
				$link = tribe_get_event_link( $event );
				if ( is_string( $link ) && '' !== $link ) {
					return $link;
				}
			}

			$id = $event instanceof \WP_Post ? $event->ID : absint( $event );

			return $id ? (string) get_permalink( $id ) : '';
		}

		/**
		 * Plain-text venue address ('' when the event has no usable venue).
		 *
		 * @since 2.0.0
		 * @param \WP_Post|int $event Event post or id.
		 * @return string
		 */
		public static function venue_address( $event ) {
			if ( ! function_exists( 'tribe_get_venue_details' ) ) {
				return '';
			}

			$id      = $event instanceof \WP_Post ? $event->ID : absint( $event );
			$details = $id ? tribe_get_venue_details( $id ) : false;

			if ( ! is_array( $details ) || empty( $details['address'] ) ) {
				return '';
			}

			$address = wp_strip_all_tags( (string) $details['address'] );

			// Legacy check: an address that is only whitespace counts as none.
			if ( '' === trim( preg_replace( '/\s+/', '', $address ) ) ) {
				return '';
			}

			return $address;
		}

		/**
		 * Formatted event cost ('' when free/unknown or unavailable).
		 *
		 * @since 2.0.0
		 * @param \WP_Post|int $event Event post or id.
		 * @return string
		 */
		public static function cost( $event ) {
			if ( ! function_exists( 'tribe_get_cost' ) ) {
				return '';
			}

			$id   = $event instanceof \WP_Post ? $event->ID : absint( $event );
			$cost = $id ? tribe_get_cost( $id, true ) : '';

			return is_string( $cost ) ? trim( wp_strip_all_tags( $cost ) ) : '';
		}

		/**
		 * Featured-image HTML for the event ('' when none).
		 *
		 * @since 2.0.0
		 * @param \WP_Post|int $event Event post or id.
		 * @return string
		 */
		public static function featured_image_html( $event ) {
			if ( ! function_exists( 'tribe_event_featured_image' ) ) {
				return '';
			}

			$id = $event instanceof \WP_Post ? $event->ID : absint( $event );
			if ( ! $id || ! tribe_event_featured_image( $id ) ) {
				return '';
			}

			$html = tribe_event_featured_image( $id, 'full', false );

			return is_string( $html ) ? $html : '';
		}

		/**
		 * Localized, display-formatted start date (site setting, NOT for math).
		 *
		 * @since 2.0.0
		 * @param \WP_Post|int $event Event post or id.
		 * @return string
		 */
		public static function formatted_start_date( $event ) {
			if ( ! function_exists( 'tribe_get_start_date' ) ) {
				return '';
			}

			$format = function_exists( 'tribe_get_option' ) ? tribe_get_option( 'dateWithYearFormat', 'd F Y' ) : 'd F Y';
			$date   = tribe_get_start_date( $event, false, $format );

			return is_string( $date ) ? $date : '';
		}
	}
}
