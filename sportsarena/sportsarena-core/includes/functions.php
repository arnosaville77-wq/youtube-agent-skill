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
 * <time> element: shows the site-timezone time server side; the theme JS
 * converts it to the visitor's timezone and keeps the original as a tooltip.
 *
 * @param int    $timestamp UTC timestamp.
 * @param string $format    PHP date format.
 * @return string
 */
function sportsarena_time_html( $timestamp, $format = 'H:i' ) {
	if ( ! $timestamp ) {
		return '';
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
 * Team crest. Provider logos are only shown when the site owner confirms the
 * data plan licenses them ("allow_logos"); otherwise a generic shield with
 * the team's initial is rendered.
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
	$logo = $team_id ? (string) get_post_meta( $team_id, '_sa_logo_url', true ) : '';
	if ( $logo && Options::get( 'allow_logos' ) ) {
		return sprintf(
			'<img class="sa-logo" src="%1$s" width="%2$d" height="%2$d" alt="%3$s" loading="lazy" decoding="async">',
			esc_url( $logo ),
			$size,
			esc_attr( $title )
		);
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
