<?php
/**
 * Public template API used by the theme. Every function is safe to call
 * from templates (the theme checks function_exists() before using them).
 *
 * @package SportsArena\Core
 */

defined( 'ABSPATH' ) || exit;

use SportsArena\Core\Options;
use SportsArena\Core\Repository;
use SportsArena\Core\Rewrites;

/**
 * All display data for a match.
 *
 * @param int $post_id Match ID.
 * @return array<string,mixed>
 */
function sportsarena_match( $post_id ) {
	return Repository::match( $post_id );
}

/**
 * Human status label.
 *
 * @param array $data Match data from sportsarena_match().
 * @return string
 */
function sportsarena_status_label( array $data ) {
	switch ( $data['status'] ) {
		case 'live':
			/* translators: %d: elapsed minutes. */
			return null !== $data['elapsed'] ? sprintf( __( 'مباشر %d\'', 'sportsarena-core' ), $data['elapsed'] ) : __( 'مباشر', 'sportsarena-core' );
		case 'halftime':
			return __( 'استراحة', 'sportsarena-core' );
		case 'finished':
			return __( 'انتهت', 'sportsarena-core' );
		case 'postponed':
			return __( 'مؤجلة', 'sportsarena-core' );
		case 'cancelled':
			return __( 'ملغاة', 'sportsarena-core' );
		default:
			return __( 'لم تبدأ', 'sportsarena-core' );
	}
}

/**
 * Whether a match is in play.
 *
 * @param array $data Match data.
 * @return bool
 */
function sportsarena_is_live( array $data ) {
	return in_array( $data['status'], array( 'live', 'halftime' ), true );
}

/**
 * Arabic round label: "Regular Season - 10" => «الجولة 10».
 *
 * @param string $round Provider round.
 * @return string
 */
function sportsarena_round_label( $round ) {
	$round = (string) $round;
	if ( preg_match( '/^Regular Season\s*-\s*(\d+)$/i', $round, $m ) ) {
		/* translators: %d: round number. */
		return sprintf( __( 'الجولة %d', 'sportsarena-core' ), (int) $m[1] );
	}
	$map = array(
		'Regular Season' => __( 'الموسم المنتظم', 'sportsarena-core' ),
		'Group Stage'    => __( 'دور المجموعات', 'sportsarena-core' ),
		'Round of 16'    => __( 'دور الـ16', 'sportsarena-core' ),
		'Quarter-finals' => __( 'ربع النهائي', 'sportsarena-core' ),
		'Semi-finals'    => __( 'نصف النهائي', 'sportsarena-core' ),
		'Final'          => __( 'النهائي', 'sportsarena-core' ),
	);
	foreach ( $map as $english => $arabic ) {
		if ( 0 === stripos( $round, $english ) ) {
			return $arabic . substr( $round, strlen( $english ) );
		}
	}
	return $round;
}

/**
 * <time> element. GMT mode: Greenwich time with a "GMT" label. Local mode:
 * the site-timezone time server side, which the theme JS converts to the
 * visitor's timezone (the original is kept as a tooltip).
 *
 * @param int    $timestamp UTC timestamp.
 * @param string $format    PHP date format.
 * @return string
 */
function sportsarena_time_html( $timestamp, $format = 'H:i' ) {
	if ( ! $timestamp ) {
		return '';
	}
	if ( SportsArena\Core\Time_Display::is_gmt() ) {
		return sprintf(
			'<time class="sa-time" datetime="%1$s">%2$s <span class="sa-tz">GMT</span></time>',
			esc_attr( gmdate( 'c', $timestamp ) ),
			esc_html( wp_date( $format, $timestamp ) )
		);
	}
	$label = (string) Options::get( 'timezone_label' );
	$title = wp_date( 'Y-m-d H:i', $timestamp ) . ( $label ? ' (' . $label . ')' : ' (' . wp_timezone_string() . ')' );
	return sprintf(
		'<time class="sa-time" datetime="%1$s" data-sa-local="%2$s" title="%3$s">%4$s</time>',
		esc_attr( gmdate( 'c', $timestamp ) ),
		esc_attr( 'H:i' === $format ? 'time' : 'datetime' ),
		esc_attr( $title ),
		esc_html( wp_date( $format, $timestamp ) )
	);
}

