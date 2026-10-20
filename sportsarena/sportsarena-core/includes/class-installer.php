<?php
/**
 * Activation / deactivation / schema upgrades.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
final class Installer {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'sportsarena_db_version';

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		add_option( Options::GENERAL, Options::general_defaults() );
		add_option( Options::ADS, Options::ads_defaults() );
		add_option( Options::CONSENT, Options::consent_defaults() );

		( new Post_Types() )->register();
		( new Rewrites() )->add_rules();
		Data\Sync::schedule_events();
		Pages::create_required_pages();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation: remove scheduled events, keep all data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Data\Sync::unschedule_events();
		flush_rewrite_rules();
	}

	/**
	 * Run schema upgrades when the stored version is older.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
		}
	}

	/**
	 * Create (or update) the custom tables via dbDelta.
	 *
	 * Tables:
	 * - {prefix}sa_logs:  internal sync / error log shown in the dashboard.
	 * - {prefix}sa_cache: raw API responses (long lived, survives object cache flushes).
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$logs    = $wpdb->prefix . 'sa_logs';
		$cache   = $wpdb->prefix . 'sa_cache';

		dbDelta(
			"CREATE TABLE {$logs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				level varchar(10) NOT NULL DEFAULT 'info',
				source varchar(60) NOT NULL DEFAULT '',
				message text NOT NULL,
				context longtext NULL,
				PRIMARY KEY  (id),
				KEY level (level),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$cache} (
				cache_key char(32) NOT NULL,
				provider varchar(40) NOT NULL DEFAULT '',
				endpoint varchar(191) NOT NULL DEFAULT '',
				payload longtext NOT NULL,
				expires_at int(10) unsigned NOT NULL DEFAULT 0,
				updated_at int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (cache_key),
				KEY expires_at (expires_at)
			) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}
}
