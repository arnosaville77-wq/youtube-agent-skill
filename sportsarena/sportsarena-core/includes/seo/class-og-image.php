<?php
/**
 * Auto-generated share image for matches (1200x630, GD): both team names,
 * the competition and the date. Generated once and stored in uploads.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Seo;

use SportsArena\Core\Data\Names;
use SportsArena\Core\Post_Types;
use SportsArena\Core\Repository;
use SportsArena\Core\Time_Display;

defined( 'ABSPATH' ) || exit;

/**
 * Open Graph image generator.
 */
final class Og_Image {

	const WIDTH  = 1200;
	const HEIGHT = 630;
	const DIR    = 'sportsarena-og';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve' ), 1 );
	}

	/**
	 * Rewrite rule: /og-image/match-123.jpg.
	 *
	 * @return void
	 */
	public function rewrite() {
		add_rewrite_rule( '^og-image/match-([0-9]+)\.jpg$', 'index.php?sa_og_match=$matches[1]', 'top' );
	}

	/**
	 * Query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = 'sa_og_match';
		return $vars;
	}

	/**
	 * Public URL: the static file when already generated, otherwise the
	 * generator route (which creates the file on first request).
	 *
	 * @param int $post_id Match ID.
	 * @return string
	 */
	public static function url( $post_id ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}
		$file = self::file( $post_id );
		if ( $file && file_exists( $file['path'] ) ) {
			return $file['url'];
		}
		return home_url( '/og-image/match-' . (int) $post_id . '.jpg' );
	}

	/**
	 * Stored file location. The hash changes when names/date change.
	 *
	 * @param int $post_id Match ID.
	 * @return array{path:string,url:string}|null
	 */
	private static function file( $post_id ) {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}
		$lines = self::lines( $post_id );
		$hash  = substr( md5( wp_json_encode( $lines ) . '|v3|' . determine_locale() ), 0, 10 );
		$name  = 'match-' . (int) $post_id . '-' . $hash . '.jpg';
		return array(
			'path' => trailingslashit( $uploads['basedir'] ) . self::DIR . '/' . $name,
			'url'  => trailingslashit( $uploads['baseurl'] ) . self::DIR . '/' . $name,
		);
	}

	/**
	 * Text content of the image.
	 *
	 * @param int $post_id Match ID.
	 * @return array{site:string,competition:string,home:string,away:string,date:string}
	 */
	private static function lines( $post_id ) {
		$m      = Repository::match( $post_id );
		$decode = static function ( $text ) {
			return html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		};
		return array(
			'site'        => $decode( get_bloginfo( 'name' ) ),
			'competition' => $m['competition'] ? $decode( get_the_title( $m['competition'] ) ) : '',
			'home'        => $decode( $m['home'] ? get_the_title( $m['home'] ) : get_the_title( $post_id ) ),
			'away'        => $m['away'] ? $decode( get_the_title( $m['away'] ) ) : '',
			'date'        => $m['kickoff'] ? wp_date( 'j F Y · H:i', $m['kickoff'] ) . Time_Display::suffix() : '',
		);
	}

	/**
	 * Serve (and generate if needed) the image.
	 *
	 * @return void
	 */
	public function serve() {
		$post_id = absint( get_query_var( 'sa_og_match' ) );
		if ( ! $post_id ) {
			return;
		}
		if ( Post_Types::MATCH !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			status_header( 404 );
			exit;
		}
		$file = self::file( $post_id );
		if ( ! $file ) {
			status_header( 500 );
			exit;
		}
		if ( ! file_exists( $file['path'] ) && ! self::generate( $post_id, $file['path'] ) ) {
			status_header( 500 );
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: image/jpeg' );
		header( 'Cache-Control: public, max-age=604800' );
		header( 'Content-Length: ' . (string) filesize( $file['path'] ) );
		readfile( $file['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a generated image.
		exit;
	}

	/**
	 * Draw the image.
	 *
	 * @param int    $post_id Match ID.
	 * @param string $path    Destination.
	 * @return bool
	 */
	public static function generate( $post_id, $path ) {
		/**
		 * Filter the TTF font used for the share image. It must contain the
		 * Arabic Presentation Forms-B glyphs (the bundled DejaVu Sans Bold does).
		 *
		 * @param string $font Absolute path.
		 */
		$font = (string) apply_filters( 'sportsarena_og_font', SPORTSARENA_CORE_DIR . 'assets/fonts/DejaVuSans-Bold.ttf' );
		if ( ! is_readable( $font ) || ! function_exists( 'imagettftext' ) ) {
			return false;
		}
		wp_mkdir_p( dirname( $path ) );

		$lines = self::lines( $post_id );
		$img   = imagecreatetruecolor( self::WIDTH, self::HEIGHT );

		// Vertical gradient background.
		for ( $y = 0; $y < self::HEIGHT; $y++ ) {
			$t     = $y / self::HEIGHT;
			$color = imagecolorallocate( $img, (int) ( 10 + 8 * $t ), (int) ( 40 + 30 * $t ), (int) ( 30 + 20 * $t ) );
			imageline( $img, 0, $y, self::WIDTH, $y, $color );
		}
		$white  = imagecolorallocate( $img, 255, 255, 255 );
		$muted  = imagecolorallocate( $img, 190, 220, 205 );
		$accent = imagecolorallocate( $img, 34, 197, 94 );
		$panel  = imagecolorallocatealpha( $img, 255, 255, 255, 115 );

		imagefilledrectangle( $img, 0, 0, self::WIDTH, 10, $accent );
		imagefilledrectangle( $img, 60, 170, self::WIDTH - 60, 450, $panel );

		self::text_center( $img, $lines['site'], $font, 30, 80, $muted );
		if ( $lines['competition'] ) {
			self::text_center( $img, $lines['competition'], $font, 34, 140, $accent );
		}

		if ( $lines['away'] ) {
			// Home team on the reading-start side (right in RTL, left in LTR).
			$rtl = is_rtl() || Names::is_arabic();
			self::text_fit( $img, $lines['home'], $font, 56, 320, self::WIDTH * ( $rtl ? 0.75 : 0.25 ), 460, $white );
			/* translators: Short word between two team names on the share image. */
			self::text_center( $img, __( 'ضد', 'sportsarena-core' ), $font, 36, 330, $muted );
			self::text_fit( $img, $lines['away'], $font, 56, 320, self::WIDTH * ( $rtl ? 0.25 : 0.75 ), 460, $white );
		} else {
			self::text_fit( $img, $lines['home'], $font, 60, 330, self::WIDTH / 2, 1000, $white );
		}

		if ( $lines['date'] ) {
			self::text_center( $img, $lines['date'], $font, 34, 540, $white );
		}

		$ok = imagejpeg( $img, $path, 85 );
		imagedestroy( $img );
		return $ok;
	}

	/**
	 * Draw centered text.
	 *
	 * @param \GdImage $img   Image.
	 * @param string   $text  Text.
	 * @param string   $font  Font.
	 * @param int      $size  Size.
	 * @param int      $y     Baseline.
	 * @param int      $color Color.
	 * @return void
	 */
	private static function text_center( $img, $text, $font, $size, $y, $color ) {
		self::text_fit( $img, $text, $font, $size, $y, self::WIDTH / 2, self::WIDTH - 100, $color );
	}

	/**
	 * Draw text centered on $cx, shrinking the size until it fits $max_width.
	 *
	 * @param \GdImage $img       Image.
	 * @param string   $text      Text.
	 * @param string   $font      Font.
	 * @param int      $size      Initial size.
	 * @param int      $y         Baseline.
	 * @param float    $cx        Center X.
	 * @param float    $max_width Max width.
	 * @param int      $color     Color.
	 * @return void
	 */
	private static function text_fit( $img, $text, $font, $size, $y, $cx, $max_width, $color ) {
		// Only Arabic lines need shaping and RTL reordering.
		$shaped = Names::has_arabic( $text ) ? Arabic_Shaper::shape( $text ) : $text;
		do {
			$box   = imagettfbbox( $size, 0, $font, $shaped );
			$width = abs( $box[2] - $box[0] );
			if ( $width <= $max_width || $size <= 18 ) {
				break;
			}
			$size -= 2;
		} while ( true );
		imagettftext( $img, $size, 0, (int) ( $cx - $width / 2 ), (int) $y, $color, $font, $shaped );
	}
}
