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
 * Languages a visitor can switch to, from the plugin's built-in mode,
 * Polylang or WPML.
 *
 * @return array<int,array{code:string,name:string,url:string,current:bool}>
 */
function sportsarena_theme_languages() {
	$list = array();
	if ( function_exists( 'pll_the_languages' ) ) {
		$raw = pll_the_languages(
			array(
				'raw'                    => 1,
				'hide_if_no_translation' => 0,
			)
		);
		foreach ( (array) $raw as $language ) {
			$list[] = array(
				'code'    => (string) $language['slug'],
				'name'    => (string) $language['name'],
				'url'     => (string) $language['url'],
				'current' => ! empty( $language['current_lang'] ),
			);
		}
	} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		$raw = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API.
		foreach ( (array) $raw as $language ) {
			$list[] = array(
				'code'    => (string) $language['language_code'],
				'name'    => (string) $language['native_name'],
				'url'     => (string) $language['url'],
				'current' => ! empty( $language['active'] ),
			);
		}
	} elseif ( function_exists( 'sportsarena_language_links' ) ) {
		$list = sportsarena_language_links();
	}
	return count( $list ) > 1 ? $list : array();
}

/**
 * Language links (shared by the header menu and the footer list).
 *
 * @param array  $languages  Languages from sportsarena_theme_languages().
 * @param string $list_class List class.
 * @return void
 */
function sportsarena_theme_language_items( array $languages, $list_class ) {
	echo '<ul class="' . esc_attr( $list_class ) . '" role="list">';
	foreach ( $languages as $language ) {
		printf(
			'<li><a href="%1$s" hreflang="%2$s" lang="%2$s" dir="auto"%3$s>%4$s</a></li>',
			esc_url( $language['url'] ),
			esc_attr( $language['code'] ),
			$language['current'] ? ' aria-current="true"' : '',
			esc_html( $language['name'] )
		);
	}
	echo '</ul>';
}

/**
 * Language menu in the header: a globe button opening the list of languages.
 *
 * @return void
 */
function sportsarena_theme_language_switcher() {
	$languages = sportsarena_theme_languages();
	if ( ! $languages ) {
		return;
	}
	$current = '';
	foreach ( $languages as $language ) {
		if ( $language['current'] ) {
			$current = $language['code'];
		}
	}
	?>
	<details class="sa-lang">
		<summary class="sa-icon-btn sa-lang__toggle">
			<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 12h19M12 2.5c2.6 2.8 3.9 6 3.9 9.5s-1.3 6.7-3.9 9.5c-2.6-2.8-3.9-6-3.9-9.5s1.3-6.7 3.9-9.5z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
			<span class="sa-lang__code" aria-hidden="true"><?php echo esc_html( strtoupper( $current ) ); ?></span>
			<span class="screen-reader-text"><?php esc_html_e( 'اللغة', 'sportsarena-theme' ); ?></span>
		</summary>
		<?php sportsarena_theme_language_items( $languages, 'sa-lang__menu' ); ?>
	</details>
	<?php
}

/**
 * Language list in the footer.
 *
 * @return void
 */
function sportsarena_theme_language_list() {
	$languages = sportsarena_theme_languages();
	if ( ! $languages ) {
		return;
	}
	?>
	<nav class="sa-footer__langs" aria-label="<?php esc_attr_e( 'اللغة', 'sportsarena-theme' ); ?>">
		<h2 class="sa-footer__title">
			<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 12h19M12 2.5c2.6 2.8 3.9 6 3.9 9.5s-1.3 6.7-3.9 9.5c-2.6-2.8-3.9-6-3.9-9.5s1.3-6.7 3.9-9.5z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
			<?php esc_html_e( 'اللغة', 'sportsarena-theme' ); ?>
		</h2>
		<?php sportsarena_theme_language_items( $languages, 'sa-footer__menu sa-footer__langlist' ); ?>
	</nav>
	<?php
}

/**
 * Whether kickoff times are shown in GMT (plugin setting, the default).
 *
 * @return bool
 */
function sportsarena_theme_time_is_gmt() {
	return class_exists( 'SportsArena\\Core\\Time_Display' ) && SportsArena\Core\Time_Display::is_gmt();
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
