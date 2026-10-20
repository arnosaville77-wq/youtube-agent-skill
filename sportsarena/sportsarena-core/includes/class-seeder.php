<?php
/**
 * Demo (seed) data so the site can be previewed before connecting an API.
 * Everything created here is flagged with _sa_demo = 1 and can be removed
 * from Tools > "حذف البيانات التجريبية" or `wp sportsarena unseed`.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

use SportsArena\Core\Data\Importer;
use SportsArena\Core\Data\Names;

defined( 'ABSPATH' ) || exit;

/**
 * Seeder.
 */
final class Seeder {

	/**
	 * Public crest library used for the demo logos (512x512 PNG).
	 */
	const LOGO_BASE = 'https://assets.football-logos.cc/logos/';

	const PROVIDER = 'demo';

	/**
	 * Competitions: key => array( name, country, sport, priority, teams[] ).
	 *
	 * @return array<string,array>
	 */
	private static function competitions() {
		return array(
			'ucl'    => array( 'UEFA Champions League', 'World', 'football', 1, array( 'Real Madrid', 'Manchester City', 'Bayern Munich', 'Paris Saint Germain', 'Inter', 'Arsenal' ) ),
			'pl'     => array( 'Premier League', 'England', 'football', 2, array( 'Liverpool', 'Arsenal', 'Manchester City', 'Chelsea', 'Manchester United', 'Tottenham', 'Newcastle', 'Aston Villa', 'Brighton', 'Everton', 'Bournemouth', 'Brentford', 'Crystal Palace', 'Fulham', 'Nottingham Forest', 'Leeds United', 'Sunderland', 'Coventry City', 'Ipswich Town', 'Hull City' ) ),
			'laliga' => array( 'La Liga', 'Spain', 'football', 3, array( 'Real Madrid', 'Barcelona', 'Atletico Madrid', 'Sevilla', 'Real Sociedad', 'Villarreal', 'Real Betis', 'Valencia', 'Athletic Club', 'Celta Vigo', 'Osasuna', 'Getafe', 'Rayo Vallecano', 'Espanyol', 'Alaves', 'Elche', 'Levante', 'Malaga', 'Racing Santander', 'Deportivo La Coruna' ) ),
			'spl'    => array( 'Pro League', 'Saudi Arabia', 'football', 4, array( 'Al-Hilal Saudi FC', 'Al-Nassr', 'Al-Ittihad FC', 'Al-Ahli Jeddah', 'Al-Shabab', 'Al-Taawoun', 'Al-Qadsiah', 'Al-Ettifaq', 'Al-Fateh', 'Al-Khaleej', 'Al-Fayha', 'Al-Riyadh', 'Al-Kholood', 'Al-Hazem', 'Neom', 'Diriyah', 'Abha', 'Al-Faisaly' ) ),
			'egy'    => array( 'Premier League', 'Egypt', 'football', 5, array( 'Al Ahly', 'Zamalek SC', 'Pyramids FC', 'Al Masry', 'Ceramica Cleopatra', 'ZED FC', 'Smouha', 'ENPPI', 'National Bank of Egypt', 'Al Ittihad Alexandria', 'Al Mokawloon Al Arab', 'Modern Sport', 'El Gouna', 'Petrojet', 'Talaea El Gaish', 'Ghazl El Mahalla', 'Wadi Degla', 'El Qanah', 'Abu Qir Semad', 'Asyut Petroleum' ) ),
			'botola' => array( 'Botola Pro', 'Morocco', 'football', 6, array( 'Wydad AC', 'Raja Casablanca', 'AS FAR', 'RS Berkane', 'FUS Rabat', 'Maghreb Fez', 'Hassania Agadir', 'IR Tanger', 'Union Touarga', 'COD Meknes', 'Difaa El Jadida', 'RCA Zemamra', 'Kawkab Marrakech', 'Moghreb Tetouan', 'Amal Tiznit', 'Widad Temara' ) ),
			'ligue1' => array( 'Ligue 1', 'France', 'football', 7, array( 'Paris Saint Germain', 'Marseille', 'Monaco', 'Lyon', 'Lille', 'Nice', 'Lens', 'Rennes', 'Strasbourg', 'Brest', 'Toulouse', 'Auxerre', 'Angers', 'Le Havre', 'Lorient', 'Paris FC', 'Le Mans', 'Troyes' ) ),
			'seriea' => array( 'Serie A', 'Italy', 'football', 8, array( 'Inter', 'Napoli', 'Juventus', 'AC Milan', 'Atalanta', 'AS Roma', 'Lazio', 'Fiorentina', 'Bologna', 'Torino', 'Como', 'Udinese', 'Genoa', 'Cagliari', 'Parma', 'Lecce', 'Sassuolo', 'Venezia', 'Monza', 'Frosinone' ) ),
			'bund'   => array( 'Bundesliga', 'Germany', 'football', 9, array( 'Bayern Munich', 'Bayer Leverkusen', 'Borussia Dortmund', 'RB Leipzig', 'Eintracht Frankfurt', 'VfB Stuttgart', 'Freiburg', 'Werder Bremen', 'Borussia Monchengladbach', 'Hoffenheim', 'Mainz 05', 'Union Berlin', 'Augsburg', 'FC Koln', 'Hamburger SV', 'Schalke 04', 'Paderborn', 'Elversberg' ) ),
			'nba'    => array( 'NBA', 'USA', 'basketball', 10, array( 'Los Angeles Lakers', 'Boston Celtics', 'Golden State Warriors', 'Denver Nuggets' ) ),
			'atp'    => array( 'ATP Finals', 'World', 'tennis', 12, array( 'Carlos Alcaraz', 'Jannik Sinner', 'Novak Djokovic', 'Alexander Zverev' ) ),
		);
	}

	/**
	 * Real country of demo teams / athletes (competition country otherwise).
	 *
	 * @param string $name     Team name.
	 * @param string $fallback Competition country.
	 * @return string
	 */
	private static function team_country( $name, $fallback ) {
		$map = array(
			'Real Madrid'         => 'Spain',
			'Manchester City'     => 'England',
			'Bayern Munich'       => 'Germany',
			'Paris Saint Germain' => 'France',
			'Inter'               => 'Italy',
			'Arsenal'             => 'England',
			'Carlos Alcaraz'      => 'Spain',
			'Jannik Sinner'       => 'Italy',
			'Novak Djokovic'      => 'Serbia',
			'Alexander Zverev'    => 'Germany',
		);
		return $map[ $name ] ?? $fallback;
	}

