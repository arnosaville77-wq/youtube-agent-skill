<?php
/**
 * Typed access to the plugin options with defaults.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Options helper. API keys live in the database (entered in the dashboard),
 * never in code. A constant override (e.g. SPORTSARENA_API_FOOTBALL_KEY in
 * wp-config.php) is supported for hosts that prefer environment secrets.
 */
final class Options {

	const GENERAL = 'sportsarena_settings';
	const ADS     = 'sportsarena_ads';
	const CONSENT = 'sportsarena_consent';
	const PAGES   = 'sportsarena_required_pages';

	/**
	 * Default values for general settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function general_defaults() {
		return array(
			'sync_enabled'           => 0,
			'api_football_key'       => '',
			'api_football_leagues'   => '39,140,135,78,61,2,3,1,4,9,6,307,233,200,12',
			'api_basketball_key'     => '',
			'api_basketball_leagues' => '12,120',
			'thesportsdb_key'        => '',
			'thesportsdb_sports'     => 'Tennis,Motorsport,Handball,Volleyball,Fighting',
			'daily_limit'            => 100,
			'minute_limit'           => 10,
			'live_interval'          => 60,
			'allow_logos'            => 0,
			'cache_logos'            => 1,
			'timezone_label'         => '',
			'news_sitemap'           => 1,
			'alternate_sites'        => '',
			'delete_on_uninstall'    => 0,
		);
	}

	/**
	 * Default values for ad settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function ads_defaults() {
		$slots = array();
		foreach ( array_keys( self::ad_slots() ) as $slot ) {
			$slots[ $slot ] = array(
				'enabled' => 0,
				'slot_id' => '',
				'html'    => '',
			);
		}
		return array(
			'publisher_id' => '',
			'auto_ads'     => 0,
			'ads_txt'      => 1,
			'slots'        => $slots,
		);
	}

	/**
	 * Default values for consent settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function consent_defaults() {
		return array(
			'mode'         => 'builtin', // builtin | cmp | off.
			'consent_mode' => 1,
			'eea_only'     => 1,
		);
	}

	/**
	 * Registered ad slots with their labels and reserved heights (px).
	 *
	 * @return array<string,array{label:string,mobile:int,desktop:int,format:string}>
	 */
	public static function ad_slots() {
		return array(
			'header'         => array(
				'label'   => __( 'أسفل الهيدر', 'sportsarena-core' ),
				'mobile'  => 100,
				'desktop' => 90,
				'format'  => 'horizontal',
			),
			'in_article'     => array(
				'label'   => __( 'داخل المقال (بعد الفقرة الثانية)', 'sportsarena-core' ),
				'mobile'  => 280,
				'desktop' => 280,
				'format'  => 'rectangle',
			),
			'in_article_2'   => array(
				'label'   => __( 'داخل المقال الطويل (بعد الفقرة السادسة)', 'sportsarena-core' ),
				'mobile'  => 280,
				'desktop' => 280,
				'format'  => 'rectangle',
			),
			'article_bottom' => array(
				'label'   => __( 'أسفل المقال', 'sportsarena-core' ),
				'mobile'  => 280,
				'desktop' => 250,
				'format'  => 'rectangle',
			),
			'in_feed'        => array(
				'label'   => __( 'بين بطاقات الأخبار (كل 6 أخبار)', 'sportsarena-core' ),
				'mobile'  => 280,
				'desktop' => 250,
				'format'  => 'rectangle',
			),
			'sidebar'        => array(
				'label'   => __( 'الشريط الجانبي (ثابت على الديسكتوب)', 'sportsarena-core' ),
				'mobile'  => 280,
				'desktop' => 600,
				'format'  => 'vertical',
			),
			'between_lists'  => array(
				'label'   => __( 'بين مجموعات البطولات (كل 3 بطولات)', 'sportsarena-core' ),
				'mobile'  => 280,
				'desktop' => 250,
				'format'  => 'rectangle',
			),
			'match_bottom'   => array(
				'label'   => __( 'أسفل صفحة المباراة', 'sportsarena-core' ),
				'mobile'  => 280,
				'desktop' => 250,
				'format'  => 'rectangle',
			),
		);
	}

	/**
	 * Get a general setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$const = 'SPORTSARENA_' . strtoupper( $key );
		if ( str_ends_with( $key, '_key' ) && defined( $const ) ) {
			return (string) constant( $const );
		}
		$values = wp_parse_args( (array) get_option( self::GENERAL, array() ), self::general_defaults() );
		return $values[ $key ] ?? null;
	}

	/**
	 * Get all ad settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function ads() {
		$defaults = self::ads_defaults();
		$values   = wp_parse_args( (array) get_option( self::ADS, array() ), $defaults );
		foreach ( $defaults['slots'] as $slot => $slot_defaults ) {
			$values['slots'][ $slot ] = wp_parse_args( (array) ( $values['slots'][ $slot ] ?? array() ), $slot_defaults );
		}
		return $values;
	}

	/**
	 * Get all consent settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function consent() {
		return wp_parse_args( (array) get_option( self::CONSENT, array() ), self::consent_defaults() );
	}

	/**
	 * Parse a comma separated list into trimmed, non-empty strings.
	 *
	 * @param string $value Raw CSV.
	 * @return string[]
	 */
	public static function csv( $value ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' ) );
	}
}
