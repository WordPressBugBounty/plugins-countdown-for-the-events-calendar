<?php
/**
 * Display Rules evaluator — PURE logic (no WP calls).
 *
 * Matches a URL-derived context snapshot against the validated rule list and
 * returns at most ONE winning rule per location (first enabled match by
 * priority). Client-side condition types (user_logged_in / is_mobile — the
 * cache-safety carve-out) never decide the server outcome; they are carried
 * on the winner as {require:[], forbid:[]} for the front-end JS.
 *
 * @package CoolPlugins\Countdown
 * @since   2.0.0
 */

namespace CoolPlugins\Countdown\Display;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoolPlugins\Countdown\Display\Rules_Evaluator' ) ) {

	/**
	 * Pure rule matching (golden-master harness-tested).
	 *
	 * @since 2.0.0
	 */
	final class Rules_Evaluator {

		/**
		 * Winning rule per location.
		 *
		 * @since 2.0.0
		 * @param array  $context  URL-derived snapshot (Locations::snapshot()).
		 * @param array  $rules    Validated rules (Rules::sanitize shape).
		 * @param int    $now      Current UTC epoch. Retained for signature/back-
		 *                         compat; unused since scheduling was removed.
		 * @param string $timezone Site timezone identifier. Retained for signature/
		 *                         back-compat; unused since scheduling was removed.
		 * @return array location => {rule: array, client: {require:[], forbid:[]}}
		 */
		public static function winning_rules( array $context, array $rules, $now, $timezone = 'UTC' ) {
			$candidates = array();
			$order      = 0;
			foreach ( $rules as $rule ) {
				if ( empty( $rule['enabled'] ) || empty( $rule['location'] ) ) {
					continue;
				}

				$verdict = self::match( $context, isset( $rule['conditions'] ) ? $rule['conditions'] : array() );
				if ( null === $verdict ) {
					continue;
				}

				$candidates[ $rule['location'] ][] = array(
					'rule'   => $rule,
					'client' => $verdict,
					'order'  => $order++,
				);
			}

			$winners = array();
			foreach ( $candidates as $location => $list ) {
				usort(
					$list,
					function ( $a, $b ) {
						$diff = (int) $a['rule']['priority'] - (int) $b['rule']['priority'];

						// Deterministic tie-break: list order (usort is not a
						// stable sort on the PHP 7.2 floor).
						return 0 !== $diff ? $diff : $a['order'] - $b['order'];
					}
				);

				$primary = $list[0];
				unset( $primary['order'] );

				// A per-visitor-conditional winner also carries the highest
				// fully-unconditional rule as a server-visible baseline; the
				// browser shows exactly one of the layered pair.
				$primary['fallback'] = null;
				$conditional = ! empty( $primary['client']['require'] ) || ! empty( $primary['client']['forbid'] );
				if ( $conditional ) {
					$count = count( $list );
					for ( $i = 1; $i < $count; $i++ ) {
						if ( empty( $list[ $i ]['client']['require'] ) && empty( $list[ $i ]['client']['forbid'] ) ) {
							$fallback = $list[ $i ];
							unset( $fallback['order'] );
							$primary['fallback'] = $fallback;
							break;
						}
					}
				}

				$winners[ $location ] = $primary;
			}

			return $winners;
		}

		/**
		 * Evaluate include/exclude conditions against the context.
		 *
		 * Semantics: show when ANY include matches AND NO exclude matches.
		 * Client-typed conditions are deferred: a client include only makes
		 * the rule a candidate when no server include already matched; client
		 * excludes are always deferred to the browser.
		 *
		 * @since 2.0.0
		 * @param array $context    Context snapshot.
		 * @param array $conditions {include: [], exclude: []}.
		 * @return array|null {require:[], forbid:[]} when candidate, null when rejected.
		 */
		public static function match( array $context, array $conditions ) {
			$includes = isset( $conditions['include'] ) ? (array) $conditions['include'] : array();
			$excludes = isset( $conditions['exclude'] ) ? (array) $conditions['exclude'] : array();

			$server_include = false;
			$client_require = array();

			foreach ( $includes as $condition ) {
				$type = isset( $condition['type'] ) ? $condition['type'] : '';
				if ( in_array( $type, Rules::CLIENT_TYPES, true ) ) {
					$client_require[] = self::client_key( $type );
					continue;
				}
				if ( self::condition_matches( $context, $condition ) ) {
					$server_include = true;
				}
			}

			if ( ! $server_include && empty( $client_require ) ) {
				return null; // No include can ever match.
			}

			$client_forbid = array();
			foreach ( $excludes as $condition ) {
				$type = isset( $condition['type'] ) ? $condition['type'] : '';
				if ( in_array( $type, Rules::CLIENT_TYPES, true ) ) {
					$client_forbid[] = self::client_key( $type );
					continue;
				}
				if ( self::condition_matches( $context, $condition ) ) {
					return null; // Server exclude kills the rule outright.
				}
			}

			return array(
				// When a server include matched, client includes are moot.
				'require' => $server_include ? array() : array_values( array_unique( $client_require ) ),
				'forbid'  => array_values( array_unique( $client_forbid ) ),
			);
		}

		/**
		 * One server-side condition against the context.
		 *
		 * @since 2.0.0
		 * @param array $context   Context snapshot.
		 * @param array $condition {type, value}.
		 * @return bool
		 */
		private static function condition_matches( array $context, array $condition ) {
			$type  = isset( $condition['type'] ) ? $condition['type'] : '';
			$value = isset( $condition['value'] ) ? $condition['value'] : '';

			switch ( $type ) {
				case 'entire_site':
					return true;

				case 'front_page':
					return ! empty( $context['is_front'] );

				case 'blog':
					return ! empty( $context['is_blog'] );

				case 'all_tec_pages':
					return ! empty( $context['is_tec'] );

				case 'single_event':
					return ! empty( $context['is_single_event'] );

				case 'event_archive':
					return ! empty( $context['is_event_archive'] );

				case 'post_type':
					return '' !== $value && isset( $context['post_type'] ) && $value === $context['post_type'];

				case 'page_id':
				case 'post_id':
					return ! empty( $context['post_id'] ) && in_array( (int) $context['post_id'], (array) $value, true );

				case 'tec_category':
					return self::slugs_intersect( $value, isset( $context['tec_categories'] ) ? $context['tec_categories'] : array() );

				case 'tec_tag':
					return self::slugs_intersect( $value, isset( $context['tec_tags'] ) ? $context['tec_tags'] : array() );

				case 'tec_venue':
					return ! empty( $context['tec_venue_id'] ) && in_array( (int) $context['tec_venue_id'], (array) $value, true );

				case 'url_contains':
					return is_string( $value ) && '' !== $value
						&& isset( $context['url'] ) && false !== strpos( (string) $context['url'], $value );
			}

			return false;
		}

		/**
		 * Condition type -> client flag key used by the front-end JS.
		 *
		 * @since 2.0.0
		 * @param string $type Client condition type.
		 * @return string 'logged_in' | 'mobile'.
		 */
		private static function client_key( $type ) {
			return 'user_logged_in' === $type ? 'logged_in' : 'mobile';
		}

		/**
		 * Any overlap between two slug lists.
		 *
		 * @since 2.0.0
		 * @param mixed $needles  Condition slugs.
		 * @param mixed $haystack Context slugs.
		 * @return bool
		 */
		private static function slugs_intersect( $needles, $haystack ) {
			$needles  = (array) $needles;
			$haystack = (array) $haystack;

			return ! empty( $needles ) && ! empty( $haystack ) && count( array_intersect( $needles, $haystack ) ) > 0;
		}
	}
}