	/**
	 * Logo URL of a demo team: the football-logos.cc crest for football clubs,
	 * the API-Sports logo for NBA teams. Shown only when the site enables
	 * provider logos; real data uses the logos sent by the data provider.
	 *
	 * @param string $name Team name.
	 * @return string
	 */
	private static function team_logo( $name ) {
		$football   = array(
			'Liverpool'                => 'england/512x512/liverpool.bc7f4063.png',
			'Arsenal'                  => 'england/512x512/arsenal.02d595b0.png',
			'Manchester City'          => 'england/512x512/manchester-city.ef1fe757.png',
			'Chelsea'                  => 'england/512x512/chelsea.340855a2.png',
			'Manchester United'        => 'england/512x512/manchester-united.92071bee.png',
			'Tottenham'                => 'england/512x512/tottenham.e9f48315.png',
			'Newcastle'                => 'england/512x512/newcastle.9d4dbaa7.png',
			'Aston Villa'              => 'england/512x512/aston-villa.ac06ff1f.png',
			'Brighton'                 => 'england/512x512/brighton.b97367b5.png',
			'Everton'                  => 'england/512x512/everton.e756feed.png',
			'Bournemouth'              => 'england/512x512/bournemouth.2205e81a.png',
			'Brentford'                => 'england/512x512/brentford.b03c55b5.png',
			'Crystal Palace'           => 'england/512x512/crystal-palace.feda4790.png',
			'Fulham'                   => 'england/512x512/fulham.a93cda89.png',
			'Nottingham Forest'        => 'england/512x512/nottingham-forest.9a083644.png',
			'Leeds United'             => 'england/512x512/leeds-united.ce857129.png',
			'Sunderland'               => 'england/512x512/sunderland.7a86e5ee.png',
			'Coventry City'            => 'england/512x512/coventry-city.7985d41e.png',
			'Ipswich Town'             => 'england/512x512/ipswich.ccd782fd.png',
			'Hull City'                => 'england/512x512/hull-city.423e44b7.png',
			'Real Madrid'              => 'spain/512x512/real-madrid.c97a476c.png',
			'Barcelona'                => 'spain/512x512/barcelona.9ee7ed85.png',
			'Atletico Madrid'          => 'spain/512x512/atletico-madrid.0e6714cc.png',
			'Sevilla'                  => 'spain/512x512/sevilla.19d97463.png',
			'Real Sociedad'            => 'spain/512x512/real-sociedad.1fc4678b.png',
			'Villarreal'               => 'spain/512x512/villarreal.dca647c8.png',
			'Real Betis'               => 'spain/512x512/real-betis.ff95aa03.png',
			'Valencia'                 => 'spain/512x512/valencia.e7b59fb6.png',
			'Athletic Club'            => 'spain/512x512/athletic-club.7902b314.png',
			'Celta Vigo'               => 'spain/512x512/celta.6e3dfd7a.png',
			'Osasuna'                  => 'spain/512x512/osasuna.44c61200.png',
			'Getafe'                   => 'spain/512x512/getafe.8b79883e.png',
			'Rayo Vallecano'           => 'spain/512x512/rayo-vallecano.81658ded.png',
			'Espanyol'                 => 'spain/512x512/espanyol.4b49196a.png',
			'Alaves'                   => 'spain/512x512/deportivo.3159aed6.png',
			'Elche'                    => 'spain/512x512/elche.544fba10.png',
			'Levante'                  => 'spain/512x512/levante.1732f29f.png',
			'Malaga'                   => 'spain/512x512/malaga.b2a849e1.png',
			'Racing Santander'         => 'spain/512x512/racing.0dcda955.png',
			'Deportivo La Coruna'      => 'spain/512x512/deportivo-la-coruna.2a147b0c.png',
			'Inter'                    => 'italy/512x512/inter.ba4c3469.png',
			'Napoli'                   => 'italy/512x512/napoli.cbf9f0a6.png',
			'Juventus'                 => 'italy/512x512/juventus.4efced70.png',
			'AC Milan'                 => 'italy/512x512/milan.73b83ace.png',
			'Atalanta'                 => 'italy/512x512/atalanta.941be90e.png',
			'AS Roma'                  => 'italy/512x512/roma.8dfa8968.png',
			'Lazio'                    => 'italy/512x512/lazio.db9df500.png',
			'Fiorentina'               => 'italy/512x512/fiorentina.cae55547.png',
			'Bologna'                  => 'italy/512x512/bologna.a91a07cf.png',
			'Torino'                   => 'italy/512x512/torino.4a08b344.png',
			'Como'                     => 'italy/512x512/como-1907.a4178b8a.png',
			'Udinese'                  => 'italy/512x512/udinese.599d202b.png',
			'Genoa'                    => 'italy/512x512/genoa.521ea31d.png',
			'Cagliari'                 => 'italy/512x512/cagliari.8ff1c9e6.png',
			'Parma'                    => 'italy/512x512/parma.cdd09d90.png',
			'Lecce'                    => 'italy/512x512/lecce.7d6b74f5.png',
			'Sassuolo'                 => 'italy/512x512/sassuolo.b1b99656.png',
			'Venezia'                  => 'italy/512x512/venezia.c127a0a3.png',
			'Monza'                    => 'italy/512x512/monza.1a8203de.png',
			'Frosinone'                => 'italy/512x512/frosinone.7c7fa08c.png',
			'Bayern Munich'            => 'germany/512x512/bayern-munchen.8eda8ecc.png',
			'Bayer Leverkusen'         => 'germany/512x512/bayer-leverkusen.8e88c731.png',
			'Borussia Dortmund'        => 'germany/512x512/borussia-dortmund.ebd95525.png',
			'RB Leipzig'               => 'germany/512x512/rb-leipzig.5a21bcbd.png',
			'Eintracht Frankfurt'      => 'germany/512x512/eintracht-frankfurt.422e036d.png',
			'VfB Stuttgart'            => 'germany/512x512/vfb-stuttgart.67491c8f.png',
			'Freiburg'                 => 'germany/512x512/freiburg.40e62d41.png',
			'Werder Bremen'            => 'germany/512x512/werder-bremen.7dbfe857.png',
			'Borussia Monchengladbach' => 'germany/512x512/borussia-monchengladbach.452424e7.png',
			'Hoffenheim'               => 'germany/512x512/hoffenheim.73bc855e.png',
			'Mainz 05'                 => 'germany/512x512/mainz-05.a086fb18.png',
			'Union Berlin'             => 'germany/512x512/union-berlin.484bbfaa.png',
			'Augsburg'                 => 'germany/512x512/augsburg.75361590.png',
			'FC Koln'                  => 'germany/512x512/koln.4221251d.png',
			'Hamburger SV'             => 'germany/512x512/hamburger-sv.6a4c40a6.png',
			'Schalke 04'               => 'germany/512x512/schalke-04.54ee2b84.png',
			'Paderborn'                => 'germany/512x512/paderborn.7a64d8e2.png',
			'Elversberg'               => 'germany/512x512/sv-elversberg.f52c2cf6.png',
			'Paris Saint Germain'      => 'france/512x512/paris-saint-germain.5329be3c.png',
			'Marseille'                => 'france/512x512/marseille.f89278df.png',
			'Monaco'                   => 'france/512x512/as-monaco.61229e2f.png',
			'Lyon'                     => 'france/512x512/lyon.c7612bb5.png',
			'Lille'                    => 'france/512x512/lille.71772c6d.png',
			'Nice'                     => 'france/512x512/nice.be285080.png',
			'Lens'                     => 'france/512x512/rc-lens.5370d47c.png',
			'Rennes'                   => 'france/512x512/rennes.a36b45c6.png',
			'Strasbourg'               => 'france/512x512/rc-strasbourg-alsace.58bd7116.png',
			'Brest'                    => 'france/512x512/brest.263bb08a.png',
			'Toulouse'                 => 'france/512x512/toulouse.b40bb3e2.png',
			'Auxerre'                  => 'france/512x512/auxerre.e475acaa.png',
			'Angers'                   => 'france/512x512/angers.bb65fb9c.png',
			'Le Havre'                 => 'france/512x512/le-havre-ac.1950fe12.png',
			'Lorient'                  => 'france/512x512/lorient.7dde32cc.png',
			'Paris FC'                 => 'france/512x512/paris-fc.63e29830.png',
			'Le Mans'                  => 'france/512x512/le-mans.103d37a2.png',
			'Troyes'                   => 'france/512x512/troyes.04708692.png',
			'Al-Hilal Saudi FC'        => 'saudi-arabia/512x512/al-hilal.b6fdc2c0.png',
			'Al-Nassr'                 => 'saudi-arabia/512x512/al-nassr.1de248ec.png',
			'Al-Ittihad FC'            => 'saudi-arabia/512x512/al-ittihad.5f9bf4f3.png',
			'Al-Ahli Jeddah'           => 'saudi-arabia/512x512/al-ahli.9f39d0c0.png',
			'Al-Shabab'                => 'saudi-arabia/512x512/al-shabab.89785fab.png',
			'Al-Taawoun'               => 'saudi-arabia/512x512/al-taawoun.f0fed438.png',
			'Al-Qadsiah'               => 'saudi-arabia/512x512/al-qadsiah.bebce4f2.png',
			'Al-Ettifaq'               => 'saudi-arabia/512x512/al-ettifaq.359bd346.png',
			'Al-Fateh'                 => 'saudi-arabia/512x512/al-fateh.0371bf14.png',
			'Al-Khaleej'               => 'saudi-arabia/512x512/al-khaleej.4c2a4215.png',
			'Al-Fayha'                 => 'saudi-arabia/512x512/al-fayha.f562bfb8.png',
			'Al-Riyadh'                => 'saudi-arabia/512x512/al-riyadh.cb677893.png',
			'Al-Kholood'               => 'saudi-arabia/512x512/al-kholood.2a7655e0.png',
			'Al-Hazem'                 => 'saudi-arabia/512x512/al-hazem.f605867c.png',
			'Neom'                     => 'saudi-arabia/512x512/neom.b727e665.png',
			'Diriyah'                  => 'saudi-arabia/512x512/diriyah-club.58bc9fa5.png',
			'Abha'                     => 'saudi-arabia/512x512/abha.92247913.png',
			'Al-Faisaly'               => 'saudi-arabia/512x512/al-faisaly-fc.0e97f2c3.png',
			'Al Ahly'                  => 'egypt/512x512/al-ahly.807381aa.png',
			'Zamalek SC'               => 'egypt/512x512/zamalek.4fde1095.png',
			'Pyramids FC'              => 'egypt/512x512/pyramids.ca754973.png',
			'Al Masry'                 => 'egypt/512x512/al-masry.2a53269a.png',
			'Ceramica Cleopatra'       => 'egypt/512x512/ceramica-cleopatra.7e6fd091.png',
			'ZED FC'                   => 'egypt/512x512/zed-fc.6cd3fcc1.png',
			'Smouha'                   => 'egypt/512x512/smouha.77b26b9d.png',
			'ENPPI'                    => 'egypt/512x512/enppi.d59a88f3.png',
			'National Bank of Egypt'   => 'egypt/512x512/national-bank.470c646e.png',
			'Al Ittihad Alexandria'    => 'egypt/512x512/al-ittihad-alexandria.0010f4a2.png',
			'Al Mokawloon Al Arab'     => 'egypt/512x512/al-mokawloon-al-arab.5ec478d0.png',
			'Modern Sport'             => 'egypt/512x512/modern-sport.42a3361c.png',
			'El Gouna'                 => 'egypt/512x512/el-gouna.d3d34663.png',
			'Petrojet'                 => 'egypt/512x512/petrojet.f2878056.png',
			'Talaea El Gaish'          => 'egypt/512x512/talaea-el-gaish.0ff1284e.png',
			'Ghazl El Mahalla'         => 'egypt/512x512/ghazl-el-mahalla.df5e51d6.png',
			'Wadi Degla'               => 'egypt/512x512/wadi-degla.a3e27add.png',
			'El Qanah'                 => 'egypt/512x512/el-qanah.fcaec875.png',
			'Abu Qir Semad'            => 'egypt/512x512/abu-qir-semad.14740956.png',
			'Asyut Petroleum'          => 'egypt/512x512/asyut-petroleum.1314c5e1.png',
			'Wydad AC'                 => 'morocco/512x512/wydad-ac.3c1b30d8.png',
			'Raja Casablanca'          => 'morocco/512x512/raja-ca.14b63b39.png',
			'AS FAR'                   => 'morocco/512x512/as-far.e4696dc0.png',
			'RS Berkane'               => 'morocco/512x512/rs-berkane.34a47ba3.png',
			'FUS Rabat'                => 'morocco/512x512/fus-rabat.6d8f3539.png',
			'Maghreb Fez'              => 'morocco/512x512/maghreb-of-fez.af5687d3.png',
			'Hassania Agadir'          => 'morocco/512x512/hassania-agadir.13961d97.png',
			'IR Tanger'                => 'morocco/512x512/ir-tanger.497bede1.png',
			'Union Touarga'            => 'morocco/512x512/uts-rabat.848564cc.png',
			'COD Meknes'               => 'morocco/512x512/cod-meknes.ee585029.png',
			'Difaa El Jadida'          => 'morocco/512x512/dh-jadida.7f2f70c6.png',
			'RCA Zemamra'              => 'morocco/512x512/rca-zemamra.95a2499c.png',
			'Kawkab Marrakech'         => 'morocco/512x512/kac-marrakech.dbe0b246.png',
			'Moghreb Tetouan'          => 'morocco/512x512/moghreb-tetouan.c99c30c7.png',
			'Amal Tiznit'              => 'morocco/512x512/us-amal-tiznit.8510a8b5.png',
			'Widad Temara'             => 'morocco/512x512/widad-temara.4c5422cd.png',
		);
		$basketball = array(
			'Boston Celtics'        => 133,
			'Denver Nuggets'        => 139,
			'Golden State Warriors' => 141,
			'Los Angeles Lakers'    => 145,
		);
		if ( isset( $football[ $name ] ) ) {
			return self::LOGO_BASE . $football[ $name ];
		}
		if ( isset( $basketball[ $name ] ) ) {
			return 'https://media.api-sports.io/basketball/teams/' . $basketball[ $name ] . '.png';
		}
		return '';
	}

