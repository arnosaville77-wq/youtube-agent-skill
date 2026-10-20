<?php
/**
 * Theme setup: supports, menus, sidebars, RTL and activation tasks.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme supports and menus.
 *
 * @return void
 */
function sportsarena_theme_setup() {
	load_theme_textdomain( 'sportsarena-theme', SPORTSARENA_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// Card images: 16:9, sized for 2x on mobile.
	add_image_size( 'sportsarena-card', 640, 360, true );
	add_image_size( 'sportsarena-hero', 1200, 675, true );

	register_nav_menus(
		array(
			'primary' => __( 'القائمة الرئيسية', 'sportsarena-theme' ),
			'footer'  => __( 'قائمة الفوتر', 'sportsarena-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'sportsarena_theme_setup' );

/**
 * Arabic-first: render RTL when the site language is Arabic, even before
 * the WordPress Arabic language pack is installed.
 *
 * @return void
 */
function sportsarena_theme_force_rtl() {
	global $wp_locale;
	$force = str_starts_with( get_locale(), 'ar' );

	/**
	 * Filter whether the theme forces right-to-left direction.
	 *
	 * @param bool $force Force RTL.
	 */
	if ( apply_filters( 'sportsarena_theme_force_rtl', $force ) && $wp_locale instanceof WP_Locale ) {
		$wp_locale->text_direction = 'rtl';
	}
}
add_action( 'init', 'sportsarena_theme_force_rtl', 1 );

/**
 * Content width.
 *
 * @return void
 */
function sportsarena_theme_content_width() {
	$GLOBALS['content_width'] = 760; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core convention.
}
add_action( 'after_setup_theme', 'sportsarena_theme_content_width', 0 );

/**
 * Sidebar.
 *
 * @return void
 */
function sportsarena_theme_widgets() {
	register_sidebar(
		array(
			'name'          => __( 'الشريط الجانبي', 'sportsarena-theme' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'يظهر بجانب المحتوى على الشاشات الكبيرة.', 'sportsarena-theme' ),
			'before_widget' => '<section id="%1$s" class="sa-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="sa-widget__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'sportsarena_theme_widgets' );

/**
 * On theme activation: create the required pages (About, Contact, Privacy,
 * Terms, Disclaimer) through the companion plugin and refresh permalinks.
 *
 * @return void
 */
function sportsarena_theme_activate() {
	if ( function_exists( 'sportsarena_create_required_pages' ) ) {
		sportsarena_create_required_pages();
	}
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'sportsarena_theme_activate' );

/**
 * Remind admins to activate the companion plugin.
 *
 * @return void
 */
function sportsarena_theme_plugin_notice() {
	if ( function_exists( 'sportsarena_match' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'قالب SportsArena يحتاج إلى إضافة «SportsArena Core» لعرض المباريات والفرق والبطولات. ارفعها وفعّلها من صفحة الإضافات.', 'sportsarena-theme' ) . '</p></div>';
}
add_action( 'admin_notices', 'sportsarena_theme_plugin_notice' );

/**
 * Body classes.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function sportsarena_theme_body_class( $classes ) {
	if ( is_active_sidebar( 'sidebar-1' ) || function_exists( 'sportsarena_ad_slot' ) ) {
		$classes[] = 'has-sidebar';
	}
	return $classes;
}
add_filter( 'body_class', 'sportsarena_theme_body_class' );

/**
 * Shorter excerpts for cards.
 *
 * @return int
 */
function sportsarena_theme_excerpt_length() {
	return 24;
}
add_filter( 'excerpt_length', 'sportsarena_theme_excerpt_length' );

/**
 * Plain ellipsis for excerpts.
 *
 * @return string
 */
function sportsarena_theme_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'sportsarena_theme_excerpt_more' );
