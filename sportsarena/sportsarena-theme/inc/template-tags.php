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

/**
 * Inline SVG interface icon (decorative, inherits the text colour).
 *
 * @param string $name Icon: search|user|star|chevron-prev|chevron-next|calendar|moon|sun|trophy|list|news|download|ball|fire.
 * @param int    $size Pixel size.
 * @return string
 */
function sportsarena_theme_icon( $name, $size = 20 ) {
	$paths = array(
		'search'       => '<circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'user'         => '<circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 20.5c1.4-3.6 4.4-5.5 8-5.5s6.6 1.9 8 5.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'star'         => '<path d="m12 3.2 2.6 5.6 6.1.7-4.5 4.2 1.2 6.1L12 16.8l-5.4 3 1.2-6.1-4.5-4.2 6.1-.7z" fill="var(--sa-star-fill,none)" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
		'chevron-prev' => '<path d="m9 5 7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
		'chevron-next' => '<path d="m15 5-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
		'calendar'     => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3.5 10h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'moon'         => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" fill="currentColor"/>',
		'sun'          => '<circle cx="12" cy="12" r="4.5" fill="currentColor"/><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3M4.6 4.6l2.1 2.1M17.3 17.3l2.1 2.1M4.6 19.4l2.1-2.1M17.3 6.7l2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'trophy'       => '<path d="M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v1.5A3.5 3.5 0 0 0 7.5 11M17 6h3v1.5a3.5 3.5 0 0 1-3.5 3.5M12 14v4M8 20.5h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		'list'         => '<path d="M8 6h12M8 12h12M8 18h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="4" cy="6" r="1.3" fill="currentColor"/><circle cx="4" cy="12" r="1.3" fill="currentColor"/><circle cx="4" cy="18" r="1.3" fill="currentColor"/>',
		'news'         => '<rect x="3.5" y="4.5" width="13" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16.5 8.5h3a1 1 0 0 1 1 1V17a2.5 2.5 0 0 1-5 0M7 9h6M7 12.5h6M7 16h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'download'     => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v7m-3-3 3 3 3-3M10 18.5h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		'ball'         => '<defs><clipPath id="sa-ball-clip"><circle cx="12" cy="12" r="9.6"/></clipPath></defs><circle cx="12" cy="12" r="9.6" fill="#fff"/><g clip-path="url(#sa-ball-clip)" fill="#071a2b"><path d="M12.00 8.60 L15.23 10.95 L14.00 14.75 L10.00 14.75 L8.77 10.95Z"/><path d="M15.23 7.55 L14.07 3.97 L17.11 1.76 L20.16 3.97 L18.99 7.55ZM17.23 13.70 L20.27 11.49 L23.32 13.70 L22.16 17.28 L18.39 17.28ZM12.00 17.50 L15.04 19.71 L13.88 23.29 L10.12 23.29 L8.96 19.71ZM6.77 13.70 L5.61 17.28 L1.84 17.28 L0.68 13.70 L3.73 11.49ZM8.77 7.55 L5.01 7.55 L3.84 3.97 L6.89 1.76 L9.93 3.97Z"/></g><path d="M12.00 8.60L12.00 5.40M15.23 10.95L18.28 9.96M14.00 14.75L15.88 17.34M10.00 14.75L8.12 17.34M8.77 10.95L5.72 9.96" stroke="#071a2b" stroke-width="1.1"/>',
		'fire'         => '<path d="M12 21c-4 0-7-2.7-7-6.6 0-3.1 2-5.3 3.6-7 .4 1.8 1.4 3 2.6 3.6C11 7.4 12.6 4.6 15 3c-.3 2.6.6 4.4 2 6 1.3 1.5 2 3.2 2 5.3C19 18.3 16 21 12 21z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf( '<svg class="sa-i sa-i--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" aria-hidden="true" focusable="false">%3$s</svg>', esc_attr( $name ), (int) $size, $paths[ $name ] );
}

/**
 * Inline SVG icon of a sport for the main menu.
 *
 * @param string $slug Sport slug.
 * @param int    $size Pixel size.
 * @return string
 */
function sportsarena_theme_sport_icon( $slug, $size = 22 ) {
	$paths = array(
		'football'   => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m12 7.2 3.6 2.6-1.4 4.2H9.8L8.4 9.8z" fill="currentColor"/><path d="M12 7.2V3.1M15.6 9.8l3.9-1.3M14.2 14l2.4 3.3M9.8 14l-2.4 3.3M8.4 9.8 4.5 8.5" stroke="currentColor" stroke-width="1.6"/>',
		'basketball' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3 12h18M12 3v18M5.6 5.6c2.6 2.6 2.6 10.2 0 12.8M18.4 5.6c-2.6 2.6-2.6 10.2 0 12.8" fill="none" stroke="currentColor" stroke-width="1.6"/>',
		'tennis'     => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5.2 6.2c3.3 3 3.3 8.6 0 11.6M18.8 6.2c-3.3 3-3.3 8.6 0 11.6" fill="none" stroke="currentColor" stroke-width="1.6"/>',
		'formula-1'  => '<path d="M5 21V3.5M5 4h13l-2.5 4L18 12H5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 4v8M13 4v8M5 8h13" stroke="currentColor" stroke-width="1.2"/>',
		'handball'   => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 3c-2 3-2 6 0 9s2 6 0 9M3.5 9c3 1 6 1 8.5 3s5.5 2 8.5 3" fill="none" stroke="currentColor" stroke-width="1.6"/>',
		'volleyball' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 12V3.2M12 12l7.6 4.4M12 12l-7.6 4.4M12 3.2c3.5 2.5 5.4 6.7 4.8 10.8M19.6 16.4c-4 1.7-8.5 1.2-11.8-1.3M4.4 16.4C4 12.1 6 8 9.6 5.8" fill="none" stroke="currentColor" stroke-width="1.4"/>',
		'mma'        => '<path d="M7 10V7a3 3 0 0 1 3-3h4.5A3.5 3.5 0 0 1 18 7.5V13a6 6 0 0 1-6 6h-1a5 5 0 0 1-5-5v-2a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 19v2.5h7V19" fill="none" stroke="currentColor" stroke-width="1.8"/>',
	);
	if ( ! isset( $paths[ $slug ] ) ) {
		return sportsarena_theme_icon( 'trophy', $size );
	}
	return sprintf( '<svg class="sa-i" width="%1$d" height="%1$d" viewBox="0 0 24 24" aria-hidden="true" focusable="false">%2$s</svg>', (int) $size, $paths[ $slug ] );
}

/**
 * Favourite (star) toggle, saved in the visitor's browser (no account).
 *
 * @param string $type  match|team|competition.
 * @param int    $id    Post ID.
 * @param string $title Label shown in «مفضلتي».
 * @param string $url   Link.
 * @return string
 */
function sportsarena_theme_fav_button( $type, $id, $title, $url ) {
	/* translators: %s: match, team or competition name. */
	$label = sprintf( __( 'إضافة %s إلى المفضلة', 'sportsarena-theme' ), $title );
	return sprintf(
		'<button type="button" class="sa-fav" data-sa-fav="%1$s" data-id="%2$d" data-title="%3$s" data-url="%4$s" aria-pressed="false" aria-label="%5$s" title="%5$s">%6$s</button>',
		esc_attr( $type ),
		(int) $id,
		esc_attr( $title ),
		esc_url( $url ),
		esc_attr( $label ),
		sportsarena_theme_icon( 'star', 18 )
	);
}

/**
 * Country of a competition in the page language ('' for international
 * competitions). Arabic from the plugin dictionary, other languages from
 * PHP intl when available, English otherwise.
 *
 * @param int $competition_id Competition ID.
 * @return string
 */
function sportsarena_theme_country_label( $competition_id ) {
	if ( ! function_exists( 'sportsarena_country_of' ) ) {
		return '';
	}
	$country = sportsarena_country_of( $competition_id );
	$code    = '' !== $country ? SportsArena\Core\Data\Countries::code( $country ) : '';
	if ( '' === $code ) {
		return '';
	}
	$locale = determine_locale();
	if ( 'ar' === substr( $locale, 0, 2 ) ) {
		$arabic = SportsArena\Core\Data\Names::arabic( $country );
		if ( SportsArena\Core\Data\Names::has_arabic( $arabic ) || 'gb-eng' === $code ) {
			return 'gb-eng' === $code && ! SportsArena\Core\Data\Names::has_arabic( $arabic ) ? 'إنجلترا' : $arabic;
		}
	}
	$england = array(
		'fr' => 'Angleterre',
		'es' => 'Inglaterra',
		'it' => 'Inghilterra',
		'pt' => 'Inglaterra',
		'tr' => 'İngiltere',
	);
	if ( 'gb-eng' === $code ) {
		return $england[ substr( $locale, 0, 2 ) ] ?? 'England';
	}
	if ( 2 === strlen( $code ) && class_exists( 'Locale' ) ) {
		$name = \Locale::getDisplayRegion( '-' . strtoupper( $code ), $locale );
		if ( $name && strtoupper( $code ) !== $name ) {
			return $name;
		}
	}
	return $country;
}

/**
 * Goals and red cards of a match, credited to the team they count for
 * (an own goal counts for the other team).
 *
 * @param array $m Match data.
 * @return array<int,array{minute:string,team:string,type:string,player:string}>
 */
function sportsarena_theme_key_events( array $m ) {
	$out = array();
	foreach ( (array) $m['events'] as $event ) {
		$type = (string) ( $event['type'] ?? '' );
		if ( ! in_array( $type, array( 'goal', 'penalty', 'own_goal', 'red' ), true ) ) {
			continue;
		}
		$team = (string) ( $event['team'] ?? 'home' );
		if ( 'own_goal' === $type ) {
			$team = 'home' === $team ? 'away' : 'home';
		}
		$out[] = array(
			'minute' => (string) ( $event['minute'] ?? '' ),
			'team'   => $team,
			'type'   => $type,
			'player' => function_exists( 'sportsarena_text' ) ? sportsarena_text( (string) ( $event['player'] ?? '' ) ) : (string) ( $event['player'] ?? '' ),
		);
	}
	return $out;
}

/**
 * Scorers (and red cards) of one team under the score board.
 *
 * @param array  $m    Match data.
 * @param string $team home|away.
 * @return string
 */
function sportsarena_theme_scorers_html( array $m, $team ) {
	$items = '';
	foreach ( sportsarena_theme_key_events( $m ) as $event ) {
		if ( $event['team'] !== $team ) {
			continue;
		}
		$note = array(
			'penalty'  => ' (' . __( 'ج', 'sportsarena-theme' ) . ')',
			'own_goal' => ' (' . __( 'عكسي', 'sportsarena-theme' ) . ')',
		);
		$items .= sprintf(
			'<li class="sa-scorers__item sa-scorers__item--%1$s"><span class="sa-scorers__icon" aria-hidden="true"></span>%2$s <span class="sa-scorers__min">%3$s</span></li>',
			esc_attr( 'red' === $event['type'] ? 'red' : 'goal' ),
			esc_html( $event['player'] . ( $note[ $event['type'] ] ?? '' ) ),
			esc_html( $event['minute'] . "'" )
		);
	}
	return '<ul class="sa-scorers" data-sa-scorers="' . esc_attr( $team ) . '">' . $items . '</ul>';
}

/**
 * Red card badge next to a team name in match rows.
 *
 * @param array  $m    Match data.
 * @param string $team home|away.
 * @return string
 */
function sportsarena_theme_red_cards_html( array $m, $team ) {
	$count = 0;
	foreach ( sportsarena_theme_key_events( $m ) as $event ) {
		if ( 'red' === $event['type'] && $event['team'] === $team ) {
			++$count;
		}
	}
	return sprintf(
		'<span class="sa-redcard" data-sa-red="%1$s" title="%2$s"%3$s>%4$s</span>',
		esc_attr( $team ),
		esc_attr__( 'بطاقة حمراء', 'sportsarena-theme' ),
		$count ? '' : ' hidden',
		$count > 1 ? (int) $count : ''
	);
}
