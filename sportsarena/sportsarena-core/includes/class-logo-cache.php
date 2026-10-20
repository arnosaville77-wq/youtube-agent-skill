<?php
/**
 * Local copies of provider logos (teams and competitions).
 *
 * Only runs when the site owner enabled "allow_logos" (the data plan
 * licenses logo display) and "cache_logos". Logos are downloaded in small
 * batches by WP-Cron, resized to 128px (WebP when supported) and stored in
 * uploads/sportsarena-logos/, so pages never hotlink the provider and load
 * fast with fixed dimensions. API-Sports recommends storing logos locally.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Logo cache.
 */
final class Logo_Cache {

	const HOOK  = 'sportsarena_cache_logos';
	const DIR   = 'sportsarena-logos';
	const BATCH = 25;
	const SIZE  = 128;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( self::HOOK, array( self::class, 'run' ) );
		add_action( 'init', array( $this, 'schedule' ), 25 );
	}

	/**
	 * Schedule the hourly batch.
	 *
	 * @return void
	 */
	public function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 120, 'hourly', self::HOOK );
		}
	}

	/**
	 * Whether caching is active.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) Options::get( 'allow_logos' ) && (bool) Options::get( 'cache_logos' );
	}

	/**
	 * Download one batch of missing logos.
	 *
	 * @param int $limit Batch size.
	 * @return int Number of logos stored.
	 */
	public static function run( $limit = self::BATCH ) {
		if ( ! self::enabled() ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => array( Post_Types::TEAM, Post_Types::COMPETITION ),
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Small hourly batch.
				'meta_query'     => array(
					array(
						'key'     => '_sa_logo_url',
						'value'   => '',
						'compare' => '!=',
					),
					array(
						'key'     => '_sa_logo_local',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$done = 0;
		foreach ( $ids as $id ) {
			$stored = self::store( (int) $id );
			// Remember failures too (empty value), so a broken URL is not retried forever.
			update_post_meta( $id, '_sa_logo_local', $stored );
			if ( '' !== $stored ) {
				++$done;
			}
		}
		if ( $done ) {
			Logger::info( 'logos', sprintf( 'Cached %d logos.', $done ) );
		}
		return $done;
	}

	/**
	 * Download, validate, resize and store one logo.
	 *
	 * @param int $post_id Team or competition ID.
	 * @return string Path relative to the uploads base dir, '' on failure.
	 */
	private static function store( $post_id ) {
		$url = (string) get_post_meta( $post_id, '_sa_logo_url', true );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return '';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = download_url( $url, 15 );
		if ( is_wp_error( $tmp ) ) {
			Logger::warning( 'logos', $tmp->get_error_message(), array( 'post' => $post_id ) );
			return '';
		}
		$mime = wp_get_image_mime( $tmp );
		if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ), true ) ) {
			wp_delete_file( $tmp );
			return '';
		}
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::DIR;
		wp_mkdir_p( $dir );

		$base   = get_post_type( $post_id ) . '-' . $post_id;
		$editor = wp_get_image_editor( $tmp );
		$file   = '';
		if ( ! is_wp_error( $editor ) ) {
			$editor->resize( self::SIZE, self::SIZE, false );
			$webp  = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
			$saved = $editor->save( $dir . '/' . $base . ( $webp ? '.webp' : '.png' ), $webp ? 'image/webp' : 'image/png' );
			if ( ! is_wp_error( $saved ) && ! empty( $saved['file'] ) ) {
				$file = $saved['file'];
			}
		}
		if ( '' === $file ) {
			// No image editor: keep the original file as is.
			$ext  = 'image/png' === $mime ? '.png' : ( 'image/gif' === $mime ? '.gif' : ( 'image/webp' === $mime ? '.webp' : '.jpg' ) );
			$file = $base . $ext;
			if ( ! copy( $tmp, $dir . '/' . $file ) ) {
				wp_delete_file( $tmp );
				return '';
			}
		}
		wp_delete_file( $tmp );
		return self::DIR . '/' . $file;
	}

	/**
	 * Forget cached copies (after changing settings).
	 *
	 * @return void
	 */
	public static function reset() {
		delete_post_meta_by_key( '_sa_logo_local' );
	}
}
