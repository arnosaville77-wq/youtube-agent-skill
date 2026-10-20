<?php
/**
 * Registry of data providers (adapters).
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Provider registry. Third parties can add adapters with the
 * "sportsarena_providers" filter (any class implementing Provider).
 */
final class Provider_Registry {

	/**
	 * Instantiated providers.
	 *
	 * @var array<string,Provider>|null
	 */
	private static $providers = null;

	/**
	 * All registered providers.
	 *
	 * @return array<string,Provider>
	 */
	public static function all() {
		if ( null === self::$providers ) {
			$list = array(
				new Providers\Api_Football(),
				new Providers\Api_Basketball(),
				new Providers\Thesportsdb(),
			);

			/**
			 * Filter the list of provider instances.
			 *
			 * @param Provider[] $list Providers.
			 */
			$list = (array) apply_filters( 'sportsarena_providers', $list );

			self::$providers = array();
			foreach ( $list as $provider ) {
				if ( $provider instanceof Provider ) {
					self::$providers[ $provider->get_id() ] = $provider;
				}
			}
		}
		return self::$providers;
	}

	/**
	 * Providers with credentials.
	 *
	 * @return array<string,Provider>
	 */
	public static function configured() {
		return array_filter(
			self::all(),
			static function ( Provider $provider ) {
				return $provider->is_configured();
			}
		);
	}

	/**
	 * Get a provider by id.
	 *
	 * @param string $id Provider id.
	 * @return Provider|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return $all[ $id ] ?? null;
	}

	/**
	 * Reset (used after settings change).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$providers = null;
	}
}
