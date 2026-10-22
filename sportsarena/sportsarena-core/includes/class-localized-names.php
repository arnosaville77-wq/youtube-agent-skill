<?php
/**
 * Show team, competition, match, sport and country names in the visitor's
 * language without changing what is stored.
 *
 * On a non-Arabic site (or a non-Arabic Polylang/WPML language), Arabic
 * titles are replaced on the front end by the provider's English name
 * (_sa_name_en), match titles are rebuilt from the localized team names,
 * and sport / country terms use their translated names. On an Arabic site
 * the reverse happens for posts that were imported with English names.
 * The admin always shows the stored titles.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

use SportsArena\Core\Data\Names;

defined( 'ABSPATH' ) || exit;

/**
 * Localized names.
 */
final class Localized_Names {

	/**
	 * Re-entrancy guard for match titles.
	 *
	 * @var bool
	 */
	private $building = false;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		add_filter( 'the_title', array( $this, 'title' ), 5, 2 );
		add_filter( 'single_post_title', array( $this, 'title' ), 5, 2 );
		add_filter( 'get_' . Post_Types::TAX_SPORT, array( $this, 'sport_term' ) );
		add_filter( 'get_' . Post_Types::TAX_COUNTRY, array( $this, 'country_term' ) );
	}

	/**
	 * Localize a post title.
	 *
	 * @param string            $title Title.
	 * @param int|\WP_Post|null $post Post.
	 * @return string
	 */
	public function title( $title, $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $title;
		}
		$arabic_site  = Names::is_arabic();
		$arabic_title = Names::has_arabic( $title );

		switch ( $post->post_type ) {
			case Post_Types::TEAM:
			case Post_Types::COMPETITION:
				$english = (string) get_post_meta( $post->ID, '_sa_name_en', true );
				if ( ! $arabic_site && $arabic_title && '' !== $english ) {
					// Same-named leagues: "Premier League (Egypt)" instead of a bare "Premier League".
					foreach ( Names::dictionary() as $key => $arabic ) {
						if ( $arabic === $title && str_starts_with( $key, $english . ' (' ) ) {
							return $key;
						}
					}
					return $english;
				}
				if ( $arabic_site && ! $arabic_title && '' !== $english && $title === $english ) {
					return Names::arabic( $english );
				}
				return $title;

			case Post_Types::MATCH:
				if ( $this->building || $arabic_site === $arabic_title ) {
					return $title;
				}
				$home = (int) get_post_meta( $post->ID, '_sa_home_team', true );
				$away = (int) get_post_meta( $post->ID, '_sa_away_team', true );
				if ( ! $home ) {
					return $title;
				}
				$this->building = true;
				$built          = $away
					/* translators: 1: home team, 2: away team. */
					? sprintf( __( '%1$s ضد %2$s', 'sportsarena-core' ), get_the_title( $home ), get_the_title( $away ) )
					: get_the_title( $home );
				$this->building = false;
				return $built;
		}
		return $title;
	}

	/**
	 * Translated sport names (stored terms keep the name chosen at creation).
	 *
	 * @param \WP_Term $term Term.
	 * @return \WP_Term
	 */
	public function sport_term( $term ) {
		if ( $term instanceof \WP_Term && Names::has_arabic( $term->name ) !== Names::is_arabic() ) {
			$names = Names::sports();
			if ( isset( $names[ $term->slug ] ) ) {
				$term->name = $names[ $term->slug ];
			}
		}
		return $term;
	}

	/**
	 * Country names: Arabic terms shown in English on non-Arabic sites.
	 *
	 * @param \WP_Term $term Term.
	 * @return \WP_Term
	 */
	public function country_term( $term ) {
		if ( $term instanceof \WP_Term && ! Names::is_arabic() && Names::has_arabic( $term->name ) ) {
			$english = array_search( $term->name, Names::dictionary(), true );
			if ( false !== $english ) {
				$term->name = (string) $english;
			}
		}
		return $term;
	}
}
