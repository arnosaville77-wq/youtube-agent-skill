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
	sportsarena_theme_retire_default_widgets();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'sportsarena_theme_activate' );

/**
 * Browser titles of the 404 and search pages from the theme translations
 * (WordPress core would use its own strings, English until its language
 * pack is installed).
 *
 * @param array $parts Title parts.
 * @return array
 */
function sportsarena_theme_title_parts( $parts ) {
	if ( is_404() ) {
		$parts['title'] = __( 'الصفحة غير موجودة', 'sportsarena-theme' );
	} elseif ( is_search() ) {
		/* translators: %s: search query. */
		$parts['title'] = sprintf( __( 'نتائج البحث عن: %s', 'sportsarena-theme' ), get_search_query( false ) );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'sportsarena_theme_title_parts' );

// Archive headings show the term or date alone ("Football news", not "Category: Football news").
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

/**
 * Move the widgets WordPress adds on install (search, recent posts, recent
 * comments, archives, categories) out of the sidebar, so the theme's own
 * sidebar (competitions, latest news) shows instead. Their headings are
 * saved in the install language and would mix languages after a switch.
 * Widgets the owner added are kept.
 *
 * @return void
 */
function sportsarena_theme_retire_default_widgets() {
	$sidebars = wp_get_sidebars_widgets();
	if ( empty( $sidebars['sidebar-1'] ) ) {
		return;
	}
	$blocks  = (array) get_option( 'widget_block', array() );
	$pattern = '/<!-- wp:(search|latest-posts|latest-comments|archives|categories)\b/';
	$keep    = array();
	$retired = array();
	foreach ( (array) $sidebars['sidebar-1'] as $widget_id ) {
		$default = (bool) preg_match( '/^(search|recent-posts|recent-comments|archives|categories|meta)-\d+$/', $widget_id );
		if ( preg_match( '/^block-(\d+)$/', $widget_id, $m ) && isset( $blocks[ $m[1] ]['content'] ) ) {
			$default = (bool) preg_match( $pattern, (string) $blocks[ $m[1] ]['content'] );
		}
		if ( $default ) {
			$retired[] = $widget_id;
		} else {
			$keep[] = $widget_id;
		}
	}
	if ( $retired ) {
		$sidebars['sidebar-1']           = $keep;
		$sidebars['wp_inactive_widgets'] = array_merge( (array) ( $sidebars['wp_inactive_widgets'] ?? array() ), $retired );
		wp_set_sidebars_widgets( $sidebars );
	}
}
add_action( 'sportsarena_relocalized', 'sportsarena_theme_retire_default_widgets' );

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
