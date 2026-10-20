<?php
/**
 * Read-side queries used by the theme, REST endpoints and sync jobs.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Repository.
 */
final class Repository {

	const VERSION_OPTION = 'sportsarena_data_version';

	/**
	 * Invalidate cached query results (called after every import batch).
	 *
	 * @return void
	 */
	public static function bump_version() {
		update_option( self::VERSION_OPTION, (string) microtime( true ) );
	}

	/**
	 * Current cache version.
	 *
	 * @return string
	 */
	public static function version() {
		return (string) get_option( self::VERSION_OPTION, '1' );
	}

	/**
	 * Cached wrapper keyed on the data version.
	 *
	 * @param string   $key      Cache key.
	 * @param int      $ttl      TTL.
	 * @param callable $callback Producer.
	 * @return mixed
	 */
	private static function remember( $key, $ttl, callable $callback ) {
		$transient = 'sa_q_' . md5( $key . '|' . self::version() );
		$value     = get_transient( $transient );
		if ( false === $value ) {
			$value = $callback();
			set_transient( $transient, $value, $ttl );
		}
		return $value;
	}

	/**
	 * Query match IDs.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *     @type string[] $status      Normalized statuses.
	 *     @type string   $provider    Provider id.
	 *     @type int      $from        Kickoff >= (UTC ts).
	 *     @type int      $to          Kickoff <= (UTC ts).
	 *     @type int      $competition Competition post ID.
	 *     @type int      $team        Team post ID (home or away).
	 *     @type string   $sport       Sport slug.
	 *     @type int      $limit       Max rows (-1 for all).
	 *     @type string   $order       Sort order by kickoff: ASC or DESC.
	 * }
	 * @return int[]
	 */
	public static function query_matches( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'status'      => array(),
				'provider'    => '',
				'from'        => null,
				'to'          => null,
				'competition' => 0,
				'team'        => 0,
				'sport'       => '',
				'limit'       => 50,
				'order'       => 'ASC',
			)
		);

		$meta_query = array(
			'relation' => 'AND',
			'kickoff'  => array(
				'key'  => '_sa_kickoff',
				'type' => 'NUMERIC',
			),
		);
		if ( null !== $args['from'] && null !== $args['to'] ) {
			$meta_query['kickoff']['value']   = array( (int) $args['from'], (int) $args['to'] );
			$meta_query['kickoff']['compare'] = 'BETWEEN';
		} elseif ( null !== $args['from'] ) {
			$meta_query['kickoff']['value']   = (int) $args['from'];
			$meta_query['kickoff']['compare'] = '>=';
		} elseif ( null !== $args['to'] ) {
			$meta_query['kickoff']['value']   = (int) $args['to'];
			$meta_query['kickoff']['compare'] = '<=';
		} else {
			$meta_query['kickoff']['compare'] = 'EXISTS';
		}
		if ( $args['status'] ) {
			$meta_query[] = array(
				'key'     => '_sa_status',
				'value'   => array_map( 'strval', (array) $args['status'] ),
				'compare' => 'IN',
			);
		}
		if ( $args['provider'] ) {
			$meta_query[] = array(
				'key'   => '_sa_provider',
				'value' => $args['provider'],
			);
		}
		if ( $args['competition'] ) {
			$meta_query[] = array(
				'key'   => '_sa_competition',
				'value' => (int) $args['competition'],
				'type'  => 'NUMERIC',
			);
		}
		if ( $args['team'] ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'   => '_sa_home_team',
					'value' => (int) $args['team'],
					'type'  => 'NUMERIC',
				),
				array(
					'key'   => '_sa_away_team',
					'value' => (int) $args['team'],
					'type'  => 'NUMERIC',
				),
			);
		}

		$query = array(
			'post_type'              => Post_Types::MATCH,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $args['limit'],
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Results are cached.
			'orderby'                => array( 'kickoff' => 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC' ),
		);
		if ( $args['sport'] ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query -- Results are cached.
			$query['tax_query'] = array(
				array(
					'taxonomy' => Post_Types::TAX_SPORT,
					'field'    => 'slug',
					'terms'    => $args['sport'],
				),
			);
		}

		$ids = get_posts( $query );
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
		}
		return array_map( 'intval', $ids );
	}

	/**
	 * UTC timestamps for the start and end of a local (site timezone) day.
	 *
	 * @param string $date Y-m-d.
	 * @return array{0:int,1:int}
	 */
	public static function day_bounds( $date ) {
		$start = new \DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
		return array( $start->getTimestamp(), $start->modify( '+1 day' )->getTimestamp() - 1 );
	}

	/**
	 * Matches of a local day grouped by competition, competitions ordered
	 * by priority (menu order) then name.
	 *
	 * @param string $date  Y-m-d in site timezone.
	 * @param string $sport Optional sport slug.
	 * @return array<int,array{competition:int,matches:int[]}>
	 */
	public static function matches_by_competition( $date, $sport = '' ) {
		return self::remember(
			'day|' . $date . '|' . $sport,
			5 * MINUTE_IN_SECONDS,
			static function () use ( $date, $sport ) {
				list( $from, $to ) = self::day_bounds( $date );
				$ids               = self::query_matches(
					array(
						'from'  => $from,
						'to'    => $to,
						'sport' => $sport,
						'limit' => 500,
					)
				);
				$groups            = array();
				foreach ( $ids as $id ) {
					$competition = (int) get_post_meta( $id, '_sa_competition', true );
					if ( ! isset( $groups[ $competition ] ) ) {
						$groups[ $competition ] = array(
							'competition' => $competition,
							'matches'     => array(),
						);
					}
					$groups[ $competition ]['matches'][] = $id;
				}
				uasort(
					$groups,
					static function ( $a, $b ) {
						$pa = $a['competition'] ? self::competition_priority( $a['competition'] ) : PHP_INT_MAX;
						$pb = $b['competition'] ? self::competition_priority( $b['competition'] ) : PHP_INT_MAX;
						if ( $pa !== $pb ) {
							return $pa <=> $pb;
						}
						return strcmp( get_the_title( $a['competition'] ), get_the_title( $b['competition'] ) );
					}
				);
				return array_values( $groups );
			}
		);
	}

	/**
	 * Competition priority (lower = shown first). Uses the menu order field.
	 *
	 * @param int $competition_id Competition post ID.
	 * @return int
	 */
	public static function competition_priority( $competition_id ) {
		$post = get_post( $competition_id );
		if ( ! $post ) {
			return PHP_INT_MAX;
		}
		$priority = get_post_meta( $competition_id, '_sa_priority', true );
		return (int) $post->menu_order > 0 ? (int) $post->menu_order : ( '' !== $priority ? (int) $priority : 50 );
	}

	/**
	 * Live (or half time) matches.
	 *
	 * @param int $limit Max rows.
	 * @return int[]
	 */
	public static function live_matches( $limit = 20 ) {
		return self::remember(
			'live|' . $limit,
			MINUTE_IN_SECONDS,
			static function () use ( $limit ) {
				return self::query_matches(
					array(
						'status' => array( 'live', 'halftime' ),
						'limit'  => $limit,
					)
				);
			}
		);
	}

	/**
	 * Whether a match is live or about to start (gates the live sync job).
	 *
	 * @return bool
	 */
	public static function has_live_window() {
		$live = self::query_matches(
			array(
				'status' => array( 'live', 'halftime' ),
				'limit'  => 1,
			)
		);
		if ( $live ) {
			return true;
		}
		$soon = self::query_matches(
			array(
				'status' => array( 'scheduled' ),
				'from'   => time() - 3 * HOUR_IN_SECONDS,
				'to'     => time() + 10 * MINUTE_IN_SECONDS,
				'limit'  => 1,
			)
		);
		return ! empty( $soon );
	}

	/**
	 * Upcoming or past matches of a team.
	 *
	 * @param int    $team_id Team post ID.
	 * @param string $when    upcoming|past.
	 * @param int    $limit   Max rows.
	 * @return int[]
	 */
	public static function team_matches( $team_id, $when = 'upcoming', $limit = 10 ) {
		return self::remember(
			'team|' . $team_id . '|' . $when . '|' . $limit,
			10 * MINUTE_IN_SECONDS,
			static function () use ( $team_id, $when, $limit ) {
				return self::query_matches(
					array(
						'team'  => $team_id,
						'from'  => 'upcoming' === $when ? time() - 3 * HOUR_IN_SECONDS : null,
						'to'    => 'past' === $when ? time() : null,
						'order' => 'upcoming' === $when ? 'ASC' : 'DESC',
						'limit' => $limit,
					)
				);
			}
		);
	}

	/**
	 * Big matches (see Big_Matches): upcoming and live ones (next three weeks)
	 * or recent results (last three weeks).
	 *
	 * @param string $when  upcoming|past.
	 * @param int    $limit Max rows.
	 * @return int[]
	 */
	public static function big_matches( $when = 'upcoming', $limit = 12 ) {
		return self::remember(
			'big|' . $when . '|' . $limit . '|' . md5( implode( ',', Big_Matches::clubs() ) ),
			10 * MINUTE_IN_SECONDS,
			static function () use ( $when, $limit ) {
				$upcoming = 'upcoming' === $when;
				$ids      = self::query_matches(
					array(
						'from'   => $upcoming ? time() - 3 * HOUR_IN_SECONDS : time() - 21 * DAY_IN_SECONDS,
						'to'     => $upcoming ? time() + 21 * DAY_IN_SECONDS : time(),
						'status' => $upcoming ? array() : array( 'finished' ),
						'order'  => $upcoming ? 'ASC' : 'DESC',
						'limit'  => 400,
					)
				);
				$big      = array();
				foreach ( $ids as $id ) {
					if ( $upcoming && 'finished' === get_post_meta( $id, '_sa_status', true ) ) {
						continue;
					}
					if ( Big_Matches::is_big( $id ) ) {
						$big[] = $id;
						if ( count( $big ) >= $limit ) {
							break;
						}
					}
				}
				return $big;
			}
		);
	}

	/**
	 * Upcoming or past matches of a competition.
	 *
	 * @param int    $competition_id Competition post ID.
	 * @param string $when           upcoming|past.
	 * @param int    $limit          Max rows.
	 * @return int[]
	 */
	public static function competition_matches( $competition_id, $when = 'upcoming', $limit = 20 ) {
		return self::remember(
			'comp|' . $competition_id . '|' . $when . '|' . $limit,
			10 * MINUTE_IN_SECONDS,
			static function () use ( $competition_id, $when, $limit ) {
				return self::query_matches(
					array(
						'competition' => $competition_id,
						'from'        => 'upcoming' === $when ? time() - 3 * HOUR_IN_SECONDS : null,
						'to'          => 'past' === $when ? time() : null,
						'order'       => 'upcoming' === $when ? 'ASC' : 'DESC',
						'limit'       => $limit,
					)
				);
			}
		);
	}

	/**
	 * Previous meetings between two teams from the local database.
	 *
	 * @param int $home_id    Team ID.
	 * @param int $away_id    Team ID.
	 * @param int $exclude_id Match to exclude.
	 * @param int $limit      Max rows.
	 * @return int[]
	 */
	public static function head_to_head( $home_id, $away_id, $exclude_id = 0, $limit = 5 ) {
		$ids = self::query_matches(
			array(
				'team'   => $home_id,
				'status' => array( 'finished' ),
				'to'     => time(),
				'order'  => 'DESC',
				'limit'  => 100,
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			if ( $id === (int) $exclude_id ) {
				continue;
			}
			$teams = array( (int) get_post_meta( $id, '_sa_home_team', true ), (int) get_post_meta( $id, '_sa_away_team', true ) );
			if ( in_array( (int) $away_id, $teams, true ) ) {
				$out[] = $id;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * News posts related to teams / competitions.
	 *
	 * @param int[] $team_ids        Team IDs.
	 * @param int[] $competition_ids Competition IDs.
	 * @param int   $limit           Max posts.
	 * @param int   $exclude         Post to exclude.
	 * @return int[]
	 */
	public static function related_news( array $team_ids, array $competition_ids, $limit = 6, $exclude = 0 ) {
		$team_ids        = array_filter( array_map( 'intval', $team_ids ) );
		$competition_ids = array_filter( array_map( 'intval', $competition_ids ) );
		if ( ! $team_ids && ! $competition_ids ) {
			return array();
		}
		$meta_query = array( 'relation' => 'OR' );
		if ( $team_ids ) {
			$meta_query[] = array(
				'key'     => '_sa_team_id',
				'value'   => $team_ids,
				'compare' => 'IN',
				'type'    => 'NUMERIC',
			);
		}
		if ( $competition_ids ) {
			$meta_query[] = array(
				'key'     => '_sa_competition_id',
				'value'   => $competition_ids,
				'compare' => 'IN',
				'type'    => 'NUMERIC',
			);
		}
		return self::remember(
			'news|' . implode( ',', $team_ids ) . '|' . implode( ',', $competition_ids ) . '|' . $limit . '|' . $exclude,
			15 * MINUTE_IN_SECONDS,
			static function () use ( $meta_query, $limit, $exclude ) {
				return array_map(
					'intval',
					get_posts(
						array(
							'post_type'      => 'post',
							'post_status'    => 'publish',
							'posts_per_page' => $limit,
							'fields'         => 'ids',
							'no_found_rows'  => true,
							'post__not_in'   => $exclude ? array( (int) $exclude ) : array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Single ID, cached.
							'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Cached.
						)
					)
				);
			}
		);
	}

	/**
	 * All display data for a match.
	 *
	 * @param int $post_id Match ID.
	 * @return array<string,mixed>
	 */
	public static function match( $post_id ) {
		$get        = static function ( $key ) use ( $post_id ) {
			return get_post_meta( $post_id, $key, true );
		};
		$json       = static function ( $key ) use ( $get ) {
			$decoded = json_decode( (string) $get( $key ), true );
			return is_array( $decoded ) ? $decoded : array();
		};
		$home_score = (string) $get( '_sa_home_score' );
		$away_score = (string) $get( '_sa_away_score' );

		return array(
			'id'          => (int) $post_id,
			'url'         => get_permalink( $post_id ),
			'title'       => get_the_title( $post_id ),
			'home'        => (int) $get( '_sa_home_team' ),
			'away'        => (int) $get( '_sa_away_team' ),
			'competition' => (int) $get( '_sa_competition' ),
			'kickoff'     => (int) $get( '_sa_kickoff' ),
			'status'      => (string) ( $get( '_sa_status' ) ? $get( '_sa_status' ) : 'scheduled' ),
			'status_raw'  => (string) $get( '_sa_status_raw' ),
			'elapsed'     => '' === (string) $get( '_sa_elapsed' ) ? null : (int) $get( '_sa_elapsed' ),
			'home_score'  => '' === $home_score ? null : (int) $home_score,
			'away_score'  => '' === $away_score ? null : (int) $away_score,
			'venue'       => (string) $get( '_sa_venue' ),
			'city'        => (string) $get( '_sa_city' ),
			'round'       => (string) $get( '_sa_round' ),
			'referee'     => (string) $get( '_sa_referee' ),
			'tv'          => (string) $get( '_sa_tv' ),
			'events'      => $json( '_sa_events' ),
			'lineups'     => $json( '_sa_lineups' ),
			'stats'       => $json( '_sa_stats' ),
			'h2h'         => $json( '_sa_h2h' ),
		);
	}

	/**
	 * Decoded standings rows of a competition.
	 *
	 * @param int $competition_id Competition ID.
	 * @return array<int,array>
	 */
	public static function standings( $competition_id ) {
		$rows = json_decode( (string) get_post_meta( $competition_id, '_sa_standings', true ), true );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Decoded top scorers of a competition.
	 *
	 * @param int $competition_id Competition ID.
	 * @return array<int,array>
	 */
	public static function scorers( $competition_id ) {
		$rows = json_decode( (string) get_post_meta( $competition_id, '_sa_scorers', true ), true );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Competitions ordered by priority.
	 *
	 * @param int    $limit Max rows.
	 * @param string $sport Optional sport slug.
	 * @return int[]
	 */
	public static function competitions( $limit = 30, $sport = '' ) {
		return self::remember(
			'comps|' . $limit . '|' . $sport,
			HOUR_IN_SECONDS,
			static function () use ( $limit, $sport ) {
				$args = array(
					'post_type'      => Post_Types::COMPETITION,
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'orderby'        => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
				);
				if ( $sport ) {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query -- Cached.
					$args['tax_query'] = array(
						array(
							'taxonomy' => Post_Types::TAX_SPORT,
							'field'    => 'slug',
							'terms'    => $sport,
						),
					);
				}
				$ids = array_map( 'intval', get_posts( $args ) );
				usort(
					$ids,
					static function ( $a, $b ) {
						return self::competition_priority( $a ) <=> self::competition_priority( $b );
					}
				);
				return $ids;
			}
		);
	}
}
