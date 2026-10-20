<?php
/**
 * Turns normalized provider data into WordPress posts (each match is an
 * indexable "match" post; teams and competitions are their own posts).
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data;

use SportsArena\Core\Post_Types;
use SportsArena\Core\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
final class Importer {

	/**
	 * Lookup cache: "type|provider|external_id" => post ID.
	 *
	 * @var array<string,int>
	 */
	private $lookup = array();

	/**
	 * Provider id of the batch being imported.
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * Constructor.
	 *
	 * @param string $provider Provider id.
	 */
	public function __construct( $provider ) {
		$this->provider = $provider;
	}

	/**
	 * Import many matches. Returns the number of created/updated posts.
	 *
	 * @param array<int,array> $matches Normalized matches.
	 * @return int
	 */
	public function import_matches( array $matches ) {
		$count = 0;
		wp_defer_term_counting( true );
		foreach ( $matches as $match ) {
			if ( $this->import_match( $match ) ) {
				++$count;
			}
		}
		wp_defer_term_counting( false );
		if ( $count ) {
			Repository::bump_version();
		}
		return $count;
	}

	/**
	 * Create or update one match post.
	 *
	 * @param array $m Normalized match.
	 * @return int Post ID or 0.
	 */
	public function import_match( array $m ) {
		if ( empty( $m['external_id'] ) || empty( $m['home']['name'] ) || empty( $m['kickoff'] ) ) {
			return 0;
		}

		$sport_term     = $this->ensure_sport( $m['sport'] );
		$competition_id = $this->ensure_competition( $m['competition'], $m['sport'] );
		$home_id        = $this->ensure_team( $m['home'], $m['sport'] );
		$away_id        = ! empty( $m['away']['name'] ) ? $this->ensure_team( $m['away'], $m['sport'] ) : 0;

		$post_id = $this->find( Post_Types::MATCH, $m['external_id'] );
		$status  = (string) $m['status'];

		if ( ! $post_id ) {
			$title = $away_id
				/* translators: 1: home team, 2: away team. */
				? sprintf( __( '%1$s ضد %2$s', 'sportsarena-core' ), get_the_title( $home_id ), get_the_title( $away_id ) )
				: get_the_title( $home_id );

			// Slug from the English names: /match/real-madrid-vs-barcelona-2026-10-26/.
			$date = wp_date( 'Y-m-d', (int) $m['kickoff'] );
			$slug = $away_id
				? sanitize_title( $m['home']['name'] . '-vs-' . $m['away']['name'] . '-' . $date )
				: sanitize_title( $m['home']['name'] . '-' . $date );

			$post_id = wp_insert_post(
				array(
					'post_type'   => Post_Types::MATCH,
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_name'   => $slug,
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				\SportsArena\Core\Logger::error( 'importer', $post_id->get_error_message(), array( 'external_id' => $m['external_id'] ) );
				return 0;
			}
			update_post_meta( $post_id, '_sa_provider', $this->provider );
			update_post_meta( $post_id, '_sa_external_id', (string) $m['external_id'] );
			$this->lookup[ Post_Types::MATCH . '|' . $this->provider . '|' . $m['external_id'] ] = $post_id;
		} elseif ( get_post_meta( $post_id, '_sa_status', true ) !== $status ) {
			// Bump post_modified when the status changes (sitemap lastmod / freshness).
			wp_update_post(
				array(
					'ID'                => $post_id,
					'post_modified'     => current_time( 'mysql' ),
					'post_modified_gmt' => current_time( 'mysql', true ),
				)
			);
		}

		$meta = array(
			'_sa_home_team'   => $home_id,
			'_sa_away_team'   => $away_id,
			'_sa_competition' => $competition_id,
			'_sa_kickoff'     => (int) $m['kickoff'],
			'_sa_status'      => $status,
			'_sa_status_raw'  => (string) $m['status_raw'],
			'_sa_elapsed'     => null === $m['elapsed'] ? '' : (int) $m['elapsed'],
			'_sa_home_score'  => null === $m['score']['home'] ? '' : (string) $m['score']['home'],
			'_sa_away_score'  => null === $m['score']['away'] ? '' : (string) $m['score']['away'],
			'_sa_venue'       => sanitize_text_field( Names::localize( (string) $m['venue'] ) ),
			'_sa_city'        => sanitize_text_field( Names::localize( (string) $m['city'] ) ),
			'_sa_round'       => sanitize_text_field( $m['competition']['round'] ?? '' ),
			'_sa_referee'     => sanitize_text_field( $m['referee'] ),
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		// List endpoints do not include details: never wipe stored details with empty arrays.
		foreach ( array(
			'_sa_events'  => 'events',
			'_sa_lineups' => 'lineups',
			'_sa_stats'   => 'statistics',
		) as $key => $field ) {
			if ( ! empty( $m[ $field ] ) ) {
				update_post_meta( $post_id, $key, wp_slash( wp_json_encode( self::clean_deep( $m[ $field ] ) ) ) );
			}
		}

		if ( $sport_term ) {
			wp_set_object_terms( $post_id, array( $sport_term ), Post_Types::TAX_SPORT );
		}
		if ( ! empty( $m['competition']['season'] ) ) {
			wp_set_object_terms( $post_id, array( (string) $m['competition']['season'] ), Post_Types::TAX_SEASON );
		}

		return (int) $post_id;
	}

	/**
	 * Store standings and scorers on the competition post.
	 *
	 * @param string $external_id Competition external id.
	 * @param string $season      Season.
	 * @param array  $standings   Normalized standings.
	 * @param array  $scorers     Normalized scorers.
	 * @return void
	 */
	public function save_tables( $external_id, $season, array $standings, array $scorers ) {
		$post_id = $this->find( Post_Types::COMPETITION, $external_id );
		if ( ! $post_id ) {
			return;
		}
		// Resolve local team links so the table can link to team pages.
		foreach ( $standings as &$row ) {
			$row['team']['post_id'] = $this->find( Post_Types::TEAM, $row['team']['external_id'] );
		}
		unset( $row );
		if ( $standings ) {
			update_post_meta( $post_id, '_sa_standings', wp_slash( wp_json_encode( self::clean_deep( $standings ) ) ) );
		}
		if ( $scorers ) {
			update_post_meta( $post_id, '_sa_scorers', wp_slash( wp_json_encode( self::clean_deep( $scorers ) ) ) );
		}
		update_post_meta( $post_id, '_sa_season', sanitize_text_field( $season ) );
		Repository::bump_version();
	}

	/**
	 * Find a post by provider external id.
	 *
	 * @param string $post_type   Post type.
	 * @param string $external_id External id.
	 * @return int
	 */
	public function find( $post_type, $external_id ) {
		if ( '' === (string) $external_id ) {
			return 0;
		}
		$cache_key = $post_type . '|' . $this->provider . '|' . $external_id;
		if ( isset( $this->lookup[ $cache_key ] ) ) {
			return $this->lookup[ $cache_key ];
		}
		$ids                        = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Indexed lookups during background sync only.
				'meta_query'       => array(
					array(
						'key'   => '_sa_provider',
						'value' => $this->provider,
					),
					array(
						'key'   => '_sa_external_id',
						'value' => (string) $external_id,
					),
				),
			)
		);
		$this->lookup[ $cache_key ] = $ids ? (int) $ids[0] : 0;
		return $this->lookup[ $cache_key ];
	}

	/**
	 * Ensure a sport term exists and return its ID.
	 *
	 * @param string $slug Sport slug.
	 * @return int
	 */
	public function ensure_sport( $slug ) {
		$term = get_term_by( 'slug', $slug, Post_Types::TAX_SPORT );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$names  = Names::sports();
		$result = wp_insert_term( $names[ $slug ] ?? $slug, Post_Types::TAX_SPORT, array( 'slug' => $slug ) );
		return is_wp_error( $result ) ? 0 : (int) $result['term_id'];
	}

	/**
	 * Ensure a competition post exists.
	 *
	 * @param array  $c     Normalized competition.
	 * @param string $sport Sport slug.
	 * @return int
	 */
	private function ensure_competition( array $c, $sport ) {
		if ( empty( $c['external_id'] ) || empty( $c['name'] ) ) {
			return 0;
		}
		$post_id = $this->find( Post_Types::COMPETITION, $c['external_id'] );
		if ( $post_id ) {
			if ( ! empty( $c['logo'] ) && '' === (string) get_post_meta( $post_id, '_sa_logo_url', true ) ) {
				update_post_meta( $post_id, '_sa_logo_url', esc_url_raw( $c['logo'] ) );
			}
			return $post_id;
		}
		$dictionary = Names::dictionary();
		$keyed      = $c['name'] . ' (' . $c['country'] . ')';
		// Same-named leagues (Premier League England / Egypt) get the country in their title.
		if ( isset( $dictionary[ $keyed ] ) ) {
			$title = Names::is_arabic() ? $dictionary[ $keyed ] : $keyed;
		} else {
			$title = Names::localize( $c['name'] );
		}
		// Avoid slug clashes between same-named leagues (Premier League England / Egypt).
		$slug = sanitize_title( $c['name'] );
		if ( get_page_by_path( $slug, OBJECT, Post_Types::COMPETITION ) && ! empty( $c['country'] ) ) {
			$slug = sanitize_title( $c['country'] . '-' . $c['name'] );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Post_Types::COMPETITION,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return 0;
		}
		update_post_meta( $post_id, '_sa_provider', $this->provider );
		update_post_meta( $post_id, '_sa_external_id', (string) $c['external_id'] );
		update_post_meta( $post_id, '_sa_name_en', sanitize_text_field( $c['name'] ) );
		update_post_meta( $post_id, '_sa_season', sanitize_text_field( $c['season'] ) );
		update_post_meta( $post_id, '_sa_type', sanitize_text_field( $c['type'] ) );
		update_post_meta( $post_id, '_sa_logo_url', esc_url_raw( $c['logo'] ) );
		update_post_meta( $post_id, '_sa_country_en', sanitize_text_field( $c['country'] ) );
		update_post_meta( $post_id, '_sa_priority', 50 );
		$sport_term = $this->ensure_sport( $sport );
		if ( $sport_term ) {
			wp_set_object_terms( $post_id, array( $sport_term ), Post_Types::TAX_SPORT );
		}
		if ( ! empty( $c['country'] ) ) {
			wp_set_object_terms( $post_id, array( Names::localize( $c['country'] ) ), Post_Types::TAX_COUNTRY );
		}
		$this->lookup[ Post_Types::COMPETITION . '|' . $this->provider . '|' . $c['external_id'] ] = (int) $post_id;
		return (int) $post_id;
	}

	/**
	 * Ensure a team post exists.
	 *
	 * @param array  $t     Normalized team.
	 * @param string $sport Sport slug.
	 * @return int
	 */
	private function ensure_team( array $t, $sport ) {
		$external_id = '' !== (string) $t['external_id'] ? (string) $t['external_id'] : 'name:' . sanitize_title( $t['name'] );
		$post_id     = $this->find( Post_Types::TEAM, $external_id );
		if ( $post_id ) {
			// A team first seen in an international cup gets its real country later.
			$known = (string) get_post_meta( $post_id, '_sa_country_en', true );
			if ( ! empty( $t['country'] ) && ( '' === $known || '' === Countries::code( $known ) ) && '' !== Countries::code( $t['country'] ) ) {
				update_post_meta( $post_id, '_sa_country_en', sanitize_text_field( $t['country'] ) );
			}
			if ( ! empty( $t['logo'] ) && '' === (string) get_post_meta( $post_id, '_sa_logo_url', true ) ) {
				update_post_meta( $post_id, '_sa_logo_url', esc_url_raw( $t['logo'] ) );
			}
			return $post_id;
		}
		$slug = sanitize_title( $t['name'] );
		if ( get_page_by_path( $slug, OBJECT, Post_Types::TEAM ) ) {
			$slug = sanitize_title( $t['name'] . '-' . $sport );
		}
		$post_id = wp_insert_post(
			array(
				'post_type'   => Post_Types::TEAM,
				'post_status' => 'publish',
				'post_title'  => Names::localize( $t['name'] ),
				'post_name'   => $slug,
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return 0;
		}
		update_post_meta( $post_id, '_sa_provider', $this->provider );
		update_post_meta( $post_id, '_sa_external_id', $external_id );
		update_post_meta( $post_id, '_sa_name_en', sanitize_text_field( $t['name'] ) );
		update_post_meta( $post_id, '_sa_logo_url', esc_url_raw( $t['logo'] ) );
		update_post_meta( $post_id, '_sa_country_en', sanitize_text_field( $t['country'] ) );
		$sport_term = $this->ensure_sport( $sport );
		if ( $sport_term ) {
			wp_set_object_terms( $post_id, array( $sport_term ), Post_Types::TAX_SPORT );
		}
		if ( ! empty( $t['country'] ) ) {
			wp_set_object_terms( $post_id, array( Names::localize( $t['country'] ) ), Post_Types::TAX_COUNTRY );
		}
		$this->lookup[ Post_Types::TEAM . '|' . $this->provider . '|' . $external_id ] = (int) $post_id;
		return (int) $post_id;
	}

	/**
	 * Recursively sanitize scalar strings in provider payloads before storage.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function clean_deep( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( self::class, 'clean_deep' ), $value );
		}
		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}
		return $value;
	}
}
