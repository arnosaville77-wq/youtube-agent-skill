<?php
/**
 * SportsArena theme bootstrap.
 *
 * Data, SEO, ads and consent live in the "SportsArena Core" plugin so that
 * nothing is lost when the theme changes. This theme only renders.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

define( 'SPORTSARENA_THEME_VERSION', '1.0.0' );
define( 'SPORTSARENA_THEME_DIR', get_template_directory() );
define( 'SPORTSARENA_THEME_URI', get_template_directory_uri() );

require SPORTSARENA_THEME_DIR . '/inc/setup.php';
require SPORTSARENA_THEME_DIR . '/inc/assets.php';
require SPORTSARENA_THEME_DIR . '/inc/template-tags.php';
require SPORTSARENA_THEME_DIR . '/inc/images.php';
require SPORTSARENA_THEME_DIR . '/inc/palette.php';
require SPORTSARENA_THEME_DIR . '/inc/pwa.php';
