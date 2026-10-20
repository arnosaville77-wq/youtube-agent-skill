<?php
/**
 * Arabic names for common teams, competitions and sports. Providers return
 * English names; new posts get the Arabic title when known (editors can
 * rename any post afterwards, the importer never overwrites titles).
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Name dictionary.
 */
final class Names {

	/**
	 * Sport slug => Arabic name.
	 *
	 * @return array<string,string>
	 */
	public static function sports() {
		return array(
			'football'   => 'كرة القدم',
			'basketball' => 'كرة السلة',
			'tennis'     => 'التنس',
			'formula-1'  => 'الفورمولا 1',
			'handball'   => 'كرة اليد',
			'volleyball' => 'الكرة الطائرة',
			'mma'        => 'الملاكمة والفنون القتالية',
		);
	}

	/**
	 * English => Arabic dictionary.
	 *
	 * @return array<string,string>
	 */
	public static function dictionary() {
		$names = array(
			// Competitions.
			'Premier League'         => 'الدوري الإنجليزي الممتاز',
			'La Liga'                => 'الدوري الإسباني',
			'Serie A'                => 'الدوري الإيطالي',
			'Bundesliga'             => 'الدوري الألماني',
			'Ligue 1'                => 'الدوري الفرنسي',
			'UEFA Champions League'  => 'دوري أبطال أوروبا',
			'UEFA Europa League'     => 'الدوري الأوروبي',
			'World Cup'              => 'كأس العالم',
			'Euro Championship'      => 'كأس أمم أوروبا',
			'Copa America'           => 'كوبا أمريكا',
			'Africa Cup of Nations'  => 'كأس أمم أفريقيا',
			'Pro League'             => 'دوري روشن السعودي',
			'Saudi Pro League'       => 'دوري روشن السعودي',
			'Premier League (Egypt)' => 'الدوري المصري الممتاز',
			'Botola Pro'             => 'البطولة المغربية الاحترافية',
			'CAF Champions League'   => 'دوري أبطال أفريقيا',
			'AFC Champions League'   => 'دوري أبطال آسيا',
			'Arab Cup'               => 'كأس العرب',
			'NBA'                    => 'دوري كرة السلة الأمريكي',
			'Euroleague'             => 'الدوري الأوروبي لكرة السلة',
			// Teams.
			'Real Madrid'            => 'ريال مدريد',
			'Barcelona'              => 'برشلونة',
			'Atletico Madrid'        => 'أتلتيكو مدريد',
			'Sevilla'                => 'إشبيلية',
			'Manchester City'        => 'مانشستر سيتي',
			'Manchester United'      => 'مانشستر يونايتد',
			'Liverpool'              => 'ليفربول',
			'Arsenal'                => 'أرسنال',
			'Chelsea'                => 'تشيلسي',
			'Tottenham'              => 'توتنهام',
			'Bayern Munich'          => 'بايرن ميونخ',
			'Borussia Dortmund'      => 'بوروسيا دورتموند',
			'Paris Saint Germain'    => 'باريس سان جيرمان',
			'Marseille'              => 'مارسيليا',
			'Juventus'               => 'يوفنتوس',
			'Inter'                  => 'إنتر ميلان',
			'AC Milan'               => 'ميلان',
			'Napoli'                 => 'نابولي',
			'AS Roma'                => 'روما',
			'Al Ahly'                => 'الأهلي',
			'Zamalek SC'             => 'الزمالك',
			'Pyramids FC'            => 'بيراميدز',
			'Al-Hilal Saudi FC'      => 'الهلال',
			'Al-Nassr'               => 'النصر',
			'Al-Ittihad FC'          => 'الاتحاد',
			'Al-Ahli Jeddah'         => 'الأهلي السعودي',
			'Wydad AC'               => 'الوداد الرياضي',
			'Raja Casablanca'        => 'الرجاء الرياضي',
			'Esperance de Tunis'     => 'الترجي التونسي',
			'Morocco'                => 'المغرب',
			'Egypt'                  => 'مصر',
			'Saudi Arabia'           => 'السعودية',
			'Algeria'                => 'الجزائر',
			'Tunisia'                => 'تونس',
			'Qatar'                  => 'قطر',
			'Spain'                  => 'إسبانيا',
			'France'                 => 'فرنسا',
			'Germany'                => 'ألمانيا',
			'Brazil'                 => 'البرازيل',
			'Argentina'              => 'الأرجنتين',
			'Portugal'               => 'البرتغال',
			'England'                => 'إنجلترا',
			'Italy'                  => 'إيطاليا',
			'Newcastle'              => 'نيوكاسل يونايتد',
			'Aston Villa'            => 'أستون فيلا',
			'Brighton'               => 'برايتون',
			'West Ham'               => 'وست هام',
			'Real Sociedad'          => 'ريال سوسيداد',
			'Villarreal'             => 'فياريال',
			'Real Betis'             => 'ريال بيتيس',
			'Valencia'               => 'فالنسيا',
			'Athletic Club'          => 'أتلتيك بلباو',
			'Girona'                 => 'جيرونا',
			'Al-Shabab'              => 'الشباب',
			'Al-Taawoun'             => 'التعاون',
			'Al Masry'               => 'المصري البورسعيدي',
			'AS FAR'                 => 'الجيش الملكي',
			'RS Berkane'             => 'نهضة بركان',
			'Denver Nuggets'         => 'دنفر ناغتس',
			'ATP Finals'             => 'بطولة ATP الختامية',
			'Carlos Alcaraz'         => 'كارلوس ألكاراز',
			'Jannik Sinner'          => 'يانيك سينر',
			'Novak Djokovic'         => 'نوفاك ديوكوفيتش',
			'Alexander Zverev'       => 'ألكسندر زفيريف',
			'Los Angeles Lakers'     => 'لوس أنجلوس ليكرز',
			'Golden State Warriors'  => 'غولدن ستايت ووريورز',
			'Boston Celtics'         => 'بوسطن سلتيكس',
		);

		/**
		 * Filter the English => Arabic name dictionary.
		 *
		 * @param array<string,string> $names Dictionary.
		 */
		return (array) apply_filters( 'sportsarena_name_dictionary', $names );
	}

	/**
	 * Translate an English name if known.
	 *
	 * @param string $name English name.
	 * @return string
	 */
	public static function arabic( $name ) {
		$dictionary = self::dictionary();
		return $dictionary[ $name ] ?? $name;
	}
}
