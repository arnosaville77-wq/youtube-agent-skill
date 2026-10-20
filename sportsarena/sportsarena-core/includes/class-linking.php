<?php
/**
 * Automatic internal linking.
 *
 * - When a news post is saved without manual relations, teams and
 *   competitions mentioned in its title/content are attached to it.
 * - The first mention of a related team/competition inside the article is
 *   linked to its page (max 3 links per article).
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Linking.
 */
final class Linking {

	const MAX_LINKS = 3;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'save_post_post', array( $this, 'auto_relate' ), 20, 2 );
		add_filter( 'the_content', array( $this, 'link_mentions' ), 20 );
	}

	/**
	 * Detect mentioned teams / competitions on save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public function auto_relate( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}
		if ( get_post_meta( $post_id, '_sa_team_id' ) || get_post_meta( $post_id, '_sa_competition_id' ) ) {
			return; // Manual relations win.
		}
		$haystack = wp_strip_all_tags( $post->post_title . ' ' . $post->post_content );
		foreach ( array(
			Post_Types::TEAM        => '_sa_team_id',
			Post_Types::COMPETITION => '_sa_competition_id',
		) as $type => $meta_key ) {
			$found = 0;
			foreach ( $this->titles( $type ) as $id => $title ) {
				if ( mb_strlen( $title ) >= 3 && false !== mb_strpos( $haystack, $title ) ) {
					add_post_meta( $post_id, $meta_key, (int) $id );
					if ( ++$found >= 5 ) {
						break;
					}
				}
			}
		}
		Repository::bump_version();
	}

	/**
	 * Link first mentions of related entities inside single news content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function link_mentions( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		$ids     = array_merge(
			array_map( 'intval', (array) get_post_meta( $post_id, '_sa_team_id' ) ),
			array_map( 'intval', (array) get_post_meta( $post_id, '_sa_competition_id' ) )
		);
		$links   = 0;
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			if ( $links >= self::MAX_LINKS || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$title = (string) get_post_field( 'post_title', $id );
			if ( mb_strlen( $title ) < 3 ) {
				continue;
			}
			// Skip tags (attributes) and existing links/headings; replace in text only.
			$pattern = '/(<(a|h[1-6])\b[^>]*>.*?<\/\2>|<[^>]+>)|(' . preg_quote( esc_html( $title ), '/' ) . ')/su';
			$done    = false;
			$content = preg_replace_callback(
				$pattern,
				static function ( $m ) use ( &$done, $id ) {
					if ( ! empty( $m[1] ) || $done ) {
						return $m[0];
					}
					$done = true;
					return '<a href="' . esc_url( get_permalink( $id ) ) . '">' . $m[3] . '</a>';
				},
				$content
			);
			if ( $done ) {
				++$links;
			}
		}
		return $content;
	}

	/**
	 * Titles of all published posts of a type (cached).
	 *
	 * @param string $type Post type.
	 * @return array<int,string>
	 */
	private function titles( $type ) {
		$key    = 'sa_titles_' . $type . '_' . md5( Repository::version() );
		$titles = get_transient( $key );
		if ( false === $titles ) {
			$titles = array();
			$ids    = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => 2000,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ( $ids as $id ) {
				$titles[ $id ] = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
			}
			// Longest first so "مانشستر يونايتد" wins over a shorter overlapping name.
			uasort(
				$titles,
				static function ( $a, $b ) {
					return mb_strlen( $b ) <=> mb_strlen( $a );
				}
			);
			set_transient( $key, $titles, HOUR_IN_SECONDS );
		}
		return $titles;
	}
}
