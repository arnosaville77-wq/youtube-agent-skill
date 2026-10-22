<?php
/**
 * Installable app (PWA): web app manifest, service worker and offline page.
 *
 * - /manifest.webmanifest: name, icons, colours and shortcuts in the page
 *   language (/en/manifest.webmanifest for English, etc.).
 * - /sw.js: caches the theme files and the pages already visited, so the app
 *   opens fast and still shows the last pages without a connection. Pages
 *   always come from the network first, so scores are never served stale
 *   while online. Live scores, admin, login, REST, feeds and ads are never
 *   cached.
 * - /?sa_offline=1: the page shown offline when a page was never visited.
 *
 * Disable with: add_filter( 'sportsarena_pwa_enabled', '__return_false' );
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the app features are on.
 *
 * @return bool
 */
function sportsarena_theme_pwa_enabled() {
	return (bool) apply_filters( 'sportsarena_pwa_enabled', true );
}

/**
 * Site root path without the trailing slash ('' for a site at the domain root).
 *
 * @return string
 */
function sportsarena_theme_pwa_base() {
	return rtrim( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
}

/**
 * Root URL of the service worker (never language-prefixed: one worker for
 * the whole site).
 *
 * @return string
 */
function sportsarena_theme_pwa_sw_url() {
	return untrailingslashit( (string) get_option( 'home' ) ) . '/sw.js';
}

/**
 * Offline page URL in the current language.
 *
 * @return string
 */
function sportsarena_theme_pwa_offline_url() {
	return add_query_arg( 'sa_offline', '1', home_url( '/' ) );
}

/**
 * Serve the manifest and the service worker before WordPress parses the
 * request (they are virtual files).
 *
 * @return void
 */
function sportsarena_theme_pwa_serve() {
	if ( ! sportsarena_theme_pwa_enabled() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	$base = sportsarena_theme_pwa_base();
	if ( '' !== $base && str_starts_with( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	if ( '/manifest.webmanifest' === $path ) {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( sportsarena_theme_pwa_manifest(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		exit;
	}
	if ( '/sw.js' === $path ) {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		header( 'Service-Worker-Allowed: ' . ( '' !== $base ? $base : '' ) . '/' );
		header( 'X-Robots-Tag: noindex' );
		echo sportsarena_theme_pwa_worker(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript built from JSON-encoded values.
		exit;
	}
}
add_action( 'init', 'sportsarena_theme_pwa_serve', 1 );

/**
 * App icons: the site icon (Appearance > Customize > Site Identity) when
 * set, the bundled icons otherwise.
 *
 * @return array<int,array<string,string>>
 */
function sportsarena_theme_pwa_icons() {
	if ( has_site_icon() ) {
		return array(
			array(
				'src'   => get_site_icon_url( 192 ),
				'sizes' => '192x192',
				'type'  => 'image/png',
			),
			array(
				'src'   => get_site_icon_url( 512 ),
				'sizes' => '512x512',
				'type'  => 'image/png',
			),
		);
	}
	$dir = SPORTSARENA_THEME_URI . '/assets/icons/';
	return array(
		array(
			'src'   => $dir . 'icon-192.png',
			'sizes' => '192x192',
			'type'  => 'image/png',
		),
		array(
			'src'   => $dir . 'icon-512.png',
			'sizes' => '512x512',
			'type'  => 'image/png',
		),
		array(
			'src'     => $dir . 'maskable-512.png',
			'sizes'   => '512x512',
			'type'    => 'image/png',
			'purpose' => 'maskable',
		),
	);
}

/**
 * Manifest in the page language.
 *
 * @return array<string,mixed>
 */
function sportsarena_theme_pwa_manifest() {
	$name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$start = wp_make_link_relative( home_url( '/' ) );
	$scope = sportsarena_theme_pwa_base() . '/';
	$short = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 12 ) : substr( $name, 0, 12 );

	$shortcuts = array();
	if ( function_exists( 'sportsarena_day_url' ) ) {
		$shortcuts[] = array(
			'name' => __( 'مباريات اليوم', 'sportsarena-theme' ),
			'url'  => wp_make_link_relative( sportsarena_day_url( 'today' ) ),
		);
		$shortcuts[] = array(
			'name' => __( 'المباريات الكبرى', 'sportsarena-theme' ),
			'url'  => wp_make_link_relative( sportsarena_big_matches_url() ),
		);
	}
	$news = sportsarena_theme_news_page();
	if ( $news ) {
		$shortcuts[] = array(
			'name' => __( 'الأخبار', 'sportsarena-theme' ),
			'url'  => wp_make_link_relative( (string) get_permalink( $news ) ),
		);
	}

	return array(
		'id'               => $start,
		'name'             => $name,
		'short_name'       => $short,
		'description'      => wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
		'lang'             => str_replace( '_', '-', determine_locale() ),
		'dir'              => is_rtl() ? 'rtl' : 'ltr',
		'start_url'        => $start,
		'scope'            => $scope,
		'display'          => 'standalone',
		'orientation'      => 'any',
		'background_color' => sportsarena_theme_palette_header(),
		'theme_color'      => sportsarena_theme_palette_header(),
		'categories'       => array( 'sports', 'news' ),
		'icons'            => sportsarena_theme_pwa_icons(),
		'shortcuts'        => $shortcuts,
	);
}

/**
 * Offline page URLs of every language (pre-cached when the worker installs).
 *
 * @return string[]
 */
function sportsarena_theme_pwa_offline_urls() {
	$urls = array( wp_make_link_relative( add_query_arg( 'sa_offline', '1', trailingslashit( (string) get_option( 'home' ) ) ) ) );
	if ( class_exists( 'SportsArena\\Core\\Languages' ) && SportsArena\Core\Languages::multilingual() ) {
		foreach ( SportsArena\Core\Languages::enabled() as $code ) {
			$urls[] = wp_make_link_relative( add_query_arg( 'sa_offline', '1', trailingslashit( SportsArena\Core\Languages::home( $code ) ) ) );
		}
	}
	return array_values( array_unique( $urls ) );
}

/**
 * Service worker source.
 *
 * @return string
 */
function sportsarena_theme_pwa_worker() {
	$config = array(
		'version' => SPORTSARENA_THEME_VERSION . '-' . substr( md5( (string) filemtime( SPORTSARENA_THEME_DIR . '/assets/js/app.js' ) . filemtime( SPORTSARENA_THEME_DIR . '/assets/css/main.css' ) . filemtime( __FILE__ ) ), 0, 8 ),
		'base'    => sportsarena_theme_pwa_base(),
		'offline' => sportsarena_theme_pwa_offline_urls(),
	);
	$js = <<<'JS'
/* SportsArena service worker: fast repeat visits and offline pages. */
'use strict';
var STATIC = 'sa-static-' + CFG.version;
var PAGES = 'sa-pages-' + CFG.version;
var MAX_PAGES = 40;
var SKIP = /\/(wp-admin|wp-login\.php|wp-json|xmlrpc\.php|wp-cron\.php|feed)(\/|$|\?)|sitemap|\.xml$|ads\.txt$|\/sw\.js$/;

self.addEventListener( 'install', function ( event ) {
	event.waitUntil(
		caches.open( PAGES ).then( function ( cache ) {
			return Promise.all( CFG.offline.map( function ( url ) {
				return cache.add( new Request( url, { credentials: 'same-origin' } ) ).catch( function () {} );
			} ) );
		} ).then( function () {
			return self.skipWaiting();
		} )
	);
} );

self.addEventListener( 'activate', function ( event ) {
	event.waitUntil(
		caches.keys().then( function ( keys ) {
			return Promise.all( keys.filter( function ( key ) {
				return key.indexOf( 'sa-' ) === 0 && key !== STATIC && key !== PAGES;
			} ).map( function ( key ) {
				return caches.delete( key );
			} ) );
		} ).then( function () {
			return self.clients.claim();
		} )
	);
} );

function trim( cache ) {
	cache.keys().then( function ( keys ) {
		var extra = keys.filter( function ( r ) {
			return r.url.indexOf( 'sa_offline=1' ) === -1;
		} ).length - MAX_PAGES;
		keys.forEach( function ( r ) {
			if ( extra > 0 && r.url.indexOf( 'sa_offline=1' ) === -1 ) {
				cache.delete( r );
				extra--;
			}
		} );
	} );
}

function offlinePage( url ) {
	return caches.open( PAGES ).then( function ( cache ) {
		var rest = url.pathname.slice( CFG.base.length );
		var match = /^\/([a-z]{2})(\/|$)/.exec( rest );
		var own = CFG.offline.filter( function ( u ) {
			return match && u.indexOf( CFG.base + '/' + match[ 1 ] + '/' ) === 0;
		} )[ 0 ];
		return cache.match( own || CFG.offline[ 0 ] ).then( function ( res ) {
			return res || cache.match( CFG.offline[ 0 ] );
		} );
	} ).then( function ( res ) {
		return res || new Response( '<h1>Offline</h1>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } } );
	} );
}

self.addEventListener( 'fetch', function ( event ) {
	var req = event.request;
	if ( req.method !== 'GET' ) {
		return;
	}
	var url = new URL( req.url );
	var sameOrigin = url.origin === self.location.origin;

	// Fonts: cache first.
	if ( url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com' ) {
		event.respondWith(
			caches.open( STATIC ).then( function ( cache ) {
				return cache.match( req ).then( function ( hit ) {
					return hit || fetch( req ).then( function ( res ) {
						if ( res.ok || res.type === 'opaque' ) {
							cache.put( req, res.clone() );
						}
						return res;
					} );
				} );
			} )
		);
		return;
	}
	// Everything else from other sites (ads, analytics...) is left to the network.
	if ( ! sameOrigin || SKIP.test( url.pathname ) || url.searchParams.has( 'preview' ) || url.searchParams.has( 's' ) ) {
		return;
	}

	// Pages: network first (fresh scores), the cached copy when offline.
	if ( req.mode === 'navigate' ) {
		event.respondWith(
			fetch( req ).then( function ( res ) {
				if ( res.ok && res.type === 'basic' ) {
					var copy = res.clone();
					caches.open( PAGES ).then( function ( cache ) {
						cache.put( req, copy ).then( function () {
							trim( cache );
						} );
					} );
				}
				return res;
			} ).catch( function () {
				return caches.match( req, { ignoreVary: true } ).then( function ( hit ) {
					return hit || offlinePage( url );
				} );
			} )
		);
		return;
	}

	// Theme, plugin and uploaded files: cached copy now, refreshed in the background.
	if ( /\/wp-(content|includes)\//.test( url.pathname ) && /\.(css|js|png|jpe?g|webp|gif|svg|woff2?)$/.test( url.pathname ) ) {
		event.respondWith(
			caches.open( STATIC ).then( function ( cache ) {
				return cache.match( req ).then( function ( hit ) {
					var network = fetch( req ).then( function ( res ) {
						if ( res.ok ) {
							cache.put( req, res.clone() );
						}
						return res;
					} ).catch( function () {
						return hit;
					} );
					return hit || network;
				} );
			} )
		);
	}
} );
JS;
	return 'var CFG = ' . wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) . ";\n" . $js;
}

/**
 * Manifest link, iOS app tags and theme colour.
 *
 * @return void
 */
function sportsarena_theme_pwa_head() {
	printf( '<meta name="theme-color" content="%s" media="(prefers-color-scheme: light)">' . "\n", esc_attr( sportsarena_theme_palette_header() ) );
	printf( '<meta name="theme-color" content="%s" media="(prefers-color-scheme: dark)">' . "\n", esc_attr( sportsarena_theme_palette_header( true ) ) );
	if ( ! sportsarena_theme_pwa_enabled() ) {
		return;
	}
	printf( '<link rel="manifest" href="%s">' . "\n", esc_url( home_url( '/manifest.webmanifest' ) ) );
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
	printf( '<meta name="apple-mobile-web-app-title" content="%s">' . "\n", esc_attr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) );
	if ( ! has_site_icon() ) {
		printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( SPORTSARENA_THEME_URI . '/assets/icons/apple-touch-icon.png' ) );
		printf( '<link rel="icon" type="image/png" sizes="192x192" href="%s">' . "\n", esc_url( SPORTSARENA_THEME_URI . '/assets/icons/icon-192.png' ) );
	}
}
add_action( 'wp_head', 'sportsarena_theme_pwa_head', 2 );

/**
 * Whether the current request is the offline page.
 *
 * @return bool
 */
function sportsarena_theme_is_offline_page() {
	return isset( $_GET['sa_offline'] ) && '1' === $_GET['sa_offline']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display switch.
}

/**
 * Show the offline page instead of the home page for ?sa_offline=1.
 *
 * @param string $template Template.
 * @return string
 */
function sportsarena_theme_offline_template( $template ) {
	if ( sportsarena_theme_is_offline_page() ) {
		$offline = SPORTSARENA_THEME_DIR . '/offline.php';
		if ( is_readable( $offline ) ) {
			return $offline;
		}
	}
	return $template;
}
add_filter( 'template_include', 'sportsarena_theme_offline_template' );

/**
 * The offline page is never indexed.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function sportsarena_theme_offline_robots( $robots ) {
	if ( sportsarena_theme_is_offline_page() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'sportsarena_theme_offline_robots' );

/**
 * Title of the offline page.
 *
 * @param string $title Title.
 * @return string
 */
function sportsarena_theme_offline_title( $title ) {
	return sportsarena_theme_is_offline_page() ? __( 'لا يوجد اتصال بالإنترنت', 'sportsarena-theme' ) . ' – ' . get_bloginfo( 'name' ) : $title;
}
add_filter( 'pre_get_document_title', 'sportsarena_theme_offline_title', 20 );

/**
 * No ads on the offline page (they cannot load anyway).
 *
 * @param bool $allowed Allowed.
 * @return bool
 */
function sportsarena_theme_offline_no_ads( $allowed ) {
	return sportsarena_theme_is_offline_page() ? false : $allowed;
}
add_filter( 'sportsarena_ads_allowed', 'sportsarena_theme_offline_no_ads' );
