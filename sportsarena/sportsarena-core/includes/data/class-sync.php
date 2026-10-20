<?php
/**
 * WP-Cron synchronisation jobs.
 *
 * - Live:   every minute tick, but only calls the API while matches are live
 *           (or about to start) and at most once per "live_interval" seconds.
 * - Daily:  today + tomorrow fixtures every 30 minutes.
 * - Tables: standings, top scorers and yesterday's results every 6 hours.
 *
 * For reliable timing disable the visitor-triggered cron
 * (define( 'DISABLE_WP_CRON', true );) and run a real server cron every
 * minute; see README.md.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data;

use SportsArena\Core\Logger;
use SportsArena\Core\Options;
use SportsArena\Core\Post_Types;
use SportsArena\Core\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Sync scheduler.
 */
final class Sync {

	const HOOK_LIVE        = 'sportsarena_sync_live';
	const HOOK_DAILY       = 'sportsarena_sync_daily';
	const HOOK_TABLES      = 'sportsarena_sync_tables';
	const HOOK_MAINTENANCE = 'sportsarena_maintenance';

	/**
	 * Max detail requests per run (protects the daily quota).
	 */
	const DETAILS_PER_RUN = 5;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'cron_schedules', array( self::class, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Live scores need a 60s tick; the job itself is gated.
		add_action( self::HOOK_LIVE, array( $this, 'run_live' ) );
		add_action( self::HOOK_DAILY, array( $this, 'run_daily' ) );
		add_action( self::HOOK_TABLES, array( $this, 'run_tables' ) );
		add_action( self::HOOK_MAINTENANCE, array( $this, 'run_maintenance' ) );
		add_action( 'init', array( self::class, 'schedule_events' ), 20 );
	}

	/**
	 * Custom cron intervals.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$schedules['sa_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'كل دقيقة (SportsArena)', 'sportsarena-core' ),
		);
		$schedules['sa_30min']  = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'كل 30 دقيقة (SportsArena)', 'sportsarena-core' ),
		);
		$schedules['sa_6hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'كل 6 ساعات (SportsArena)', 'sportsarena-core' ),
		);
		return $schedules;
	}

	/**
	 * Schedule the recurring events if missing.
	 *
	 * @return void
	 */
	public static function schedule_events() {
		add_filter( 'cron_schedules', array( self::class, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- See register_hooks().
		$events = array(
			self::HOOK_LIVE        => 'sa_minute',
			self::HOOK_DAILY       => 'sa_30min',
			self::HOOK_TABLES      => 'sa_6hours',
			self::HOOK_MAINTENANCE => 'daily',
		);
		foreach ( $events as $hook => $recurrence ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + 30, $recurrence, $hook );
			}
		}
	}

