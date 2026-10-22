<?php
/**
 * Demo match clock: makes the demo data behave like a real day of sport.
 *
 * Each demo match gets a plan (final score, goals with scorers and assists,
 * cards, lineups, statistics). About once a minute the clock works out where
 * every match stands from its kick-off time: not started (kick-off time
 * shown), first half with the running minute, half-time, second half,
 * finished. Score, events and statistics only show what has happened so far.
 * Every new day the demo matches move forward, so "yesterday / today /
 * tomorrow" stay filled.
 *
 * Only posts flagged as demo data are touched; real provider data is never
 * changed. Removing the demo data removes everything.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Demo clock.
 */
final class Demo_Clock {

	/**
	 * Post meta: JSON plan of a demo match.
	 */
	const PLAN = '_sa_demo_plan';

	/**
	 * Post meta: intended final score [home, away] set by the seeder.
	 */
	const FINAL = '_sa_demo_final';

	/**
	 * Option: day (Y-m-d, site timezone) the demo matches are anchored to.
	 */
	const DAY = 'sportsarena_demo_day';

	/**
	 * Option / constant: version of the demo plans (bump to rebuild them).
	 */
	const VERSION_OPTION = 'sportsarena_demo_version';
	const VERSION        = 2;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( __CLASS__, 'maybe_tick' ), 20 );
	}

	/**
	 * Run the clock at most once a minute (any request: page, REST, cron).
	 *
	 * @return void
	 */
	public static function maybe_tick() {
		if ( wp_installing() || false !== get_transient( 'sa_demo_tick' ) ) {
			return;
		}
		if ( ! get_option( self::DAY ) ) {
			// Demo data created before the clock existed: rebuild it once.
			if ( ! get_transient( 'sa_demo_upgrade' ) && Seeder::has_demo() ) {
				set_transient( 'sa_demo_upgrade', 1, 10 * MINUTE_IN_SECONDS );
				Seeder::unseed();
				Seeder::seed();
			}
			return;
		}
		set_transient( 'sa_demo_tick', 1, MINUTE_IN_SECONDS );
		if ( (int) get_option( self::VERSION_OPTION ) < self::VERSION ) {
			// Newer plans (e.g. head-to-head history): rebuild them, the posts stay.
			self::build_plans( Seeder::player_names() );
		}
		self::tick();
	}

	/**
	 * Demo match IDs that have a plan.
	 *
	 * @return int[]
	 */
	private static function ids() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => Post_Types::MATCH,
					'post_status'      => 'any',
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'sa_all_languages' => true,
					'meta_key'         => self::PLAN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Demo data only, once a minute.
				)
			)
		);
	}

	/**
	 * Bring every demo match up to date.
	 *
	 * @param int|null $now Timestamp (tests).
	 * @return int Matches changed.
	 */
	public static function tick( $now = null ) {
		$now = null === $now ? time() : (int) $now;
		$ids = self::ids();
		if ( ! $ids ) {
			return 0;
		}
		self::reanchor( $ids );
		$changed = 0;
		foreach ( $ids as $id ) {
			$plan = json_decode( (string) get_post_meta( $id, self::PLAN, true ), true );
			if ( ! is_array( $plan ) ) {
				continue;
			}
			$state = self::state( $plan, (int) get_post_meta( $id, '_sa_kickoff', true ), $now );
			if ( self::apply( $id, $state ) ) {
				++$changed;
			}
		}
		if ( $changed ) {
			Repository::bump_version();
		}
		return $changed;
	}

	/**
	 * Move the demo matches to the current day when a new day starts.
	 *
	 * @param int[] $ids Demo match IDs.
	 * @return void
	 */
	private static function reanchor( array $ids ) {
		global $wpdb;
		$anchor = (string) get_option( self::DAY );
		$today  = wp_date( 'Y-m-d' );
		if ( '' === $anchor || $anchor >= $today ) {
			return;
		}
		$days = (int) round( ( strtotime( $today . ' 12:00:00' ) - strtotime( $anchor . ' 12:00:00' ) ) / DAY_IN_SECONDS );
		if ( $days <= 0 ) {
			return;
		}
		foreach ( $ids as $id ) {
			$old = (int) get_post_meta( $id, '_sa_kickoff', true );
			$new = $old + $days * DAY_IN_SECONDS;
			update_post_meta( $id, '_sa_kickoff', $new );
			// Keep the date in the address in step: /match/a-vs-b-2026-10-22/.
			$slug = (string) get_post_field( 'post_name', $id );
			$from = wp_date( 'Y-m-d', $old );
			if ( str_ends_with( $slug, $from ) ) {
				$wpdb->update( $wpdb->posts, array( 'post_name' => substr( $slug, 0, -strlen( $from ) ) . wp_date( 'Y-m-d', $new ) ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Slug only, no hooks wanted; cache cleaned below.
				clean_post_cache( $id );
			}
		}
		update_option( self::DAY, $today, false );
	}

	/**
	 * Where a match stands at a given time.
	 *
	 * @param array $plan    Plan.
	 * @param int   $kickoff Kick-off timestamp.
	 * @param int   $now     Timestamp.
	 * @return array{status:string,status_raw:string,elapsed:int|null,home:int|null,away:int|null,events:array,stats:array,lineups:array}
	 */
	public static function state( array $plan, $kickoff, $now ) {
		$minutes = (int) floor( ( $now - $kickoff ) / 60 );
		$final   = array_map( 'intval', (array) ( $plan['final'] ?? array( 0, 0 ) ) ) + array( 0, 0 );
		$lineups = $minutes >= -60 ? (array) ( $plan['lineups'] ?? array() ) : array();
		$base    = array(
			'status'     => 'scheduled',
			'status_raw' => 'NS',
			'elapsed'    => null,
			'home'       => null,
			'away'       => null,
			'events'     => array(),
			'stats'      => array(),
			'lineups'    => $lineups,
		);
		if ( $minutes < 0 ) {
			return $base;
		}
		$sport = (string) ( $plan['sport'] ?? 'football' );

		if ( 'football' !== $sport ) {
			$length = 'tennis' === $sport ? 150 : 140;
			if ( $minutes >= $length ) {
				return array_merge(
					$base,
					array(
						'status'     => 'finished',
						'status_raw' => 'FT',
						'home'       => $final[0],
						'away'       => $final[1],
					)
				);
			}
			$share = $minutes / $length;
			return array_merge(
				$base,
				array(
					'status'     => 'live',
					'status_raw' => 'basketball' === $sport ? 'Q' . min( 4, 1 + (int) floor( $share * 4 ) ) : 'LIVE',
					'home'       => (int) floor( $final[0] * $share ),
					'away'       => (int) floor( $final[1] * $share ),
				)
			);
		}

		// Football: 1st half 0-46, half-time 47-61, 2nd half 62-110, then full time.
		if ( $minutes > 110 ) {
			$status  = 'finished';
			$raw     = 'FT';
			$elapsed = 90;
			$upto    = 999;
		} elseif ( $minutes >= 62 ) {
			$status  = 'live';
			$raw     = '2H';
			$elapsed = min( 90, $minutes - 16 );
			$upto    = $elapsed;
		} elseif ( $minutes >= 47 ) {
			$status  = 'halftime';
			$raw     = 'HT';
			$elapsed = 45;
			$upto    = 45;
		} else {
			$status  = 'live';
			$raw     = '1H';
			$elapsed = min( 45, $minutes + 1 );
			$upto    = $elapsed;
		}
		$events = array();
		$home   = 0;
		$away   = 0;
		foreach ( (array) ( $plan['events'] ?? array() ) as $event ) {
			if ( (int) $event['minute'] > $upto ) {
				continue;
			}
			$events[] = $event;
			if ( in_array( $event['type'], array( 'goal', 'penalty' ), true ) ) {
				'home' === $event['team'] ? ++$home : ++$away;
			} elseif ( 'own_goal' === $event['type'] ) {
				'home' === $event['team'] ? ++$away : ++$home;
			}
		}
		$share = min( 1, $elapsed / 90 );
		$stats = array();
		foreach ( (array) ( $plan['stats'] ?? array() ) as $row ) {
			$scale   = 'Ball Possession' === $row['type'] ? 1 : $share;
			$stats[] = array(
				'type' => $row['type'],
				'home' => 'Ball Possession' === $row['type'] ? $row['home'] . '%' : (string) (int) round( $row['home'] * $scale ),
				'away' => 'Ball Possession' === $row['type'] ? $row['away'] . '%' : (string) (int) round( $row['away'] * $scale ),
			);
		}
		return array_merge(
			$base,
			array(
				'status'     => $status,
				'status_raw' => $raw,
				'elapsed'    => 'finished' === $status ? null : $elapsed,
				'home'       => $home,
				'away'       => $away,
				'events'     => $events,
				'stats'      => $stats,
			)
		);
	}

	/**
	 * Store a state on a match (only what changed).
	 *
	 * @param int   $id    Match ID.
	 * @param array $state State.
	 * @return bool Whether anything changed.
	 */
	private static function apply( $id, array $state ) {
		$meta = array(
			'_sa_status'     => $state['status'],
			'_sa_status_raw' => $state['status_raw'],
			'_sa_elapsed'    => null === $state['elapsed'] ? '' : (string) $state['elapsed'],
			'_sa_home_score' => null === $state['home'] ? '' : (string) $state['home'],
			'_sa_away_score' => null === $state['away'] ? '' : (string) $state['away'],
			'_sa_events'     => $state['events'] ? wp_json_encode( $state['events'] ) : '',
			'_sa_stats'      => $state['stats'] ? wp_json_encode( $state['stats'] ) : '',
			'_sa_lineups'    => $state['lineups'] ? wp_json_encode( $state['lineups'] ) : '',
		);
		$changed = false;
		foreach ( $meta as $key => $value ) {
			if ( (string) get_post_meta( $id, $key, true ) !== $value ) {
				update_post_meta( $id, $key, wp_slash( $value ) );
				$changed = true;
			}
		}
		return $changed;
	}

	/**
	 * Create the plans of every demo match (called by the seeder).
	 *
	 * @param string[] $players Player names: 0-10 home starters, 11-21 away starters, 22-27 substitutes.
	 * @return void
	 */
	public static function build_plans( array $players ) {
		$ids = array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => Post_Types::MATCH,
					'post_status'      => 'any',
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'sa_all_languages' => true,
					'meta_key'         => '_sa_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- One-off, demo data only.
					'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- One-off, demo data only.
				)
			)
		);
		foreach ( $ids as $id ) {
			$terms = get_the_terms( $id, Post_Types::TAX_SPORT );
			$sport = $terms && ! is_wp_error( $terms ) ? $terms[0]->slug : 'football';
			$seed  = (int) sprintf( '%u', crc32( (string) get_post_meta( $id, '_sa_external_id', true ) ) );
			$final = json_decode( (string) get_post_meta( $id, self::FINAL, true ), true );
			if ( ! is_array( $final ) ) {
				$final = 'finished' === get_post_meta( $id, '_sa_status', true )
					? array( (int) get_post_meta( $id, '_sa_home_score', true ), (int) get_post_meta( $id, '_sa_away_score', true ) )
					: self::random_final( $sport, $seed );
			}
			$plan = array(
				'sport' => $sport,
				'final' => array_map( 'intval', $final ),
			);
			if ( 'football' === $sport ) {
				$plan += self::football_plan( $plan['final'], $players, $seed );
			}
			update_post_meta( $id, self::PLAN, wp_slash( wp_json_encode( $plan ) ) );
			$h2h = self::head_to_head( $id, $sport, $seed );
			if ( $h2h ) {
				update_post_meta( $id, '_sa_h2h', wp_slash( wp_json_encode( $h2h ) ) );
			}
		}
		if ( ! get_option( self::DAY ) ) {
			update_option( self::DAY, wp_date( 'Y-m-d' ), false );
		}
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Five earlier meetings of the two teams (newest first), in the shape the
	 * provider sync stores: English names, shown in the page language.
	 *
	 * @param int    $id    Match ID.
	 * @param string $sport Sport slug.
	 * @param int    $seed  Seed.
	 * @return array<int,array{date:int,home:string,away:string,score:string,comp:string}>
	 */
	private static function head_to_head( $id, $sport, $seed ) {
		$home = (int) get_post_meta( $id, '_sa_home_team', true );
		$away = (int) get_post_meta( $id, '_sa_away_team', true );
		if ( ! $home || ! $away || ! in_array( $sport, array( 'football', 'basketball' ), true ) ) {
			return array();
		}
		$name    = static function ( $team ) {
			$english = (string) get_post_meta( $team, '_sa_name_en', true );
			return '' !== $english ? $english : get_the_title( $team );
		};
		$comp_id = (int) get_post_meta( $id, '_sa_competition', true );
		$comp    = $comp_id ? (string) get_post_meta( $comp_id, '_sa_name_en', true ) : '';
		$comp    = '' !== $comp ? $comp : ( $comp_id ? get_the_title( $comp_id ) : '' );
		$kickoff = (int) get_post_meta( $id, '_sa_kickoff', true );
		$rows    = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$seed = ( $seed * 1103515245 + 12345 ) % 2147483648;
			$days = 120 + $i * 182 + $seed % 40;
			$flip = 1 === $i % 2;
			if ( 'basketball' === $sport ) {
				$a = 92 + $seed % 30;
				$b = 90 + intdiv( $seed, 31 ) % 30;
			} else {
				$a = $seed % 4;
				$b = intdiv( $seed, 4 ) % 3;
			}
			$rows[] = array(
				'date'  => $kickoff - $days * DAY_IN_SECONDS,
				'home'  => $name( $flip ? $away : $home ),
				'away'  => $name( $flip ? $home : $away ),
				'score' => $a . ' - ' . $b,
				'comp'  => $comp,
			);
		}
		return $rows;
	}

	/**
	 * A believable final score.
	 *
	 * @param string $sport Sport slug.
	 * @param int    $seed  Seed.
	 * @return int[]
	 */
	private static function random_final( $sport, $seed ) {
		if ( 'basketball' === $sport ) {
			return array( 98 + $seed % 25, 95 + intdiv( $seed, 7 ) % 25 );
		}
		if ( 'tennis' === $sport ) {
			return $seed % 2 ? array( 2, 1 ) : array( 1, 2 );
		}
		return array( $seed % 4, intdiv( $seed, 5 ) % 3 );
	}

	/**
	 * Goals, cards, lineups and statistics of a football match.
	 *
	 * @param int[]    $final   Final score.
	 * @param string[] $players Players.
	 * @param int      $seed    Seed.
	 * @return array
	 */
	private static function football_plan( array $final, array $players, $seed ) {
		$rand   = static function ( $min, $max ) use ( &$seed ) {
			$seed = ( $seed * 1103515245 + 12345 ) % 2147483648;
			return $min + $seed % ( $max - $min + 1 );
		};
		$used   = array();
		$minute = static function () use ( $rand, &$used ) {
			do {
				$m = $rand( 2, 90 );
			} while ( isset( $used[ $m ] ) );
			$used[ $m ] = true;
			return $m;
		};
		$side   = array(
			'home' => 0,
			'away' => 11,
		);
		$events = array();
		foreach ( array(
			'home' => $final[0],
			'away' => $final[1],
		) as $team => $goals ) {
			for ( $g = 0; $g < $goals; $g++ ) {
				$penalty  = 0 === $rand( 0, 7 );
				$scorer   = $players[ $side[ $team ] + $rand( 6, 10 ) ];
				$assister = $players[ $side[ $team ] + $rand( 2, 9 ) ];
				$events[] = array(
					'minute' => (string) $minute(),
					'team'   => $team,
					'type'   => $penalty ? 'penalty' : 'goal',
					'detail' => $penalty ? 'Penalty' : 'Normal Goal',
					'player' => $scorer,
					'assist' => $penalty || $assister === $scorer || 0 === $rand( 0, 3 ) ? '' : $assister,
				);
			}
			$cards = $rand( 1, 3 );
			for ( $c = 0; $c < $cards; $c++ ) {
				$events[] = array(
					'minute' => (string) $minute(),
					'team'   => $team,
					'type'   => 'yellow',
					'detail' => 'Yellow Card',
					'player' => $players[ $side[ $team ] + $rand( 1, 10 ) ],
					'assist' => '',
				);
			}
			if ( 0 === $rand( 0, 9 ) ) {
				$events[] = array(
					'minute' => (string) $minute(),
					'team'   => $team,
					'type'   => 'red',
					'detail' => 'Red Card',
					'player' => $players[ $side[ $team ] + $rand( 2, 6 ) ],
					'assist' => '',
				);
			}
		}
		usort(
			$events,
			static function ( $a, $b ) {
				return (int) $a['minute'] <=> (int) $b['minute'];
			}
		);

		$lineup  = static function ( $team ) use ( $players, $side ) {
			$start = array();
			for ( $i = 0; $i < 11; $i++ ) {
				$start[] = ( $i + 1 ) . ' ' . $players[ $side[ $team ] + $i ];
			}
			$bench = 'home' === $team ? 22 : 25;
			return array(
				'team'      => $team,
				'formation' => 'home' === $team ? '4-3-3' : '4-2-3-1',
				'coach'     => 'home' === $team ? 'Khalid Mansouri' : 'Adel Ramzi',
				'start'     => $start,
				'subs'      => array( '12 ' . $players[ $bench ], '14 ' . $players[ $bench + 1 ], '20 ' . $players[ $bench + 2 ] ),
			);
		};
		$poss    = $rand( 38, 62 );
		$shots_h = $rand( 6, 18 ) + 2 * $final[0];
		$shots_a = $rand( 4, 15 ) + 2 * $final[1];
		$yellow  = array(
			'home' => 0,
			'away' => 0,
		);
		foreach ( $events as $event ) {
			if ( 'yellow' === $event['type'] ) {
				++$yellow[ $event['team'] ];
			}
		}
		return array(
			'events'  => $events,
			'lineups' => array( $lineup( 'home' ), $lineup( 'away' ) ),
			'stats'   => array(
				array(
					'type' => 'Ball Possession',
					'home' => $poss,
					'away' => 100 - $poss,
				),
				array(
					'type' => 'Total Shots',
					'home' => $shots_h,
					'away' => $shots_a,
				),
				array(
					'type' => 'Shots on Goal',
					'home' => max( $final[0], (int) round( $shots_h * 0.4 ) ),
					'away' => max( $final[1], (int) round( $shots_a * 0.4 ) ),
				),
				array(
					'type' => 'Corner Kicks',
					'home' => $rand( 2, 10 ),
					'away' => $rand( 1, 8 ),
				),
				array(
					'type' => 'Fouls',
					'home' => $rand( 8, 16 ),
					'away' => $rand( 8, 16 ),
				),
				array(
					'type' => 'Yellow Cards',
					'home' => $yellow['home'],
					'away' => $yellow['away'],
				),
			),
		);
	}
}
