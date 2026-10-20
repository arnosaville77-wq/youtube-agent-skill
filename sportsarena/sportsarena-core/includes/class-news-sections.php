<?php
/**
 * News sections: one post category per sport (football news, basketball
 * news...) plus "analysis & opinion" and "transfers", and a news hub page.
 *
 * A post's category and its sport term are kept in sync, so an article
 * filed under "Basketball news" also appears on /sport/basketball/, and an
 * article tagged with a sport lands in that sport's news category.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * News sections.
 */
final class News_Sections {

	const META = '_sa_sport';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'save_post_post', array( $this, 'sync_terms' ), 30, 2 );
		// The block editor saves terms after save_post: sync again once it is done.
		add_action(
			'rest_after_insert_post',
			function ( $post ) {
				$this->sync_terms( $post->ID, $post );
			}
		);
	}

	/**
	 * Section definitions: category slug => array( name, sport slug, description ).
	 *
	 * @return array<string,array{name:string,sport:string,description:string}>
	 */
	public static function definitions() {
		return array(
			'football-news'   => array(
				'name'        => __( 'أخبار كرة القدم', 'sportsarena-core' ),
				'sport'       => 'football',
				'description' => __( 'آخر أخبار كرة القدم: الدوريات العربية والأوروبية ودوري الأبطال والمنتخبات.', 'sportsarena-core' ),
			),
			'transfers'       => array(
				'name'        => __( 'الانتقالات', 'sportsarena-core' ),
				'sport'       => 'football',
				'description' => __( 'أخبار انتقالات اللاعبين والمدربين والصفقات الرسمية والشائعات الموثوقة.', 'sportsarena-core' ),
			),
			'basketball-news' => array(
				'name'        => __( 'أخبار كرة السلة', 'sportsarena-core' ),
				'sport'       => 'basketball',
				'description' => __( 'أخبار كرة السلة: الدوري الأمريكي والدوري الأوروبي والبطولات العربية.', 'sportsarena-core' ),
			),
			'tennis-news'     => array(
				'name'        => __( 'أخبار التنس', 'sportsarena-core' ),
				'sport'       => 'tennis',
				'description' => __( 'أخبار التنس وبطولات الجراند سلام ورابطة المحترفين.', 'sportsarena-core' ),
			),
			'formula-1-news'  => array(
				'name'        => __( 'أخبار الفورمولا 1', 'sportsarena-core' ),
				'sport'       => 'formula-1',
				'description' => __( 'أخبار الفورمولا 1: السباقات والسائقون والفرق.', 'sportsarena-core' ),
			),
			'handball-news'   => array(
				'name'        => __( 'أخبار كرة اليد', 'sportsarena-core' ),
				'sport'       => 'handball',
				'description' => __( 'أخبار كرة اليد والبطولات القارية والعالمية.', 'sportsarena-core' ),
			),
			'volleyball-news' => array(
				'name'        => __( 'أخبار الكرة الطائرة', 'sportsarena-core' ),
				'sport'       => 'volleyball',
				'description' => __( 'أخبار الكرة الطائرة والبطولات المحلية والدولية.', 'sportsarena-core' ),
			),
			'mma-news'        => array(
				'name'        => __( 'أخبار الملاكمة والفنون القتالية', 'sportsarena-core' ),
				'sport'       => 'mma',
				'description' => __( 'أخبار الملاكمة وUFC والفنون القتالية المختلطة.', 'sportsarena-core' ),
			),
			'analysis'        => array(
				'name'        => __( 'تحليلات ومقالات رأي', 'sportsarena-core' ),
				'sport'       => '',
				'description' => __( 'تحليلات فنية ومقالات رأي ومعاينات معمّقة يكتبها فريق التحرير.', 'sportsarena-core' ),
			),
		);
	}

	/**
	 * Create missing section categories (existing ones are never renamed).
	 *
	 * @return array<string,int> Slug => term ID.
	 */
	public static function create() {
		$ids = array();
		foreach ( self::definitions() as $slug => $def ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( ! $term ) {
				$result = wp_insert_term(
					$def['name'],
					'category',
					array(
						'slug'        => $slug,
						'description' => $def['description'],
					)
				);
				if ( is_wp_error( $result ) ) {
					continue;
				}
				$term_id = (int) $result['term_id'];
			} else {
				$term_id = (int) $term->term_id;
			}
			if ( $def['sport'] ) {
				update_term_meta( $term_id, self::META, $def['sport'] );
			}
			$ids[ $slug ] = $term_id;
		}
		return $ids;
	}

	/**
	 * Section categories that exist, in definition order.
	 *
	 * @return \WP_Term[]
	 */
	public static function terms() {
		$terms = array();
		foreach ( array_keys( self::definitions() ) as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term ) {
				$terms[] = $term;
			}
		}
		return $terms;
	}

	/**
	 * Keep categories and sport terms of a post in sync.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public function sync_terms( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		$categories = wp_get_post_categories( $post_id, array( 'fields' => 'all' ) );
		$sports     = wp_get_object_terms( $post_id, Post_Types::TAX_SPORT, array( 'fields' => 'slugs' ) );
		$sports     = is_wp_error( $sports ) ? array() : $sports;

		// Category => sport.
		$add_sports = array();
		foreach ( $categories as $category ) {
			$sport = (string) get_term_meta( $category->term_id, self::META, true );
			if ( '' !== $sport && ! in_array( $sport, $sports, true ) && term_exists( $sport, Post_Types::TAX_SPORT ) ) {
				$add_sports[] = $sport;
			}
		}
		if ( $add_sports ) {
			wp_set_object_terms( $post_id, $add_sports, Post_Types::TAX_SPORT, true );
		}

		// Sport => category, only when the post has no real category yet.
		$default = (int) get_option( 'default_category' );
		$real    = array_filter(
			$categories,
			static function ( $category ) use ( $default ) {
				return (int) $category->term_id !== $default;
			}
		);
		if ( ! $real && $sports ) {
			foreach ( self::definitions() as $slug => $def ) {
				if ( $def['sport'] && in_array( $def['sport'], $sports, true ) && 'transfers' !== $slug ) {
					$term = get_term_by( 'slug', $slug, 'category' );
					if ( $term ) {
						wp_set_post_categories( $post_id, array( (int) $term->term_id ) );
					}
					break;
				}
			}
		}
	}
}
