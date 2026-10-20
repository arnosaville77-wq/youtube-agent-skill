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

	const PROVIDER = 'demo';

	/**
	 * Competitions: key => array( name, country, sport, priority, teams[] ).
	 *
	 * @return array<string,array>
	 */
	private static function competitions() {
		return array(
			'ucl'    => array( 'UEFA Champions League', 'World', 'football', 1, array( 'Real Madrid', 'Manchester City', 'Bayern Munich', 'Paris Saint Germain', 'Inter', 'Arsenal' ) ),
			'pl'     => array( 'Premier League', 'England', 'football', 2, array( 'Liverpool', 'Arsenal', 'Manchester City', 'Chelsea', 'Manchester United', 'Tottenham', 'Newcastle', 'Aston Villa', 'Brighton', 'West Ham' ) ),
			'laliga' => array( 'La Liga', 'Spain', 'football', 3, array( 'Real Madrid', 'Barcelona', 'Atletico Madrid', 'Sevilla', 'Real Sociedad', 'Villarreal', 'Real Betis', 'Valencia', 'Athletic Club', 'Girona' ) ),
			'spl'    => array( 'Pro League', 'Saudi Arabia', 'football', 4, array( 'Al-Hilal Saudi FC', 'Al-Nassr', 'Al-Ittihad FC', 'Al-Ahli Jeddah', 'Al-Shabab', 'Al-Taawoun' ) ),
			'egy'    => array( 'Premier League', 'Egypt', 'football', 5, array( 'Al Ahly', 'Zamalek SC', 'Pyramids FC', 'Al Masry' ) ),
			'botola' => array( 'Botola Pro', 'Morocco', 'football', 6, array( 'Wydad AC', 'Raja Casablanca', 'AS FAR', 'RS Berkane' ) ),
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
						'logo'        => '',
						'country'     => self::team_country( $home, $c[1] ),
					),
					'away'        => array(
						'external_id' => 'demo-team-' . sanitize_title( $away ),
						'name'        => $away,
						'logo'        => '',
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
						'logo'        => '',
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
					'referee'    => 'Referee Demo',
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
		foreach ( array( 'pl', 'laliga', 'spl', 'egy', 'botola' ) as $key ) {
			$teams = $comps[ $key ][4];
			$rows  = array();
			foreach ( $teams as $i => $name ) {
				$played = 10;
				$win    = max( 0, 8 - $i );
				$draw   = min( $played - $win, 1 + ( $i % 3 ) );
				$lose   = $played - $win - $draw;
				$gf     = 22 - 2 * $i;
				$ga     = 6 + $i;
				$rows[] = array(
					'group'         => '',
					'rank'          => $i + 1,
					'team'          => array(
						'external_id' => 'demo-team-' . sanitize_title( $name ),
						'name'        => $name,
						'logo'        => '',
					),
					'played'        => $played,
					'win'           => $win,
					'draw'          => $draw,
					'lose'          => $lose,
					'goals_for'     => max( 0, $gf ),
					'goals_against' => $ga,
					'goal_diff'     => max( 0, $gf ) - $ga,
					'points'        => $win * 3 + $draw,
					'form'          => substr( 'WWDLW', $i % 3, 5 ),
				);
				// Make sure every team in the table has a page.
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
							'logo'        => '',
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
							'logo'        => '',
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
