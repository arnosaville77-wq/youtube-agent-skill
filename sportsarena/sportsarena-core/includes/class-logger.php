<?php
/**
 * Internal log stored in {prefix}sa_logs and shown in the dashboard.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Logger.
 */
final class Logger {

	const MAX_ROWS = 2000;

	/**
	 * Write an info entry.
	 *
	 * @param string $source  Component name.
	 * @param string $message Message.
	 * @param array  $context Extra data (never put secrets here).
	 * @return void
	 */
	public static function info( $source, $message, array $context = array() ) {
		self::log( 'info', $source, $message, $context );
	}

	/**
	 * Write a warning entry.
	 *
	 * @param string $source  Component name.
	 * @param string $message Message.
	 * @param array  $context Extra data.
	 * @return void
	 */
	public static function warning( $source, $message, array $context = array() ) {
		self::log( 'warning', $source, $message, $context );
	}

	/**
	 * Write an error entry.
	 *
	 * @param string $source  Component name.
	 * @param string $message Message.
	 * @param array  $context Extra data.
	 * @return void
	 */
	public static function error( $source, $message, array $context = array() ) {
		self::log( 'error', $source, $message, $context );
	}

	/**
	 * Insert a log row.
	 *
	 * @param string $level   info|warning|error.
	 * @param string $source  Component name.
	 * @param string $message Message.
	 * @param array  $context Extra data.
	 * @return void
	 */
	public static function log( $level, $source, $message, array $context = array() ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom log table.
		$wpdb->insert(
			$wpdb->prefix . 'sa_logs',
			array(
				'created_at' => current_time( 'mysql', true ),
				'level'      => substr( sanitize_key( $level ), 0, 10 ),
				'source'     => substr( sanitize_text_field( $source ), 0, 60 ),
				'message'    => sanitize_text_field( $message ),
				'context'    => $context ? wp_json_encode( $context ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Fetch recent entries.
	 *
	 * @param int    $limit Number of rows.
	 * @param string $level Optional level filter.
	 * @return array<int,object>
	 */
	public static function recent( $limit = 200, $level = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sa_logs';
		if ( $level ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh log rows.
			return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE level = %s ORDER BY id DESC LIMIT %d', $table, $level, $limit ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh log rows.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit ) );
	}

	/**
	 * Delete all entries.
	 *
	 * @return void
	 */
	public static function clear() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . 'sa_logs' ) );
	}

	/**
	 * Keep the table bounded (called by the daily maintenance event).
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;
		$table = $wpdb->prefix . 'sa_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$threshold = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i ORDER BY id DESC LIMIT 1 OFFSET %d', $table, self::MAX_ROWS ) );
		if ( $threshold > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id <= %d', $table, $threshold ) );
		}
	}
}
