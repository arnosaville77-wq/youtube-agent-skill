<?php
/**
 * API-Football (api-sports.io) adapter for football.
 *
 * Docs: https://www.api-football.com/documentation-v3
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data\Providers;

use SportsArena\Core\Data\Http_Client;
use SportsArena\Core\Data\Provider;
use SportsArena\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * API-Football provider.
 */
class Api_Football implements Provider {

	const BASE_URL = 'https://v3.football.api-sports.io/';

	/**
	 * HTTP client.
	 *
	 * @var Http_Client
	 */
	protected $http;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->http = new Http_Client( $this->get_id() );
	}

	/**
	 * Implements Provider::get_id().
	 */
	public function get_id() {
		return 'api-football';
	}

	/**
	 * Implements Provider::get_label().
	 */
	public function get_label() {
		return 'API-Football (api-sports.io)';
	}

	/**
	 * Implements Provider::get_sports().
	 */
	public function get_sports() {
		return array( 'football' );
	}

	/**
	 * API key from settings (or the SPORTSARENA_API_FOOTBALL_KEY constant).
	 *
	 * @return string
	 */
	protected function key() {
		return (string) Options::get( 'api_football_key' );
	}

	/**
	 * Tracked league ids from settings.
	 *
	 * @return string[]
	 */
	protected function league_ids() {
		return Options::csv( Options::get( 'api_football_leagues' ) );
	}

	/**
	 * Implements Provider::is_configured().
	 */
	public function is_configured() {
		return '' !== $this->key();
	}

	/**
	 * Implements Provider::supports().
	 *
	 * @param string $capability See the Provider interface.
	 */
	public function supports( $capability ) {
		return in_array( $capability, array( 'live', 'details', 'standings', 'scorers', 'h2h' ), true );
	}

	/**
	 * Perform a GET request.
	 *
	 * @param string $endpoint Endpoint path.
	 * @param array  $params   Query params.
	 * @param int    $ttl      Cache TTL.
	 * @return array|\WP_Error The "response" array.
	 */
	protected function request( $endpoint, array $params, $ttl ) {
		$url  = add_query_arg( array_map( 'rawurlencode', $params ), static::BASE_URL . $endpoint );
		$body = $this->http->get_json(
			$url,
			array(
				'x-apisports-key' => $this->key(),
				'Accept'          => 'application/json',
			),
			$ttl,
			$endpoint . '?' . http_build_query( $params )
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		return isset( $body['response'] ) && is_array( $body['response'] ) ? $body['response'] : array();
	}

	/**
	 * Implements Provider::get_matches_by_date().
	 *
	 * @param string $date See the Provider interface.
	 */
	public function get_matches_by_date( $date ) {
		$items = $this->request(
			'fixtures',
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
	 * Implements Provider::get_live_matches().
	 */
	public function get_live_matches() {
		$params  = array( 'live' => 'all' );
		$leagues = $this->league_ids();
		if ( $leagues ) {
			// API-Football accepts "live=39-140-2" to restrict to leagues.
			$params['live'] = implode( '-', $leagues );
		}
		$items = $this->request( 'fixtures', $params, 55 );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		return $this->normalize_many( $items );
	}

	/**
	 * Implements Provider::get_match_details().
	 *
	 * @param string $external_id See the Provider interface.
	 */
	public function get_match_details( $external_id ) {
		$items = $this->request( 'fixtures', array( 'id' => $external_id ), 5 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		return $items ? $this->normalize( $items[0] ) : null;
	}

	/**
	 * Details for up to 20 matches in one request ("ids=1-2-3").
	 *
	 * @param string[] $external_ids Provider match ids.
	 * @return array<int,array>|\WP_Error
	 */
	public function get_many_details( array $external_ids ) {
		$external_ids = array_slice( array_values( $external_ids ), 0, 20 );
		if ( ! $external_ids ) {
			return array();
		}
		$items = $this->request( 'fixtures', array( 'ids' => implode( '-', $external_ids ) ), 10 * MINUTE_IN_SECONDS );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		return $this->normalize_many( $items );
	}

	/**
	 * Implements Provider::get_head_to_head().
	 *
	 * @param string $home_external_id See the Provider interface.
	 * @param string $away_external_id See the Provider interface.
	 */
	public function get_head_to_head( $home_external_id, $away_external_id ) {
		$items = $this->request(
			'fixtures/headtohead',
			array(
				'h2h'  => $home_external_id . '-' . $away_external_id,
				'last' => 5,
			),
			DAY_IN_SECONDS
		);
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		return $this->normalize_many( $items );
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
		foreach ( $items[0]['league']['standings'] ?? array() as $group ) {
			foreach ( (array) $group as $row ) {
				$rows[] = array(
					'group'         => (string) ( $row['group'] ?? '' ),
					'rank'          => (int) ( $row['rank'] ?? 0 ),
					'team'          => array(
						'external_id' => (string) ( $row['team']['id'] ?? '' ),
						'name'        => (string) ( $row['team']['name'] ?? '' ),
						'logo'        => (string) ( $row['team']['logo'] ?? '' ),
					),
					'played'        => (int) ( $row['all']['played'] ?? 0 ),
					'win'           => (int) ( $row['all']['win'] ?? 0 ),
					'draw'          => (int) ( $row['all']['draw'] ?? 0 ),
					'lose'          => (int) ( $row['all']['lose'] ?? 0 ),
					'goals_for'     => (int) ( $row['all']['goals']['for'] ?? 0 ),
					'goals_against' => (int) ( $row['all']['goals']['against'] ?? 0 ),
					'goal_diff'     => (int) ( $row['goalsDiff'] ?? 0 ),
					'points'        => (int) ( $row['points'] ?? 0 ),
					'form'          => (string) ( $row['form'] ?? '' ),
				);
			}
		}
		return $rows;
	}

	/**
	 * Implements Provider::get_top_scorers().
	 *
	 * @param string $competition_external_id See the Provider interface.
	 * @param string $season See the Provider interface.
	 */
	public function get_top_scorers( $competition_external_id, $season ) {
		$items = $this->request(
			'players/topscorers',
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
		foreach ( $items as $i => $item ) {
			$stats  = $item['statistics'][0] ?? array();
			$rows[] = array(
				'rank'    => $i + 1,
				'player'  => (string) ( $item['player']['name'] ?? '' ),
				'team'    => (string) ( $stats['team']['name'] ?? '' ),
				'goals'   => (int) ( $stats['goals']['total'] ?? 0 ),
				'assists' => (int) ( $stats['goals']['assists'] ?? 0 ),
				'played'  => (int) ( $stats['games']['appearences'] ?? 0 ),
			);
		}
		return $rows;
	}

	/**
	 * Implements Provider::get_tracked_competitions().
	 */
	public function get_tracked_competitions() {
		$tracked = array();
		foreach ( $this->league_ids() as $league_id ) {
			$season = $this->current_season( $league_id );
			if ( $season ) {
				$tracked[ $league_id ] = $season;
			}
		}
		return $tracked;
	}

	/**
	 * Current season of a league (cached for a week).
	 *
	 * @param string $league_id League id.
	 * @return string
	 */
	protected function current_season( $league_id ) {
		$items = $this->request(
			'leagues',
			array(
				'id'      => $league_id,
				'current' => 'true',
			),
			WEEK_IN_SECONDS
		);
		if ( is_wp_error( $items ) || empty( $items[0]['seasons'] ) ) {
			return '';
		}
		foreach ( $items[0]['seasons'] as $season ) {
			if ( ! empty( $season['current'] ) ) {
				return (string) $season['year'];
			}
		}
		return '';
	}

	/**
	 * Keep only fixtures from tracked leagues (if a list is configured).
	 *
	 * @param array $items Raw fixtures.
	 * @return array
	 */
	protected function filter_tracked( array $items ) {
		$leagues = $this->league_ids();
		if ( ! $leagues ) {
			return $items;
		}
		return array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $leagues ) {
					return in_array( (string) ( $item['league']['id'] ?? '' ), $leagues, true );
				}
			)
		);
	}

	/**
	 * Normalize a list of fixtures.
	 *
	 * @param array $items Raw fixtures.
	 * @return array<int,array>
	 */
	protected function normalize_many( array $items ) {
		return array_values( array_filter( array_map( array( $this, 'normalize' ), $items ) ) );
	}

	/**
	 * Map provider status codes to the normalized status.
	 *
	 * @param string $short Provider short status.
	 * @return string
	 */
	public static function map_status( $short ) {
		$map = array(
			'TBD'  => 'scheduled',
			'NS'   => 'scheduled',
			'1H'   => 'live',
			'2H'   => 'live',
			'ET'   => 'live',
			'BT'   => 'live',
			'P'    => 'live',
			'LIVE' => 'live',
			'INT'  => 'live',
			'HT'   => 'halftime',
			'FT'   => 'finished',
			'AET'  => 'finished',
			'PEN'  => 'finished',
			'AWD'  => 'finished',
			'WO'   => 'finished',
			'PST'  => 'postponed',
			'SUSP' => 'postponed',
			'CANC' => 'cancelled',
			'ABD'  => 'cancelled',
		);
		return $map[ strtoupper( (string) $short ) ] ?? 'scheduled';
	}

	/**
	 * Normalize one fixture.
	 *
	 * @param array $item Raw fixture.
	 * @return array|null
	 */
	protected function normalize( $item ) {
		if ( empty( $item['fixture']['id'] ) ) {
			return null;
		}
		$short = (string) ( $item['fixture']['status']['short'] ?? 'NS' );
		$home  = $item['teams']['home'] ?? array();
		$away  = $item['teams']['away'] ?? array();

		$events = array();
		foreach ( (array) ( $item['events'] ?? array() ) as $event ) {
			$minute = (string) ( $event['time']['elapsed'] ?? '' );
			if ( ! empty( $event['time']['extra'] ) ) {
				$minute .= '+' . $event['time']['extra'];
			}
			$events[] = array(
				'minute' => $minute,
				'team'   => ( (string) ( $event['team']['id'] ?? '' ) === (string) ( $home['id'] ?? '' ) ) ? 'home' : 'away',
				'type'   => self::map_event_type( (string) ( $event['type'] ?? '' ), (string) ( $event['detail'] ?? '' ) ),
				'detail' => (string) ( $event['detail'] ?? '' ),
				'player' => (string) ( $event['player']['name'] ?? '' ),
				'assist' => (string) ( $event['assist']['name'] ?? '' ),
			);
		}

		$lineups = array();
		foreach ( (array) ( $item['lineups'] ?? array() ) as $lineup ) {
			$lineups[] = array(
				'team'      => ( (string) ( $lineup['team']['id'] ?? '' ) === (string) ( $home['id'] ?? '' ) ) ? 'home' : 'away',
				'formation' => (string) ( $lineup['formation'] ?? '' ),
				'coach'     => (string) ( $lineup['coach']['name'] ?? '' ),
				'start'     => self::lineup_names( $lineup['startXI'] ?? array() ),
				'subs'      => self::lineup_names( $lineup['substitutes'] ?? array() ),
			);
		}

		$statistics = array();
		if ( ! empty( $item['statistics'] ) && count( $item['statistics'] ) >= 2 ) {
			$home_stats = (array) ( $item['statistics'][0]['statistics'] ?? array() );
			$away_stats = (array) ( $item['statistics'][1]['statistics'] ?? array() );
			foreach ( $home_stats as $i => $stat ) {
				$statistics[] = array(
					'type' => (string) ( $stat['type'] ?? '' ),
					'home' => (string) ( $stat['value'] ?? '0' ),
					'away' => (string) ( $away_stats[ $i ]['value'] ?? '0' ),
				);
			}
		}

		return array(
			'external_id' => (string) $item['fixture']['id'],
			'sport'       => 'football',
			'kickoff'     => (int) ( $item['fixture']['timestamp'] ?? strtotime( (string) ( $item['fixture']['date'] ?? '' ) ) ),
			'status'      => self::map_status( $short ),
			'status_raw'  => $short,
			'elapsed'     => isset( $item['fixture']['status']['elapsed'] ) ? (int) $item['fixture']['status']['elapsed'] : null,
			'home'        => array(
				'external_id' => (string) ( $home['id'] ?? '' ),
				'name'        => (string) ( $home['name'] ?? '' ),
				'logo'        => (string) ( $home['logo'] ?? '' ),
				'country'     => (string) ( $item['league']['country'] ?? '' ),
			),
			'away'        => array(
				'external_id' => (string) ( $away['id'] ?? '' ),
				'name'        => (string) ( $away['name'] ?? '' ),
				'logo'        => (string) ( $away['logo'] ?? '' ),
				'country'     => (string) ( $item['league']['country'] ?? '' ),
			),
			'score'       => array(
				'home' => isset( $item['goals']['home'] ) ? (int) $item['goals']['home'] : null,
				'away' => isset( $item['goals']['away'] ) ? (int) $item['goals']['away'] : null,
			),
			'competition' => array(
				'external_id' => (string) ( $item['league']['id'] ?? '' ),
				'name'        => (string) ( $item['league']['name'] ?? '' ),
				'country'     => (string) ( $item['league']['country'] ?? '' ),
				'logo'        => (string) ( $item['league']['logo'] ?? '' ),
				'season'      => (string) ( $item['league']['season'] ?? '' ),
				'round'       => (string) ( $item['league']['round'] ?? '' ),
				'type'        => '',
			),
			'venue'       => (string) ( $item['fixture']['venue']['name'] ?? '' ),
			'city'        => (string) ( $item['fixture']['venue']['city'] ?? '' ),
			'referee'     => (string) ( $item['fixture']['referee'] ?? '' ),
			'events'      => $events,
			'lineups'     => $lineups,
			'statistics'  => $statistics,
		);
	}

	/**
	 * Normalized event type: goal|own_goal|penalty|missed_penalty|yellow|red|sub|var.
	 *
	 * @param string $type   Provider type.
	 * @param string $detail Provider detail.
	 * @return string
	 */
	protected static function map_event_type( $type, $detail ) {
		$type   = strtolower( $type );
		$detail = strtolower( $detail );
		if ( 'goal' === $type ) {
			if ( str_contains( $detail, 'own' ) ) {
				return 'own_goal';
			}
			if ( str_contains( $detail, 'missed' ) ) {
				return 'missed_penalty';
			}
			return str_contains( $detail, 'penalty' ) ? 'penalty' : 'goal';
		}
		if ( 'card' === $type ) {
			return str_contains( $detail, 'red' ) || str_contains( $detail, 'second' ) ? 'red' : 'yellow';
		}
		if ( 'subst' === $type ) {
			return 'sub';
		}
		return 'var';
	}

	/**
	 * Player names from a lineup list.
	 *
	 * @param array $players Raw players.
	 * @return string[]
	 */
	protected static function lineup_names( $players ) {
		$names = array();
		foreach ( (array) $players as $player ) {
			$p       = $player['player'] ?? array();
			$names[] = trim( ( isset( $p['number'] ) ? $p['number'] . ' ' : '' ) . ( $p['name'] ?? '' ) );
		}
		return $names;
	}
}
