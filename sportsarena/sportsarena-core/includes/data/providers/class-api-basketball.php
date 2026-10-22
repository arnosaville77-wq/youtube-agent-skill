<?php
/**
 * API-Basketball (api-sports.io) adapter.
 *
 * Docs: https://api-sports.io/documentation/basketball/v1
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data\Providers;

use SportsArena\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * API-Basketball provider. Shares transport and auth with API-Football.
 */
class Api_Basketball extends Api_Football {

	const BASE_URL = 'https://v1.basketball.api-sports.io/';

	/**
	 * Implements Provider::get_id().
	 */
	public function get_id() {
		return 'api-basketball';
	}

	/**
	 * Implements Provider::get_label().
	 */
	public function get_label() {
		return 'API-Basketball (api-sports.io)';
	}

	/**
	 * Implements Provider::get_sports().
	 */
	public function get_sports() {
		return array( 'basketball' );
	}

	/**
	 * A dedicated key can be set; falls back to the API-Football key because
	 * one api-sports.io account key works for every sport.
	 *
	 * @return string
	 */
	protected function key() {
		$key = (string) Options::get( 'api_basketball_key' );
		return '' !== $key ? $key : (string) Options::get( 'api_football_key' );
	}

	/**
	 * Implements Provider::league_ids().
	 */
	protected function league_ids() {
		return Options::csv( Options::get( 'api_basketball_leagues' ) );
	}

	/**
	 * Basketball is enabled only when at least one league is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->key() && (bool) $this->league_ids();
	}

	/**
	 * Implements Provider::supports().
	 *
	 * @param string $capability See the Provider interface.
	 */
	public function supports( $capability ) {
		return in_array( $capability, array( 'live', 'standings' ), true );
	}

	/**
	 * Implements Provider::get_matches_by_date().
	 *
	 * @param string $date See the Provider interface.
	 */
	public function get_matches_by_date( $date ) {
		$items = $this->request(
			'games',
			array(
				'date'     => $date,
				'timezone' => 'UTC',
			),
			30 * MINUTE_IN_SECONDS - 60
		);
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		return $this->normalize_many( $this->filter_tracked( $items ) );
	}

	/**
	 * API-Basketball has no "live=all" filter: poll today's games (short TTL)
	 * and keep those in play.
	 */
	public function get_live_matches() {
		$items = $this->request(
			'games',
			array(
				'date'     => gmdate( 'Y-m-d' ),
				'timezone' => 'UTC',
			),
			55
		);
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		$matches = $this->normalize_many( $this->filter_tracked( $items ) );
		return array_values(
			array_filter(
				$matches,
				static function ( $game ) {
					return in_array( $game['status'], array( 'live', 'halftime' ), true );
				}
			)
		);
	}

	/**
	 * Implements Provider::get_match_details().
	 *
	 * @param string $external_id See the Provider interface.
	 */
	public function get_match_details( $external_id ) {
		return null;
	}

	/**
	 * Not available for basketball (no per-game events in this adapter).
	 *
	 * @param string[] $external_ids Ids.
	 * @return array
	 */
	public function get_many_details( array $external_ids ) {
		return array();
	}

	/**
	 * Implements Provider::get_head_to_head().
	 *
	 * @param string $home_external_id See the Provider interface.
	 * @param string $away_external_id See the Provider interface.
	 */
	public function get_head_to_head( $home_external_id, $away_external_id ) {
		return array();
	}

	/**
	 * Implements Provider::get_top_scorers().
	 *
	 * @param string $competition_external_id See the Provider interface.
	 * @param string $season See the Provider interface.
	 */
	public function get_top_scorers( $competition_external_id, $season ) {
		return array();
	}

	/**
	 * Implements Provider::get_standings().
	 *
	 * @param string $competition_external_id See the Provider interface.
	 * @param string $season See the Provider interface.
	 */
	public function get_standings( $competition_external_id, $season ) {
		$items = $this->request(
			'standings',
			array(
				'league' => $competition_external_id,
				'season' => $season,
			),
			6 * HOUR_IN_SECONDS - 60
		);
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		$rows = array();
		foreach ( $items as $group ) {
			foreach ( (array) $group as $row ) {
				$for     = (int) ( $row['points']['for'] ?? 0 );
				$against = (int) ( $row['points']['against'] ?? 0 );
				$rows[]  = array(
					'group'         => (string) ( $row['group']['name'] ?? '' ),
					'rank'          => (int) ( $row['position'] ?? 0 ),
					'team'          => array(
						'external_id' => (string) ( $row['team']['id'] ?? '' ),
						'name'        => (string) ( $row['team']['name'] ?? '' ),
						'logo'        => (string) ( $row['team']['logo'] ?? '' ),
					),
					'played'        => (int) ( $row['games']['played'] ?? 0 ),
					'win'           => (int) ( $row['games']['win']['total'] ?? 0 ),
					'draw'          => 0,
					'lose'          => (int) ( $row['games']['lose']['total'] ?? 0 ),
					'goals_for'     => $for,
					'goals_against' => $against,
					'goal_diff'     => $for - $against,
					'points'        => (int) ( $row['games']['win']['total'] ?? 0 ),
					'form'          => (string) ( $row['form'] ?? '' ),
				);
			}
		}
		return $rows;
	}

