<?php
/**
 * Cached, rate limited HTTP client shared by all providers.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Data;

use SportsArena\Core\Logger;
use SportsArena\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * HTTP client.
 *
 * Caching strategy:
 * 1. Transient (fast, can live in the object cache) for the endpoint TTL.
 * 2. {prefix}sa_cache table keeps the last good payload; it is served as a
 *    stale fallback when the API fails or the quota is exhausted.
 */
final class Http_Client {

	/**
	 * Provider id used for quota bookkeeping.
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * Constructor.
	 *
	 * @param string $provider Provider id.
	 */
	public function __construct( $provider ) {
		$this->provider = $provider;
	}

	/**
	 * GET a JSON endpoint.
	 *
	 * @param string               $url     Full URL (no secrets in query unless the provider requires it).
	 * @param array<string,string> $headers Request headers.
	 * @param int                  $ttl     Cache lifetime in seconds.
	 * @param string               $label   Endpoint label for logs (must not contain secrets).
	 * @return array|\WP_Error Decoded JSON.
	 */
	public function get_json( $url, array $headers, $ttl, $label ) {
		$key       = md5( $this->provider . '|' . $url );
		$transient = 'sa_api_' . $key;

		$cached = get_transient( $transient );
		if ( false !== $cached ) {
			return $cached;
		}

		$quota = $this->check_quota();
		if ( is_wp_error( $quota ) ) {
			Logger::warning( $this->provider, $quota->get_error_message(), array( 'endpoint' => $label ) );
			return $this->stale( $key, $quota );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'headers'    => $headers,
				'user-agent' => 'SportsArena/' . SPORTSARENA_CORE_VERSION . '; ' . home_url( '/' ),
			)
		);
		$this->record_request();

		if ( is_wp_error( $response ) ) {
			Logger::error( $this->provider, $response->get_error_message(), array( 'endpoint' => $label ) );
			return $this->stale( $key, $response );
		}