	/**
	 * Logo URL of a demo competition.
	 *
	 * @param string $key Competition key.
	 * @return string
	 */
	private static function competition_logo( $key ) {
		$paths = array(
			'ucl'    => 'tournaments/512x512/uefa-champions-league.effc906c.png',
			'pl'     => 'england/512x512/english-premier-league.b597f797.png',
			'laliga' => 'spain/512x512/la-liga.0ea0b0c5.png',
			'spl'    => 'saudi-arabia/512x512/saudi-professional-league.59d5c8af.png',
			'egy'    => 'egypt/512x512/egyptian-premier-league.b2e98144.png',
			'botola' => 'morocco/512x512/botola-pro.b4277f0c.png',
			'ligue1' => 'france/512x512/ligue-1.bcb13eb0.png',
			'seriea' => 'italy/512x512/serie-a.1289b16a.png',
			'bund'   => 'germany/512x512/bundesliga.24d9c6f9.png',
		);
		if ( isset( $paths[ $key ] ) ) {
			return self::LOGO_BASE . $paths[ $key ];
		}
		return 'nba' === $key ? 'https://media.api-sports.io/basketball/leagues/12.png' : '';
	}

	/**
	 * Fictional player names for demo lineups and scorers, in the site's script.
	 *
	 * @return string[]
	 */
	private static function player_names() {
		if ( Names::is_arabic() ) {
			return array( 'أحمد سالم', 'يوسف العلي', 'محمد حسن', 'عمر خالد', 'كريم ناصر', 'سامي فؤاد', 'زياد منصور', 'ماجد عادل', 'هشام رضا', 'طارق جمال', 'نبيل فارس', 'رامي سعيد', 'باسل مراد', 'وليد كمال', 'فيصل نادر', 'جمال عيسى' );
		}
		return array( 'Ahmed Salem', 'Youssef Ali', 'Mohamed Hassan', 'Omar Khaled', 'Karim Nasser', 'Sami Fouad', 'Ziad Mansour', 'Majed Adel', 'Hisham Reda', 'Tarek Gamal', 'Nabil Fares', 'Rami Saeed', 'Basel Mourad', 'Walid Kamal', 'Faisal Nader', 'Gamal Issa' );
	}

