<?php
/**
 * Localized names for common teams, competitions and sports. Providers
 * return English names; on an Arabic site new posts get the Arabic title
 * when known, on any other language they keep the provider's name (editors
 * can rename any post afterwards, the importer never overwrites titles).
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
			'football'   => __( 'كرة القدم', 'sportsarena-core' ),
			'basketball' => __( 'كرة السلة', 'sportsarena-core' ),
			'tennis'     => __( 'التنس', 'sportsarena-core' ),
			'formula-1'  => __( 'الفورمولا 1', 'sportsarena-core' ),
			'handball'   => __( 'كرة اليد', 'sportsarena-core' ),
			'volleyball' => __( 'الكرة الطائرة', 'sportsarena-core' ),
			'mma'        => __( 'الملاكمة والفنون القتالية', 'sportsarena-core' ),
		);
	}

	/**
	 * Whether the current locale is Arabic.
	 *
	 * @return bool
	 */
	public static function is_arabic() {
		return str_starts_with( determine_locale(), 'ar' );
	}

	/**
	 * Whether a string contains Arabic letters.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	public static function has_arabic( $text ) {
		return (bool) preg_match( '/[\x{0600}-\x{06FF}]/u', (string) $text );
	}

	/**
	 * English => Arabic dictionary.
	 *
	 * @return array<string,string>
	 */
	public static function dictionary() {
		$names = array(
			// Competitions.
			'Premier League'              => 'الدوري الإنجليزي الممتاز',
			'La Liga'                     => 'الدوري الإسباني',
			'Serie A'                     => 'الدوري الإيطالي',
			'Bundesliga'                  => 'الدوري الألماني',
			'Ligue 1'                     => 'الدوري الفرنسي',
			'UEFA Champions League'       => 'دوري أبطال أوروبا',
			'UEFA Europa League'          => 'الدوري الأوروبي',
			'World Cup'                   => 'كأس العالم',
			'Euro Championship'           => 'كأس أمم أوروبا',
			'Copa America'                => 'كوبا أمريكا',
			'Africa Cup of Nations'       => 'كأس أمم أفريقيا',
			'Pro League'                  => 'دوري روشن السعودي',
			'Saudi Pro League'            => 'دوري روشن السعودي',
			'Premier League (Egypt)'      => 'الدوري المصري الممتاز',
			'Botola Pro'                  => 'البطولة المغربية الاحترافية',
			'CAF Champions League'        => 'دوري أبطال أفريقيا',
			'AFC Champions League'        => 'دوري أبطال آسيا',
			'Arab Cup'                    => 'كأس العرب',
			'NBA'                         => 'دوري كرة السلة الأمريكي',
			'Euroleague'                  => 'الدوري الأوروبي لكرة السلة',
			// Teams.
			'Real Madrid'                 => 'ريال مدريد',
			'Barcelona'                   => 'برشلونة',
			'Atletico Madrid'             => 'أتلتيكو مدريد',
			'Sevilla'                     => 'إشبيلية',
			'Manchester City'             => 'مانشستر سيتي',
			'Manchester United'           => 'مانشستر يونايتد',
			'Liverpool'                   => 'ليفربول',
			'Arsenal'                     => 'أرسنال',
			'Chelsea'                     => 'تشيلسي',
			'Tottenham'                   => 'توتنهام',
			'Bayern Munich'               => 'بايرن ميونخ',
			'Borussia Dortmund'           => 'بوروسيا دورتموند',
			'Paris Saint Germain'         => 'باريس سان جيرمان',
			'Marseille'                   => 'مارسيليا',
			'Juventus'                    => 'يوفنتوس',
			'Inter'                       => 'إنتر ميلان',
			'AC Milan'                    => 'ميلان',
			'Napoli'                      => 'نابولي',
			'AS Roma'                     => 'روما',
			'Lyon'                        => 'ليون',
			'Monaco'                      => 'موناكو',
			'Bayer Leverkusen'            => 'باير ليفركوزن',
			'RB Leipzig'                  => 'لايبزيغ',
			'Everton'                     => 'إيفرتون',
			'Bournemouth'                 => 'بورنموث',
			'Brentford'                   => 'برينتفورد',
			'Coventry City'               => 'كوفنتري سيتي',
			'Crystal Palace'              => 'كريستال بالاس',
			'Fulham'                      => 'فولهام',
			'Hull City'                   => 'هال سيتي',
			'Ipswich Town'                => 'إيبسويتش تاون',
			'Leeds United'                => 'ليدز يونايتد',
			'Nottingham Forest'           => 'نوتنغهام فورست',
			'Sunderland'                  => 'سندرلاند',
			'Espanyol'                    => 'إسبانيول',
			'Celta Vigo'                  => 'سيلتا فيغو',
			'Alaves'                      => 'ألافيس',
			'Deportivo La Coruna'         => 'ديبورتيفو لاكورونيا',
			'Elche'                       => 'إلتشي',
			'Getafe'                      => 'خيتافي',
			'Levante'                     => 'ليفانتي',
			'Malaga'                      => 'مالقة',
			'Osasuna'                     => 'أوساسونا',
			'Racing Santander'            => 'راسينغ سانتاندير',
			'Rayo Vallecano'              => 'رايو فايكانو',
			'Lazio'                       => 'لاتسيو',
			'Fiorentina'                  => 'فيورنتينا',
			'Atalanta'                    => 'أتالانتا',
			'Bologna'                     => 'بولونيا',
			'Cagliari'                    => 'كالياري',
			'Como'                        => 'كومو',
			'Frosinone'                   => 'فروزينوني',
			'Genoa'                       => 'جنوى',
			'Lecce'                       => 'ليتشي',
			'Monza'                       => 'مونزا',
			'Parma'                       => 'بارما',
			'Sassuolo'                    => 'ساسولو',
			'Torino'                      => 'تورينو',
			'Udinese'                     => 'أودينيزي',
			'Venezia'                     => 'فينيسيا',
			'Eintracht Frankfurt'         => 'آينتراخت فرانكفورت',
			'Union Berlin'                => 'يونيون برلين',
			'Schalke 04'                  => 'شالكه',
			'Hamburger SV'                => 'هامبورغ',
			'Augsburg'                    => 'أوغسبورغ',
			'Werder Bremen'               => 'فيردر بريمن',
			'Freiburg'                    => 'فرايبورغ',
			'Hoffenheim'                  => 'هوفنهايم',
			'FC Koln'                     => 'كولن',
			'Mainz 05'                    => 'ماينز',
			'Borussia Monchengladbach'    => 'بوروسيا مونشنغلادباخ',
			'Paderborn'                   => 'بادربورن',
			'Elversberg'                  => 'إلفرسبرغ',
			'VfB Stuttgart'               => 'شتوتغارت',
			'Angers'                      => 'أنجيه',
			'Auxerre'                     => 'أوكسير',
			'Brest'                       => 'بريست',
			'Le Havre'                    => 'لوهافر',
			'Lens'                        => 'لانس',
			'Le Mans'                     => 'لومان',
			'Lille'                       => 'ليل',
			'Lorient'                     => 'لوريان',
			'Nice'                        => 'نيس',
			'Paris FC'                    => 'باريس إف سي',
			'Rennes'                      => 'رين',
			'Strasbourg'                  => 'ستراسبورغ',
			'Toulouse'                    => 'تولوز',
			'Troyes'                      => 'تروا',
			'Al-Qadsiah'                  => 'القادسية',
			'Al-Hazem'                    => 'الحزم',
			'Al-Ettifaq'                  => 'الاتفاق',
			'Al-Riyadh'                   => 'الرياض',
			'Al-Khaleej'                  => 'الخليج',
			'Diriyah'                     => 'الدرعية',
			'Al-Kholood'                  => 'الخلود',
			'Al-Fateh'                    => 'الفتح',
			'Al-Fayha'                    => 'الفيحاء',
			'Neom'                        => 'نيوم',
			'Abha'                        => 'أبها',
			'Al-Faisaly'                  => 'الفيصلي',
			'Wadi Degla'                  => 'وادي دجلة',
			'Petrojet'                    => 'بتروجت',
			'ENPPI'                       => 'إنبي',
			'ZED FC'                      => 'زد',
			'Ghazl El Mahalla'            => 'غزل المحلة',
			'El Gouna'                    => 'الجونة',
			'Talaea El Gaish'             => 'طلائع الجيش',
			'National Bank of Egypt'      => 'البنك الأهلي',
			'Al Ittihad Alexandria'       => 'الاتحاد السكندري',
			'Al Mokawloon Al Arab'        => 'المقاولون العرب',
			'Modern Sport'                => 'مودرن سبورت',
			'Smouha'                      => 'سموحة',
			'Ceramica Cleopatra'          => 'سيراميكا كليوباترا',
			'Abu Qir Semad'               => 'أبو قير للأسمدة',
			'El Qanah'                    => 'القناة',
			'Asyut Petroleum'             => 'بترول أسيوط',
			'Maghreb Fez'                 => 'المغرب الفاسي',
			'COD Meknes'                  => 'النادي المكناسي',
			'Union Touarga'               => 'اتحاد تواركة',
			'FUS Rabat'                   => 'الفتح الرباطي',
			'Hassania Agadir'             => 'حسنية أكادير',
			'IR Tanger'                   => 'اتحاد طنجة',
			'Difaa El Jadida'             => 'الدفاع الحسني الجديدي',
			'RCA Zemamra'                 => 'نهضة الزمامرة',
			'Kawkab Marrakech'            => 'الكوكب المراكشي',
			'Moghreb Tetouan'             => 'المغرب التطواني',
			'Amal Tiznit'                 => 'أمل تيزنيت',
			'Widad Temara'                => 'وداد تمارة',
			'Al Ahly'                     => 'الأهلي',
			'Zamalek SC'                  => 'الزمالك',
			'Pyramids FC'                 => 'بيراميدز',
			'Al-Hilal Saudi FC'           => 'الهلال',
			'Al-Nassr'                    => 'النصر',
			'Al-Ittihad FC'               => 'الاتحاد',
			'Al-Ahli Jeddah'              => 'الأهلي السعودي',
			'Wydad AC'                    => 'الوداد الرياضي',
			'Raja Casablanca'             => 'الرجاء الرياضي',
			'Esperance de Tunis'          => 'الترجي التونسي',
			'Morocco'                     => 'المغرب',
			'Egypt'                       => 'مصر',
			'Saudi Arabia'                => 'السعودية',
			'Algeria'                     => 'الجزائر',
			'Tunisia'                     => 'تونس',
			'Qatar'                       => 'قطر',
			'Spain'                       => 'إسبانيا',
			'France'                      => 'فرنسا',
			'Germany'                     => 'ألمانيا',
			'Brazil'                      => 'البرازيل',
			'Argentina'                   => 'الأرجنتين',
			'Portugal'                    => 'البرتغال',
			'England'                     => 'إنجلترا',
			'Italy'                       => 'إيطاليا',
			'USA'                         => 'الولايات المتحدة',
			'United States'               => 'الولايات المتحدة',
			// Stadiums and cities.
			'Santiago Bernabéu'           => 'ملعب سانتياغو برنابيو',
			'Anfield'                     => 'ملعب أنفيلد',
			'Metropolitano'               => 'ملعب ميتروبوليتانو',
			'Kingdom Arena'               => 'المملكة أرينا',
			'Cairo International Stadium' => 'استاد القاهرة الدولي',
			'Madrid'                      => 'مدريد',
			'Riyadh'                      => 'الرياض',
			'Cairo'                       => 'القاهرة',
			// Demo players, coaches and referee.
			'Ahmed Salem'                 => 'أحمد سالم',
			'Youssef Ali'                 => 'يوسف العلي',
			'Mohamed Hassan'              => 'محمد حسن',
			'Omar Khaled'                 => 'عمر خالد',
			'Karim Nasser'                => 'كريم ناصر',
			'Sami Fouad'                  => 'سامي فؤاد',
			'Ziad Mansour'                => 'زياد منصور',
			'Majed Adel'                  => 'ماجد عادل',
			'Hisham Reda'                 => 'هشام رضا',
			'Tarek Gamal'                 => 'طارق جمال',
			'Nabil Fares'                 => 'نبيل فارس',
			'Rami Saeed'                  => 'رامي سعيد',
			'Basel Mourad'                => 'باسل مراد',
			'Walid Kamal'                 => 'وليد كمال',
			'Faisal Nader'                => 'فيصل نادر',
			'Gamal Issa'                  => 'جمال عيسى',
			'Hassan Idrissi'              => 'حسن الإدريسي',
			'Khalid Mansouri'             => 'خالد المنصوري',
			'Adel Ramzi'                  => 'عادل رمزي',
			'Newcastle'                   => 'نيوكاسل يونايتد',
			'Aston Villa'                 => 'أستون فيلا',
			'Brighton'                    => 'برايتون',
			'West Ham'                    => 'وست هام',
			'Real Sociedad'               => 'ريال سوسيداد',
			'Villarreal'                  => 'فياريال',
			'Real Betis'                  => 'ريال بيتيس',
			'Valencia'                    => 'فالنسيا',
			'Athletic Club'               => 'أتلتيك بلباو',
			'Girona'                      => 'جيرونا',
			'Al-Shabab'                   => 'الشباب',
			'Al-Taawoun'                  => 'التعاون',
			'Al Masry'                    => 'المصري البورسعيدي',
			'AS FAR'                      => 'الجيش الملكي',
			'RS Berkane'                  => 'نهضة بركان',
			'Denver Nuggets'              => 'دنفر ناغتس',
			'ATP Finals'                  => 'بطولة ATP الختامية',
			'Carlos Alcaraz'              => 'كارلوس ألكاراز',
			'Jannik Sinner'               => 'يانيك سينر',
			'Novak Djokovic'              => 'نوفاك ديوكوفيتش',
			'Alexander Zverev'            => 'ألكسندر زفيريف',
			'Los Angeles Lakers'          => 'لوس أنجلوس ليكرز',
			'Golden State Warriors'       => 'غولدن ستايت ووريورز',
			'Boston Celtics'              => 'بوسطن سلتيكس',
		);

		/**
		 * Filter the English => Arabic name dictionary.
		 *
		 * @param array<string,string> $names Dictionary.
		 */
		return (array) apply_filters( 'sportsarena_name_dictionary', $names );
	}

	/**
	 * Name in the site language: Arabic from the dictionary on Arabic sites,
	 * the provider's (English) name otherwise.
	 *
	 * @param string $name English name.
	 * @return string
	 */
	public static function localize( $name ) {
		if ( ! self::is_arabic() ) {
			return $name;
		}
		$dictionary = self::dictionary();
		return $dictionary[ $name ] ?? $name;
	}

	/**
	 * Arabic name if known (regardless of the site language).
	 *
	 * @param string $name English name.
	 * @return string
	 */
	public static function arabic( $name ) {
		$dictionary = self::dictionary();
		return $dictionary[ $name ] ?? $name;
	}
}
