<?php
/**
 * JSON-LD structured data.
 *
 * Always: SportsEvent (match), SportsTeam (team), SportsOrganization (competition).
 * Only without Yoast / Rank Math (which already output them): WebSite +
 * SearchAction, Organization, BreadcrumbList and NewsArticle.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Seo;

use SportsArena\Core\Post_Types;
use SportsArena\Core\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Schema.
 */
final class Schema {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_head', array( $this, 'output' ), 30 );
	}

	/**
	 * Print the JSON-LD graph.
	 *
	 * @return void
	 */
	public function output() {
		$graph      = array();
		$seo_plugin = Seo::seo_plugin();
		$home       = home_url( '/' );
		$org_id     = $home . '#organization';
		$website_id = $home . '#website';

		if ( '' === $seo_plugin ) {
			$graph[] = $this->organization( $org_id );
			$graph[] = array(
				'@type'           => 'WebSite',
				'@id'             => $website_id,
				'url'             => $home,
				'name'            => get_bloginfo( 'name' ),
				'inLanguage'      => get_bloginfo( 'language' ),
				'publisher'       => array( '@id' => $org_id ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => home_url( '/?s={search_term_string}' ),
					),
					'query-input' => 'required name=search_term_string',
				),
			);
			if ( ! is_front_page() && ! is_404() ) {
				$graph[] = $this->breadcrumbs();
			}
			if ( is_singular( 'post' ) ) {
				$graph[] = $this->news_article( get_queried_object_id(), $org_id );
			}
		}

		if ( is_singular( Post_Types::MATCH ) ) {
			$graph[] = $this->sports_event( get_queried_object_id() );
		} elseif ( is_singular( Post_Types::TEAM ) ) {
			$graph[] = $this->sports_team( get_queried_object_id(), true );
		} elseif ( is_singular( Post_Types::COMPETITION ) ) {
			$graph[] = $this->sports_organization( get_queried_object_id() );
		}

		$graph = array_values( array_filter( $graph ) );

		/**
		 * Filter the JSON-LD graph before output.
		 *
		 * @param array $graph Graph nodes.
		 */
		$graph = (array) apply_filters( 'sportsarena_schema_graph', $graph );
		if ( ! $graph ) {
			return;
		}

		printf(
			"<script type=\"application/ld+json\">%s</script>\n",
			wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
			)
		);
	}

	/**
	 * Organization node.
	 *
	 * @param string $id Node id.
	 * @return array
	 */
	private function organization( $id ) {
		$node    = array(
			'@type' => 'Organization',
			'@id'   => $id,
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : get_site_icon_url( 512 );
		if ( $logo ) {
			$node['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}
		return $node;
	}

	/**
	 * BreadcrumbList node.
	 *
	 * @return array
	 */
	private function breadcrumbs() {
		$items = array();
		foreach ( Breadcrumbs::trail() as $i => $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => html_entity_decode( wp_strip_all_tags( $crumb['name'] ), ENT_QUOTES, 'UTF-8' ),
			);
			if ( $crumb['url'] ) {
				$item['item'] = $crumb['url'];
			}
			$items[] = $item;
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/**
	 * NewsArticle node.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $org_id  Publisher node id.
	 * @return array
	 */
	private function news_article( $post_id, $org_id ) {
		$node = array(
			'@type'            => 'NewsArticle',
			'headline'         => wp_html_excerpt( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ), 110, '' ),
			'datePublished'    => get_the_date( 'c', $post_id ),
			'dateModified'     => get_the_modified_date( 'c', $post_id ),
			'mainEntityOfPage' => get_permalink( $post_id ),
			'inLanguage'       => get_bloginfo( 'language' ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ),
				'url'   => get_author_posts_url( (int) get_post_field( 'post_author', $post_id ) ),
			),
			'publisher'        => array( '@id' => $org_id ),
			'description'      => Seo::description(),
		);
		if ( has_post_thumbnail( $post_id ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
			if ( $src ) {
				$node['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $src[0],
					'width'  => $src[1],
					'height' => $src[2],
				);
			}
		}
		$sections = wp_list_pluck( (array) get_the_category( $post_id ), 'name' );
		if ( $sections ) {
			$node['articleSection'] = $sections;
		}
		return $node;
	}

	/**
	 * SportsEvent node for a match.
	 *
	 * @param int $post_id Match ID.
	 * @return array
	 */
	public function sports_event( $post_id ) {
		$m    = Repository::match( $post_id );
		$node = array(
			'@type'               => 'SportsEvent',
			'@id'                 => get_permalink( $post_id ) . '#event',
			'name'                => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
			'url'                 => get_permalink( $post_id ),
			'startDate'           => gmdate( 'c', $m['kickoff'] ),
			'eventStatus'         => $this->event_status( $m['status'] ),
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'description'         => Seo::description(),
			'image'               => array( Seo::image() ? Seo::image() : Og_Image::url( $post_id ) ),
		);
		if ( 'finished' === $m['status'] ) {
			$node['endDate'] = gmdate( 'c', $m['kickoff'] + 2 * HOUR_IN_SECONDS );
		}
		$sports = get_the_terms( $post_id, Post_Types::TAX_SPORT );
		if ( $sports && ! is_wp_error( $sports ) ) {
			$node['sport'] = $sports[0]->name;
		}
		$node['location'] = array(
			'@type'   => 'Place',
			'name'    => $m['venue'] ? $m['venue'] : __( 'يُحدد لاحقاً', 'sportsarena-core' ),
			'address' => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => $m['city'] ? $m['city'] : ( $m['venue'] ? $m['venue'] : get_bloginfo( 'name' ) ),
			),
		);
		if ( $m['home'] ) {
			$node['homeTeam'] = $this->sports_team( $m['home'] );
		}
		if ( $m['away'] ) {
			$node['awayTeam'] = $this->sports_team( $m['away'] );
		}
		if ( $m['home'] && $m['away'] ) {
			$node['competitor'] = array( $node['homeTeam'], $node['awayTeam'] );
		}
		if ( $m['competition'] ) {
			$node['superEvent'] = array(
				'@type' => 'SportsEvent',
				'name'  => html_entity_decode( get_the_title( $m['competition'] ), ENT_QUOTES, 'UTF-8' ),
				'url'   => get_permalink( $m['competition'] ),
			);
			$node['organizer']  = array(
				'@type' => 'SportsOrganization',
				'name'  => html_entity_decode( get_the_title( $m['competition'] ), ENT_QUOTES, 'UTF-8' ),
				'url'   => get_permalink( $m['competition'] ),
			);
		}
		return $node;
	}

	/**
	 * Map the normalized status to schema.org EventStatusType.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function event_status( $status ) {
		switch ( $status ) {
			case 'postponed':
				return 'https://schema.org/EventPostponed';
			case 'cancelled':
				return 'https://schema.org/EventCancelled';
			default:
				return 'https://schema.org/EventScheduled';
		}
	}

	/**
	 * SportsTeam node.
	 *
	 * @param int  $team_id Team ID.
	 * @param bool $full    Include extended properties (team page).
	 * @return array
	 */
	public function sports_team( $team_id, $full = false ) {
		$node   = array(
			'@type' => 'SportsTeam',
			'name'  => html_entity_decode( get_the_title( $team_id ), ENT_QUOTES, 'UTF-8' ),
			'url'   => get_permalink( $team_id ),
		);
		$sports = get_the_terms( $team_id, Post_Types::TAX_SPORT );
		if ( $sports && ! is_wp_error( $sports ) ) {
			$node['sport'] = $sports[0]->name;
		}
		if ( $full ) {
			$node['@id']         = get_permalink( $team_id ) . '#team';
			$node['description'] = Seo::description();
			$english             = (string) get_post_meta( $team_id, '_sa_name_en', true );
			if ( $english && $english !== $node['name'] ) {
				$node['alternateName'] = $english;
			}
			$venue = (string) get_post_meta( $team_id, '_sa_venue', true );
			if ( $venue ) {
				$node['location'] = array(
					'@type' => 'Place',
					'name'  => $venue,
				);
			}
			$founded = (string) get_post_meta( $team_id, '_sa_founded', true );
			if ( $founded ) {
				$node['foundingDate'] = $founded;
			}
		}
		if ( has_post_thumbnail( $team_id ) ) {
			$node['logo'] = get_the_post_thumbnail_url( $team_id, 'medium' );
		}
		return $node;
	}

	/**
	 * SportsOrganization node for a competition.
	 *
	 * @param int $competition_id Competition ID.
	 * @return array
	 */
	public function sports_organization( $competition_id ) {
		$node   = array(
			'@type'       => 'SportsOrganization',
			'@id'         => get_permalink( $competition_id ) . '#organization',
			'name'        => html_entity_decode( get_the_title( $competition_id ), ENT_QUOTES, 'UTF-8' ),
			'url'         => get_permalink( $competition_id ),
			'description' => Seo::description(),
		);
		$sports = get_the_terms( $competition_id, Post_Types::TAX_SPORT );
		if ( $sports && ! is_wp_error( $sports ) ) {
			$node['sport'] = $sports[0]->name;
		}
		$english = (string) get_post_meta( $competition_id, '_sa_name_en', true );
		if ( $english && $english !== $node['name'] ) {
			$node['alternateName'] = $english;
		}
		$members = array();
		foreach ( array_slice( Repository::standings( $competition_id ), 0, 40 ) as $row ) {
			if ( ! empty( $row['team']['post_id'] ) ) {
				$members[] = array(
					'@type' => 'SportsTeam',
					'name'  => html_entity_decode( get_the_title( (int) $row['team']['post_id'] ), ENT_QUOTES, 'UTF-8' ),
					'url'   => get_permalink( (int) $row['team']['post_id'] ),
				);
			}
		}
		if ( $members ) {
			$node['member'] = $members;
		}
		return $node;
	}
}
