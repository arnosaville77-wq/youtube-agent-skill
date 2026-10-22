<?php
/**
 * Visible breadcrumbs + the data used for the BreadcrumbList schema.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Seo;

use SportsArena\Core\Big_Matches;
use SportsArena\Core\Post_Types;
use SportsArena\Core\Rewrites;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumbs.
 */
final class Breadcrumbs {

	/**
	 * Build the trail for the current request.
	 *
	 * @return array<int,array{name:string,url:string}>
	 */
	public static function trail() {
		$trail = array(
			array(
				'name' => __( 'الرئيسية', 'sportsarena-core' ),
				'url'  => home_url( '/' ),
			),
		);

		if ( get_query_var( 'sa_big' ) ) {
			$trail[] = array(
				'name' => __( 'المباريات الكبرى', 'sportsarena-core' ),
				'url'  => Big_Matches::url(),
			);
			return $trail;
		}

		$day = get_query_var( 'sa_day' );
		if ( $day ) {
			$date    = Rewrites::resolve_day( $day );
			$trail[] = array(
				'name' => __( 'المباريات', 'sportsarena-core' ),
				'url'  => Rewrites::day_url( 'today' ),
			);
			if ( $date && 'today' !== $day ) {
				$trail[] = array(
					'name' => wp_date( 'j F Y', strtotime( $date . ' 12:00:00' ) ),
					'url'  => Seo::canonical(),
				);
			}
			return $trail;
		}

		if ( is_singular( Post_Types::MATCH ) ) {
			$id          = get_queried_object_id();
			$competition = (int) get_post_meta( $id, '_sa_competition', true );
			self::add_sport( $trail, $id );
			if ( $competition ) {
				$trail[] = array(
					'name' => get_the_title( $competition ),
					'url'  => get_permalink( $competition ),
				);
			}
			$trail[] = array(
				'name' => get_the_title( $id ),
				'url'  => get_permalink( $id ),
			);
			return $trail;
		}

		if ( is_singular( array( Post_Types::TEAM, Post_Types::COMPETITION, Post_Types::PLAYER ) ) ) {
			$id   = get_queried_object_id();
			$type = get_post_type_object( get_post_type( $id ) );
			self::add_sport( $trail, $id );
			if ( ! empty( $type->has_archive ) && 1 === count( $trail ) ) {
				$trail[] = array(
					'name' => $type->labels->name,
					'url'  => (string) get_post_type_archive_link( $type->name ),
				);
			}
			$trail[] = array(
				'name' => get_the_title( $id ),
				'url'  => get_permalink( $id ),
			);
			$tab     = get_query_var( 'sa_tab' );
			if ( $tab ) {
				$labels  = array(
					'matches'   => __( 'المباريات', 'sportsarena-core' ),
					'standings' => __( 'الترتيب', 'sportsarena-core' ),
					'scorers'   => __( 'الهدافون', 'sportsarena-core' ),
				);
				$trail[] = array(
					'name' => $labels[ $tab ] ?? $tab,
					'url'  => Rewrites::competition_tab_url( $id, $tab ),
				);
			}
			return $trail;
		}

		if ( is_singular( 'post' ) ) {
			$id         = get_queried_object_id();
			$categories = get_the_category( $id );
			if ( $categories ) {
				$trail[] = array(
					'name' => $categories[0]->name,
					'url'  => get_category_link( $categories[0] ),
				);
			}
			$trail[] = array(
				'name' => get_the_title( $id ),
				'url'  => get_permalink( $id ),
			);
			return $trail;
		}

		if ( is_page() ) {
			$id = get_queried_object_id();
			foreach ( array_reverse( get_post_ancestors( $id ) ) as $ancestor ) {
				$trail[] = array(
					'name' => get_the_title( $ancestor ),
					'url'  => get_permalink( $ancestor ),
				);
			}
			$trail[] = array(
				'name' => get_the_title( $id ),
				'url'  => get_permalink( $id ),
			);
			return $trail;
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$term    = get_queried_object();
			$link    = get_term_link( $term );
			$trail[] = array(
				'name' => $term->name,
				'url'  => is_wp_error( $link ) ? '' : $link,
			);
			return $trail;
		}

		if ( is_post_type_archive() ) {
			$type    = get_post_type_object( get_query_var( 'post_type' ) );
			$trail[] = array(
				'name' => $type ? $type->labels->name : '',
				'url'  => (string) get_post_type_archive_link( get_query_var( 'post_type' ) ),
			);
			return $trail;
		}

		if ( is_search() ) {
			$trail[] = array(
				/* translators: %s: search term. */
				'name' => sprintf( __( 'نتائج البحث عن: %s', 'sportsarena-core' ), get_search_query() ),
				'url'  => '',
			);
			return $trail;
		}

		if ( is_404() ) {
			$trail[] = array(
				'name' => __( 'الصفحة غير موجودة', 'sportsarena-core' ),
				'url'  => '',
			);
			return $trail;
		}

		if ( is_home() && ! is_front_page() ) {
			$trail[] = array(
				'name' => __( 'الأخبار', 'sportsarena-core' ),
				'url'  => Seo::canonical(),
			);
		}
		return $trail;
	}

	/**
	 * Add the sport term of a post to the trail.
	 *
	 * @param array $trail   Trail (by reference).
	 * @param int   $post_id Post ID.
	 * @return void
	 */
	private static function add_sport( array &$trail, $post_id ) {
		$terms = get_the_terms( $post_id, Post_Types::TAX_SPORT );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$link = get_term_link( $terms[0] );
			if ( ! is_wp_error( $link ) ) {
				$trail[] = array(
					'name' => $terms[0]->name,
					'url'  => $link,
				);
			}
		}
	}

	/**
	 * Visible breadcrumb HTML.
	 *
	 * @return string
	 */
	public static function render() {
		if ( is_front_page() ) {
			return '';
		}
		$trail = self::trail();
		$last  = count( $trail ) - 1;
		$items = array();
		foreach ( $trail as $i => $crumb ) {
			if ( $i === $last || '' === $crumb['url'] ) {
				$items[] = '<li aria-current="page"><span>' . esc_html( wp_strip_all_tags( $crumb['name'] ) ) . '</span></li>';
			} else {
				$items[] = '<li><a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( wp_strip_all_tags( $crumb['name'] ) ) . '</a></li>';
			}
		}
		return '<nav class="sa-breadcrumbs" aria-label="' . esc_attr__( 'مسار التنقل', 'sportsarena-core' ) . '"><ol>' . implode( '', $items ) . '</ol></nav>';
	}
}
