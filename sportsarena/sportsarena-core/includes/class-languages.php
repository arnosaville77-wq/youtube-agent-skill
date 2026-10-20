<?php
/**
 * Built-in multilingual mode: one site, one URL prefix per extra language.
 *
 * The site language (Settings > General) lives at the root (/), every other
 * enabled language under its code (/en/, /fr/ ...). A request under a prefix
 * runs entirely in that language: interface, dates, team and competition
 * names, required pages and news sections. News posts carry their own
 * language and only appear on their language's pages. Matches, teams and
 * competitions are shared data, shown in every language.
 *
 * Disabled automatically when Polylang or WPML is active.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

use SportsArena\Core\Data\Names;

defined( 'ABSPATH' ) || exit;

/**
 * Languages.
 */
final class Languages {

	/**
	 * Post meta: language code of a news post.
	 */
	const META = '_sa_lang';

	/**
	 * Post meta: posts sharing a value are translations of each other.
	 */
	const GROUP = '_sa_translation_group';

	/**
	 * Language code of the current request ('' until detected).
	 *
	 * @var string
	 */
	private static $current = '';

	/**
	 * Locale before our override (the site language).
	 *
	 * @var string
	 */
	private static $base_locale = '';

	/**
	 * Locale forced while generating content in another language.
	 *
	 * @var string
	 */
	private static $forced = '';

	/**
	 * Request path without the home path and language prefix.
	 *
	 * @var string
	 */
	private static $path = '/';

	/**
	 * Languages the plugin ships translations for: code => [locale, native name].
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function supported() {
		return array(
			'ar' => array( 'ar', 'العربية' ),
			'en' => array( 'en_US', 'English' ),
			'fr' => array( 'fr_FR', 'Français' ),
			'es' => array( 'es_ES', 'Español' ),
			'de' => array( 'de_DE', 'Deutsch' ),
			'it' => array( 'it_IT', 'Italiano' ),
			'pt' => array( 'pt_BR', 'Português' ),
			'tr' => array( 'tr_TR', 'Türkçe' ),
		);
	}

	/**
	 * Language code of a locale ('' if not supported).
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function code_of( $locale ) {
		$short = strtolower( substr( (string) $locale, 0, 2 ) );
		if ( '' === $locale || 'en' === $short ) {
			return 'en';
		}
		return isset( self::supported()[ $short ] ) ? $short : '';
	}

	/**
	 * Whether a third-party multilingual plugin handles languages.
	 *
	 * @return bool
	 */
	public static function external() {
		return function_exists( 'pll_languages_list' ) || defined( 'ICL_SITEPRESS_VERSION' );
	}