	/**
	 * Create the demo data (idempotent: existing demo posts are reused).
	 *
	 * @return int Number of matches created/updated.
	 */
	public static function seed() {
		$importer = new Importer( self::PROVIDER );
		$comps    = self::competitions();
		$today    = new \DateTimeImmutable( 'today', wp_timezone() );
		$now      = time();
		$matches  = array();
		$n        = 0;

		$make = static function ( $comp_key, $home, $away, $kickoff, $round, array $extra = array() ) use ( $comps, &$n ) {
			$c = $comps[ $comp_key ];
			++$n;
			return array_merge(
				array(
					'external_id' => 'demo-' . $comp_key . '-' . sanitize_title( $home . '-' . $away ) . '-' . gmdate( 'Ymd', $kickoff ),
					'sport'       => $c[2],
					'kickoff'     => $kickoff,
					'status'      => 'scheduled',
					'status_raw'  => 'NS',
					'elapsed'     => null,
					'home'        => array(
						'external_id' => 'demo-team-' . sanitize_title( $home ),
						'name'        => $home,
						'logo'        => self::team_logo( $home ),
						'country'     => self::team_country( $home, $c[1] ),
					),
					'away'        => array(
						'external_id' => 'demo-team-' . sanitize_title( $away ),
						'name'        => $away,
						'logo'        => self::team_logo( $away ),
						'country'     => self::team_country( $away, $c[1] ),
					),
					'score'       => array(
						'home' => null,
						'away' => null,
					),
					'competition' => array(
						'external_id' => 'demo-comp-' . $comp_key,
						'name'        => $c[0],
						'country'     => $c[1],
						'logo'        => self::competition_logo( $comp_key ),
						'season'      => '2026',
						'round'       => $round,
						'type'        => 'league',
					),
					'venue'       => '',
					'city'        => '',
					'referee'     => '',
					'events'      => array(),
					'lineups'     => array(),
					'statistics'  => array(),
				),
				$extra
			);
		};

		$at = static function ( \DateTimeImmutable $day, $time ) {
			return $day->modify( $time )->getTimestamp();
		};

		// Status relative to "now" for fixed local kickoff times.
		$state = static function ( $kickoff, $home_goals, $away_goals ) use ( $now ) {
			$minutes = (int) floor( ( $now - $kickoff ) / 60 );
			if ( $minutes < 0 ) {
				return array();
			}
			if ( $minutes > 110 ) {
				return array(
					'status'     => 'finished',
					'status_raw' => 'FT',
					'score'      => array(
						'home' => $home_goals,
						'away' => $away_goals,
					),
				);
			}
			$elapsed = $minutes > 60 ? $minutes - 15 : min( 45, $minutes );
			$ht      = $minutes > 45 && $minutes <= 60;
			return array(
				'status'     => $ht ? 'halftime' : 'live',
				'status_raw' => $ht ? 'HT' : ( $elapsed > 45 ? '2H' : '1H' ),
				'elapsed'    => $ht ? 45 : $elapsed,
				'score'      => array(
					'home' => (int) min( $home_goals, floor( $elapsed / 40 ) ),
					'away' => (int) min( $away_goals, floor( $elapsed / 55 ) ),
				),
			);
		};

		$players = self::player_names();
		$events  = static function ( array $goals_home, array $goals_away ) use ( $players ) {
			$list = array();
			foreach ( $goals_home as $i => $minute ) {
				$list[] = array(
					'minute' => (string) $minute,
					'team'   => 'home',
					'type'   => 'goal',
					'detail' => 'Normal Goal',
					'player' => $players[ $i % count( $players ) ],
					'assist' => $players[ ( $i + 3 ) % count( $players ) ],
				);
			}
			foreach ( $goals_away as $i => $minute ) {
				$list[] = array(
					'minute' => (string) $minute,
					'team'   => 'away',
					'type'   => 'goal',
					'detail' => 'Normal Goal',
					'player' => $players[ ( $i + 8 ) % count( $players ) ],
					'assist' => '',
				);
			}
			$list[] = array(
				'minute' => '38',
				'team'   => 'away',
				'type'   => 'yellow',
				'detail' => 'Yellow Card',
				'player' => $players[10],
				'assist' => '',
			);
			usort(
				$list,
				static function ( $a, $b ) {
					return (int) $a['minute'] <=> (int) $b['minute'];
				}
			);
			return $list;
		};

		$lineup = static function ( $side, $offset ) use ( $players ) {
			$start = array();
			for ( $i = 0; $i < 11; $i++ ) {
				$start[] = ( $i + 1 ) . ' ' . $players[ ( $i + $offset ) % count( $players ) ];
			}
			return array(
				'team'      => $side,
				'formation' => 'home' === $side ? '4-3-3' : '4-2-3-1',
				'coach'     => 'home' === $side ? __( 'مدرب الفريق المضيف', 'sportsarena-core' ) : __( 'مدرب الفريق الضيف', 'sportsarena-core' ),
				'start'     => $start,
				'subs'      => array( '12 ' . $players[ ( 11 + $offset ) % 16 ], '14 ' . $players[ ( 12 + $offset ) % 16 ], '20 ' . $players[ ( 13 + $offset ) % 16 ] ),
			);
		};

		$stats = array(
			array(
				'type' => 'Ball Possession',
				'home' => '58%',
				'away' => '42%',
			),
			array(
				'type' => 'Total Shots',
				'home' => '14',
				'away' => '9',
			),
			array(
				'type' => 'Shots on Goal',
				'home' => '6',
				'away' => '3',
			),
			array(
				'type' => 'Corner Kicks',
				'home' => '7',
				'away' => '4',
			),
			array(
				'type' => 'Fouls',
				'home' => '11',
				'away' => '14',
			),
			array(
				'type' => 'Yellow Cards',
				'home' => '1',
				'away' => '2',
			),
		);

		$yesterday = $today->modify( '-1 day' );
		$tomorrow  = $today->modify( '+1 day' );

		// Yesterday: finished results.
		$matches[] = $make(
			'pl',
			'Liverpool',
			'Chelsea',
			$at( $yesterday, '18:30' ),
			'Regular Season - 9',
			array(
				'status'     => 'finished',
				'status_raw' => 'FT',
				'score'      => array(
					'home' => 2,
					'away' => 1,
				),
				'events'     => $events( array( 23, 71 ), array( 55 ) ),
				'venue'      => 'Anfield',
				'city'       => 'Liverpool',
			)
		);
		$matches[] = $make(
			'laliga',
			'Atletico Madrid',
			'Sevilla',
			$at( $yesterday, '21:00' ),
			'Regular Season - 10',
			array(
				'status'     => 'finished',
				'status_raw' => 'FT',
				'score'      => array(
					'home' => 1,
					'away' => 1,
				),
				'events'     => $events( array( 12 ), array( 80 ) ),
				'venue'      => 'Metropolitano',
				'city'       => 'Madrid',
			)
		);
		$matches[] = $make(
			'spl',
			'Al-Hilal Saudi FC',
			'Al-Ittihad FC',
			$at( $yesterday, '20:00' ),
			'Regular Season - 7',
			array(
				'status'     => 'finished',
				'status_raw' => 'FT',
				'score'      => array(
					'home' => 3,
					'away' => 0,
				),
				'events'     => $events( array( 9, 44, 88 ), array() ),
				'venue'      => 'Kingdom Arena',
				'city'       => 'Riyadh',
			)
		);
		$matches[] = $make(
			'egy',
			'Al Ahly',
			'Pyramids FC',
			$at( $yesterday, '19:00' ),
			'Regular Season - 8',
			array(
				'status'     => 'finished',
				'status_raw' => 'FT',
				'score'      => array(
					'home' => 2,
					'away' => 2,
				),
				'events'     => $events( array( 30, 66 ), array( 41, 90 ) ),
				'venue'      => 'Cairo International Stadium',
				'city'       => 'Cairo',
			)
		);

		// Today: fixed local times, status depends on the current time.
		$featured  = $at( $today, '22:00' );
		$today_set = array(
			array( 'ucl', 'Manchester City', 'Inter', '13:30', 2, 0 ),
			array( 'pl', 'Arsenal', 'Manchester United', '15:00', 1, 1 ),
			array( 'pl', 'Tottenham', 'Newcastle', '17:30', 0, 2 ),
			array( 'spl', 'Al-Nassr', 'Al-Ahli Jeddah', '18:00', 2, 1 ),
			array( 'egy', 'Zamalek SC', 'Al Masry', '19:00', 1, 0 ),
			array( 'botola', 'Wydad AC', 'Raja Casablanca', '20:00', 1, 1 ),
			array( 'ucl', 'Bayern Munich', 'Paris Saint Germain', '21:00', 3, 2 ),
			array( 'ligue1', 'Marseille', 'Lyon', '19:45', 2, 2 ),
			array( 'seriea', 'Juventus', 'AC Milan', '18:45', 1, 0 ),
			array( 'bund', 'Borussia Dortmund', 'RB Leipzig', '16:30', 3, 1 ),
		);
		foreach ( $today_set as $row ) {
			$kickoff   = $at( $today, $row[3] );
			$matches[] = $make( $row[0], $row[1], $row[2], $kickoff, 'Regular Season - 10', $state( $kickoff, $row[4], $row[5] ) );
		}
		// Featured clásico with lineups and statistics.
		$matches[] = $make(
			'laliga',
			'Real Madrid',
			'Barcelona',
			$featured,
			'Regular Season - 10',
			array_merge(
				$state( $featured, 2, 1 ),
				array(
					'venue'      => 'Santiago Bernabéu',
					'city'       => 'Madrid',
					'referee'    => Names::is_arabic() ? 'حكم تجريبي' : 'Demo Referee',
					'lineups'    => array( $lineup( 'home', 0 ), $lineup( 'away', 5 ) ),
					'statistics' => $stats,
				)
			)
		);
		// A match that is always live (for the live ticker demo).
		$live_kickoff = $now - 52 * MINUTE_IN_SECONDS;
		$matches[]    = $make(
			'nba',
			'Los Angeles Lakers',
			'Boston Celtics',
			$live_kickoff,
			'Regular Season',
			array(
				'status'     => 'live',
				'status_raw' => 'Q3',
				'score'      => array(
					'home' => 78,
					'away' => 74,
				),
			)
		);
		$matches[]    = $make( 'atp', 'Carlos Alcaraz', 'Jannik Sinner', $at( $today, '16:00' ), 'Group Stage', $state( $at( $today, '16:00' ), 2, 1 ) );

		// Tomorrow.
		$tomorrow_set = array(
			array( 'ucl', 'Real Madrid', 'Arsenal', '22:00' ),
			array( 'pl', 'Aston Villa', 'Brighton', '16:00' ),
			array( 'laliga', 'Villarreal', 'Real Betis', '19:00' ),
			array( 'spl', 'Al-Shabab', 'Al-Taawoun', '17:00' ),
			array( 'nba', 'Golden State Warriors', 'Denver Nuggets', '04:30' ),
			array( 'ligue1', 'Monaco', 'Paris Saint Germain', '20:00' ),
			array( 'seriea', 'Napoli', 'Inter', '19:45' ),
			array( 'bund', 'Bayer Leverkusen', 'Bayern Munich', '18:30' ),
		);
		foreach ( $tomorrow_set as $row ) {
			$matches[] = $make( $row[0], $row[1], $row[2], $at( $tomorrow, $row[3] ), 'Regular Season - 11' );
		}

		// Older meetings for the head-to-head section.
		$meetings = array(
			array( 60, 'Barcelona', 'Real Madrid', 1, 2 ),
			array( 200, 'Real Madrid', 'Barcelona', 3, 1 ),
			array( 380, 'Barcelona', 'Real Madrid', 0, 0 ),
		);
		foreach ( $meetings as $meeting ) {
			$kickoff   = $at( $today->modify( '-' . $meeting[0] . ' days' ), '21:00' );
			$matches[] = $make(
				'laliga',
				$meeting[1],
				$meeting[2],
				$kickoff,
				'Regular Season',
				array(
					'status'     => 'finished',
					'status_raw' => 'FT',
					'score'      => array(
						'home' => $meeting[3],
						'away' => $meeting[4],
					),
				)
			);
		}

		$count = 0;
		foreach ( $matches as $match ) {
			$post_id = $importer->import_match( $match );
			if ( ! $post_id ) {
				continue;
			}
			++$count;
			update_post_meta( $post_id, '_sa_demo', 1 );
			if ( 'football' === $match['sport'] ) {
				update_post_meta( $post_id, '_sa_tv', __( 'beIN Sports (مثال)', 'sportsarena-core' ) );
			}
		}

		// Featured match preview text written "by the editor".
		$featured_id = $importer->find( Post_Types::MATCH, 'demo-laliga-real-madrid-barcelona-' . gmdate( 'Ymd', $featured ) );
		if ( $featured_id && '' === get_post_field( 'post_content', $featured_id ) ) {
			wp_update_post(
				array(
					'ID'           => $featured_id,
					'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html__( 'معاينة تجريبية: يستضيف ريال مدريد غريمه التقليدي برشلونة في قمة الجولة العاشرة من الدوري الإسباني، في مواجهة تحمل أهمية كبيرة في صراع الصدارة.', 'sportsarena-core' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>" . esc_html__( 'يدخل الفريقان المباراة بمعنويات مرتفعة بعد نتائج إيجابية في الجولات الأخيرة، ويُتوقع أن يعتمد كل مدرب على تشكيلته الأساسية. هذا النص مثال على قسم «المعاينة/التحليل» الذي يكتبه المحرر من لوحة التحكم.', 'sportsarena-core' ) . "</p>\n<!-- /wp:paragraph -->",
				)
			);
		}

		self::seed_tables( $importer, $comps );
		self::flag_demo_entities();
		self::seed_news( $importer );
		Repository::bump_version();

		Logger::info( 'seeder', sprintf( 'Demo data created: %d matches.', $count ) );
		return $count;
	}