/**
 * Country flag image (bundled, flag-icons MIT). '' when the country is
 * unknown or "World".
 *
 * @param string $country Country name (English from the provider, or a known Arabic name).
 * @param int    $width   Pixel width (height is 3/4).
 * @return string
 */
function sportsarena_flag( $country, $width = 20 ) {
	$code = SportsArena\Core\Data\Countries::code( (string) $country );
	if ( '' === $code ) {
		return '';
	}
	$width = (int) $width;
	return sprintf(
		'<img class="sa-flag" src="%1$s" width="%2$d" height="%3$d" alt="%4$s" loading="lazy" decoding="async">',
		esc_url( SPORTSARENA_CORE_URL . 'assets/flags/' . $code . '.png' ),
		$width,
		(int) round( $width * 0.75 ),
		esc_attr( (string) $country )
	);
}

/**
 * Country (English provider name) of a team or competition.
 *
 * @param int $post_id Team or competition ID.
 * @return string
 */
function sportsarena_country_of( $post_id ) {
	$country = (string) get_post_meta( $post_id, '_sa_country_en', true );
	if ( '' === $country ) {
		$terms   = get_the_terms( $post_id, 'country' );
		$country = $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '';
	}
	return $country;
}

/**
 * Provider logo URL when logos are allowed: the local cached copy first
 * (see SportsArena\Core\Logo_Cache), the provider URL otherwise.
 *
 * @param int $post_id Team or competition ID.
 * @return string
 */
function sportsarena_provider_logo_url( $post_id ) {
	if ( ! $post_id || ! Options::get( 'allow_logos' ) ) {
		return '';
	}
	$local = (string) get_post_meta( $post_id, '_sa_logo_local', true );
	if ( '' !== $local ) {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['baseurl'] ) . ltrim( $local, '/' );
	}
	return (string) get_post_meta( $post_id, '_sa_logo_url', true );
}

/**
 * Team crest, in this order:
 * 1. The team's featured image (a logo the site owner uploaded).
 * 2. The provider logo, only when "allow_logos" is enabled (the data plan
 *    licenses them), served from the local cache when available.
 * 3. The country flag for national teams and individual athletes (tennis, MMA...).
 * 4. A generic shield with the team's initial.
 *
 * @param int $team_id Team ID.
 * @param int $size    Pixel size.
 * @return string
 */
function sportsarena_team_logo( $team_id, $size = 32 ) {
	$size  = (int) $size;
	$title = $team_id ? get_the_title( $team_id ) : '';
	if ( $team_id && has_post_thumbnail( $team_id ) ) {
		return get_the_post_thumbnail(
			$team_id,
			array( $size, $size ),
			array(
				'class'   => 'sa-logo',
				'loading' => 'lazy',
				'alt'     => $title,
			)
		);
	}
	$logo = sportsarena_provider_logo_url( $team_id );
	if ( $logo ) {
		return sprintf(
			'<img class="sa-logo" src="%1$s" width="%2$d" height="%2$d" alt="%3$s" loading="lazy" decoding="async">',
			esc_url( $logo ),
			$size,
			esc_attr( $title )
		);
	}
	if ( $team_id ) {
		$english    = (string) get_post_meta( $team_id, '_sa_name_en', true );
		$individual = has_term( array( 'tennis', 'mma', 'formula-1' ), 'sport', $team_id );
		$country    = SportsArena\Core\Data\Countries::code( $english ) ? $english : ( $individual ? sportsarena_country_of( $team_id ) : '' );
		$flag       = $country ? sportsarena_flag( $country, $size ) : '';
		if ( $flag ) {
			return str_replace( 'class="sa-flag"', 'class="sa-logo sa-logo--flag"', $flag );
		}
	}
	$initial = $title ? mb_substr( $title, 0, 1 ) : '?';
	$hue     = $team_id ? ( (int) $team_id * 47 ) % 360 : 210;
	return sprintf(
		'<svg class="sa-logo sa-logo--generic" width="%1$d" height="%1$d" viewBox="0 0 32 32" role="img" aria-label="%2$s"><path d="M16 1 3 6v9c0 8 5.6 13.6 13 16 7.4-2.4 13-8 13-16V6z" fill="hsl(%3$d 55%% 42%%)"/><text x="16" y="21" text-anchor="middle" font-size="13" font-weight="700" fill="#fff" font-family="sans-serif">%4$s</text></svg>',
		$size,
		esc_attr( $title ),
		(int) $hue,
		esc_html( $initial )
	);
}

