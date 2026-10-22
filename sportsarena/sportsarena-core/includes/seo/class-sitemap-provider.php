<?php
/**
 * Core sitemap provider for the day pages (/matches-today/ ...).
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Day pages provider (wp-sitemap-sapages-1.xml).
 */
class Sitemap_Provider extends \WP_Sitemaps_Provider {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name        = 'sapages';
		$this->object_type = 'sapages';
	}

	/**
	 * URL list.
	 *
	 * @param int    $page_num       Page number.
	 * @param string $object_subtype Unused.
	 * @return array<int,array{loc:string}>
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( 1 !== (int) $page_num ) {
			return array();
		}
		return array_map(
			static function ( $url ) {
				return array( 'loc' => $url );
			},
			Sitemaps::day_urls()
		);
	}

	/**
	 * One page only.
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
