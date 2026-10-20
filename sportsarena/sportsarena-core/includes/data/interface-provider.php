<?php
/**
 * Unified data provider contract. Every adapter returns normalized arrays
 * so the importer never deals with provider specific payloads.
 *
 * Normalized match:
 *   array(
 *     'external_id' => string,
 *     'sport'       => string (football|basketball|tennis|formula-1|handball|volleyball|mma),
 *     'kickoff'     => int (UTC timestamp),
 *     'status'      => string (scheduled|live|halftime|finished|postponed|cancelled),
 *     'status_raw'  => string,
 *     'elapsed'     => int|null,
 *     'home'        => array( 'external_id', 'name', 'logo', 'country' ),
 *     'away'        => array( 'external_id', 'name', 'logo', 'country' ),
 *     'score'       => array( 'home' => int|null, 'away' => int|null ),
 *     'competition' => array( 'external_id', 'name', 'country', 'logo', 'season', 'round', 'type' ),
 *     'venue'       => string, 'city' => string, 'referee' => string,
 *     'events'      => array<array{minute:string,team:string,type:string,detail:string,player:string,assist:string}>,
 *     'lineups'     => array<array{team:string,formation:string,coach:string,start:string[],subs:string[]}>,
 *     'statistics'  => array<array{type:string,home:string,away:string}>,
 *   )
 *
 * Normalized standings row:
 *   array( 'group', 'rank', 'team' => array( 'external_id', 'name', 'logo' ), 'played',
 *          'win', 'draw', 'lose', 'goals_for', 'goals_against', 'goal_diff', 'points', 'form' )
 *
 * Normalized scorer row:
 *   array( 'rank', 'player', 'team', 'goals', 'assists', 'played' )
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Provider interface.
 */
interface Provider {

	/**
	 * Machine id (e.g. api-football).
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Sport slugs this provider covers.
	 *
	 * @return string[]
	 */
	public function get_sports();

	/**
	 * Whether an API key (and anything else required) is set.
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Whether the provider supports a capability: live|details|standings|scorers.
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 */
	public function supports( $capability );

	/**
	 * Matches on a given UTC date (Y-m-d), filtered to tracked competitions.
	 *
	 * @param string $date Date.
	 * @return array<int,array>|\WP_Error
	 */
	public function get_matches_by_date( $date );

	/**
	 * Matches currently in play.
	 *
	 * @return array<int,array>|\WP_Error
	 */
	public function get_live_matches();

	/**
	 * Full details (events, lineups, statistics) for one match.
	 *
	 * @param string $external_id Provider match id.
	 * @return array|null|\WP_Error
	 */
	public function get_match_details( $external_id );

	/**
	 * Head to head matches between two teams.
	 *
	 * @param string $home_external_id Home team id.
	 * @param string $away_external_id Away team id.
	 * @return array<int,array>|\WP_Error
	 */
	public function get_head_to_head( $home_external_id, $away_external_id );

	/**
	 * Standings rows for a competition.
	 *
	 * @param string $competition_external_id Competition id.
	 * @param string $season                  Season.
	 * @return array<int,array>|\WP_Error
	 */
	public function get_standings( $competition_external_id, $season );

	/**
	 * Top scorers for a competition.
	 *
	 * @param string $competition_external_id Competition id.
	 * @param string $season                  Season.
	 * @return array<int,array>|\WP_Error
	 */
	public function get_top_scorers( $competition_external_id, $season );

	/**
	 * Tracked competitions as array( external_id => season ).
	 *
	 * @return array<string,string>
	 */
	public function get_tracked_competitions();
}