/**
 * Competition emblem: featured image, then provider logo (when allowed),
 * then the country flag, then a neutral globe for international events.
 *
 * @param int $competition_id Competition ID.
 * @param int $size           Pixel size.
 * @return string
 */
function sportsarena_competition_logo( $competition_id, $size = 24 ) {
	$size  = (int) $size;
	$title = $competition_id ? get_the_title( $competition_id ) : '';
	if ( ! $competition_id ) {
		return '';
	}
	if ( has_post_thumbnail( $competition_id ) ) {
		return get_the_post_thumbnail(
			$competition_id,
			array( $size, $size ),
			array(
				'class'   => 'sa-logo sa-logo--comp',
				'loading' => 'lazy',
				'alt'     => $title,
			)
		);
	}
	$logo = sportsarena_provider_logo_url( $competition_id );
	if ( $logo ) {
		// The theme swaps in the country flag (or the globe for international
		// competitions) if the emblem cannot be loaded.
		$code     = SportsArena\Core\Data\Countries::code( sportsarena_country_of( $competition_id ) );
		$fallback = $code ? SPORTSARENA_CORE_URL . 'assets/flags/' . $code . '.png' : 'data:image/svg+xml,' . rawurlencode( sportsarena_globe_svg() );
		return sprintf(
			'<img class="sa-logo sa-logo--comp" src="%1$s" width="%2$d" height="%2$d" alt="%3$s" loading="lazy" decoding="async" data-sa-fallback="%4$s">',
			esc_url( $logo ),
			$size,
			esc_attr( $title ),
			esc_attr( $fallback )
		);
	}
	$flag = sportsarena_flag( sportsarena_country_of( $competition_id ), $size );
	if ( $flag ) {
		return $flag;
	}
	return str_replace(
		'<svg ',
		sprintf( '<svg class="sa-flag sa-flag--world" width="%1$d" height="%2$d" role="img" aria-label="%3$s" ', $size, (int) round( $size * 0.75 ), esc_attr( $title ) ),
		sportsarena_globe_svg()
	);
}

/**
 * Neutral globe emblem for international competitions (no country flag).
 *
 * @return string SVG markup.
 */
function sportsarena_globe_svg() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 18"><rect width="24" height="18" rx="2" fill="#1d4ed8"/><circle cx="12" cy="9" r="6" fill="none" stroke="#fff" stroke-width="1.2"/><path d="M6 9h12M12 3c-2.5 3.4-2.5 8.6 0 12M12 3c2.5 3.4 2.5 8.6 0 12" fill="none" stroke="#fff" stroke-width="1"/></svg>';
}

/**
 * URL of a day page (today|tomorrow|yesterday|Y-m-d).
 *
 * @param string $day Day.
 * @return string
 */
function sportsarena_day_url( $day ) {
	return Rewrites::day_url( $day );
}

/**
 * Resolved Y-m-d of the current day page.
 *
 * @return string|null
 */
function sportsarena_current_day() {
	$day = get_query_var( 'sa_day' );
	return $day ? Rewrites::resolve_day( $day ) : null;
}

/**
 * Competition tab URL.
 *
 * @param int    $competition_id Competition ID.
 * @param string $tab            matches|standings|scorers|''.
 * @return string
 */
function sportsarena_competition_tab_url( $competition_id, $tab = '' ) {
	return Rewrites::competition_tab_url( $competition_id, $tab );
}

/**
 * Render an ad slot (echoes). Does nothing when the slot is disabled, when
 * Auto Ads mode is on, or on pages where ads are not allowed.
 *
 * @param string $slot Slot id (header|in_article|sidebar|between_lists|match_bottom).
 * @return void
 */
