<?php
/**
 * Main plugin bootstrap: wires every component to WordPress hooks.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get (and boot on first call) the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Register all components.
	 *
	 * @return void
	 */
	private function boot() {
		load_plugin_textdomain( 'sportsarena-core', false, dirname( plugin_basename( SPORTSARENA_CORE_FILE ) ) . '/languages' );

		Installer::maybe_upgrade();

		( new Languages() )->register_hooks();
		( new Time_Display() )->register_hooks();
		( new Locale_Fallback() )->register_hooks();
		( new Localized_Names() )->register_hooks();
		( new Post_Types() )->register_hooks();
		( new Rewrites() )->register_hooks();
		( new Data\Sync() )->register_hooks();
		( new Rest() )->register_hooks();
		( new Pages() )->register_hooks();
		( new Contact_Form() )->register_hooks();
		( new Seo\Seo() )->register_hooks();
		( new Seo\Schema() )->register_hooks();
		( new Seo\Sitemaps() )->register_hooks();
		( new Seo\Og_Image() )->register_hooks();
		( new Ads\Ads() )->register_hooks();
		( new Ads\Consent() )->register_hooks();
		( new Linking() )->register_hooks();
		( new Logo_Cache() )->register_hooks();
		( new News_Sections() )->register_hooks();
		( new Relocalize() )->register_hooks();

		if ( is_admin() ) {
			( new Admin\Settings() )->register_hooks();
			( new Admin\Meta_Boxes() )->register_hooks();
			( new Admin\Tools() )->register_hooks();
			( new Admin\Adsense_Check() )->register_hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'sportsarena', Cli::class );
		}
	}
}
