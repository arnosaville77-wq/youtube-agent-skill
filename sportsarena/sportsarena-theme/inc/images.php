<?php
/**
 * Images: WebP generation for uploads, lazy loading with explicit sizes.
 *
 * WordPress already adds width/height and loading="lazy" to content and
 * attachment images; the helpers below keep the same behaviour for theme
 * cards and make the first (LCP) image eager with fetchpriority="high".
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generate WebP sub-sizes for JPEG/PNG uploads when the server supports it.
 *
 * @param array $formats Output formats.
 * @return array
 */
function sportsarena_theme_webp_output( $formats ) {
	if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
	}
	return $formats;
}
add_filter( 'image_editor_output_format', 'sportsarena_theme_webp_output' );

/**
 * Card image (or a neutral placeholder with fixed dimensions).
 *
 * @param int    $post_id Post ID.
 * @param string $size    Image size.
 * @param bool   $eager   Load eagerly (above the fold / LCP).
 * @return string
 */
function sportsarena_theme_card_image( $post_id, $size = 'sportsarena-card', $eager = false ) {
	if ( has_post_thumbnail( $post_id ) ) {
		$attrs = array(
			'class'    => 'sa-card__img',
			'loading'  => $eager ? 'eager' : 'lazy',
			'decoding' => 'async',
		);
		if ( $eager ) {
			$attrs['fetchpriority'] = 'high';
		}
		return get_the_post_thumbnail( $post_id, $size, $attrs );
	}
	// Generic placeholder (no protected club logos or player photos).
	return '<span class="sa-card__img sa-card__img--placeholder" aria-hidden="true"><svg viewBox="0 0 64 36" width="640" height="360"><rect width="64" height="36" fill="currentColor" opacity=".08"/><circle cx="32" cy="18" r="9" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".35"/><path d="M32 9v18M23 18h18" stroke="currentColor" stroke-width="1" opacity=".25"/></svg></span>';
}
