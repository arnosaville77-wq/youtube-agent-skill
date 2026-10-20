<?php
/**
 * TheSportsDB adapter (multi-sport: tennis, F1, handball, volleyball, MMA...).
 *
 * Docs: https://www.thesportsdb.com/documentation
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data\Providers;

use SportsArena\Core\Data\Http_Client;
use SportsArena\Core\Data\Provider;
use SportsArena\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * TheSportsDB provider. The free v1 API has no live scores, so matches are
 * refreshed by the 30-minute daily sync.
 */
class Thesportsdb implements Provider {

	const BASE_URL = 'https://www.thesportsdb.com/api/v1/json/';

	/**
	 * TheSportsDB sport name => local sport slug.
	 */
	const SPORT_MAP = array(
		'Soccer'     => 'football',
		'Basketball' => 'basketball',
		'Tennis'     => 'tennis',
		'Motorsport' => 'formula-1',
		'Handball'   => 'handball',
		'Volleyball' => 'volleyball',
		'Fighting'   => 'mma',
	);

	/**
	 * HTTP client.
	 *
	 * @var Http_Client
	 */
	private $http;

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
		return 'thesportsdb';
	}

	/**
	 * Implements Provider::get_label().
	 */
	public function get_label() {
		return 'TheSportsDB';
	}

	/**
	 * Implements Provider::get_sports().
	 */
	public function get_sports() {
		$sports = array();
		foreach ( Options::csv( Options::get( 'thesportsdb_sports' ) ) as $name ) {
			if ( isset( self::SPORT_MAP[ $name ] ) ) {
				$sports[] = self::SPORT_MAP[ $name ];
			}
		}
		return $sports;
	}

	/**
	 * Implements Provider::is_configured().
	 */
	public function is_configured() {
		return '' !== (string) Options::get( 'thesportsdb_key' );
	}

	/**
	 * Implements Provider::supports().
	 *
	 * @param string $capability See the Provider interface.
	 */
	public function supports( $capability ) {
		return false;
	}

	/**
	 * GET request. TheSportsDB puts the key in the path; the log label omits it.
	 *
	 * @param string $endpoint Endpoint file (e.g. eventsday.php).
	 * @param array  $params   Query params.
	 * @param int    $ttl      TTL.
	 * @return array|\WP_Error
	 */
	private function request( $endpoint, array $params, $ttl ) {
		$key = rawurlencode( (string) Options::get( 'thesportsdb_key' ) );
		$url = add_query_arg( array_map( 'rawurlencode', $params ), self::BASE_URL . $key . '/' . $endpoint );
		return $this->http->get_json( $url, array( 'Accept' => 'application/json' ), $ttl, $endpoint . '?' . http_build_query( $params ) );
	}

	/**
	 * Implements Provider::get_matches_by_date().
	 *
	 * @param string $date See the Provider interface.
	 */
	public function get_matches_by_date( $date ) {
		$matches = array();
		foreach ( Options::csv( Options::get( 'thesportsdb_sports' ) ) as $sport_name ) {
			if ( ! isset( self::SPORT_MAP[ $sport_name ] ) ) {
				continue;
			}
			$body = $this->request(
				'eventsday.php',
				array(
					'd' => $date,
					's' => $sport_name,
				),
				30 * MINUTE_IN_SECONDS - 60
			);
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			foreach ( (array) ( $body['events'] ?? array() ) as $event ) {
				// Motorsport covers many series: keep Formula 1 only.
				if ( 'Motorsport' === $sport_name && false === stripos( (string) ( $event['strLeague'] ?? '' ), 'Formula 1' ) ) {
					continue;
				}
				$normalized = $this->normalize( $event, self::SPORT_MAP[ $sport_name ] );
				if ( $normalized ) {
					$matches[] = $normalized;
				}
			}
		}
		return $matches;
	}

	/**
	 * Implements Provider::get_live_matches().
	 */
	public function get_live_matches() {
		return array();
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
	 * Implements Provider::get_head_to_head().
	 *
	 * @param string $home_external_id See the Provider interface.
	 * @param string $away_external_id See the Provider interface.
	 */
	public function get_head_to_head( $home_external_id, $away_external_id ) {
		return array();
	}

	/**
	 * Implements Provider::get_standings().
	 *
	 * @param string $competition_external_id See the Provider interface.
	 * @param string $season See the Provider interface.
	 */
	public function get_standings( $competition_external_id, $season ) {
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
	 * Implements Provider::get_tracked_competitions().
	 */
	public function get_tracked_competitions() {
		return array();
	}

	/**
	 * Map a TheSportsDB status string.
	 *
	 * @param array $event Raw event.
	 * @return string
	 */
	private static function map_status( array $event ) {
		if ( 'yes' === strtolower( (string) ( $event['strPostponed'] ?? '' ) ) ) {
			return 'postponed';
		}
		$status = strtolower( (string) ( $event['strStatus'] ?? '' ) );
		if ( in_array( $status, array( 'match finished', 'ft', 'aet', 'pen', 'finished' ), true ) ) {
			return 'finished';
		}
		if ( in_array( $status, array( 'cancelled', 'canc', 'abandoned' ), true ) ) {
			return 'cancelled';
		}
		if ( in_array( $status, array( 'ht', 'halftime' ), true ) ) {
			return 'halftime';
		}
		if ( '' === $status || in_array( $status, array( 'not started', 'ns', 'tbd' ), true ) ) {
			// Some sports never get a status: infer "finished" from a score in the past.
			if ( isset( $event['intHomeScore'] ) && '' !== (string) $event['intHomeScore'] && strtotime( (string) ( $event['strTimestamp'] ?? '' ) . ' UTC' ) < time() - 3 * HOUR_IN_SECONDS ) {
				return 'finished';
			}
			return 'scheduled';
		}
		return 'live';
	}

	/**
	 * Normalize one event. Single-participant events (an F1 race) have no
	 * away side: "away" stays empty and the event name is used as the title.
	 *
	 * @param array  $event Raw event.
	 * @param string $sport Local sport slug.
	 * @return array|null
	 */
	private function normalize( array $event, $sport ) {
		if ( empty( $event['idEvent'] ) ) {
			return null;
		}
		$timestamp = ! empty( $event['strTimestamp'] )
			? strtotime( $event['strTimestamp'] . ( str_contains( $event['strTimestamp'], '+' ) ? '' : ' UTC' ) )
			: strtotime( ( $event['dateEvent'] ?? '' ) . ' ' . ( $event['strTime'] ?? '00:00:00' ) . ' UTC' );

		$home_name = (string) ( $event['strHomeTeam'] ?? '' );
		$away_name = (string) ( $event['strAwayTeam'] ?? '' );
		if ( '' === $home_name ) {
			$home_name = (string) ( $event['strEvent'] ?? '' );
		}

		return array(
			'external_id' => (string) $event['idEvent'],
			'sport'       => $sport,
			'kickoff'     => (int) $timestamp,
			'status'      => self::map_status( $event ),
			'status_raw'  => (string) ( $event['strStatus'] ?? '' ),
			'elapsed'     => null,
			'home'        => array(
				'external_id' => (string) ( $event['idHomeTeam'] ?? '' ),
				'name'        => $home_name,
				'logo'        => (string) ( $event['strHomeTeamBadge'] ?? '' ),
				'country'     => (string) ( $event['strCountry'] ?? '' ),
			),
			'away'        => array(
				'external_id' => (string) ( $event['idAwayTeam'] ?? '' ),
				'name'        => $away_name,
				'logo'        => (string) ( $event['strAwayTeamBadge'] ?? '' ),
				'country'     => (string) ( $event['strCountry'] ?? '' ),
			),
			'score'       => array(
				'home' => isset( $event['intHomeScore'] ) && '' !== (string) $event['intHomeScore'] ? (int) $event['intHomeScore'] : null,
				'away' => isset( $event['intAwayScore'] ) && '' !== (string) $event['intAwayScore'] ? (int) $event['intAwayScore'] : null,
			),
			'competition' => array(
				'external_id' => (string) ( $event['idLeague'] ?? '' ),
				'name'        => (string) ( $event['strLeague'] ?? '' ),
				'country'     => (string) ( $event['strCountry'] ?? '' ),
				'logo'        => (string) ( $event['strLeagueBadge'] ?? '' ),
				'season'      => (string) ( $event['strSeason'] ?? '' ),
				'round'       => (string) ( $event['intRound'] ?? '' ),
				'type'        => '',
			),
			'venue'       => (string) ( $event['strVenue'] ?? '' ),
			'city'        => (string) ( $event['strCity'] ?? '' ),
			'referee'     => '',
			'events'      => array(),
			'lineups'     => array(),
			'statistics'  => array(),
		);
	}
}
