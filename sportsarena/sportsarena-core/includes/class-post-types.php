<?php
/**
 * Custom post types, taxonomies and post meta.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers match / team / competition / player and the sport / season / country taxonomies.
 */
final class Post_Types {

	const MATCH       = 'match';
	const TEAM        = 'team';
	const COMPETITION = 'competition';
	const PLAYER      = 'player';

	const TAX_SPORT   = 'sport';
	const TAX_SEASON  = 'season';
	const TAX_COUNTRY = 'country';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ), 5 );
	}

	/**
	 * Register everything (also called directly on activation).
	 *
	 * @return void
	 */
	public function register() {
		$this->register_post_types();
		$this->register_taxonomies();
		$this->register_meta();
	}

	/**
	 * Post types.
	 *
	 * @return void
	 */
	private function register_post_types() {
		$common = array(
			'public'        => true,
			'show_in_rest'  => true,
			'menu_position' => 5,
			'map_meta_cap'  => true,
		);

		register_post_type(
			self::MATCH,
			array_merge(
				$common,
				array(
					'labels'      => $this->labels( __( 'المباريات', 'sportsarena-core' ), __( 'مباراة', 'sportsarena-core' ) ),
					'menu_icon'   => 'dashicons-awards',
					'has_archive' => 'matches',
					'rewrite'     => array(
						'slug'       => 'match',
						'with_front' => false,
					),
					'query_var'   => 'sa_match',
					// The editor writes the "preview / analysis" in the content.
					'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
				)
			)
		);

		register_post_type(
			self::TEAM,
			array_merge(
				$common,
				array(
					'labels'      => $this->labels( __( 'الفرق', 'sportsarena-core' ), __( 'فريق', 'sportsarena-core' ) ),
					'menu_icon'   => 'dashicons-groups',
					'has_archive' => 'teams',
					'rewrite'     => array(
						'slug'       => 'team',
						'with_front' => false,
					),
					'query_var'   => 'sa_team',
					'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
				)
			)
		);

		register_post_type(
			self::COMPETITION,
			array_merge(
				$common,
				array(
					'labels'      => $this->labels( __( 'البطولات', 'sportsarena-core' ), __( 'بطولة', 'sportsarena-core' ) ),
					'menu_icon'   => 'dashicons-flag',
					'has_archive' => 'competitions',
					'rewrite'     => array(
						'slug'       => 'competition',
						'with_front' => false,
					),
					'query_var'   => 'sa_competition',
					'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'page-attributes' ),
				)
			)
		);

		register_post_type(
			self::PLAYER,
			array_merge(
				$common,
				array(
					'labels'      => $this->labels( __( 'اللاعبون', 'sportsarena-core' ), __( 'لاعب', 'sportsarena-core' ) ),
					'menu_icon'   => 'dashicons-admin-users',
					'has_archive' => false,
					'rewrite'     => array(
						'slug'       => 'player',
						'with_front' => false,
					),
					'query_var'   => 'sa_player',
					'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
				)
			)
		);
	}

	/**
	 * Taxonomies.
	 *
	 * @return void
	 */
	private function register_taxonomies() {
		$all = array( 'post', self::MATCH, self::TEAM, self::COMPETITION, self::PLAYER );

		register_taxonomy(
			self::TAX_SPORT,
			$all,
			array(
				'labels'            => $this->tax_labels( __( 'الرياضات', 'sportsarena-core' ), __( 'رياضة', 'sportsarena-core' ) ),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'sport',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			self::TAX_SEASON,
			array( self::MATCH, self::COMPETITION ),
			array(
				'labels'            => $this->tax_labels( __( 'المواسم', 'sportsarena-core' ), __( 'موسم', 'sportsarena-core' ) ),
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'season',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			self::TAX_COUNTRY,
			array( self::TEAM, self::COMPETITION, self::PLAYER ),
			array(
				'labels'            => $this->tax_labels( __( 'الدول', 'sportsarena-core' ), __( 'دولة', 'sportsarena-core' ) ),
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'country',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Register typed meta so it is sanitized and protected.
	 *
	 * @return void
	 */
	private function register_meta() {
		$auth = static function () {
			return current_user_can( 'edit_posts' );
		};

		$fields = array(
			self::MATCH       => array(
				'_sa_home_team'   => 'integer',
				'_sa_away_team'   => 'integer',
				'_sa_competition' => 'integer',
				'_sa_kickoff'     => 'integer', // UTC unix timestamp.
				'_sa_status'      => 'string',  // One of: scheduled, live, halftime, finished, postponed, cancelled.
				'_sa_status_raw'  => 'string',
				'_sa_elapsed'     => 'integer',
				'_sa_home_score'  => 'string',  // Empty string until a score exists.
				'_sa_away_score'  => 'string',
				'_sa_venue'       => 'string',
				'_sa_city'        => 'string',
				'_sa_round'       => 'string',
				'_sa_referee'     => 'string',
				'_sa_tv'          => 'string',  // TV channel as plain text only (never a stream).
				'_sa_events'      => 'string',  // JSON.
				'_sa_lineups'     => 'string',  // JSON.
				'_sa_stats'       => 'string',  // JSON.
				'_sa_provider'    => 'string',
				'_sa_external_id' => 'string',
				'_sa_demo'        => 'boolean',
			),
			self::TEAM        => array(
				'_sa_short_name'  => 'string',
				'_sa_name_en'     => 'string',
				'_sa_founded'     => 'string',
				'_sa_venue'       => 'string',
				'_sa_logo_url'    => 'string',
				'_sa_provider'    => 'string',
				'_sa_external_id' => 'string',
				'_sa_demo'        => 'boolean',
			),
			self::COMPETITION => array(
				'_sa_name_en'     => 'string',
				'_sa_type'        => 'string',
				'_sa_priority'    => 'integer',
				'_sa_season'      => 'string',
				'_sa_standings'   => 'string', // JSON.
				'_sa_scorers'     => 'string', // JSON.
				'_sa_logo_url'    => 'string',
				'_sa_provider'    => 'string',
				'_sa_external_id' => 'string',
				'_sa_demo'        => 'boolean',
			),
			'post'            => array(
				'_sa_team_id'        => 'integer', // Multiple rows: one per related team.
				'_sa_competition_id' => 'integer', // Multiple rows: one per related competition.
				'_sa_demo'           => 'boolean',
			),
		);

		foreach ( $fields as $post_type => $meta ) {
			foreach ( $meta as $key => $type ) {
				$multi = in_array( $key, array( '_sa_team_id', '_sa_competition_id' ), true );
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'          => $type,
						'single'        => ! $multi,
						'show_in_rest'  => false,
						'auth_callback' => $auth,
					)
				);
			}
		}
	}

	/**
	 * Post type labels.
	 *
	 * @param string $plural   Plural name.
	 * @param string $singular Singular name.
	 * @return array<string,string>
	 */
	private function labels( $plural, $singular ) {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			/* translators: %s: singular item name. */
			'add_new_item'  => sprintf( __( 'إضافة %s', 'sportsarena-core' ), $singular ),
			/* translators: %s: singular item name. */
			'edit_item'     => sprintf( __( 'تحرير %s', 'sportsarena-core' ), $singular ),
			/* translators: %s: singular item name. */
			'view_item'     => sprintf( __( 'عرض %s', 'sportsarena-core' ), $singular ),
			'all_items'     => $plural,
			/* translators: %s: plural item name. */
			'search_items'  => sprintf( __( 'بحث في %s', 'sportsarena-core' ), $plural ),
			'not_found'     => __( 'لا توجد عناصر.', 'sportsarena-core' ),
		);
	}

	/**
	 * Taxonomy labels.
	 *
	 * @param string $plural   Plural name.
	 * @param string $singular Singular name.
	 * @return array<string,string>
	 */
	private function tax_labels( $plural, $singular ) {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			/* translators: %s: singular term name. */
			'add_new_item'  => sprintf( __( 'إضافة %s', 'sportsarena-core' ), $singular ),
			/* translators: %s: singular term name. */
			'edit_item'     => sprintf( __( 'تحرير %s', 'sportsarena-core' ), $singular ),
			'all_items'     => $plural,
		);
	}
}
