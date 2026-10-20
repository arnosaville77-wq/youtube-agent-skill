<?php
/**
 * Template helpers. Plugin functions are always guarded so the theme keeps
 * working (news only) when SportsArena Core is inactive.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the companion plugin is active.
 *
 * @return bool
 */
function sportsarena_theme_core() {
	return function_exists( 'sportsarena_match' );
}

/**
 * Print an ad slot (no-op without the plugin).
 *
 * @param string $slot Slot id.
 * @return void
 */
function sportsarena_theme_ad( $slot ) {
	if ( function_exists( 'sportsarena_ad_slot' ) ) {
		sportsarena_ad_slot( $slot );
	}
}

/**
 * Print a single match row.
 *
 * @param int  $match_id         Match ID.
 * @param bool $show_competition Show the competition name.
 * @return void
 */
function sportsarena_theme_match_row( $match_id, $show_competition = false ) {
	get_template_part(
		'template-parts/match-row',
		null,
		array(
			'id'               => (int) $match_id,
			'show_competition' => (bool) $show_competition,
		)
	);
}

/**
 * Print matches grouped by competition (ad slot every 3 groups).
 *
 * @param array  $groups Groups from the repository.
 * @param string $empty  Empty state message.
 * @return void
 */
function sportsarena_theme_match_list( array $groups, $empty = '' ) {
	get_template_part(
		'template-parts/match-list',
		null,
		array(
			'groups' => $groups,
			'empty'  => $empty,
		)
	);
}

/**
 * Print a flat list of match rows.
 *
 * @param int[]  $ids              Match IDs.
 * @param string $empty            Empty message.
 * @param bool   $show_competition Show competitions.
 * @return void
 */
function sportsarena_theme_match_rows( array $ids, $empty = '', $show_competition = true ) {
	if ( ! $ids ) {
		if ( $empty ) {
			echo '<p class="sa-empty">' . esc_html( $empty ) . '</p>';
		}
		return;
	}
	echo '<ul class="sa-matches" role="list">';
	foreach ( $ids as $id ) {
		sportsarena_theme_match_row( $id, $show_competition );
	}
	echo '</ul>';
}

/**
 * Print post meta (date + category).
 *
 * @return void
 */
function sportsarena_theme_posted_on() {
	printf(
		'<span class="sa-meta"><time datetime="%1$s">%2$s</time>%3$s</span>',
		esc_attr( get_the_date( 'c' ) ),
		esc_html( get_the_date() ),
		has_category() ? ' · ' . get_the_category_list( wp_get_list_item_separator() ) : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escaped list.
	);
}

/**
 * Numbered pagination.
 *
 * @return void
 */
function sportsarena_theme_pagination() {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => __( 'السابق', 'sportsarena-theme' ),
			'next_text'          => __( 'التالي', 'sportsarena-theme' ),
			'screen_reader_text' => __( 'التنقل بين الصفحات', 'sportsarena-theme' ),
		)
	);
}

/**
 * Sport terms (for the sports navigation and tabs).
 *
 * @return WP_Term[]
 */
function sportsarena_theme_sports() {
	if ( ! taxonomy_exists( 'sport' ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'sport',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Footer links to the required pages (used when no footer menu is set).
 *
 * @return void
 */
function sportsarena_theme_required_links() {
	if ( ! function_exists( 'sportsarena_required_pages' ) ) {
		wp_page_menu(
			array(
				'depth'      => 1,
				'menu_class' => 'sa-footer__menu',
			)
		);
		return;
	}
	$order = array( 'about', 'contact', 'privacy', 'terms', 'disclaimer' );
	$pages = sportsarena_required_pages();
	echo '<ul class="sa-footer__menu">';
	foreach ( $order as $key ) {
		if ( ! empty( $pages[ $key ] ) && 'publish' === get_post_status( $pages[ $key ] ) ) {
			printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( get_permalink( $pages[ $key ] ) ), esc_html( get_the_title( $pages[ $key ] ) ) );
		}
	}
	echo '</ul>';
}

/**
 * Score / time markup for a match (also used by the live updater).
 *
 * @param array $m Match data.
 * @return string
 */
function sportsarena_theme_score_html( array $m ) {
	if ( in_array( $m['status'], array( 'scheduled', 'postponed', 'cancelled' ), true ) || null === $m['home_score'] ) {
		return '<span class="sa-score sa-score--time">' . sportsarena_time_html( $m['kickoff'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper.
	}
	return sprintf(
		'<span class="sa-score"><span class="sa-score__home" data-sa-home>%1$s</span><span class="sa-score__sep">-</span><span class="sa-score__away" data-sa-away>%2$s</span></span>',
		esc_html( (string) $m['home_score'] ),
		esc_html( (string) (int) $m['away_score'] )
	);
}

/**
 * Whether a match should be polled by the live updater: in play, or due
 * to start within 15 minutes (or recently kicked off but not yet synced).
 *
 * @param array $m Match data.
 * @return bool
 */
function sportsarena_theme_should_poll( array $m ) {
	if ( sportsarena_is_live( $m ) ) {
		return true;
	}
	return 'scheduled' === $m['status'] && $m['kickoff'] && $m['kickoff'] - time() < 15 * MINUTE_IN_SECONDS && time() - $m['kickoff'] < 3 * HOUR_IN_SECONDS;
}

/**
 * Language switcher (Polylang or WPML), shown only when a multilingual
 * plugin is active and more than one language exists.
 *
 * @return void
 */
function sportsarena_theme_language_switcher() {
	if ( function_exists( 'pll_the_languages' ) ) {
		$items = pll_the_languages(
			array(
				'echo'                   => 0,
				'show_flags'             => 0,
				'display_names_as'       => 'slug',
				'hide_if_no_translation' => 0,
			)
		);
		if ( $items ) {
			echo '<ul class="sa-langs" aria-label="' . esc_attr__( 'اللغة', 'sportsarena-theme' ) . '">' . $items . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup built and escaped by Polylang.
		}
		return;
	}
	$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API.
	if ( is_array( $languages ) && count( $languages ) > 1 ) {
		echo '<ul class="sa-langs" aria-label="' . esc_attr__( 'اللغة', 'sportsarena-theme' ) . '">';
		foreach ( $languages as $language ) {
			printf(
				'<li><a href="%1$s" hreflang="%2$s" lang="%2$s"%3$s>%4$s</a></li>',
				esc_url( $language['url'] ),
				esc_attr( $language['language_code'] ),
				! empty( $language['active'] ) ? ' aria-current="true"' : '',
				esc_html( $language['language_code'] )
			);
		}
		echo '</ul>';
	}
}

/**
 * ID of the news hub page (plugin-created "news" page, or the WordPress
 * posts page), 0 when none.
 *
 * @return int
 */
function sportsarena_theme_news_page() {
	if ( function_exists( 'sportsarena_required_pages' ) ) {
		$pages = sportsarena_required_pages();
		if ( ! empty( $pages['news'] ) && 'publish' === get_post_status( $pages['news'] ) ) {
			return (int) $pages['news'];
		}
	}
	return (int) get_option( 'page_for_posts' );
}
