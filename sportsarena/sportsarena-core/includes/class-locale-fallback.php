<?php
/**
 * Arabic date names before the WordPress Arabic language pack is installed.
 *
 * The wp_date() function takes month / weekday names from WP_Locale, which stays English
 * until the "ar" language pack exists. When the site language is Arabic and
 * the names are still untranslated, Arabic names are filled in.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Locale fallback.
 */
final class Locale_Fallback {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'apply' ), 2 );
	}

	/**
	 * Fill Arabic month and weekday names when untranslated.
	 *
	 * @return void
	 */
	public function apply() {
		global $wp_locale;
		if ( ! $wp_locale instanceof \WP_Locale || ! str_starts_with( get_locale(), 'ar' ) || 'October' !== $wp_locale->get_month( 10 ) ) {
			return;
		}
		$months = array( 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر' );
		foreach ( $months as $i => $name ) {
			$key                               = zeroise( $i + 1, 2 );
			$wp_locale->month[ $key ]          = $name;
			$wp_locale->month_genitive[ $key ] = $name;
			$wp_locale->month_abbrev[ $name ]  = $name;
		}
		$days = array( 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت' );
		foreach ( $days as $i => $name ) {
			$wp_locale->weekday[ $i ]           = $name;
			$wp_locale->weekday_abbrev[ $name ] = $name;
		}
		$wp_locale->meridiem = array(
			'am' => 'ص',
			'pm' => 'م',
			'AM' => 'ص',
			'PM' => 'م',
		);
	}
}
