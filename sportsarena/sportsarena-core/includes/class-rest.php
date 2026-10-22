<?php
/**
 * Public, read-only REST endpoints: live score fragments and quick search.
 *
 * These endpoints never change state, so they intentionally do not require a
 * nonce (a nonce would break full-page caching for anonymous visitors). All
 * state-changing requests (contact form, admin tools, settings) verify a
 * nonce and the user's capabilities.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
final class Rest {

	const NAMESPACE = 'sportsarena/v1';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			self::NAMESPACE,
			'/live',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'live' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $value ) {
							return is_string( $value ) && (bool) preg_match( '/^[0-9,]{1,600}$/', $value );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $value ) {
							$length = mb_strlen( trim( (string) $value ) );
							return $length >= 2 && $length <= 60;
						},
					),
				),
			)
		);
	}

	/**
	 * Live score fragments for the given match IDs (score area only).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function live( $request ) {
		$ids = array_slice( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $request['ids'] ) ) ) ), 0, 60 );
		$out = array();
		foreach ( $ids as $id ) {
			if ( Post_Types::MATCH !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$match  = Repository::match( $id );
			$events = array();
			foreach ( (array) $match['events'] as $event ) {
				$type = (string) ( $event['type'] ?? '' );
				if ( in_array( $type, array( 'goal', 'penalty', 'own_goal', 'red' ), true ) ) {
					$team     = (string) ( $event['team'] ?? 'home' );
					$events[] = array(
						'minute' => (string) ( $event['minute'] ?? '' ),
						// The team the event counts for (an own goal counts for the other side).
						'team'   => 'own_goal' === $type ? ( 'home' === $team ? 'away' : 'home' ) : $team,
						'type'   => $type,
						'player' => sportsarena_text( (string) ( $event['player'] ?? '' ) ),
					);
				}
			}
			$out[] = array(
				'id'      => $id,
				'status'  => $match['status'],
				'label'   => sportsarena_status_label( $match ),
				'home'    => $match['home_score'],
				'away'    => $match['away_score'],
				'elapsed' => $match['elapsed'],
				'live'    => sportsarena_is_live( $match ),
				'events'  => $events,
			);
		}
		$response = new \WP_REST_Response(
			array(
				'matches'  => $out,
				'interval' => max( 60, (int) Options::get( 'live_interval' ) ),
			)
		);
		// Let a CDN/page cache absorb bursts; data changes at most once a minute.
		$response->header( 'Cache-Control', 'public, max-age=30' );
		return $response;
	}

	/**
	 * Quick search for teams and competitions by title.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function search( $request ) {
		$term  = trim( (string) $request['q'] );
		$query = new \WP_Query(
			array(
				'post_type'      => array( Post_Types::TEAM, Post_Types::COMPETITION ),
				'post_status'    => 'publish',
				's'              => $term,
				'posts_per_page' => 8,
				'no_found_rows'  => true,
				'orderby'        => 'relevance',
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'url'   => get_permalink( $post ),
				'type'  => Post_Types::TEAM === $post->post_type ? __( 'فريق', 'sportsarena-core' ) : __( 'بطولة', 'sportsarena-core' ),
			);
		}
		$response = new \WP_REST_Response( array( 'items' => $items ) );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}
}
