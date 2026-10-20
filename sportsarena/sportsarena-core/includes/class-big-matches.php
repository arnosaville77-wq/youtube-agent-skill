<?php
/**
 * Big matches: games between big clubs (a list editable in the settings) and
 * games the editor marked by hand. Shown on /big-matches/ and on the home page.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Big matches.
 */
final class Big_Matches {

	/**
	 * Match meta set by the "big match" checkbox: 1 = always big, 0 = never.
	 */
	const META = '_sa_big';

	/**
	 * Default big clubs (English provider names).
	 *
	 * @return string[]
	 */
	public static function default_clubs() {
		return array(
			'Real Madrid',
			'Barcelona',
			'Atletico Madrid',
			'Manchester City',
			'Manchester United',
			'Liverpool',
			'Arsenal',
			'Chelsea',
			'Tottenham',
			'Bayern Munich',
			'Borussia Dortmund',
			'Bayer Leverkusen',
			'Paris Saint Germain',
			'Marseille',
			'Juventus',
			'Inter',
			'AC Milan',
			'Napoli',
			'Al-Hilal Saudi FC',
			'Al-Nassr',
			'Al-Ittihad FC',
			'Al-Ahli Jeddah',
			'Al Ahly',
			'Zamalek SC',
			'Wydad AC',
			'Raja Casablanca',
			'Esperance de Tunis',
			'Los Angeles Lakers',
			'Boston Celtics',
			'Golden State Warriors',
		);
	}

	/**
	 * Big clubs from the settings (one English name per line), lowercased.
	 *
	 * @return string[]
	 */
	public static function clubs() {
		$raw   = (string) Options::get( 'big_clubs' );
		$clubs = '' === trim( $raw ) ? self::default_clubs() : preg_split( '/\r\n|\r|\n/', $raw );
		/**
		 * Filter the big clubs (English names as sent by the data provider).
		 *
		 * @param string[] $clubs Club names.
		 */
		$clubs = (array) apply_filters( 'sportsarena_big_clubs', $clubs );
		return array_values( array_filter( array_map( static fn( $name ) => strtolower( trim( (string) $name ) ), $clubs ) ) );
	}

	/**
	 * Whether a match is a big match.
	 *
	 * @param int $match_id Match ID.
	 * @return bool
	 */
	public static function is_big( $match_id ) {
		$flag = get_post_meta( $match_id, self::META, true );
		if ( '' !== $flag ) {
			return '1' === (string) $flag;
		}
		$home = (int) get_post_meta( $match_id, '_sa_home_team', true );
		$away = (int) get_post_meta( $match_id, '_sa_away_team', true );
		if ( ! $home || ! $away ) {
			return false;
		}
		$clubs = self::clubs();
		foreach ( array( $home, $away ) as $team ) {
			$name = strtolower( (string) get_post_meta( $team, '_sa_name_en', true ) );
			if ( '' === $name || ! in_array( $name, $clubs, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The UEFA Champions League competition post, 0 if not imported.
	 *
	 * @return int
	 */
	public static function champions_league() {
		$ids = get_posts(
			array(
				'post_type'      => Post_Types::COMPETITION,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_sa_name_en', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Single indexed lookup.
				'meta_value'     => 'UEFA Champions League', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Single indexed lookup.
			)
		);
		/**
		 * Filter the competition shown in the Champions League section.
		 *
		 * @param int $id Competition post ID.
		 */
		return (int) apply_filters( 'sportsarena_champions_league_id', $ids ? (int) $ids[0] : 0 );
	}

	/**
	 * URL of the big matches page.
	 *
	 * @return string
	 */
	public static function url() {
		return home_url( user_trailingslashit( Rewrites::BIG_MATCHES_SLUG ) );
	}
}
