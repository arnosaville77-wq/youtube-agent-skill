<?php
/**
 * Country names (as sent by the providers, in English) => ISO 3166 codes,
 * used to show the country flags bundled in assets/flags (flag-icons, MIT).
 * Country flags are not protected logos, so they are always shown.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Countries.
 */
final class Countries {

	/**
	 * Normalized English name => flag code (file name in assets/flags).
	 */
	const MAP = array(
		'afghanistan'            => 'af',
		'albania'                => 'al',
		'algeria'                => 'dz',
		'andorra'                => 'ad',
		'angola'                 => 'ao',
		'argentina'              => 'ar',
		'armenia'                => 'am',
		'australia'              => 'au',
		'austria'                => 'at',
		'azerbaijan'             => 'az',
		'bahrain'                => 'bh',
		'bangladesh'             => 'bd',
		'belarus'                => 'by',
		'belgium'                => 'be',
		'benin'                  => 'bj',
		'bolivia'                => 'bo',
		'bosnia'                 => 'ba',
		'bosnia and herzegovina' => 'ba',
		'botswana'               => 'bw',
		'brazil'                 => 'br',
		'bulgaria'               => 'bg',
		'burkina faso'           => 'bf',
		'burundi'                => 'bi',
		'cameroon'               => 'cm',
		'canada'                 => 'ca',
		'cape verde islands'     => 'cv',
		'cape verde'             => 'cv',
		'chile'                  => 'cl',
		'china'                  => 'cn',
		'colombia'               => 'co',
		'comoros'                => 'km',
		'congo'                  => 'cg',
		'congo dr'               => 'cd',
		'dr congo'               => 'cd',
		'costa rica'             => 'cr',
		'croatia'                => 'hr',
		'cuba'                   => 'cu',
		'cyprus'                 => 'cy',
		'czech republic'         => 'cz',
		'czechia'                => 'cz',
		'denmark'                => 'dk',
		'djibouti'               => 'dj',
		'ecuador'                => 'ec',
		'egypt'                  => 'eg',
		'el salvador'            => 'sv',
		'england'                => 'gb-eng',
		'equatorial guinea'      => 'gq',
		'estonia'                => 'ee',
		'ethiopia'               => 'et',
		'europe'                 => 'eu',
		'faroe islands'          => 'fo',
		'finland'                => 'fi',
		'france'                 => 'fr',
		'gabon'                  => 'ga',
		'gambia'                 => 'gm',
		'georgia'                => 'ge',
		'germany'                => 'de',
		'ghana'                  => 'gh',
		'gibraltar'              => 'gi',
		'greece'                 => 'gr',
		'guatemala'              => 'gt',
		'guinea'                 => 'gn',
		'guinea bissau'          => 'gw',
		'haiti'                  => 'ht',
		'honduras'               => 'hn',
		'hong kong'              => 'hk',
		'hungary'                => 'hu',
		'iceland'                => 'is',
		'india'                  => 'in',
		'indonesia'              => 'id',
		'iran'                   => 'ir',
		'iraq'                   => 'iq',
		'ireland'                => 'ie',
		'israel'                 => 'il',
		'italy'                  => 'it',
		'ivory coast'            => 'ci',
		'cote d ivoire'          => 'ci',
		'jamaica'                => 'jm',
		'japan'                  => 'jp',
		'jordan'                 => 'jo',
		'kazakhstan'             => 'kz',
		'kenya'                  => 'ke',
		'kosovo'                 => 'xk',
		'kuwait'                 => 'kw',
		'kyrgyzstan'             => 'kg',
		'latvia'                 => 'lv',
		'lebanon'                => 'lb',
		'liberia'                => 'lr',
		'libya'                  => 'ly',
		'liechtenstein'          => 'li',
		'lithuania'              => 'lt',
		'luxembourg'             => 'lu',
		'macedonia'              => 'mk',
		'north macedonia'        => 'mk',
		'madagascar'             => 'mg',
		'malawi'                 => 'mw',
		'malaysia'               => 'my',
		'mali'                   => 'ml',
		'malta'                  => 'mt',
		'mauritania'             => 'mr',
		'mauritius'              => 'mu',
		'mexico'                 => 'mx',
		'moldova'                => 'md',
		'monaco'                 => 'mc',
		'mongolia'               => 'mn',
		'montenegro'             => 'me',
		'morocco'                => 'ma',
		'mozambique'             => 'mz',
		'namibia'                => 'na',
		'netherlands'            => 'nl',
		'new zealand'            => 'nz',
		'nicaragua'              => 'ni',
		'niger'                  => 'ne',
		'nigeria'                => 'ng',
		'northern ireland'       => 'gb-nir',
		'north korea'            => 'kp',
		'norway'                 => 'no',
		'oman'                   => 'om',
		'pakistan'               => 'pk',
		'palestine'              => 'ps',
		'panama'                 => 'pa',
		'paraguay'               => 'py',
		'peru'                   => 'pe',
		'philippines'            => 'ph',
		'poland'                 => 'pl',
		'portugal'               => 'pt',
		'qatar'                  => 'qa',
		'romania'                => 'ro',
		'russia'                 => 'ru',
		'rwanda'                 => 'rw',
		'san marino'             => 'sm',
		'saudi arabia'           => 'sa',
		'scotland'               => 'gb-sct',
		'senegal'                => 'sn',
		'serbia'                 => 'rs',
		'sierra leone'           => 'sl',
		'singapore'              => 'sg',
		'slovakia'               => 'sk',
		'slovenia'               => 'si',
		'somalia'                => 'so',
		'south africa'           => 'za',
		'south korea'            => 'kr',
		'korea republic'         => 'kr',
		'south sudan'            => 'ss',
		'spain'                  => 'es',
		'sudan'                  => 'sd',
		'sweden'                 => 'se',
		'switzerland'            => 'ch',
		'syria'                  => 'sy',
		'tajikistan'             => 'tj',
		'tanzania'               => 'tz',
		'thailand'               => 'th',
		'togo'                   => 'tg',
		'trinidad and tobago'    => 'tt',
		'tunisia'                => 'tn',
		'turkey'                 => 'tr',
		'turkiye'                => 'tr',
		'turkmenistan'           => 'tm',
		'uganda'                 => 'ug',
		'ukraine'                => 'ua',
		'united arab emirates'   => 'ae',
		'uae'                    => 'ae',
		'united kingdom'         => 'gb',
		'great britain'          => 'gb',
		'uruguay'                => 'uy',
		'usa'                    => 'us',
		'united states'          => 'us',
		'uzbekistan'             => 'uz',
		'venezuela'              => 've',
		'vietnam'                => 'vn',
		'wales'                  => 'gb-wls',
		'yemen'                  => 'ye',
		'zambia'                 => 'zm',
		'zimbabwe'               => 'zw',
	);

	/**
	 * Normalize a country name: lowercase, no accents, hyphens as spaces.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function normalize( $name ) {
		$name = strtolower( remove_accents( (string) $name ) );
		$name = str_replace( array( '-', '_', "'", '.' ), ' ', $name );
		return trim( (string) preg_replace( '/\s+/', ' ', $name ) );
	}

	/**
	 * Flag code for a country name (English, or Arabic from the name
	 * dictionary), '' when unknown (e.g. "World").
	 *
	 * @param string $name Country name.
	 * @return string
	 */
	public static function code( $name ) {
		$name = (string) $name;
		if ( '' === $name ) {
			return '';
		}
		if ( Names::has_arabic( $name ) ) {
			$english = array_search( $name, Names::dictionary(), true );
			$name    = false === $english ? '' : (string) $english;
		}
		$key = self::normalize( $name );
		return self::MAP[ $key ] ?? '';
	}

	/**
	 * All flag codes used by the map (to bundle only the needed SVGs).
	 *
	 * @return string[]
	 */
	public static function codes() {
		return array_values( array_unique( self::MAP ) );
	}
}
