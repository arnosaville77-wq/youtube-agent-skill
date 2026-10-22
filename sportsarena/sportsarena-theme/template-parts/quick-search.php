<?php
/**
 * Quick search for teams / competitions (REST suggestions + normal search fallback).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<form class="sa-qsearch" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="sa-qsearch-input"><?php esc_html_e( 'ابحث عن فريق أو بطولة', 'sportsarena-theme' ); ?></label>
	<input id="sa-qsearch-input" class="sa-qsearch__input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'ابحث عن فريق أو بطولة…', 'sportsarena-theme' ); ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="sa-qsearch-list" aria-autocomplete="list">
	<ul id="sa-qsearch-list" class="sa-qsearch__list" role="listbox" hidden></ul>
</form>
