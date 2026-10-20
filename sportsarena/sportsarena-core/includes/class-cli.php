<?php
/**
 * WP-CLI commands.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

use SportsArena\Core\Data\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Manage SportsArena data from the command line.
 *
 * ## EXAMPLES
 *
 *     wp sportsarena seed
 *     wp sportsarena sync daily
 *     wp sportsarena pages
 */
final class Cli {

	/**
	 * Create demo data.
	 *
	 * @return void
	 */
	public function seed() {
		$count = Seeder::seed();
		\WP_CLI::success( sprintf( 'Demo data created (%d matches).', $count ) );
	}

	/**
	 * Delete demo data.
	 *
	 * @return void
	 */
	public function unseed() {
		$count = Seeder::unseed();
		\WP_CLI::success( sprintf( 'Deleted %d demo posts.', $count ) );
	}

	/**
	 * Run a sync job.
	 *
	 * ## OPTIONS
	 *
	 * <job>
	 * : live|daily|tables
	 *
	 * @param array $args Positional args.
	 * @return void
	 */
	public function sync( $args ) {
		$sync = new Sync();
		switch ( $args[0] ?? '' ) {
			case 'live':
				delete_option( 'sportsarena_live_last_run' );
				$sync->run_live();
				break;
			case 'daily':
				$sync->run_daily();
				break;
			case 'tables':
				$sync->run_tables();
				break;
			default:
				\WP_CLI::error( 'Usage: wp sportsarena sync <live|daily|tables>' );
		}
		\WP_CLI::success( 'Done. See SportsArena > Log for details.' );
	}

	/**
	 * Create the required pages (about, contact, privacy, terms, disclaimer).
	 *
	 * @return void
	 */
	public function pages() {
		$ids = Pages::create_required_pages();
		\WP_CLI::success( 'Pages: ' . wp_json_encode( $ids ) );
	}
}
