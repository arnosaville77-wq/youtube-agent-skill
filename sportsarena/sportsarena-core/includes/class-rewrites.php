<?php
/**
 * SEO friendly routes: /matches-today/, /matches/2026-10-26/, /big-matches/,
 * competition tabs.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrite rules and their query vars.
 */
final class Rewrites {

	const DAY_SLUGS = array(
		'today'     => 'matches-today',
		'tomorrow'  => 'matches-tomorrow',
		'yesterday' => 'matches-yesterday',
	);

	const COMPETITION_TABS = array( 'matches', 'standings', 'scorers' );

	const BIG_MATCHES_SLUG = 'big-matches';

	/**
	 * Bumped when rules change, so they are flushed once on existing sites.
	 */
	const RULES_VERSION = '2';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'add_rules' ), 10 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ), 5 );
		add_filter( 'template_include', array( $this, 'template_include' ) );
	}

	/**
	 * Add rewrite rules.
	 *
	 * @return void
	 */
	public function add_rules() {
		foreach ( self::DAY_SLUGS as $day => $slug ) {
			add_rewrite_rule( '^' . $slug . '/?$', 'index.php?post_type=match&sa_day=' . $day, 'top' );
		}
		add_rewrite_rule( '^matches/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$', 'index.php?post_type=match&sa_day=$matches[1]', 'top' );
		add_rewrite_rule(
			'^competition/([^/]+)/(' . implode( '|', self::COMPETITION_TABS ) . ')/?$',
			'index.php?sa_competition=$matches[1]&sa_tab=$matches[2]',
			'top'
		);
		add_rewrite_rule( '^' . self::BIG_MATCHES_SLUG . '/?$', 'index.php?post_type=match&sa_big=1', 'top' );
		if ( get_option( 'sportsarena_rules_version' ) !== self::RULES_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'sportsarena_rules_version', self::RULES_VERSION );
		}
	}

	/**
	 * Whitelist custom query vars.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = 'sa_day';
		$vars[] = 'sa_tab';
		$vars[] = 'sa_big';
		return $vars;
	}

	/**
	 * Keep the main query of the day pages cheap: the template loads
	 * matches through the repository (grouped by competition).
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function pre_get_posts( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->get( 'sa_day' ) || $query->get( 'sa_big' ) ) {
			$query->set( 'posts_per_page', 1 );
			$query->set( 'no_found_rows', true );
			$query->set( 'fields', 'ids' );
		}
		// The internal search covers news, teams and competitions.
		if ( $query->is_search() && ! $query->get( 'post_type' ) ) {
			$query->set( 'post_type', array( 'post', Post_Types::TEAM, Post_Types::COMPETITION, Post_Types::MATCH ) );
		}
	}

	/**
	 * Redirect the bare archive to today and reject invalid dates.
	 *
	 * @return void
	 */
	public function template_redirect() {
		$day = get_query_var( 'sa_day' );
		if ( ! $day && ! get_query_var( 'sa_big' ) && is_post_type_archive( Post_Types::MATCH ) && ! is_feed() ) {
			wp_safe_redirect( self::day_url( 'today' ), 301 );
			exit;
		}
		if ( $day && null === self::resolve_day( $day ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
		$tab = get_query_var( 'sa_tab' );
		if ( $tab && ! in_array( $tab, self::COMPETITION_TABS, true ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		}
	}

	/**
	 * Use the theme's big-matches.php and matches-day.php templates.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function template_include( $template ) {
		if ( get_query_var( 'sa_big' ) && ! is_404() ) {
			$found = locate_template( array( 'big-matches.php' ) );
			if ( $found ) {
				return $found;
			}
		}
		if ( get_query_var( 'sa_day' ) && ! is_404() ) {
			$found = locate_template( array( 'matches-day.php', 'archive-match.php' ) );
			if ( $found ) {
				return $found;
			}
		}
		return $template;
	}

	/**
	 * Turn a sa_day value (today|tomorrow|yesterday|Y-m-d) into a Y-m-d date
	 * in the site timezone, or null if invalid.
	 *
	 * @param string $day Raw value.
	 * @return string|null
	 */
	public static function resolve_day( $day ) {
		$tz  = wp_timezone();
		$now = new \DateTimeImmutable( 'now', $tz );
		switch ( $day ) {
			case 'today':
				return $now->format( 'Y-m-d' );
			case 'tomorrow':
				return $now->modify( '+1 day' )->format( 'Y-m-d' );
			case 'yesterday':
				return $now->modify( '-1 day' )->format( 'Y-m-d' );
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $day, $tz );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $day ) {
			return null;
		}
		return $day;
	}

	/**
	 * Public URL of a day page. Relative days map to their dedicated slugs.
	 *
	 * @param string $day today|tomorrow|yesterday|Y-m-d.
	 * @return string
	 */
	public static function day_url( $day ) {
		if ( isset( self::DAY_SLUGS[ $day ] ) ) {
			return home_url( user_trailingslashit( self::DAY_SLUGS[ $day ] ) );
		}
		foreach ( array_keys( self::DAY_SLUGS ) as $relative ) {
			if ( self::resolve_day( $relative ) === $day ) {
				return self::day_url( $relative );
			}
		}
		return home_url( user_trailingslashit( 'matches/' . $day ) );
	}

	/**
	 * URL of a competition tab.
	 *
	 * @param int    $competition_id Competition post ID.
	 * @param string $tab            Tab slug ('' for the overview).
	 * @return string
	 */
	public static function competition_tab_url( $competition_id, $tab = '' ) {
		$base = get_permalink( $competition_id );
		if ( ! $tab || ! in_array( $tab, self::COMPETITION_TABS, true ) ) {
			return $base;
		}
		return trailingslashit( $base ) . user_trailingslashit( $tab );
	}
}