		$this->read_quota_headers( $response );

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 429 === $code ) {
			$this->block_until( time() + MINUTE_IN_SECONDS );
			$error = new \WP_Error( 'sa_rate_limited', __( 'تم تجاوز حد الطلبات لدى المزوّد (429).', 'sportsarena-core' ) );
			Logger::warning( $this->provider, $error->get_error_message(), array( 'endpoint' => $label ) );
			return $this->stale( $key, $error );
		}

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			/* translators: %d: HTTP status code. */
			$error = new \WP_Error( 'sa_http_error', sprintf( __( 'استجابة غير صالحة من المزوّد (HTTP %d).', 'sportsarena-core' ), $code ) );
			Logger::error( $this->provider, $error->get_error_message(), array( 'endpoint' => $label ) );
			return $this->stale( $key, $error );
		}

		// API-Sports reports logical errors (bad key, plan limits) inside the body.
		if ( ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
			$message = implode( ' | ', array_map( 'strval', array_map( 'wp_json_encode', $body['errors'] ) ) );
			Logger::error( $this->provider, $message, array( 'endpoint' => $label ) );
			return $this->stale( $key, new \WP_Error( 'sa_api_error', $message ) );
		}

		set_transient( $transient, $body, max( 30, (int) $ttl ) );
		$this->store( $key, $label, $body, $ttl );

		return $body;
	}

	/**
	 * Return the last good payload or the error.
	 *
	 * @param string    $key   Cache key.
	 * @param \WP_Error $error Error to return when nothing cached.
	 * @return array|\WP_Error
	 */
	private function stale( $key, \WP_Error $error ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom cache table.
		$payload = $wpdb->get_var( $wpdb->prepare( 'SELECT payload FROM %i WHERE cache_key = %s', $wpdb->prefix . 'sa_cache', $key ) );
		if ( $payload ) {
			$data = json_decode( $payload, true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		return $error;
	}

	/**
	 * Persist a good payload.
	 *
	 * @param string $key   Cache key.
	 * @param string $label Endpoint label.
	 * @param array  $body  Payload.
	 * @param int    $ttl   TTL.
	 * @return void
	 */
	private function store( $key, $label, array $body, $ttl ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom cache table.
		$wpdb->replace(
			$wpdb->prefix . 'sa_cache',
			array(
				'cache_key'  => $key,
				'provider'   => $this->provider,
				'endpoint'   => substr( $label, 0, 191 ),
				'payload'    => wp_json_encode( $body ),
				'expires_at' => time() + (int) $ttl,
				'updated_at' => time(),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d' )
		);
	}

	/**
	 * Quota state option name.
	 *
	 * @return string
	 */
	private function quota_option() {
		return 'sportsarena_quota_' . sanitize_key( $this->provider );
	}

	/**
	 * Current quota state.
	 *
	 * @return array{day:string,count:int,remaining:int|null,blocked_until:int}
	 */
	public function quota_state() {
		$state = wp_parse_args(
			(array) get_option( $this->quota_option(), array() ),
			array(
				'day'           => '',
				'count'         => 0,
				'remaining'     => null,
				'blocked_until' => 0,
			)
		);
		if ( gmdate( 'Y-m-d' ) !== $state['day'] ) {
			$state['day']       = gmdate( 'Y-m-d' );
			$state['count']     = 0;
			$state['remaining'] = null;
		}
		return $state;
	}

	/**
	 * Verify daily and per-minute budgets before a request.
	 *
	 * @return true|\WP_Error
	 */
	private function check_quota() {
		$state = $this->quota_state();
		if ( $state['blocked_until'] > time() ) {
			return new \WP_Error( 'sa_quota_blocked', __( 'الطلبات متوقفة مؤقتاً بسبب حد المزوّد.', 'sportsarena-core' ) );
		}
		$daily = (int) Options::get( 'daily_limit' );
		if ( $daily > 0 && $state['count'] >= $daily ) {
			return new \WP_Error( 'sa_quota_daily', __( 'تم استهلاك الحد اليومي المحدد للطلبات.', 'sportsarena-core' ) );
		}
		if ( null !== $state['remaining'] && (int) $state['remaining'] <= 0 ) {
			return new \WP_Error( 'sa_quota_provider', __( 'رصيد الطلبات لدى المزوّد انتهى لهذا اليوم.', 'sportsarena-core' ) );
		}
		$minute = (int) Options::get( 'minute_limit' );
		$used   = (int) get_transient( 'sa_minute_' . sanitize_key( $this->provider ) );
		if ( $minute > 0 && $used >= $minute ) {
			return new \WP_Error( 'sa_quota_minute', __( 'تم بلوغ حد الطلبات في الدقيقة.', 'sportsarena-core' ) );
		}
		return true;
	}

	/**
	 * Count one outgoing request.
	 *
	 * @return void
	 */
	private function record_request() {
		$state = $this->quota_state();
		++$state['count'];
		update_option( $this->quota_option(), $state, false );

		$minute_key = 'sa_minute_' . sanitize_key( $this->provider );
		$used       = (int) get_transient( $minute_key );
		set_transient( $minute_key, $used + 1, MINUTE_IN_SECONDS );
	}

	/**
	 * Read provider quota headers (API-Sports style).
	 *
	 * @param array $response HTTP response.
	 * @return void
	 */
	private function read_quota_headers( $response ) {
		$remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-requests-remaining' );
		if ( '' !== $remaining && is_numeric( $remaining ) ) {
			$state              = $this->quota_state();
			$state['remaining'] = (int) $remaining;
			update_option( $this->quota_option(), $state, false );
		}
		$minute_remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
		if ( '' !== $minute_remaining && is_numeric( $minute_remaining ) && (int) $minute_remaining <= 0 ) {
			$this->block_until( time() + MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Pause requests until a timestamp.
	 *
	 * @param int $timestamp Unix time.
	 * @return void
	 */
	private function block_until( $timestamp ) {
		$state                  = $this->quota_state();
		$state['blocked_until'] = (int) $timestamp;
		update_option( $this->quota_option(), $state, false );
	}

	/**
	 * Remove expired rows from the cache table.
	 *
	 * @return void
	 */
	public static function purge_expired() {
		global $wpdb;
		// Keep stale payloads for a week as an outage fallback.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom cache table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires_at < %d', $wpdb->prefix . 'sa_cache', time() - WEEK_IN_SECONDS ) );
	}
}