	/**
	 * Run as soon as the plugin file loads, before WordPress parses the
	 * request: detect the language prefix, strip it from the request and
	 * switch the locale.
	 *
	 * @return void
	 */
	public static function boot_early() {
		add_filter( 'locale', array( __CLASS__, 'filter_locale' ), 999 );
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared and re-assigned without the prefix.
		$home = rtrim( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
		$rest = '' !== $home && str_starts_with( $uri, $home ) ? substr( $uri, strlen( $home ) ) : $uri;
		if ( preg_match( '#^/([a-z]{2})(/|\?|$)#', $rest, $m ) ) {
			$code = $m[1];
			if ( in_array( $code, self::enabled(), true ) && self::default_code() !== $code && ! self::external() ) {
				self::$current = $code;
				$rest          = substr( $rest, 3 );
				$rest          = '' === $rest || '?' === $rest[0] ? '/' . $rest : $rest;
				// WordPress parses the request without the prefix.
				$_SERVER['REQUEST_URI'] = $home . $rest;
			}
		}
		self::$path = (string) strtok( $rest, '?' );
	}

	/**
	 * Locale of the current request.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function filter_locale( $locale ) {
		if ( '' !== self::$forced ) {
			return self::$forced;
		}
		if ( isset( $GLOBALS['wp_locale_switcher'] ) && is_locale_switched() ) {
			return $locale;
		}
		self::$base_locale = (string) $locale;
		if ( '' === self::$current || is_admin() ) {
			return $locale;
		}
		return self::supported()[ self::$current ][0];
	}

	/**
	 * Run a callback in another language (plugin strings, names and dates),
	 * without needing the WordPress language pack of that language.
	 *
	 * @param string   $code     Language code.
	 * @param callable $callback Callback.
	 * @return mixed Callback result.
	 */
	public static function run_in( $code, callable $callback ) {
		if ( ! isset( self::supported()[ $code ] ) || self::current() === $code ) {
			return $callback();
		}
		$previous     = self::$forced;
		self::$forced = self::supported()[ $code ][0];
		self::reload_textdomain();
		try {
			return $callback();
		} finally {
			self::$forced = $previous;
			self::reload_textdomain();
		}
	}

	/**
	 * Reload the plugin translations for the current locale.
	 *
	 * @return void
	 */
	private static function reload_textdomain() {
		unload_textdomain( 'sportsarena-core', true );
		load_plugin_textdomain( 'sportsarena-core', false, dirname( plugin_basename( SPORTSARENA_CORE_FILE ) ) . '/languages' );
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( self::external() ) {
			return;
		}
		add_filter( 'home_url', array( $this, 'home_url' ) );
		add_filter( 'redirect_canonical', array( $this, 'redirect_canonical' ), 10, 2 );
		add_filter( 'post_link', array( $this, 'post_link' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_posts' ) );
		add_filter( 'option_blogname', array( $this, 'site_name' ) );
		add_filter( 'option_blogdescription', array( $this, 'site_tagline' ) );
		add_filter( 'language_attributes', array( $this, 'language_attributes' ) );
		add_action( 'wp_head', array( $this, 'print_hreflang' ), 2 );
		add_action( 'save_post_post', array( $this, 'tag_new_post' ), 5 );
		add_action( 'template_redirect', array( $this, 'redirect_post' ), 1 );
		if ( ! is_admin() ) {
			add_filter( 'the_title', array( $this, 'page_title' ), 10, 2 );
			add_filter( 'single_post_title', array( $this, 'page_title' ), 10, 2 );
			add_filter( 'the_content', array( $this, 'page_content' ), 5 );
			add_filter( 'get_term', array( $this, 'section_term' ), 10, 2 );
		}
	}

	/**
	 * Site language code (root URLs).
	 *
	 * @return string
	 */
	public static function default_code() {
		if ( '' === self::$base_locale ) {
			get_locale();
		}
		$code = self::code_of( self::$base_locale );
		return '' !== $code ? $code : 'en';
	}

	/**
	 * Enabled languages, the site language first.
	 *
	 * @return string[]
	 */
	public static function enabled() {
		$default = self::default_code();
		$extra   = array_values( array_intersect( (array) Options::get( 'languages' ), array_keys( self::supported() ) ) );
		return array_values( array_unique( array_merge( array( $default ), $extra ) ) );
	}

	/**
	 * Whether visitors can switch language (more than one enabled).
	 *
	 * @return bool
	 */
	public static function multilingual() {
		return ! self::external() && count( self::enabled() ) > 1;
	}

	/**
	 * Language code of the current request.
	 *
	 * @return string
	 */
	public static function current() {
		return '' !== self::$current ? self::$current : self::default_code();
	}

	/**
	 * Whether the request is in a language other than the site language.
	 *
	 * @return bool
	 */
	public static function is_translated_request() {
		return '' !== self::$current;
	}

	/**
	 * Root URL of a language.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public static function home( $code ) {
		$home = untrailingslashit( (string) get_option( 'home' ) );
		return self::default_code() === $code ? $home : $home . '/' . $code;
	}

	/**
	 * Move a URL of this site to another language.
	 *
	 * @param string $url  Absolute URL.
	 * @param string $code Target language.
	 * @return string
	 */
	public static function translate_url( $url, $code ) {
		$home = untrailingslashit( (string) get_option( 'home' ) );
		if ( ! str_starts_with( $url, $home ) ) {
			return $url;
		}
		$rest = substr( $url, strlen( $home ) );
		foreach ( self::enabled() as $lang ) {
			if ( preg_match( '#^/' . $lang . '(/|\?|$)#', $rest ) ) {
				$rest = substr( $rest, 3 );
				break;
			}
		}
		return self::home( $code ) . ( '' === $rest ? '/' : $rest );
	}

	/**
	 * Prefix front-end URLs with the current language.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function home_url( $url ) {
		if ( '' === self::$current || is_admin() ) {
			return $url;
		}
		$home = untrailingslashit( (string) get_option( 'home' ) );
		$home = preg_replace( '#^https?:#', '', $home );
		$bare = preg_replace( '#^https?:#', '', $url );
		if ( ! str_starts_with( $bare, $home ) || str_starts_with( substr( $bare, strlen( $home ) ), '/' . self::$current . '/' ) || substr( $bare, strlen( $home ) ) === '/' . self::$current ) {
			return $url;
		}
		$scheme = substr( $url, 0, strlen( $url ) - strlen( $bare ) );
		return $scheme . $home . '/' . self::$current . substr( $bare, strlen( $home ) );
	}

	/**
	 * WordPress compares the canonical URL (with the prefix) to the parsed
	 * request (without it): do not redirect when they only differ by it.
	 *
	 * @param string $redirect_url  Redirect target.
	 * @param string $requested_url Requested URL (prefix already stripped).
	 * @return string|false
	 */
	public function redirect_canonical( $redirect_url, $requested_url ) {
		if ( '' === self::$current || ! $redirect_url ) {
			return $redirect_url;
		}
		$requested = self::translate_url( (string) $requested_url, self::$current );
		return untrailingslashit( $requested ) === untrailingslashit( (string) $redirect_url ) ? false : $redirect_url;
	}

	/**
	 * Language of a post ('' for shared content).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function post_language( $post_id ) {
		return (string) get_post_meta( $post_id, self::META, true );
	}

	/**
	 * News posts link to their own language.
	 *
	 * @param string   $url  Permalink.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function post_link( $url, $post ) {
		$lang = self::post_language( $post->ID );
		return '' !== $lang && in_array( $lang, self::enabled(), true ) ? self::translate_url( $url, $lang ) : $url;
	}

	/**
	 * Front-end queries only return news posts of the current language
	 * (shared content has no language and always appears).
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function filter_posts( $query ) {
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || $query->get( 'sa_all_languages' ) || ! self::multilingual() ) {
			return;
		}
		// A news post opened under another language is redirected (see redirect_post()).
		if ( $query->is_main_query() && $query->is_singular() ) {
			return;
		}
		$types = (array) $query->get( 'post_type' );
		if ( array( '' ) !== $types && $types && ! in_array( 'post', $types, true ) && ! in_array( 'any', $types, true ) ) {
			return;
		}
		$meta     = array_filter( (array) $query->get( 'meta_query' ) );
		$language = array(
			'relation' => 'OR',
			array(
				'key'   => self::META,
				'value' => self::current(),
			),
			array(
				'key'     => self::META,
				'compare' => 'NOT EXISTS',
			),
		);
		// Nest so an existing OR relation cannot swallow the language clause.
		$meta = $meta ? array(
			'relation' => 'AND',
			$meta,
			$language,
		) : array( $language );
		$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Small news tables, cached pages.
	}

	/**
	 * A news post opened under another language's URL moves to its own.
	 *
	 * @return void
	 */
	public function redirect_post() {
		if ( ! is_singular( 'post' ) || ! self::multilingual() || is_preview() ) {
			return;
		}
		$lang = self::post_language( get_queried_object_id() );
		if ( '' !== $lang && self::current() !== $lang && in_array( $lang, self::enabled(), true ) ) {
			wp_safe_redirect( (string) get_permalink( get_queried_object_id() ), 301 );
			exit;
		}
	}

	/**
	 * New posts are written in the site language unless set otherwise.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function tag_new_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || '' !== self::post_language( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, self::META, self::default_code() );
	}

	/**
	 * Tag existing posts with the site language (once, on upgrade).
	 *
	 * @return void
	 */
	public static function tag_existing_posts() {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- One-off migration.
				'meta_query'     => array(
					array(
						'key'     => self::META,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			update_post_meta( $id, self::META, self::default_code() );
		}
	}

	/**
	 * Per-language site names ("en=Name|Tagline" lines).
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function site_names() {
		$names = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) Options::get( 'site_names' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[1] ) {
				$pair               = array_map( 'trim', explode( '|', $parts[1], 2 ) );
				$names[ $parts[0] ] = array( $pair[0], $pair[1] ?? '' );
			}
		}
		return $names;
	}

	/**
	 * Site name in the current language.
	 *
	 * @param string $name Stored name.
	 * @return string
	 */
	public function site_name( $name ) {
		$names = '' !== self::$current ? self::site_names() : array();
		return isset( $names[ self::$current ] ) && '' !== $names[ self::$current ][0] ? $names[ self::$current ][0] : $name;
	}

	/**
	 * Tagline in the current language.
	 *
	 * @param string $tagline Stored tagline.
	 * @return string
	 */
	public function site_tagline( $tagline ) {
		$names = '' !== self::$current ? self::site_names() : array();
		return isset( $names[ self::$current ] ) && '' !== $names[ self::$current ][1] ? $names[ self::$current ][1] : $tagline;
	}

	/**
	 * The <html lang> follows the request language.
	 *
	 * @param string $output Attributes.
	 * @return string
	 */
	public function language_attributes( $output ) {
		if ( '' === self::$current ) {
			return $output;
		}
		$lang = str_replace( '_', '-', self::supported()[ self::$current ][0] );
		return (string) preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $lang ) . '"', $output );
	}

	/**
	 * Links to the current page in every enabled language.
	 *
	 * @return array<int,array{code:string,name:string,locale:string,url:string,current:bool}>
	 */
	public static function links() {
		if ( ! self::multilingual() ) {
			return array();
		}
		$group = 0;
		if ( is_singular( 'post' ) ) {
			$group = (int) get_post_meta( get_queried_object_id(), self::GROUP, true );
		}
		$links = array();
		foreach ( self::enabled() as $code ) {
			if ( is_singular( 'post' ) ) {
				$url = self::translation_url( get_queried_object_id(), $group, $code );
			} else {
				$url = self::home( $code ) . self::$path;
			}
			$links[] = array(
				'code'    => $code,
				'name'    => self::supported()[ $code ][1],
				'locale'  => self::supported()[ $code ][0],
				'url'     => $url,
				'current' => self::current() === $code,
			);
		}
		return $links;
	}

	/**
	 * URL of a news post in another language: its translation, or that
	 * language's home page when there is none.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $group   Translation group.
	 * @param string $code    Language.
	 * @return string
	 */
	private static function translation_url( $post_id, $group, $code ) {
		if ( self::post_language( $post_id ) === $code ) {
			return (string) get_permalink( $post_id );
		}
		if ( $group ) {
			$ids = get_posts(
				array(
					'post_type'        => 'post',
					'posts_per_page'   => 1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'sa_all_languages' => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Two indexed keys, one row.
					'meta_query'       => array(
						array(
							'key'   => self::GROUP,
							'value' => $group,
						),
						array(
							'key'   => self::META,
							'value' => $code,
						),
					),
				)
			);
			if ( $ids ) {
				return (string) get_permalink( $ids[0] );
			}
		}
		return trailingslashit( self::home( $code ) );
	}

	/**
	 * Alternate language links for search engines.
	 *
	 * @return void
	 */
	public function print_hreflang() {
		$links = self::links();
		if ( ! $links || Seo\Seo::is_noindex() ) {
			return;
		}
		foreach ( $links as $link ) {
			if ( is_singular( 'post' ) && trailingslashit( self::home( $link['code'] ) ) === $link['url'] && ! $link['current'] ) {
				continue;
			}
			printf( '<link rel="alternate" hreflang="%1$s" href="%2$s">' . "\n", esc_attr( $link['code'] ), esc_url( $link['url'] ) );
		}
		$default = self::default_code();
		foreach ( $links as $link ) {
			if ( $link['code'] === $default ) {
				printf( '<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url( $link['url'] ) );
			}
		}
	}

	/**
	 * Key of a required page still holding the generated text ('' otherwise).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function generated_page_key( $post_id ) {
		static $keys = null;
		if ( null === $keys ) {
			$keys = array_flip( Pages::stored_ids() );
		}
		if ( ! isset( $keys[ $post_id ] ) ) {
			return '';
		}
		$post   = get_post( $post_id );
		$stored = (string) get_post_meta( $post_id, Relocalize::META, true );
		if ( ! $post || '' === $stored || Relocalize::hash( $post->post_title . "\n" . $post->post_content ) !== $stored ) {
			return '';
		}
		return (string) $keys[ $post_id ];
	}

	/**
	 * Required pages (about, privacy...) in the request language.
	 *
	 * @param string            $title   Title.
	 * @param int|\WP_Post|null $post   Post.
	 * @return string
	 */
	public function page_title( $title, $post = null ) {
		if ( '' === self::$current ) {
			return $title;
		}
		$post = get_post( $post );
		$key  = $post && 'page' === $post->post_type ? self::generated_page_key( $post->ID ) : '';
		if ( '' === $key ) {
			return $title;
		}
		$definitions = Pages::definitions();
		return $definitions[ $key ]['title'] ?? $title;
	}

	/**
	 * Required page content in the request language.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function page_content( $content ) {
		$post = get_post();
		if ( '' === self::$current || ! $post ) {
			return $content;
		}
		if ( 'page' === $post->post_type ) {
			$key = self::generated_page_key( $post->ID );
			if ( '' !== $key ) {
				$definitions = Pages::definitions();
				return isset( $definitions[ $key ] ) ? Pages::fill( $definitions[ $key ]['content'] ) : $content;
			}
			return $content;
		}
		$text = Seeder::demo_text( $post );
		return null !== $text ? $text : $content;
	}

	/**
	 * News section names in the request language.
	 *
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Taxonomy.
	 * @return \WP_Term
	 */
	public function section_term( $term, $taxonomy ) {
		if ( '' === self::$current || 'category' !== $taxonomy || ! $term instanceof \WP_Term ) {
			return $term;
		}
		$definitions = News_Sections::definitions();
		if ( ! isset( $definitions[ $term->slug ] ) ) {
			return $term;
		}
		$stored = (string) get_term_meta( $term->term_id, Relocalize::META, true );
		if ( '' !== $stored && Relocalize::hash( $term->name . "\n" . $term->description ) === $stored ) {
			$term              = clone $term;
			$term->name        = $definitions[ $term->slug ]['name'];
			$term->description = $definitions[ $term->slug ]['description'];
		}
		return $term;
	}

	/**
	 * Name of a player, venue or other free text in the request language
	 * (known Arabic/English pairs only).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function text( $text ) {
		$text = (string) $text;
		// Lineups store "10 Name": translate the name, keep the shirt number.
		if ( preg_match( '/^(\d+\s+)(.+)$/u', $text, $m ) ) {
			return $m[1] . self::text( $m[2] );
		}
		if ( Names::is_arabic() ) {
			return Names::localize( $text );
		}
		if ( Names::has_arabic( $text ) ) {
			$english = array_search( $text, Names::dictionary(), true );
			return false !== $english ? (string) $english : $text;
		}
		return $text;
	}
}
