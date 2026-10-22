<?php
/**
 * Keep generated content in the site language.
 *
 * Pages, news sections and demo data are written in the site language when
 * they are created. When the site language changes (Settings > General), the
 * ones the owner has not edited are rewritten in the new language, so no page
 * mixes two languages. Edited content is never touched.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Relocalize.
 */
final class Relocalize {

	const PENDING = 'sportsarena_relocalize_pending';

	/**
	 * Post / term meta holding the hash of the generated content.
	 */
	const META = '_sa_generated';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'update_option_WPLANG', array( $this, 'flag' ) );
		add_action( 'add_option_WPLANG', array( $this, 'flag' ) );
		// Runs on the next admin request, once the new language is loaded.
		add_action( 'admin_init', array( $this, 'maybe_run' ) );
	}

	/**
	 * Remember that the site language changed.
	 *
	 * @return void
	 */
	public function flag() {
		update_option( self::PENDING, 1, false );
	}

	/**
	 * Run once after a language change.
	 *
	 * @return void
	 */
	public function maybe_run() {
		if ( get_option( self::PENDING ) && current_user_can( 'manage_options' ) ) {
			delete_option( self::PENDING );
			self::run();
		}
	}

	/**
	 * Rewrite unedited generated content in the current site language.
	 *
	 * @return array{pages:int,sections:int,demo:int}
	 */
	public static function run() {
		$result = array(
			'pages'    => self::pages(),
			'sections' => self::sections(),
			'demo'     => self::demo(),
		);
		/**
		 * Fires after generated content was rewritten in the site language.
		 *
		 * @param array $result Counts of rewritten items.
		 */
		do_action( 'sportsarena_relocalized', $result );
		Logger::info( 'i18n', sprintf( 'Content rewritten in %1$s: %2$d pages, %3$d sections, %4$d demo items.', get_locale(), $result['pages'], $result['sections'], $result['demo'] ) );
		return $result;
	}

	/**
	 * Hash of generated text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function hash( $text ) {
		return md5( trim( (string) $text ) );
	}

	/**
	 * Remember the generated content of a page.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public static function mark_post( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			update_post_meta( $post_id, self::META, self::hash( $post->post_title . "\n" . $post->post_content ) );
		}
	}

	/**
	 * Whether a page still holds the content the plugin generated.
	 *
	 * @param \WP_Post $post Page.
	 * @return bool
	 */
	private static function post_untouched( $post ) {
		$stored = (string) get_post_meta( $post->ID, self::META, true );
		return '' !== $stored && self::hash( $post->post_title . "\n" . $post->post_content ) === $stored;
	}

	/**
	 * Remember the generated name and description of a term.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public static function mark_term( $term_id ) {
		$term = get_term( $term_id );
		if ( $term && ! is_wp_error( $term ) ) {
			update_term_meta( $term_id, self::META, self::hash( $term->name . "\n" . $term->description ) );
		}
	}

	/**
	 * Required pages. Skipped with Polylang (one page per language already).
	 *
	 * @return int
	 */
	private static function pages() {
		if ( function_exists( 'pll_languages_list' ) && pll_languages_list() ) {
			return 0;
		}
		$done        = 0;
		$definitions = Pages::definitions();
		foreach ( Pages::stored_ids() as $key => $id ) {
			$post = get_post( $id );
			if ( ! $post || empty( $definitions[ $key ] ) || ! self::post_untouched( $post ) ) {
				continue;
			}
			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_title'   => $definitions[ $key ]['title'],
					'post_content' => Pages::fill( $definitions[ $key ]['content'] ),
				)
			);
			self::mark_post( $post->ID );
			++$done;
		}
		return $done;
	}

	/**
	 * News section categories.
	 *
	 * @return int
	 */
	private static function sections() {
		$done = 0;
		foreach ( News_Sections::definitions() as $slug => $def ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( ! $term ) {
				continue;
			}
			$stored = (string) get_term_meta( $term->term_id, self::META, true );
			if ( '' === $stored || self::hash( $term->name . "\n" . $term->description ) !== $stored ) {
				continue;
			}
			wp_update_term(
				$term->term_id,
				'category',
				array(
					'name'        => $def['name'],
					'description' => $def['description'],
				)
			);
			self::mark_term( $term->term_id );
			++$done;
		}
		return $done;
	}

	/**
	 * Demo data is recreated in the new language when present.
	 *
	 * @return int
	 */
	private static function demo() {
		if ( ! Seeder::has_demo() ) {
			return 0;
		}
		Seeder::unseed();
		return Seeder::seed();
	}
}
