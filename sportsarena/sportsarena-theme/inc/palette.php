<?php
/**
 * Colour palettes: Appearance > Customize > Colours > "Site colours".
 *
 * Each palette only overrides the CSS colour tokens (header, menu, accent,
 * live), so every page follows. The default stays navy + red.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Available palettes: key => [label, light tokens, dark tokens].
 *
 * @return array<string,array{0:string,1:array<string,string>,2:array<string,string>}>
 */
function sportsarena_theme_palettes() {
	return array(
		'navy-red'        => array(
			__( 'كحلي + أحمر', 'sportsarena-theme' ),
			array(),
			array(),
		),
		'pitch-gold'      => array(
			__( 'أخضر الملعب + ذهبي', 'sportsarena-theme' ),
			array(
				'--brand'       => '#0b3324',
				'--brand-2'     => '#0f4430',
				'--header-bg'   => '#0b3324',
				'--accent'      => '#c99700',
				'--accent-text' => '#1a1300',
				'--live'        => '#e11d48',
			),
			array(
				'--brand'       => '#04180f',
				'--brand-2'     => '#0a2a1d',
				'--header-bg'   => '#04180f',
				'--accent'      => '#f2b705',
				'--accent-text' => '#1a1300',
				'--live'        => '#fb7185',
			),
		),
		'midnight-orange' => array(
			__( 'أزرق ليلي + برتقالي', 'sportsarena-theme' ),
			array(
				'--brand'       => '#0b1530',
				'--brand-2'     => '#14234a',
				'--header-bg'   => '#0b1530',
				'--accent'      => '#e85d04',
				'--accent-text' => '#fff',
				'--live'        => '#dc2626',
			),
			array(
				'--brand'       => '#050b1c',
				'--brand-2'     => '#0d1836',
				'--header-bg'   => '#050b1c',
				'--accent'      => '#ff7a1a',
				'--accent-text' => '#1f0d00',
				'--live'        => '#f87171',
			),
		),
		'ocean-teal'      => array(
			__( 'أزرق محيطي + فيروزي', 'sportsarena-theme' ),
			array(
				'--brand'       => '#06283d',
				'--brand-2'     => '#0a3550',
				'--header-bg'   => '#06283d',
				'--accent'      => '#0f8f84',
				'--accent-text' => '#fff',
				'--live'        => '#e11d48',
			),
			array(
				'--brand'       => '#021420',
				'--brand-2'     => '#072536',
				'--header-bg'   => '#021420',
				'--accent'      => '#2dd4bf',
				'--accent-text' => '#022c26',
				'--live'        => '#fb7185',
			),
		),
		'royal-purple'    => array(
			__( 'بنفسجي ملكي + أخضر', 'sportsarena-theme' ),
			array(
				'--brand'       => '#1e1240',
				'--brand-2'     => '#2b1b57',
				'--header-bg'   => '#1e1240',
				'--accent'      => '#15803d',
				'--accent-text' => '#fff',
				'--live'        => '#dc2626',
			),
			array(
				'--brand'       => '#0f0824',
				'--brand-2'     => '#1b1038',
				'--header-bg'   => '#0f0824',
				'--accent'      => '#22c55e',
				'--accent-text' => '#04210f',
				'--live'        => '#f87171',
			),
		),
	);
}

/**
 * Current palette key.
 *
 * @return string
 */
function sportsarena_theme_palette() {
	$key = (string) get_theme_mod( 'sportsarena_palette', 'navy-red' );
	return isset( sportsarena_theme_palettes()[ $key ] ) ? $key : 'navy-red';
}

/**
 * Header colour of the current palette (browser bar and installed app).
 *
 * @param bool $dark Dark mode colour.
 * @return string
 */
function sportsarena_theme_palette_header( $dark = false ) {
	$palette = sportsarena_theme_palettes()[ sportsarena_theme_palette() ];
	$tokens  = $dark ? $palette[2] : $palette[1];
	return $tokens['--header-bg'] ?? ( $dark ? '#020c15' : '#071a2b' );
}

/**
 * Customizer control.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 * @return void
 */
function sportsarena_theme_palette_customizer( $wp_customize ) {
	$choices = array();
	foreach ( sportsarena_theme_palettes() as $key => $palette ) {
		$choices[ $key ] = $palette[0];
	}
	$wp_customize->add_setting(
		'sportsarena_palette',
		array(
			'default'           => 'navy-red',
			'sanitize_callback' => static function ( $value ) {
				return isset( sportsarena_theme_palettes()[ $value ] ) ? $value : 'navy-red';
			},
		)
	);
	$wp_customize->add_control(
		'sportsarena_palette',
		array(
			'label'       => __( 'ألوان الموقع', 'sportsarena-theme' ),
			'description' => __( 'لون الهيدر والقائمة والأزرار. لون المباريات المباشرة يبقى أحمر.', 'sportsarena-theme' ),
			'section'     => 'colors',
			'type'        => 'radio',
			'choices'     => $choices,
		)
	);
}
add_action( 'customize_register', 'sportsarena_theme_palette_customizer' );

/**
 * Print the palette tokens right after the critical CSS.
 *
 * @return void
 */
function sportsarena_theme_palette_css() {
	$palette = sportsarena_theme_palettes()[ sportsarena_theme_palette() ];
	if ( ! $palette[1] ) {
		return;
	}
	$css = '';
	foreach ( array(
		':root'               => $palette[1],
		'[data-theme="dark"]' => $palette[2],
	) as $selector => $tokens ) {
		$rules = '';
		foreach ( $tokens as $name => $value ) {
			$rules .= $name . ':' . $value . ';';
		}
		$css .= $selector . '{' . $rules . '}';
	}
	echo '<style id="sa-palette">' . wp_strip_all_tags( $css ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed tokens from the palette list.
}
add_action( 'wp_head', 'sportsarena_theme_palette_css', 1 );