	/**
	 * Remove all events (deactivation).
	 *
	 * @return void
	 */
	public static function unschedule_events() {
		foreach ( array( self::HOOK_LIVE, self::HOOK_DAILY, self::HOOK_TABLES, self::HOOK_MAINTENANCE ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Whether syncing is enabled at all.
	 *
	 * @return bool
	 */
	private function enabled() {
		return (bool) Options::get( 'sync_enabled' ) && Provider_Registry::configured();
	}

	/**
	 * Acquire a short lock so overlapping cron runs do not double-call the API.
	 *
	 * @param string $name Lock name.
	 * @param int    $ttl  Lock TTL.
	 * @return bool
	 */
	private function lock( $name, $ttl ) {
		$key = 'sa_lock_' . $name;
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, $ttl );
		return true;
	}

	/**
	 * Release a lock.
	 *
	 * @param string $name Lock name.
	 * @return void
	 */
	private function unlock( $name ) {
		delete_transient( 'sa_lock_' . $name );
	}

	/**
	 * Live job.
	 *
	 * @return void
	 */
	public function run_live() {
		if ( ! $this->enabled() ) {
			return;
		}
		// Gate 1: only when something is (or is about to be) in play.
		if ( ! Repository::has_live_window() ) {
			return;
		}
		// Gate 2: configured interval (never below 60 seconds).
		$interval = max( 60, (int) Options::get( 'live_interval' ) );
		$last     = (int) get_option( 'sportsarena_live_last_run', 0 );
		if ( time() - $last < $interval - 5 ) {
			return;
		}
		if ( ! $this->lock( 'live', 55 ) ) {
			return;
		}
		update_option( 'sportsarena_live_last_run', time(), false );

		foreach ( Provider_Registry::configured() as $provider ) {
			if ( ! $provider->supports( 'live' ) ) {
				continue;
			}
			$matches = $provider->get_live_matches();
			if ( is_wp_error( $matches ) ) {
				continue;
			}
			$importer = new Importer( $provider->get_id() );
			$importer->import_matches( $matches );

			// Matches still "live" locally but gone from the live feed have
			// ended: fetch their final state.
			$live_ids = wp_list_pluck( $matches, 'external_id' );
			$this->refresh_stale_live( $provider, $importer, $live_ids );
			// Lineups are published ~40 minutes before kickoff.
			$this->refresh_upcoming_lineups( $provider, $importer );
		}
		$this->unlock( 'live' );
	}

	/**
	 * Daily job: today's and tomorrow's fixtures.
	 *
	 * @return void
	 */
	public function run_daily() {
		if ( ! $this->enabled() || ! $this->lock( 'daily', 10 * MINUTE_IN_SECONDS ) ) {
			return;
		}
		$dates = array( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', time() + DAY_IN_SECONDS ) );
		foreach ( Provider_Registry::configured() as $provider ) {
			$this->import_dates( $provider, $dates );
			$this->backfill_details( $provider );
		}
		$this->unlock( 'daily' );
	}

	/**
	 * Tables job: standings, scorers, yesterday's final results.
	 *
	 * @return void
	 */
	public function run_tables() {
		if ( ! $this->enabled() || ! $this->lock( 'tables', 20 * MINUTE_IN_SECONDS ) ) {
			return;
		}
		foreach ( Provider_Registry::configured() as $provider ) {
			$this->import_dates( $provider, array( gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) ) );

			if ( ! $provider->supports( 'standings' ) ) {
				continue;
			}
			$importer = new Importer( $provider->get_id() );
			foreach ( $provider->get_tracked_competitions() as $external_id => $season ) {
				$standings = $provider->get_standings( (string) $external_id, $season );
				$scorers   = $provider->supports( 'scorers' ) ? $provider->get_top_scorers( (string) $external_id, $season ) : array();
				$importer->save_tables(
					(string) $external_id,
					$season,
					is_wp_error( $standings ) ? array() : $standings,
					is_wp_error( $scorers ) ? array() : $scorers
				);
			}
		}
		$this->unlock( 'tables' );
	}

	/**
	 * Daily maintenance.
	 *
	 * @return void
	 */
	public function run_maintenance() {
		Logger::prune();
		Http_Client::purge_expired();
	}

	/**
	 * Import fixtures of the given dates from a provider.
	 *
	 * @param Provider $provider Provider.
	 * @param string[] $dates    UTC dates.
	 * @return int
	 */
	public function import_dates( Provider $provider, array $dates ) {
		$importer = new Importer( $provider->get_id() );
		$total    = 0;
		foreach ( $dates as $date ) {
			$matches = $provider->get_matches_by_date( $date );
			if ( is_wp_error( $matches ) ) {
				continue;
			}
			$total += $importer->import_matches( $matches );
		}
		if ( $total ) {
			Logger::info(
				$provider->get_id(),
				/* translators: %d: number of matches. */
				sprintf( __( 'تمت مزامنة %d مباراة.', 'sportsarena-core' ), $total ),
				array( 'dates' => $dates )
			);
		}
		return $total;
	}

	/**
	 * Fill events / lineups / statistics for today's started or finished
	 * matches in batches of 20 (one request per batch, max 2 per run).
	 *
	 * @param Provider $provider Provider.
	 * @return void
	 */
	private function backfill_details( Provider $provider ) {
		if ( ! method_exists( $provider, 'get_many_details' ) ) {
			return;
		}
		$candidates = Repository::query_matches(
			array(
				'status'   => array( 'live', 'halftime', 'finished' ),
				'provider' => $provider->get_id(),
				'from'     => time() - DAY_IN_SECONDS,
				'to'       => time(),
				'limit'    => 100,
			)
		);
		$missing    = array();
		foreach ( $candidates as $post_id ) {
			if ( ! get_post_meta( $post_id, '_sa_events', true ) && ! get_post_meta( $post_id, '_sa_details_done', true ) ) {
				$missing[ $post_id ] = (string) get_post_meta( $post_id, '_sa_external_id', true );
			}
		}
		$importer = new Importer( $provider->get_id() );
		foreach ( array_slice( array_chunk( $missing, 20, true ), 0, 2 ) as $chunk ) {
			$details = $provider->get_many_details( array_values( $chunk ) );
			if ( is_wp_error( $details ) ) {
				return;
			}
			$importer->import_matches( $details );
			foreach ( array_keys( $chunk ) as $post_id ) {
				// Matches without any event (0-0 with no cards) must not be retried forever.
				if ( 'finished' === get_post_meta( $post_id, '_sa_status', true ) ) {
					update_post_meta( $post_id, '_sa_details_done', 1 );
				}
			}
		}
	}

	/**
	 * Refresh matches that are live locally but missing from the live feed.
	 *
	 * @param Provider $provider Provider.
	 * @param Importer $importer Importer.
	 * @param string[] $live_ids External ids in the live feed.
	 * @return void
	 */
	private function refresh_stale_live( Provider $provider, Importer $importer, array $live_ids ) {
		if ( ! $provider->supports( 'details' ) ) {
			return;
		}
		$local = Repository::query_matches(
			array(
				'status'   => array( 'live', 'halftime' ),
				'provider' => $provider->get_id(),
				'limit'    => self::DETAILS_PER_RUN * 2,
			)
		);
		$done  = 0;
		foreach ( $local as $post_id ) {
			$external_id = (string) get_post_meta( $post_id, '_sa_external_id', true );
			if ( in_array( $external_id, $live_ids, true ) || $done >= self::DETAILS_PER_RUN ) {
				continue;
			}
			$details = $provider->get_match_details( $external_id );
			if ( is_array( $details ) ) {
				$importer->import_match( $details );
			}
			++$done;
		}
		if ( $done ) {
			Repository::bump_version();
		}
	}

	/**
	 * Fetch lineups for matches starting within the next hour.
	 *
	 * @param Provider $provider Provider.
	 * @param Importer $importer Importer.
	 * @return void
	 */
	private function refresh_upcoming_lineups( Provider $provider, Importer $importer ) {
		if ( ! $provider->supports( 'details' ) ) {
			return;
		}
		$upcoming = Repository::query_matches(
			array(
				'status'   => array( 'scheduled' ),
				'provider' => $provider->get_id(),
				'from'     => time(),
				'to'       => time() + HOUR_IN_SECONDS,
				'limit'    => 20,
			)
		);
		$done     = 0;
		foreach ( $upcoming as $post_id ) {
			if ( $done >= self::DETAILS_PER_RUN ) {
				break;
			}
			if ( get_post_meta( $post_id, '_sa_lineups', true ) ) {
				continue;
			}
			// Throttle: try each match at most every 10 minutes.
			$tried = (int) get_post_meta( $post_id, '_sa_lineup_checked', true );
			if ( time() - $tried < 10 * MINUTE_IN_SECONDS ) {
				continue;
			}
			update_post_meta( $post_id, '_sa_lineup_checked', time() );
			$details = $provider->get_match_details( (string) get_post_meta( $post_id, '_sa_external_id', true ) );
			if ( is_array( $details ) ) {
				$importer->import_match( $details );
			}
			++$done;
		}
	}

	/**
	 * Fetch details (events/lineups/stats) for one match on demand (admin button / CLI).
	 *
	 * @param int $post_id Match post ID.
	 * @return bool
	 */
	public static function refresh_match( $post_id ) {
		$provider = Provider_Registry::get( (string) get_post_meta( $post_id, '_sa_provider', true ) );
		if ( ! $provider || ! $provider->is_configured() || ! $provider->supports( 'details' ) ) {
			return false;
		}
		$details = $provider->get_match_details( (string) get_post_meta( $post_id, '_sa_external_id', true ) );
		if ( ! is_array( $details ) ) {
			return false;
		}
		( new Importer( $provider->get_id() ) )->import_match( $details );

		if ( $provider->supports( 'h2h' ) ) {
			$home = (int) get_post_meta( $post_id, '_sa_home_team', true );
			$away = (int) get_post_meta( $post_id, '_sa_away_team', true );
			$h2h  = $provider->get_head_to_head(
				(string) get_post_meta( $home, '_sa_external_id', true ),
				(string) get_post_meta( $away, '_sa_external_id', true )
			);
			if ( is_array( $h2h ) && $h2h ) {
				$rows = array();
				foreach ( $h2h as $game ) {
					$rows[] = array(
						'date'  => (int) $game['kickoff'],
						'home'  => Names::arabic( $game['home']['name'] ),
						'away'  => Names::arabic( $game['away']['name'] ),
						'score' => $game['score']['home'] . ' - ' . $game['score']['away'],
						'comp'  => Names::arabic( $game['competition']['name'] ),
					);
				}
				update_post_meta( $post_id, '_sa_h2h', wp_slash( wp_json_encode( Importer::clean_deep( $rows ) ) ) );
			}
		}
		Repository::bump_version();
		return true;
	}
}
