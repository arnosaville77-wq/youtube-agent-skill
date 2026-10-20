<?php
/**
 * Minimal Arabic shaping + visual reordering for GD.
 *
 * GD's imagettftext() draws code points left-to-right without OpenType
 * shaping, so Arabic would appear disconnected and reversed. This class maps
 * letters to their contextual Presentation Forms-B glyphs (isolated, final,
 * initial, medial, lam-alef ligatures) and reorders the line for display.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Arabic shaper.
 */
final class Arabic_Shaper {

	/**
	 * Base letter => array( isolated, final, initial, medial ). Letters with
	 * two forms only join to the previous letter.
	 *
	 * @var array<int,int[]>
	 */
	const FORMS = array(
		0x0621 => array( 0xFE80 ),
		0x0622 => array( 0xFE81, 0xFE82 ),
		0x0623 => array( 0xFE83, 0xFE84 ),
		0x0624 => array( 0xFE85, 0xFE86 ),
		0x0625 => array( 0xFE87, 0xFE88 ),
		0x0626 => array( 0xFE89, 0xFE8A, 0xFE8B, 0xFE8C ),
		0x0627 => array( 0xFE8D, 0xFE8E ),
		0x0628 => array( 0xFE8F, 0xFE90, 0xFE91, 0xFE92 ),
		0x0629 => array( 0xFE93, 0xFE94 ),
		0x062A => array( 0xFE95, 0xFE96, 0xFE97, 0xFE98 ),
		0x062B => array( 0xFE99, 0xFE9A, 0xFE9B, 0xFE9C ),
		0x062C => array( 0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0 ),
		0x062D => array( 0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4 ),
		0x062E => array( 0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8 ),
		0x062F => array( 0xFEA9, 0xFEAA ),
		0x0630 => array( 0xFEAB, 0xFEAC ),
		0x0631 => array( 0xFEAD, 0xFEAE ),
		0x0632 => array( 0xFEAF, 0xFEB0 ),
		0x0633 => array( 0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4 ),
		0x0634 => array( 0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8 ),
		0x0635 => array( 0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC ),
		0x0636 => array( 0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0 ),
		0x0637 => array( 0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4 ),
		0x0638 => array( 0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8 ),
		0x0639 => array( 0xFEC9, 0xFECA, 0xFECB, 0xFECC ),
		0x063A => array( 0xFECD, 0xFECE, 0xFECF, 0xFED0 ),
		0x0641 => array( 0xFED1, 0xFED2, 0xFED3, 0xFED4 ),
		0x0642 => array( 0xFED5, 0xFED6, 0xFED7, 0xFED8 ),
		0x0643 => array( 0xFED9, 0xFEDA, 0xFEDB, 0xFEDC ),
		0x0644 => array( 0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0 ),
		0x0645 => array( 0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4 ),
		0x0646 => array( 0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8 ),
		0x0647 => array( 0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC ),
		0x0648 => array( 0xFEED, 0xFEEE ),
		0x0649 => array( 0xFEEF, 0xFEF0 ),
		0x064A => array( 0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4 ),
		0x067E => array( 0xFB56, 0xFB57, 0xFB58, 0xFB59 ), // پ.
		0x0686 => array( 0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D ), // چ.
		0x06A4 => array( 0xFB6A, 0xFB6B, 0xFB6C, 0xFB6D ), // ڤ.
		0x06AF => array( 0xFB92, 0xFB93, 0xFB94, 0xFB95 ), // گ.
	);

	/**
	 * Lam-alef ligatures: alef variant => array( isolated, final ).
	 *
	 * @var array<int,int[]>
	 */
	const LAM_ALEF = array(
		0x0622 => array( 0xFEF5, 0xFEF6 ),
		0x0623 => array( 0xFEF7, 0xFEF8 ),
		0x0625 => array( 0xFEF9, 0xFEFA ),
		0x0627 => array( 0xFEFB, 0xFEFC ),
	);

	const TATWEEL = 0x0640;

	/**
	 * Shape and reorder a line for left-to-right drawing.
	 *
	 * @param string $text UTF-8 text.
	 * @return string
	 */
	public static function shape( $text ) {
		// Diacritics are dropped: they are optional in titles and break joining.
		$text  = (string) preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $text );
		$chars = self::code_points( $text );
		$out   = array();
		$count = count( $chars );

