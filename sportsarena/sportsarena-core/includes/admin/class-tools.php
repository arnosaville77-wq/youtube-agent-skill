<?php
/**
 * Handlers (admin-post.php) for the tools page (each verifies a nonce and
 * the user's capability).
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Admin;

use SportsArena\Core\Ads\Ads;
use SportsArena\Core\Data\Sync;
use SportsArena\Core\Logger;
use SportsArena\Core\Pages;
use SportsArena\Core\Repository;
use SportsArena\Core\Seeder;

defined( 'ABSPATH' ) || exit;

/**
 * Tools.
 */
final class Tools {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		foreach ( array( 'seed', 'unseed', 'sync_now', 'create_pages', 'write_ads_txt', 'flush', 'clear_log', 'cache_logos' ) as $tool ) {
			add_action( 'admin_post_sportsarena_' . $tool, array( $this, $tool ) );
		}
		add_action( 'admin_post_sportsarena_refresh_match', array( $this, 'refresh_match' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Verify nonce + capability.
	 *
	 * @param string $action Nonce action.
	 * @param string $cap    Capability.
	 * @return void
	 */
	private function verify( $action, $cap = 'manage_options' ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'ليست لديك صلاحية.', 'sportsarena-core' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Back to the tools page.
	 *
	 * @param string $page Page slug.
	 * @return never
	 */
	private function back( $page = 'sportsarena-tools' ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&sa_done=1' ) );
		exit;
	}

	/**
	 * Create demo data.
	 *
	 * @return void
	 */
	public function seed() {
		$this->verify( 'sportsarena_seed' );
		Seeder::seed();
		$this->back();
	}

	/**
	 * Delete demo data.
	 *
	 * @return void
	 */
	public function unseed() {
		$this->verify( 'sportsarena_unseed' );
		Seeder::unseed();
		$this->back();
	}

	/**
	 * Run a sync job now.
	 *
	 * @return void
	 */
	public function sync_now() {
		$this->verify( 'sportsarena_sync_now' );
		$job  = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify().
		$sync = new Sync();
		if ( 'live' === $job ) {
			delete_option( 'sportsarena_live_last_run' );
			$sync->run_live();
		} elseif ( 'tables' === $job ) {
			$sync->run_tables();
		} else {
			$sync->run_daily();
		}
		Logger::info( 'admin', 'Manual sync: ' . $job );
		$this->back();
	}

	/**
	 * Create required pages.
	 *
	 * @return void
	 */
	public function create_pages() {
		$this->verify( 'sportsarena_create_pages' );
		\SportsArena\Core\News_Sections::create();
		Pages::create_required_pages();
		$this->back();
	}

	/**
	 * Regenerate ads.txt.
	 *
	 * @return void
	 */
	public function write_ads_txt() {
		$this->verify( 'sportsarena_write_ads_txt' );
		Ads::write_ads_txt();
		$this->back();
	}

	/**
	 * Flush rewrite rules and query caches.
	 *
	 * @return void
	 */
	public function flush() {
		$this->verify( 'sportsarena_flush' );
		flush_rewrite_rules();
		Repository::bump_version();
		$this->back();
	}

	/**
	 * Download a batch of provider logos now.
	 *
	 * @return void
	 */
	public function cache_logos() {
		$this->verify( 'sportsarena_cache_logos' );
		\SportsArena\Core\Logo_Cache::run( 60 );
		$this->back();
	}

	/**
	 * Clear the log.
	 *
	 * @return void
	 */
	public function clear_log() {
		$this->verify( 'sportsarena_clear_log' );
		Logger::clear();
		$this->back( 'sportsarena-log' );
	}

	/**
	 * Refresh one match from its provider (button in the match meta box).
	 *
	 * @return void
	 */
	public function refresh_match() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'ليست لديك صلاحية.', 'sportsarena-core' ), 403 );
		}
		check_admin_referer( 'sportsarena_refresh_match_' . $post_id );
		$ok = Sync::refresh_match( $post_id );
		wp_safe_redirect( add_query_arg( 'sa_refreshed', $ok ? '1' : '0', get_edit_post_link( $post_id, 'url' ) ) );
		exit;
	}

	/**
	 * Notice after refreshing a match.
	 *
	 * @return void
	 */
	public function notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag.
		if ( ! isset( $_GET['sa_refreshed'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag.
		$ok = '1' === sanitize_key( wp_unslash( $_GET['sa_refreshed'] ) );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			$ok ? 'success' : 'warning',
			$ok ? esc_html__( 'تم تحديث بيانات المباراة من المزوّد.', 'sportsarena-core' ) : esc_html__( 'تعذّر التحديث: تحقق من المفتاح والسجل.', 'sportsarena-core' )
		);
	}
}