function sportsarena_ad_slot( $slot ) {
	echo SportsArena\Core\Ads\Ads::render( $slot ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in Ads::render().
}

/**
 * Echo the visible breadcrumbs.
 *
 * @return void
 */
function sportsarena_breadcrumbs() {
	echo SportsArena\Core\Seo\Breadcrumbs::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render().
}

/**
 * Arabic label of a normalized event type.
 *
 * @param string $type Event type.
 * @return string
 */
function sportsarena_event_label( $type ) {
	$labels = array(
		'goal'           => __( 'هدف', 'sportsarena-core' ),
		'own_goal'       => __( 'هدف عكسي', 'sportsarena-core' ),
		'penalty'        => __( 'ركلة جزاء', 'sportsarena-core' ),
		'missed_penalty' => __( 'ركلة جزاء ضائعة', 'sportsarena-core' ),
		'yellow'         => __( 'بطاقة صفراء', 'sportsarena-core' ),
		'red'            => __( 'بطاقة حمراء', 'sportsarena-core' ),
		'sub'            => __( 'تبديل', 'sportsarena-core' ),
		'var'            => __( 'فار', 'sportsarena-core' ),
	);
	return $labels[ $type ] ?? $type;
}

/**
 * Arabic label for common statistic names.
 *
 * @param string $type Provider stat name.
 * @return string
 */
function sportsarena_stat_label( $type ) {
	$labels = array(
		'Ball Possession'  => __( 'الاستحواذ', 'sportsarena-core' ),
		'Total Shots'      => __( 'إجمالي التسديدات', 'sportsarena-core' ),
		'Shots on Goal'    => __( 'تسديدات على المرمى', 'sportsarena-core' ),
		'Shots off Goal'   => __( 'تسديدات خارج المرمى', 'sportsarena-core' ),
		'Blocked Shots'    => __( 'تسديدات محجوبة', 'sportsarena-core' ),
		'Corner Kicks'     => __( 'الركلات الركنية', 'sportsarena-core' ),
		'Offsides'         => __( 'التسلل', 'sportsarena-core' ),
		'Fouls'            => __( 'الأخطاء', 'sportsarena-core' ),
		'Yellow Cards'     => __( 'البطاقات الصفراء', 'sportsarena-core' ),
		'Red Cards'        => __( 'البطاقات الحمراء', 'sportsarena-core' ),
		'Goalkeeper Saves' => __( 'تصديات الحارس', 'sportsarena-core' ),
		'Total passes'     => __( 'إجمالي التمريرات', 'sportsarena-core' ),
		'Passes accurate'  => __( 'تمريرات صحيحة', 'sportsarena-core' ),
		'Passes %'         => __( 'دقة التمرير', 'sportsarena-core' ),
		'expected_goals'   => __( 'الأهداف المتوقعة (xG)', 'sportsarena-core' ),
	);
	return $labels[ $type ] ?? $type;
}

/**
 * Related news for a set of teams / competitions.
 *
 * @param int[] $team_ids        Team IDs.
 * @param int[] $competition_ids Competition IDs.
 * @param int   $limit           Max posts.
 * @param int   $exclude         Post to exclude.
 * @return int[]
 */
function sportsarena_related_news( array $team_ids, array $competition_ids, $limit = 6, $exclude = 0 ) {
	return Repository::related_news( $team_ids, $competition_ids, $limit, $exclude );
}

/**
 * Create the required legal / info pages (used by the theme on activation).
 *
 * @return array<string,int>
 */
function sportsarena_create_required_pages() {
	SportsArena\Core\News_Sections::create();
	return SportsArena\Core\Pages::create_required_pages();
}

/**
 * IDs of the required pages keyed by type (about|contact|privacy|terms|disclaimer).
 *
 * @return array<string,int>
 */
function sportsarena_required_pages() {
	return SportsArena\Core\Pages::ids();
}

/**
 * URL of the big matches page (/big-matches/).
 *
 * @return string
 */
function sportsarena_big_matches_url() {
	return SportsArena\Core\Big_Matches::url();
}

/**
 * The UEFA Champions League competition post ID, 0 if not imported.
 *
 * @return int
 */
function sportsarena_champions_league_id() {
	return SportsArena\Core\Big_Matches::champions_league();
}

/**
 * Player, venue or referee name in the language of the page.
 *
 * @param string $text Name as stored.
 * @return string
 */
function sportsarena_text( $text ) {
	return SportsArena\Core\Languages::text( (string) $text );
}

/**
 * Language switcher links for the current page (built-in multilingual mode).
 *
 * @return array<int,array{code:string,name:string,locale:string,url:string,current:bool}>
 */
function sportsarena_language_links() {
	return SportsArena\Core\Languages::links();
}
