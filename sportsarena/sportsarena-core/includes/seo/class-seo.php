<?php
/**
 * Titles, meta descriptions, canonical, robots, Open Graph and Twitter Cards.
 *
 * If Yoast SEO or Rank Math is active, nothing is printed by this class:
 * smart defaults are passed to the SEO plugin through its filters (only
 * when the editor did not set a custom value), so no meta tag is duplicated.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Seo;

use SportsArena\Core\Big_Matches;
use SportsArena\Core\Options;
use SportsArena\Core\Post_Types;
use SportsArena\Core\Repository;
use SportsArena\Core\Rewrites;
use SportsArena\Core\Time_Display;

defined( 'ABSPATH' ) || exit;

/**
 * SEO.
 */
final class Seo {

	/**
	 * Active SEO plugin: yoast|rankmath|''.
	 *
	 * @return string
	 */
	public static function seo_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			return 'rankmath';
		}
		return '';
	}

	/**
	 * Hook into WordPress (on wp so plugin detection is reliable).
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp', array( $this, 'setup' ) );
	}

	/**
	 * Wire the output depending on the active SEO plugin.
	 *
	 * @return void
	 */
	public function setup() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( $this, 'print_hreflang' ), 4 );
		switch ( self::seo_plugin() ) {
			case 'yoast':
				add_filter( 'wpseo_title', array( $this, 'filter_plugin_title' ) );
				add_filter( 'wpseo_opengraph_title', array( $this, 'filter_plugin_title' ) );
				add_filter( 'wpseo_metadesc', array( $this, 'filter_plugin_description' ) );
				add_filter( 'wpseo_opengraph_desc', array( $this, 'filter_plugin_description' ) );
				add_filter( 'wpseo_canonical', array( $this, 'filter_plugin_canonical' ) );
				add_filter( 'wpseo_opengraph_url', array( $this, 'filter_plugin_canonical' ) );
				add_filter( 'wpseo_robots', array( $this, 'filter_yoast_robots' ) );
				add_filter( 'wpseo_opengraph_image', array( $this, 'filter_plugin_image' ) );
				add_filter( 'wpseo_twitter_image', array( $this, 'filter_plugin_image' ) );
				break;
			case 'rankmath':
				add_filter( 'rank_math/frontend/title', array( $this, 'filter_plugin_title' ) );
				add_filter( 'rank_math/frontend/description', array( $this, 'filter_plugin_description' ) );
				add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_plugin_canonical' ) );
				add_filter( 'rank_math/frontend/robots', array( $this, 'filter_rankmath_robots' ) );
				add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'filter_plugin_image' ) );
				add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'filter_plugin_image' ) );
				break;
			default:
				add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 20 );
				add_filter( 'wp_robots', array( $this, 'wp_robots' ) );
				remove_action( 'wp_head', 'rel_canonical' );
				add_action( 'wp_head', array( $this, 'print_meta' ), 2 );
		}
	}

	/*
	 * --------------------------------------------------------------------
	 * Context values.
	 * --------------------------------------------------------------------
	 */

	/**
	 * Smart title for the current request, '' to keep the default.
	 *
	 * @return string
	 */
	public static function title() {
		if ( get_query_var( 'sa_big' ) ) {
			return __( 'المباريات الكبرى ودوري أبطال أوروبا: المواعيد والنتائج', 'sportsarena-core' );
		}
		$day = get_query_var( 'sa_day' );
		if ( $day ) {
			$date = Rewrites::resolve_day( $day );
			if ( ! $date ) {
				return '';
			}
			$pretty = wp_date( 'j F Y', strtotime( $date . ' 12:00:00' ) );
			$labels = array(
				'today'     => __( 'مباريات اليوم', 'sportsarena-core' ),
				'tomorrow'  => __( 'مباريات الغد', 'sportsarena-core' ),
				'yesterday' => __( 'مباريات الأمس', 'sportsarena-core' ),
			);
			/* translators: %s: date. */
			$label  = $labels[ $day ] ?? sprintf( __( 'مباريات يوم %s', 'sportsarena-core' ), $pretty );
			$label .= isset( $labels[ $day ] ) ? ' ' . $pretty : '';
			/* translators: %s: label with date. */
			return sprintf( __( '%s: المواعيد والنتائج المباشرة', 'sportsarena-core' ), $label );
		}

		if ( is_singular( Post_Types::MATCH ) ) {
			return self::match_title( get_queried_object_id() );
		}
		if ( is_singular( Post_Types::TEAM ) ) {
			/* translators: %s: team name. */
			return sprintf( __( '%s: مواعيد المباريات والنتائج وآخر الأخبار', 'sportsarena-core' ), single_post_title( '', false ) );
		}
		if ( is_singular( Post_Types::COMPETITION ) ) {
			$id     = get_queried_object_id();
			$name   = single_post_title( '', false );
			$season = (string) get_post_meta( $id, '_sa_season', true );
			$name   = trim( $name . ' ' . $season );
			switch ( get_query_var( 'sa_tab' ) ) {
				case 'standings':
					/* translators: %s: competition. */
					return sprintf( __( 'جدول ترتيب %s', 'sportsarena-core' ), $name );
				case 'scorers':
					/* translators: %s: competition. */
					return sprintf( __( 'ترتيب هدافي %s', 'sportsarena-core' ), $name );
				case 'matches':
					/* translators: %s: competition. */
					return sprintf( __( 'مباريات %s: المواعيد والنتائج', 'sportsarena-core' ), $name );
			}
			/* translators: %s: competition. */
			return sprintf( __( '%s: جدول الترتيب والمباريات والهدافين', 'sportsarena-core' ), $name );
		}
		if ( is_tax( Post_Types::TAX_SPORT ) ) {
			/* translators: %s: sport. */
			return sprintf( __( '%s: نتائج المباريات والمواعيد والأخبار', 'sportsarena-core' ), single_term_title( '', false ) );
		}
		return '';
	}

	/**
	 * Smart match title, e.g. «ريال مدريد ضد برشلونة اليوم: موعد المباراة والتشكيلة والنتيجة المباشرة – الدوري الإسباني».
	 *
	 * @param int $post_id Match ID.
	 * @return string
	 */
	public static function match_title( $post_id ) {
		$m           = Repository::match( $post_id );
		$home        = $m['home'] ? get_the_title( $m['home'] ) : get_the_title( $post_id );
		$away        = $m['away'] ? get_the_title( $m['away'] ) : '';
		$competition = $m['competition'] ? ' – ' . get_the_title( $m['competition'] ) : '';
		/* translators: 1: home team, 2: away team. */
		$versus = $away ? sprintf( __( '%1$s ضد %2$s', 'sportsarena-core' ), $home, $away ) : $home;

		if ( 'finished' === $m['status'] && null !== $m['home_score'] ) {
			$versus = $away ? sprintf( '%s %d-%d %s', $home, $m['home_score'], $m['away_score'], $away ) : $home;
			/* translators: 1: teams and score, 2: competition. */
			return sprintf( __( '%1$s: نتيجة المباراة والأهداف والتشكيلة%2$s', 'sportsarena-core' ), $versus, $competition );
		}

		/* translators: 1: teams, 2: relative day or date, 3: competition. */
		return sprintf( __( '%1$s %2$s: موعد المباراة والتشكيلة والنتيجة المباشرة%3$s', 'sportsarena-core' ), $versus, self::relative_day( $m['kickoff'] ), $competition );
	}

	/**
	 * «اليوم» / «غداً» / «أمس» or a date.
	 *
	 * @param int $timestamp UTC timestamp.
	 * @return string
	 */
	public static function relative_day( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}
		$date = wp_date( 'Y-m-d', $timestamp );
		if ( Rewrites::resolve_day( 'today' ) === $date ) {
			return __( 'اليوم', 'sportsarena-core' );
		}
		if ( Rewrites::resolve_day( 'tomorrow' ) === $date ) {
			return __( 'غداً', 'sportsarena-core' );
		}
		if ( Rewrites::resolve_day( 'yesterday' ) === $date ) {
			return __( 'أمس', 'sportsarena-core' );
		}
		return wp_date( 'j F Y', $timestamp );
	}

	/**
	 * Smart description for the current request.
	 *
	 * @return string
	 */
	public static function description() {
		if ( get_query_var( 'sa_big' ) ) {
			return __( 'مواعيد ونتائج المباريات الكبرى بين أشهر الأندية العربية والأوروبية، ومباريات دوري أبطال أوروبا، مع النتائج المباشرة والتشكيلات.', 'sportsarena-core' );
		}
		$day = get_query_var( 'sa_day' );
		if ( $day ) {
			$date = Rewrites::resolve_day( $day );
			if ( ! $date ) {
				return '';
			}
			/* translators: %s: date. */
			return sprintf( __( 'جدول مواعيد ونتائج مباريات يوم %s في جميع البطولات: الدوريات الأوروبية والعربية ودوري الأبطال، مع النتائج المباشرة والتشكيلات وتوقيت كل مباراة بتوقيتك المحلي.', 'sportsarena-core' ), wp_date( 'l j F Y', strtotime( $date . ' 12:00:00' ) ) );
		}
		if ( is_singular( Post_Types::MATCH ) ) {
			$id = get_queried_object_id();
			$m  = Repository::match( $id );
			if ( has_excerpt( $id ) ) {
				return wp_strip_all_tags( get_the_excerpt( $id ) );
			}
			$home = $m['home'] ? get_the_title( $m['home'] ) : get_the_title( $id );
			$away = $m['away'] ? get_the_title( $m['away'] ) : '';
			/* translators: 1: home team, 2: away team. */
			$teams = $away ? sprintf( __( '%1$s ضد %2$s', 'sportsarena-core' ), $home, $away ) : $home;
			$comp  = $m['competition'] ? get_the_title( $m['competition'] ) : '';
			$when  = wp_date( 'l j F Y', $m['kickoff'] ) . ' ' . __( 'الساعة', 'sportsarena-core' ) . ' ' . wp_date( 'H:i', $m['kickoff'] ) . Time_Display::suffix();
			$venue = $m['venue'] ? ' ' . __( 'على ملعب', 'sportsarena-core' ) . ' ' . $m['venue'] : '';
			if ( 'finished' === $m['status'] && null !== $m['home_score'] ) {
				/* translators: 1: teams, 2: score, 3: competition, 4: date. */
				return sprintf( __( 'نتيجة مباراة %1$s (%2$s) في %3$s يوم %4$s: الأهداف والبطاقات والتشكيلة والإحصائيات الكاملة.', 'sportsarena-core' ), $teams, $m['home_score'] . '-' . $m['away_score'], $comp, wp_date( 'j F Y', $m['kickoff'] ) );
			}
			/* translators: 1: teams, 2: competition, 3: date/time, 4: venue. */
			return sprintf( __( 'موعد مباراة %1$s في %2$s يوم %3$s%4$s. تابع التشكيلة الرسمية والإحصائيات والنتيجة المباشرة لحظة بلحظة.', 'sportsarena-core' ), $teams, $comp, $when, $venue );
		}
		if ( is_singular( Post_Types::TEAM ) ) {
			$id = get_queried_object_id();
			if ( has_excerpt( $id ) ) {
				return wp_strip_all_tags( get_the_excerpt( $id ) );
			}
			$name = single_post_title( '', false );
			/* translators: %s: team. */
			return sprintf( __( 'كل ما يخص %s: المباريات القادمة ونتائج المباريات السابقة ومواعيدها، وآخر الأخبار والتحليلات.', 'sportsarena-core' ), $name );
		}
		if ( is_singular( Post_Types::COMPETITION ) ) {
			$id = get_queried_object_id();
			if ( has_excerpt( $id ) && ! get_query_var( 'sa_tab' ) ) {
				return wp_strip_all_tags( get_the_excerpt( $id ) );
			}
			$name = single_post_title( '', false );
			/* translators: %s: competition. */
			return sprintf( __( 'جدول ترتيب %s محدّث، ومواعيد ونتائج المباريات حسب الجولات، وقائمة الهدافين.', 'sportsarena-core' ), $name );
		}
		if ( is_tax( Post_Types::TAX_SPORT ) ) {
			$term = get_queried_object();
			if ( $term && $term->description ) {
				return wp_strip_all_tags( $term->description );
			}
			/* translators: %s: sport. */
			return sprintf( __( 'نتائج ومواعيد مباريات %s اليوم، وآخر الأخبار والتغطيات.', 'sportsarena-core' ), single_term_title( '', false ) );
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' );
			return wp_strip_all_tags( $text );
		}
		if ( is_front_page() || is_home() ) {
			$tagline = (string) get_bloginfo( 'description' );
			/* translators: %s: site name. */
			return '' !== $tagline ? $tagline : sprintf( __( '%s: نتائج المباريات المباشرة ومواعيد مباريات اليوم والغد، جداول الترتيب والهدافين، وآخر أخبار كرة القدم وباقي الرياضات.', 'sportsarena-core' ), get_bloginfo( 'name' ) );
		}
		return '';
	}

	/**
	 * Canonical URL for the current request.
	 *
	 * @return string
	 */
	public static function canonical() {
		if ( get_query_var( 'sa_big' ) ) {
			return Big_Matches::url();
		}
		$day = get_query_var( 'sa_day' );
		if ( $day ) {
			return isset( Rewrites::DAY_SLUGS[ $day ] ) ? Rewrites::day_url( $day ) : home_url( user_trailingslashit( 'matches/' . $day ) );
		}
		if ( is_singular( Post_Types::COMPETITION ) && get_query_var( 'sa_tab' ) ) {
			return Rewrites::competition_tab_url( get_queried_object_id(), get_query_var( 'sa_tab' ) );
		}
		if ( is_singular() ) {
			return (string) wp_get_canonical_url( get_queried_object_id() );
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$link = get_term_link( get_queried_object() );
			if ( is_wp_error( $link ) ) {
				return '';
			}
			$paged = (int) get_query_var( 'paged' );
			return $paged > 1 ? trailingslashit( $link ) . user_trailingslashit( 'page/' . $paged, 'paged' ) : $link;
		}
		if ( is_post_type_archive() ) {
			return (string) get_post_type_archive_link( get_query_var( 'post_type' ) );
		}
		if ( is_home() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			return $page_for_posts ? get_permalink( $page_for_posts ) : home_url( '/' );
		}
		return '';
	}

	/**
	 * Whether the current page should be kept out of the index.
	 *
	 * Thin pages: internal search, empty day pages, empty competition tabs.
	 * Old matches are NOT noindexed: they stay as an indexable archive.
	 *
	 * @return bool
	 */
	public static function is_noindex() {
		if ( is_search() || is_404() ) {
			return true;
		}
		if ( get_query_var( 'sa_big' ) ) {
			return ! Repository::big_matches( 'upcoming', 1 ) && ! Repository::big_matches( 'past', 1 );
		}
		$day = get_query_var( 'sa_day' );
		if ( $day ) {
			$date = Rewrites::resolve_day( $day );
			return ! $date || ! Repository::matches_by_competition( $date );
		}
		if ( is_singular( Post_Types::COMPETITION ) ) {
			$tab = get_query_var( 'sa_tab' );
			$id  = get_queried_object_id();
			if ( 'standings' === $tab ) {
				return ! Repository::standings( $id );
			}
			if ( 'scorers' === $tab ) {
				return ! Repository::scorers( $id );
			}
		}
		return false;
	}

	/**
	 * Open Graph locale (ll_CC), e.g. ar_AR.
	 *
	 * @return string
	 */
	public static function og_locale() {
		$locale = str_replace( '-', '_', get_locale() );
		if ( 2 === strlen( $locale ) ) {
			$locale = 'ar' === $locale ? 'ar_AR' : $locale . '_' . strtoupper( $locale );
		}
		return $locale;
	}

	/**
	 * Share image for the current page.
	 *
	 * @return string
	 */
	public static function image() {
		if ( is_singular() ) {
			$id = get_queried_object_id();
			if ( has_post_thumbnail( $id ) ) {
				$src = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'full' );
				if ( $src ) {
					return $src[0];
				}
			}
			if ( is_singular( Post_Types::MATCH ) ) {
				return Og_Image::url( $id );
			}
		}
		$icon = get_site_icon_url( 512 );
		return $icon ? $icon : '';
	}

	/*
	 * --------------------------------------------------------------------
	 * Output without an SEO plugin.
	 * --------------------------------------------------------------------
	 */

	/**
	 * Document title.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public function document_title( $title ) {
		$smart = self::title();
		if ( '' === $smart ) {
			return $title;
		}
		$separator = apply_filters( 'document_title_separator', '-' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
		return $smart . ' ' . $separator . ' ' . get_bloginfo( 'name', 'display' );
	}

	/**
	 * Core robots directives.
	 *
	 * @param array $robots Robots.
	 * @return array
	 */
	public function wp_robots( $robots ) {
		if ( self::is_noindex() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		} else {
			$robots['max-image-preview'] = 'large';
		}
		return $robots;
	}

	/**
	 * Print description, canonical, Open Graph and Twitter tags.
	 *
	 * @return void
	 */
	public function print_meta() {
		$title       = self::title();
		$title       = '' !== $title ? $title : wp_get_document_title();
		$description = self::description();
		$canonical   = self::canonical();
		$image       = self::image();
		$is_article  = is_singular( 'post' );

		echo "\n<!-- SportsArena SEO -->\n";
		if ( $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_html_excerpt( $description, 300, '' ) ) );
		}
		if ( $canonical && ! self::is_noindex() ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
		}
		$og = array(
			'og:locale'    => self::og_locale(),
			'og:type'      => $is_article ? 'article' : 'website',
			'og:title'     => $title,
			'og:site_name' => get_bloginfo( 'name' ),
			'og:url'       => $canonical,
		);
		if ( $description ) {
			$og['og:description'] = wp_html_excerpt( $description, 300, '' );
		}
		if ( $image ) {
			$og['og:image'] = $image;
			if ( is_singular( Post_Types::MATCH ) && ! has_post_thumbnail() ) {
				$og['og:image:width']  = (string) Og_Image::WIDTH;
				$og['og:image:height'] = (string) Og_Image::HEIGHT;
			}
		}
		if ( $is_article ) {
			$og['article:published_time'] = get_the_date( 'c' );
			$og['article:modified_time']  = get_the_modified_date( 'c' );
		}
		foreach ( $og as $property => $content ) {
			if ( '' !== (string) $content ) {
				printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $content ) );
			}
		}
		printf( '<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary' );
		printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
		if ( $description ) {
			printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( wp_html_excerpt( $description, 200, '' ) ) );
		}
		if ( $image ) {
			printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image ) );
		}
		echo "<!-- /SportsArena SEO -->\n";
	}

	/**
	 * Alternate language sites from the settings: array( hreflang => home URL ).
	 *
	 * @return array<string,string>
	 */
	public static function alternate_sites() {
		$sites = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) Options::get( 'alternate_sites' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
				$sites[ $parts[0] ] = untrailingslashit( $parts[1] );
			}
		}
		return $sites;
	}

	/**
	 * Hreflang links between separate language sites (one site per language,
	 * e.g. example.com in Arabic and en.example.com in English). Sports pages
	 * share the same path on every site because slugs come from the
	 * provider's English names, so the alternate URL is the same path on the
	 * other site. News and pages are skipped (their slugs differ).
	 *
	 * @return void
	 */
	public function print_hreflang() {
		$sites = self::alternate_sites();
		if ( ! $sites || self::is_noindex() ) {
			return;
		}
		$shared = is_front_page() || get_query_var( 'sa_day' ) || get_query_var( 'sa_big' ) || is_tax( Post_Types::TAX_SPORT )
			|| is_singular( array( Post_Types::MATCH, Post_Types::TEAM, Post_Types::COMPETITION ) );
		if ( ! $shared ) {
			return;
		}
		$canonical = self::canonical();
		$home      = untrailingslashit( home_url() );
		if ( '' === $canonical || ! str_starts_with( $canonical, $home ) ) {
			return;
		}
		$path  = substr( $canonical, strlen( $home ) );
		$links = array( get_bloginfo( 'language' ) => $canonical );
		foreach ( $sites as $lang => $base ) {
			$links[ $lang ] = $base . $path;
		}
		foreach ( $links as $lang => $url ) {
			printf( '<link rel="alternate" hreflang="%1$s" href="%2$s">' . "\n", esc_attr( $lang ), esc_url( $url ) );
		}
	}

	/*
	 * --------------------------------------------------------------------
	 * SEO plugin integration (only fills values the editor left empty).
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether the editor set a custom SEO title/description in the plugin.
	 *
	 * @param string $field title|description.
	 * @return bool
	 */
	private function has_custom( $field ) {
		if ( ! is_singular() ) {
			return false;
		}
		$id   = get_queried_object_id();
		$keys = 'yoast' === self::seo_plugin()
			? array(
				'title'       => '_yoast_wpseo_title',
				'description' => '_yoast_wpseo_metadesc',
			)
			: array(
				'title'       => 'rank_math_title',
				'description' => 'rank_math_description',
			);
		return '' !== (string) get_post_meta( $id, $keys[ $field ], true );
	}

	/**
	 * Is this one of our contexts?
	 *
	 * @return bool
	 */
	private function is_ours() {
		return get_query_var( 'sa_day' ) || get_query_var( 'sa_big' )
			|| is_singular( array( Post_Types::MATCH, Post_Types::TEAM, Post_Types::COMPETITION ) )
			|| is_tax( Post_Types::TAX_SPORT );
	}

	/**
	 * Title filter for Yoast / Rank Math.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public function filter_plugin_title( $title ) {
		if ( ! $this->is_ours() || $this->has_custom( 'title' ) ) {
			return $title;
		}
		$smart = self::title();
		return '' !== $smart ? $smart . ' - ' . get_bloginfo( 'name' ) : $title;
	}

	/**
	 * Description filter for Yoast / Rank Math.
	 *
	 * @param string $description Description.
	 * @return string
	 */
	public function filter_plugin_description( $description ) {
		if ( ! $this->is_ours() || $this->has_custom( 'description' ) ) {
			return $description;
		}
		$smart = self::description();
		return '' !== $smart ? $smart : $description;
	}

	/**
	 * Canonical filter for Yoast / Rank Math (day pages and competition tabs).
	 *
	 * @param string $canonical Canonical.
	 * @return string
	 */
	public function filter_plugin_canonical( $canonical ) {
		if ( get_query_var( 'sa_day' ) || get_query_var( 'sa_tab' ) || get_query_var( 'sa_big' ) ) {
			return self::canonical();
		}
		return $canonical;
	}

	/**
	 * Yoast robots string.
	 *
	 * @param string $robots Robots.
	 * @return string
	 */
	public function filter_yoast_robots( $robots ) {
		return self::is_noindex() ? 'noindex, follow' : $robots;
	}

	/**
	 * Rank Math robots array.
	 *
	 * @param array $robots Robots.
	 * @return array
	 */
	public function filter_rankmath_robots( $robots ) {
		if ( self::is_noindex() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	/**
	 * Share image filter for Yoast / Rank Math (generated match image).
	 *
	 * @param string $image Image URL.
	 * @return string
	 */
	public function filter_plugin_image( $image ) {
		if ( ! $image && is_singular( Post_Types::MATCH ) ) {
			return Og_Image::url( get_queried_object_id() );
		}
		return $image;
	}
}
