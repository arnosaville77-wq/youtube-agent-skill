<?php
/**
 * How kickoff times are shown: Greenwich time (GMT) for every visitor, or
 * converted to each visitor's device timezone.
 *
 * In GMT mode the site timezone is read as UTC everywhere, so times, the
 * "today / tomorrow" pages and the daily sync all use the same GMT day.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Time display.
 */
final class Time_Display {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( self::is_gmt() ) {
			add_filter( 'pre_option_timezone_string', array( $this, 'utc' ) );
			add_filter( 'pre_option_gmt_offset', '__return_zero' );
		}
	}

	/**
	 * Timezone string in GMT mode.
	 *
	 * @return string
	 */
	public function utc() {
		return 'UTC';
	}

	/**
	 * Whether times are shown in GMT (the default).
	 *
	 * @return bool
	 */
	public static function is_gmt() {
		return 'local' !== Options::get( 'time_display' );
	}

	/**
	 * Suffix appended to times in GMT mode.
	 *
	 * @return string
	 */
	public static function suffix() {
		return self::is_gmt() ? ' GMT' : '';
	}
}
