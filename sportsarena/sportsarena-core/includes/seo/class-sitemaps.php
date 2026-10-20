<?php
/**
 * Sitemaps.
 *
 * - Matches, teams, competitions and the sport taxonomy are public, so the
 *   core sitemap (wp-sitemap.xml) and Yoast / Rank Math include them.
 * - The day pages (/matches-today/ ...) are added through a small extra
 *   sitemap: a core provider, or a link in the Yoast / Rank Math index.
 * - Google News sitemap at /news-sitemap.xml (posts of the last 48 hours).
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Seo;

use SportsArena\Core\Options;
use SportsArena\Core\Post_Types;
use SportsArena\Core\Rewrites;

defined( 'ABSPATH' ) || exit;

/**
 * Sitemaps.
 */
final class Sitemaps {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve' ), 1 );
		add_action( 'init', array( $this, 'core_provider' ), 20 );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'core_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'core_taxonomies' ) );
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 20, 2 );
		add_filter( 'wpseo_sitemap_index', array( $this, 'plugin_index_entry' ) );
		add_filter( 'rank_math/sitemap/index', array( $this, 'plugin_index_entry' ) );
	}

	/**
	 * Rewrite rules for the custom sitemaps.
	 *
	 * @return void
	 */
	public function rewrite() {
		add_rewrite_rule( '^news-sitemap\.xml$', 'index.php?sa_sitemap=news', 'top' );
		add_rewrite_rule( '^sportsarena-pages-sitemap\.xml$', 'index.php?sa_sitemap=pages', 'top' );
	}

	/**
	 * Query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = 'sa_sitemap';
		return $vars;
	}

	/**
	 * Day page URLs.
	 *
	 * @return string[]
	 */
	public static function day_urls() {
		return array(
			Rewrites::day_url( 'today' ),
			Rewrites::day_url( 'tomorrow' ),
			Rewrites::day_url( 'yesterday' ),
		);
	}

	/**
	 * Add the day pages provider to the core sitemap.
	 *
	 * @return void
	 */
	public function core_provider() {
		if ( function_exists( 'wp_register_sitemap_provider' ) && '' === Seo::seo_plugin() ) {
			wp_register_sitemap_provider( 'sapages', new Sitemap_Provider() );
		}
	}

	/**
	 * Players are optional: keep them out of the sitemap when empty.
	 *
	 * @param array $post_types Post types.
	 * @return array
	 */
	public function core_post_types( $post_types ) {
		$counts = wp_count_posts( Post_Types::PLAYER );
		if ( empty( $counts->publish ) ) {
			unset( $post_types[ Post_Types::PLAYER ] );
		}
		return $post_types;
	}

	/**
	 * Seasons / countries are thin listing pages: only sports are mapped.
	 *
	 * @param array $taxonomies Taxonomies.
	 * @return array
	 */
	public function core_taxonomies( $taxonomies ) {
		unset( $taxonomies[ Post_Types::TAX_SEASON ], $taxonomies[ Post_Types::TAX_COUNTRY ], $taxonomies['post_tag'] );
		return $taxonomies;
	}

	/**
	 * Add our sitemaps to robots.txt.
	 *
	 * @param string $output Robots content.
	 * @param bool   $is_public Whether the site is public.
	 * @return string
	 */
	public function robots_txt( $output, $is_public ) {
		if ( ! $is_public ) {
			return $output;
		}
		if ( Options::get( 'news_sitemap' ) ) {
			$output .= "\nSitemap: " . home_url( '/news-sitemap.xml' ) . "\n";
		}
		if ( '' !== Seo::seo_plugin() ) {
			$output .= 'Sitemap: ' . home_url( '/sportsarena-pages-sitemap.xml' ) . "\n";
		}
		return $output;
	}

	/**
	 * Add the pages sitemap to the Yoast / Rank Math sitemap index.
	 *
	 * @param string $xml Extra index XML.
	 * @return string
	 */
	public function plugin_index_entry( $xml ) {
		$xml .= '<sitemap><loc>' . esc_url( home_url( '/sportsarena-pages-sitemap.xml' ) ) . '</loc><lastmod>' . esc_html( gmdate( 'c' ) ) . '</lastmod></sitemap>';
		return $xml;
	}

	/**
	 * Serve the custom sitemaps.
	 *
	 * @return void
	 */
	public function serve() {
		$type = get_query_var( 'sa_sitemap' );
		if ( ! $type ) {
			return;
		}
		if ( 'news' === $type && ! Options::get( 'news_sitemap' ) ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		if ( 'news' === $type ) {
			$this->news();
		} else {
			$this->pages();
		}
		exit;
	}

	/**
	 * Google News sitemap: posts from the last 48 hours (max 1000).
	 *
	 * @return void
	 */
	private function news() {
		$posts    = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1000,
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'after'     => '48 hours ago',
						'column'    => 'post_date_gmt',
						'inclusive' => true,
					),
				),
			)
		);
		$language = substr( get_bloginfo( 'language' ), 0, 2 );
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
		foreach ( $posts as $post ) {
			echo "<url>\n";
			echo '<loc>' . esc_url( get_permalink( $post ) ) . "</loc>\n";
			echo "<news:news>\n<news:publication>\n";
			echo '<news:name>' . esc_html( get_bloginfo( 'name' ) ) . "</news:name>\n";
			echo '<news:language>' . esc_html( $language ) . "</news:language>\n";
			echo "</news:publication>\n";
			echo '<news:publication_date>' . esc_html( get_post_time( 'c', true, $post ) ) . "</news:publication_date>\n";
			echo '<news:title>' . esc_html( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ) . "</news:title>\n";
			echo "</news:news>\n</url>\n";
		}
		echo '</urlset>';
	}

	/**
	 * Day pages sitemap (used with Yoast / Rank Math).
	 *
	 * @return void
	 */
	private function pages() {
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( self::day_urls() as $url ) {
			echo '<url><loc>' . esc_url( $url ) . '</loc><changefreq>hourly</changefreq></url>' . "\n";
		}
		echo '</urlset>';
	}
}
