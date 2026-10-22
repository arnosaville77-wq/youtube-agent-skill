<?php
/**
 * Month and weekday names before the WordPress language pack is installed.
 *
 * The wp_date() function takes month / weekday names from WP_Locale, which stays English
 * until the language pack of the site language exists. For the languages the
 * plugin ships (Arabic, French, Spanish, German, Italian, Portuguese, Turkish)
 * the names are filled in while they are still untranslated, so dates never
 * show English month names on a non-English page.
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
	 * Month names (January first) and weekday names (Sunday first) per language.
	 *
	 * @return array<string,array{0:string[],1:string[]}>
	 */
	private static function names() {
		return array(
			'ar' => array(
				array( 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر' ),
				array( 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت' ),
			),
			'fr' => array(
				array( 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' ),
				array( 'dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi' ),
			),
			'es' => array(
				array( 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' ),
				array( 'domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado' ),
			),
			'de' => array(
				array( 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' ),
				array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' ),
			),
			'it' => array(
				array( 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre' ),
				array( 'domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato' ),
			),
			'pt' => array(
				array( 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro' ),
				array( 'domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado' ),
			),
			'tr' => array(
				array( 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' ),
				array( 'Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi' ),
			),
		);
	}

	/**
	 * Fill month and weekday names when untranslated.
	 *
	 * @return void
	 */
	public function apply() {
		global $wp_locale;
		$lang  = substr( get_locale(), 0, 2 );
		$names = self::names();
		if ( ! $wp_locale instanceof \WP_Locale || ! isset( $names[ $lang ] ) || 'October' !== $wp_locale->get_month( 10 ) ) {
			return;
		}
		list( $months, $days ) = $names[ $lang ];
		foreach ( $months as $i => $name ) {
			$key                               = zeroise( $i + 1, 2 );
			$wp_locale->month[ $key ]          = $name;
			$wp_locale->month_genitive[ $key ] = $name;
			$wp_locale->month_abbrev[ $name ]  = $name;
		}
		foreach ( $days as $i => $name ) {
			$wp_locale->weekday[ $i ]           = $name;
			$wp_locale->weekday_abbrev[ $name ] = $name;
		}
		if ( 'ar' === $lang ) {
			$wp_locale->meridiem = array(
				'am' => 'ص',
				'pm' => 'م',
				'AM' => 'ص',
				'PM' => 'م',
			);
		}
	}
}