	/**
	 * Standings and scorers for the main demo competitions.
	 *
	 * @param Importer $importer Importer.
	 * @param array    $comps    Competitions.
	 * @return void
	 */
	private static function seed_tables( Importer $importer, array $comps ) {
		$players = array_slice( self::player_names(), 8, 8 );
		foreach ( array( 'pl', 'laliga', 'spl', 'egy', 'botola', 'ligue1', 'seriea', 'bund' ) as $key ) {
			$teams = $comps[ $key ][4];
			$rows  = array();
			foreach ( $teams as $i => $name ) {
				// Points fall steadily from the leader to the last club.
				$played = 10;
				$target = (int) round( 26 - $i * 24 / max( 1, count( $teams ) - 1 ) );
				$win    = min( $played, intdiv( $target, 3 ) );
				$draw   = min( $played - $win, $target - 3 * $win );
				$lose   = $played - $win - $draw;
				$gf     = max( 3, 24 - $i );
				$ga     = 6 + $i;
				$rows[] = array(
					'group'         => '',
					'rank'          => $i + 1,
					'team'          => array(
						'external_id' => 'demo-team-' . sanitize_title( $name ),
						'name'        => $name,
						'logo'        => self::team_logo( $name ),
					),
					'played'        => $played,
					'win'           => $win,
					'draw'          => $draw,
					'lose'          => $lose,
					'goals_for'     => $gf,
					'goals_against' => $ga,
					'goal_diff'     => $gf - $ga,
					'points'        => $win * 3 + $draw,
					'form'          => substr( 'WWDLW', $i % 3, 5 ),
				);
				// Make sure every team in the table has a page: one past match per pair of clubs.
				if ( 1 === $i % 2 ) {
					continue;
				}
				$importer->import_match(
					array(
						'external_id' => 'demo-placeholder-' . $key . '-' . $i,
						'sport'       => 'football',
						'kickoff'     => time() - ( 20 + $i ) * DAY_IN_SECONDS,
						'status'      => 'finished',
						'status_raw'  => 'FT',
						'elapsed'     => null,
						'home'        => $rows[ $i ]['team'] + array( 'country' => $comps[ $key ][1] ),
						'away'        => array(
							'external_id' => 'demo-team-' . sanitize_title( $teams[ ( $i + 1 ) % count( $teams ) ] ),
							'name'        => $teams[ ( $i + 1 ) % count( $teams ) ],
							'logo'        => self::team_logo( $teams[ ( $i + 1 ) % count( $teams ) ] ),
							'country'     => $comps[ $key ][1],
						),
						'score'       => array(
							'home' => ( $i + 1 ) % 3,
							'away' => $i % 2,
						),
						'competition' => array(
							'external_id' => 'demo-comp-' . $key,
							'name'        => $comps[ $key ][0],
							'country'     => $comps[ $key ][1],
							'logo'        => self::competition_logo( $key ),
							'season'      => '2026',
							'round'       => 'Regular Season - ' . ( 9 - min( 8, $i ) ),
							'type'        => 'league',
						),
						'venue'       => '',
						'city'        => '',
						'referee'     => '',
						'events'      => array(),
						'lineups'     => array(),
						'statistics'  => array(),
					)
				);
			}
			$scorers = array();
			foreach ( $players as $i => $player ) {
				$scorers[] = array(
					'rank'    => $i + 1,
					'player'  => $player,
					'team'    => Names::localize( $teams[ $i % count( $teams ) ] ),
					'goals'   => 12 - $i,
					'assists' => 3 + ( $i % 4 ),
					'played'  => 10,
				);
			}
			$importer->save_tables( 'demo-comp-' . $key, '2026', $rows, $scorers );

			$comp_id = $importer->find( Post_Types::COMPETITION, 'demo-comp-' . $key );
			if ( $comp_id ) {
				update_post_meta( $comp_id, '_sa_priority', $comps[ $key ][3] );
				if ( '' === get_post_field( 'post_excerpt', $comp_id ) ) {
					wp_update_post(
						array(
							'ID'           => $comp_id,
							/* translators: %s: competition. */
							'post_content' => '<!-- wp:paragraph --><p>' . sprintf( esc_html__( 'صفحة %s: تابع جدول الترتيب ومواعيد المباريات ونتائجها وقائمة الهدافين. (محتوى تجريبي يمكن تعديله من لوحة التحكم.)', 'sportsarena-core' ), get_the_title( $comp_id ) ) . '</p><!-- /wp:paragraph -->',
						)
					);
				}
			}
		}
	}