		for ( $i = 0; $i < $count; $i++ ) {
			$c = $chars[ $i ];
			if ( ! isset( self::FORMS[ $c ] ) ) {
				$out[] = $c;
				continue;
			}
			$prev_joins = $i > 0 && self::joins_forward( $chars[ $i - 1 ] );

			// Lam + alef ligature.
			if ( 0x0644 === $c && $i + 1 < $count && isset( self::LAM_ALEF[ $chars[ $i + 1 ] ] ) ) {
				$out[] = self::LAM_ALEF[ $chars[ $i + 1 ] ][ $prev_joins ? 1 : 0 ];
				++$i;
				continue;
			}

			$forms      = self::FORMS[ $c ];
			$dual       = 4 === count( $forms );
			$next_joins = $dual && $i + 1 < $count && self::joins_backward( $chars[ $i + 1 ] );

			if ( 1 === count( $forms ) ) {
				$out[] = $forms[0];
			} elseif ( $prev_joins && $next_joins ) {
				$out[] = $forms[3];
			} elseif ( $prev_joins ) {
				$out[] = $forms[1];
			} elseif ( $next_joins ) {
				$out[] = $forms[2];
			} else {
				$out[] = $forms[0];
			}
		}

		return self::visual_order( $out );
	}

	/**
	 * Can this char connect to the following letter?
	 *
	 * @param int $c Code point.
	 * @return bool
	 */
	private static function joins_forward( $c ) {
		return self::TATWEEL === $c || ( isset( self::FORMS[ $c ] ) && 4 === count( self::FORMS[ $c ] ) );
	}

	/**
	 * Can this char connect to the preceding letter?
	 *
	 * @param int $c Code point.
	 * @return bool
	 */
	private static function joins_backward( $c ) {
		return self::TATWEEL === $c || ( isset( self::FORMS[ $c ] ) && count( self::FORMS[ $c ] ) > 1 );
	}

	/**
	 * Reorder an RTL line: runs of LTR text (Latin letters, digits and
	 * the punctuation inside them) keep their order, everything else is
	 * reversed, and brackets are mirrored.
	 *
	 * @param int[] $chars Shaped code points.
	 * @return string
	 */
	private static function visual_order( array $chars ) {
		$runs    = array();
		$current = array();
		$ltr     = null;
		foreach ( $chars as $c ) {
			$is_ltr = self::is_ltr( $c ) || ( $ltr && $current && self::is_ltr_joiner( $c ) );
			if ( null !== $ltr && $is_ltr !== $ltr ) {
				$runs[]  = array( $ltr, $current );
				$current = array();
			}
			$ltr       = $is_ltr;
			$current[] = $c;
		}
		if ( $current ) {
			$runs[] = array( $ltr, $current );
		}

		// Trailing joiners (e.g. a space after "2026") belong to the RTL flow.
		$fixed = array();
		foreach ( $runs as $run ) {
			list( $is_ltr, $cps ) = $run;
			if ( $is_ltr ) {
				$tail = array();
				while ( $cps && ! self::is_ltr( end( $cps ) ) ) {
					array_unshift( $tail, array_pop( $cps ) );
				}
				$fixed[] = array( true, $cps );
				if ( $tail ) {
					$fixed[] = array( false, $tail );
				}
			} else {
				$fixed[] = array( false, $cps );
			}
		}

		$mirror = array(
			0x28 => 0x29,
			0x29 => 0x28,
			0x5B => 0x5D,
			0x5D => 0x5B,
			0xAB => 0xBB,
			0xBB => 0xAB,
		);
		$result = '';
		foreach ( array_reverse( $fixed ) as $run ) {
			list( $is_ltr, $cps ) = $run;
			if ( ! $is_ltr ) {
				$cps = array_reverse( $cps );
				foreach ( $cps as $k => $c ) {
					if ( isset( $mirror[ $c ] ) ) {
						$cps[ $k ] = $mirror[ $c ];
					}
				}
			}
			foreach ( $cps as $c ) {
				$result .= mb_chr( $c, 'UTF-8' );
			}
		}
		return $result;
	}

	/**
	 * Strong LTR character (Latin letter or digit).
	 *
	 * @param int $c Code point.
	 * @return bool
	 */
	private static function is_ltr( $c ) {
		return ( $c >= 0x30 && $c <= 0x39 ) || ( $c >= 0x41 && $c <= 0x5A ) || ( $c >= 0x61 && $c <= 0x7A ) || ( $c >= 0xC0 && $c <= 0x24F );
	}

	/**
	 * Characters that stay inside an LTR run (2026-10-26, 20:00, A.C. Milan).
	 *
	 * @param int $c Code point.
	 * @return bool
	 */
	private static function is_ltr_joiner( $c ) {
		return in_array( $c, array( 0x20, 0x2D, 0x2E, 0x3A, 0x2F, 0x2B, 0x27 ), true );
	}

	/**
	 * UTF-8 string to code points.
	 *
	 * @param string $text Text.
	 * @return int[]
	 */
	private static function code_points( $text ) {
		$points = array();
		foreach ( (array) preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
			$points[] = mb_ord( $char, 'UTF-8' );
		}
		return $points;
	}
}
