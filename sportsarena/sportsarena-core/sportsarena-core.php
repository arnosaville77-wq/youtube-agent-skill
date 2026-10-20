<?php
/**
 * Plugin Name:       SportsArena Core
 * Plugin URI:        https://example.com/
 * Description:       منطق البيانات لموقع رياضي: المباريات والفرق والبطولات، المزامنة مع مزوّدي البيانات، SEO وSchema، وإعدادات AdSense والموافقة على الكوكيز.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            SportsArena
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sportsarena-core
 * Domain Path:       /languages
 *
 * @package SportsArena\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'SPORTSARENA_CORE_VERSION', '1.0.0' );
define( 'SPORTSARENA_CORE_FILE', __FILE__ );
define( 'SPORTSARENA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPORTSARENA_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Minimal PSR-4-like autoloader that maps SportsArena\Core\Foo_Bar to
 * includes/class-foo-bar.php and SportsArena\Core\Data\Foo to
 * includes/data/class-foo.php (interfaces use the interface- prefix).
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'SportsArena\\Core\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );
		$dir      = SPORTSARENA_CORE_DIR . 'includes/';
		foreach ( $parts as $part ) {
			$dir .= strtolower( str_replace( '_', '-', $part ) ) . '/';
		}
		$file_base = strtolower( str_replace( '_', '-', $name ) );
		foreach ( array( 'class-', 'interface-', 'trait-' ) as $type ) {
			$file = $dir . $type . $file_base . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

require_once SPORTSARENA_CORE_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, array( 'SportsArena\\Core\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SportsArena\\Core\\Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'SportsArena\\Core\\Plugin', 'instance' ) );