	/**
	 * Flag every demo team / competition / match.
	 *
	 * @return void
	 */
	private static function flag_demo_entities() {
		$ids = get_posts(
			array(
				'post_type'      => array( Post_Types::MATCH, Post_Types::TEAM, Post_Types::COMPETITION ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_sa_provider', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- One-off seeding.
				'meta_value'     => self::PROVIDER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- One-off seeding.
			)
		);
		foreach ( $ids as $id ) {
			update_post_meta( $id, '_sa_demo', 1 );
		}
	}

	/**
	 * Demo news posts linked to teams / competitions.
	 *
	 * @param Importer $importer Importer.
	 * @return void
	 */
	private static function seed_news( Importer $importer ) {
		$sections = News_Sections::create();

		$news = array(
			array( __( 'ريال مدريد يستعد لقمة الكلاسيكو أمام برشلونة', 'sportsarena-core' ), array( 'Real Madrid', 'Barcelona' ), 'laliga', 'football-news' ),
			array( __( 'ليفربول يواصل انتصاراته ويعزز موقعه في صدارة الدوري الإنجليزي', 'sportsarena-core' ), array( 'Liverpool', 'Chelsea' ), 'pl', 'football-news' ),
			array( __( 'الهلال يكتسح الاتحاد بثلاثية نظيفة في دوري روشن', 'sportsarena-core' ), array( 'Al-Hilal Saudi FC', 'Al-Ittihad FC' ), 'spl', 'football-news' ),
			array( __( 'تعادل مثير بين الأهلي وبيراميدز في الدوري المصري', 'sportsarena-core' ), array( 'Al Ahly', 'Pyramids FC' ), 'egy', 'football-news' ),
			array( __( 'بايرن ميونخ وباريس سان جيرمان في مواجهة نارية بدوري الأبطال', 'sportsarena-core' ), array( 'Bayern Munich', 'Paris Saint Germain' ), 'ucl', 'football-news' ),
			array( __( 'ديربي الدار البيضاء: الوداد والرجاء في قمة البطولة المغربية', 'sportsarena-core' ), array( 'Wydad AC', 'Raja Casablanca' ), 'botola', 'football-news' ),
			array( __( 'ليكرز يتفوق على سلتيكس في قمة مثيرة بالدوري الأمريكي', 'sportsarena-core' ), array( 'Los Angeles Lakers', 'Boston Celtics' ), 'nba', 'basketball-news' ),
			array( __( 'ألكاراز وسينر في مواجهة جديدة بالبطولة الختامية', 'sportsarena-core' ), array( 'Carlos Alcaraz', 'Jannik Sinner' ), 'atp', 'tennis-news' ),
			array( __( 'الهلال يقترب من حسم صفقة جديدة في الميركاتو الشتوي', 'sportsarena-core' ), array( 'Al-Hilal Saudi FC', 'Al-Nassr' ), 'spl', 'transfers' ),
			array( __( 'تحليل: كيف يحسم خط الوسط مباريات الكلاسيكو؟', 'sportsarena-core' ), array( 'Real Madrid', 'Barcelona' ), 'laliga', 'analysis' ),
		);

		foreach ( $news as $i => $item ) {
			$existing = get_posts(
				array(
					'post_type'      => 'post',
					'title'          => $item[0],
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'post_status'    => 'any',
				)
			);
			if ( $existing ) {
				continue;
			}
			$teams      = array_map( array( Names::class, 'localize' ), $item[1] );
			$content    = '';
			$paragraphs = array();
			foreach (
				array(
					/* translators: 1: first team, 2: second team. */
					sprintf( __( 'خبر تجريبي: تتجه الأنظار إلى مواجهة %1$s و%2$s، حيث يسعى كل فريق إلى تحقيق نتيجة إيجابية تعزز حظوظه هذا الموسم.', 'sportsarena-core' ), $teams[0], $teams[1] ),
					/* translators: %s: team. */
					sprintf( __( 'وأكد الجهاز الفني لفريق %s جاهزية اللاعبين بعد سلسلة من الحصص التدريبية المكثفة، مع عودة بعض العناصر الأساسية من الإصابة.', 'sportsarena-core' ), $teams[0] ),
					/* translators: %s: team. */
					sprintf( __( 'في المقابل، يدخل %s اللقاء بطموح كبير، معتمداً على الانضباط الدفاعي والهجمات المرتدة السريعة.', 'sportsarena-core' ), $teams[1] ),
					__( 'ويرى المحللون أن خط الوسط سيكون مفتاح المباراة، وأن الفريق الأكثر استحواذاً على الكرة سيملك الأفضلية في الشوط الثاني.', 'sportsarena-core' ),
					__( 'هذا المحتوى تجريبي لعرض شكل صفحة الخبر، ويمكن حذفه من لوحة التحكم > SportsArena > الأدوات.', 'sportsarena-core' ),
				) as $paragraph
			) {
				$paragraphs[] = $paragraph;
			}
			if ( 'analysis' === $item[3] ) {
				// A longer article (shows the second in-article ad slot).
				$paragraphs[] = __( 'تكتيكياً، يميل الفريقان إلى الضغط العالي في الدقائق الأولى، ما يجعل الكرات الثانية والتحولات السريعة عاملاً حاسماً.', 'sportsarena-core' );
				$paragraphs[] = __( 'وتشير الأرقام إلى أن الفريق الذي يسجل أولاً في هذه المواجهة فاز في أغلب المباريات الأخيرة بينهما.', 'sportsarena-core' );
				$paragraphs[] = __( 'أما بدنياً، فقد يكون عمق دكة البدلاء هو الفارق في آخر عشرين دقيقة.', 'sportsarena-core' );
				$paragraphs[] = __( 'في المحصلة، تبقى التفاصيل الصغيرة وتركيز اللاعبين في الكرات الثابتة مفتاح الحسم.', 'sportsarena-core' );
			}
			foreach ( $paragraphs as $paragraph ) {
				$content .= "<!-- wp:paragraph -->\n<p>" . esc_html( $paragraph ) . "</p>\n<!-- /wp:paragraph -->\n\n";
			}
			$post_id = wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_title'    => $item[0],
					'post_content'  => $content,
					/* translators: 1: first team, 2: second team. */
					'post_excerpt'  => sprintf( __( 'آخر أخبار %1$s و%2$s قبل المواجهة المرتقبة.', 'sportsarena-core' ), $teams[0], $teams[1] ),
					'post_category' => ! empty( $sections[ $item[3] ] ) ? array( $sections[ $item[3] ] ) : array(),
					'post_date'     => wp_date( 'Y-m-d H:i:s', time() - ( $i * 3 + 1 ) * HOUR_IN_SECONDS ),
				)
			);
			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, '_sa_demo', 1 );
			// Category => sport sync ran on save (News_Sections::sync_terms).
			foreach ( $item[1] as $team_name ) {
				$team_id = $importer->find( Post_Types::TEAM, 'demo-team-' . sanitize_title( $team_name ) );
				if ( $team_id ) {
					add_post_meta( $post_id, '_sa_team_id', $team_id );
				}
			}
			$comp_id = $importer->find( Post_Types::COMPETITION, 'demo-comp-' . $item[2] );
			if ( $comp_id ) {
				add_post_meta( $post_id, '_sa_competition_id', $comp_id );
			}
		}
	}

	/**
	 * Whether demo content exists.
	 *
	 * @return bool
	 */
	public static function has_demo() {
		return (bool) get_posts(
			array(
				'post_type'      => array( 'post', Post_Types::MATCH, Post_Types::TEAM, Post_Types::COMPETITION ),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_sa_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Single lookup.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Single lookup.
			)
		);
	}

	/**
	 * Remove all demo content.
	 *
	 * @return int Deleted posts.
	 */
	public static function unseed() {
		$ids = get_posts(
			array(
				'post_type'      => array( 'post', Post_Types::MATCH, Post_Types::TEAM, Post_Types::COMPETITION ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_sa_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- One-off cleanup.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- One-off cleanup.
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
		Repository::bump_version();
		Logger::info( 'seeder', sprintf( 'Demo data removed: %d posts.', count( $ids ) ) );
		return count( $ids );
	}
}
