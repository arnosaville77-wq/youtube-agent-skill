<?php
/**
 * Uninstall: content (matches, teams, competitions, news) is always kept.
 * Settings and custom tables are removed only if the owner enabled
 * "delete_on_uninstall" in the settings.
 *
 * @package SportsArena\Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sportsarena_settings = (array) get_option( 'sportsarena_settings', array() );
if ( empty( $sportsarena_settings['delete_on_uninstall'] ) ) {
	return;
}

foreach ( array( 'sportsarena_settings', 'sportsarena_ads', 'sportsarena_consent', 'sportsarena_db_version', 'sportsarena_data_version', 'sportsarena_live_last_run', 'sportsarena_quota_api-football', 'sportsarena_quota_api-basketball', 'sportsarena_quota_thesportsdb' ) as $sportsarena_option ) {
	delete_option( $sportsarena_option );
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing our own tables.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i, %i', $wpdb->prefix . 'sa_logs', $wpdb->prefix . 'sa_cache' ) );
