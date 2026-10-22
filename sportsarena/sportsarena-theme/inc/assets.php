<?php
/**
 * Performance-oriented asset loading (Core Web Vitals):
 * - critical CSS inlined in <head>, the rest loaded without blocking render;
 * - Arabic Google Font with display=swap and preconnect;
 * - one small deferred script, no jQuery on the front end;
 * - block library CSS only where block content is shown.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google Fonts stylesheet URL (Cairo, swap).
 *
 * @return string
 */
function sportsarena_theme_font_url() {
	/**
	 * Filter the Google Fonts URL. Return '' to use system fonts only.
	 *
	 * @param string $url URL.
	 */
	return (string) apply_filters( 'sportsarena_theme_font_url', 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' );
}

/**
 * Preconnect hints for the font host.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Relation.
 * @return array
 */
function sportsarena_theme_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && sportsarena_theme_font_url() ) {
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
		$urls[] = 'https://fonts.googleapis.com';
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'sportsarena_theme_resource_hints', 10, 2 );

/**
 * Enqueue styles and scripts.
 *
 * @return void
 */
function sportsarena_theme_assets() {
	$font = sportsarena_theme_font_url();
	if ( $font ) {
		wp_enqueue_style( 'sportsarena-font', $font, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL must stay unversioned.
	}
	// Version from the file date: browsers (and the app) fetch the new file right after an update.
	wp_enqueue_style( 'sportsarena-main', SPORTSARENA_THEME_URI . '/assets/css/main.css', array(), SPORTSARENA_THEME_VERSION . '.' . filemtime( SPORTSARENA_THEME_DIR . '/assets/css/main.css' ) );

	wp_enqueue_script(
		'sportsarena-app',
		SPORTSARENA_THEME_URI . '/assets/js/app.js',
		array(),
		SPORTSARENA_THEME_VERSION . '.' . filemtime( SPORTSARENA_THEME_DIR . '/assets/js/app.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
	wp_add_inline_script(
		'sportsarena-app',
		'window.SportsArena=' . wp_json_encode(
			array(
				'live'     => esc_url_raw( rest_url( 'sportsarena/v1/live' ) ),
				'search'   => esc_url_raw( rest_url( 'sportsarena/v1/search' ) ),
				'dayUrl'   => function_exists( 'sportsarena_day_url' ) ? esc_url_raw( home_url( user_trailingslashit( 'matches/__DATE__' ) ) ) : '',
				'interval' => 60,
				'sw'       => sportsarena_theme_pwa_enabled() && ! is_user_logged_in() ? esc_url_raw( sportsarena_theme_pwa_sw_url() ) : '',
				'swScope'  => sportsarena_theme_pwa_base() . '/',
				'i18n'     => array(
					'yourTime'        => __( 'بتوقيتك', 'sportsarena-theme' ),
					'noResults'       => __( 'لا توجد نتائج', 'sportsarena-theme' ),
					'team'            => __( 'فريق', 'sportsarena-theme' ),
					'favMatches'      => __( 'المباريات', 'sportsarena-theme' ),
					'favCompetitions' => __( 'البطولات', 'sportsarena-theme' ),
					'favTeams'        => __( 'الفرق', 'sportsarena-theme' ),
					'favRemove'       => __( 'إزالة من المفضلة', 'sportsarena-theme' ),
					'penalty'         => __( 'ج', 'sportsarena-theme' ),
					'ownGoal'         => __( 'عكسي', 'sportsarena-theme' ),
				),
			)
		) . ';',
		'before'
	);

	// Block styles are only needed where post content is rendered.
	if ( ! is_singular() ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'global-styles' );
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'sportsarena_theme_assets', 20 );

/**
 * Load non-critical stylesheets without blocking rendering
 * (media="print" swapped on load, with a <noscript> fallback).
 *
 * @param string $tag    Link tag.
 * @param string $handle Handle.
 * @param string $href   URL.
 * @return string
 */
function sportsarena_theme_async_styles( $tag, $handle, $href ) {
	if ( ! in_array( $handle, array( 'sportsarena-main', 'sportsarena-font', 'wp-block-library' ), true ) || is_admin() ) {
		return $tag;
	}
	$async = str_replace( array( "media='all'", 'media="all"' ), 'media="print" onload="this.media=\'all\'"', $tag );
	return $async . '<noscript><link rel="stylesheet" href="' . esc_url( $href ) . '"></noscript>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Fallback for an enqueued stylesheet.
}
add_filter( 'style_loader_tag', 'sportsarena_theme_async_styles', 10, 3 );

/**
 * Inline critical CSS and the no-flash theme script as early as possible.
 *
 * @return void
 */
function sportsarena_theme_critical_css() {
	$file = SPORTSARENA_THEME_DIR . '/assets/css/critical.css';
	if ( is_readable( $file ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file.
		$css = (string) file_get_contents( $file );
		echo '<style id="sa-critical">' . wp_strip_all_tags( $css ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static theme CSS.
	}
	// Apply the saved / preferred color scheme before first paint (no flash).
	wp_print_inline_script_tag(
		"(function(){try{var t=localStorage.getItem('sa-theme');if(!t){t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();",
		array( 'id' => 'sa-theme-init' )
	);
}
add_action( 'wp_head', 'sportsarena_theme_critical_css', 1 );

/**
 * Remove front-end bloat that is not needed by this theme.
 *
 * @return void
 */
function sportsarena_theme_cleanup() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}
add_action( 'init', 'sportsarena_theme_cleanup' );