	/**
	 * Current season: the one whose start/end range contains today.
	 *
	 * @param string $league_id League id.
	 * @return string
	 */
	protected function current_season( $league_id ) {
		$items = $this->request( 'leagues', array( 'id' => $league_id ), WEEK_IN_SECONDS );
		if ( is_wp_error( $items ) || empty( $items[0]['seasons'] ) ) {
			return '';
		}
		$today  = gmdate( 'Y-m-d' );
		$latest = '';
		foreach ( $items[0]['seasons'] as $season ) {
			$latest = (string) ( $season['season'] ?? $latest );
			if ( ( $season['start'] ?? '' ) <= $today && ( $season['end'] ?? '' ) >= $today ) {
				return (string) $season['season'];
			}
		}
		return $latest;
	}

	/**
	 * Provider specific map status.
	 *
	 * @param string $short See the Provider interface.
	 */
	public static function map_status( $short ) {
		$short = strtoupper( (string) $short );
		if ( in_array( $short, array( 'Q1', 'Q2', 'Q3', 'Q4', 'OT', 'BT' ), true ) ) {
			return 'live';
		}
		$map = array(
			'NS'   => 'scheduled',
			'HT'   => 'halftime',
			'FT'   => 'finished',
			'AOT'  => 'finished',
			'AWD'  => 'finished',
			'POST' => 'postponed',
			'SUSP' => 'postponed',
			'CANC' => 'cancelled',
			'ABD'  => 'cancelled',
		);
		return $map[ $short ] ?? 'scheduled';
	}

	/**
	 * Provider specific normalize.
	 *
	 * @param array $item See the Provider interface.
	 */
	protected function normalize( $item ) {
		if ( empty( $item['id'] ) ) {
			return null;
		}
		$short = (string) ( $item['status']['short'] ?? 'NS' );
		$home  = $item['teams']['home'] ?? array();
		$away  = $item['teams']['away'] ?? array();

		return array(
			'external_id' => (string) $item['id'],
			'sport'       => 'basketball',
			'kickoff'     => (int) ( $item['timestamp'] ?? strtotime( (string) ( $item['date'] ?? '' ) ) ),
			'status'      => self::map_status( $short ),
			'status_raw'  => $short,
			'elapsed'     => null,
			'home'        => array(
				'external_id' => (string) ( $home['id'] ?? '' ),
				'name'        => (string) ( $home['name'] ?? '' ),
				'logo'        => (string) ( $home['logo'] ?? '' ),
				'country'     => (string) ( $item['country']['name'] ?? '' ),
			),
			'away'        => array(
				'external_id' => (string) ( $away['id'] ?? '' ),
				'name'        => (string) ( $away['name'] ?? '' ),
				'logo'        => (string) ( $away['logo'] ?? '' ),
				'country'     => (string) ( $item['country']['name'] ?? '' ),
			),
			'score'       => array(
				'home' => isset( $item['scores']['home']['total'] ) ? (int) $item['scores']['home']['total'] : null,
				'away' => isset( $item['scores']['away']['total'] ) ? (int) $item['scores']['away']['total'] : null,
			),
			'competition' => array(
				'external_id' => (string) ( $item['league']['id'] ?? '' ),
				'name'        => (string) ( $item['league']['name'] ?? '' ),
				'country'     => (string) ( $item['country']['name'] ?? '' ),
				'logo'        => (string) ( $item['league']['logo'] ?? '' ),
				'season'      => (string) ( $item['league']['season'] ?? '' ),
				'round'       => (string) ( $item['week'] ?? $item['stage'] ?? '' ),
				'type'        => (string) ( $item['league']['type'] ?? '' ),
			),
			'venue'       => '',
			'city'        => '',
			'referee'     => '',
			'events'      => array(),
			'lineups'     => array(),
			'statistics'  => array(),
		);
	}
}
