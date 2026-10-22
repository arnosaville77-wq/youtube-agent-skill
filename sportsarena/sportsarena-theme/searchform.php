<?php
/**
 * Search form.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

$sportsarena_uid = wp_unique_id( 'sa-search-' );
?>
<form role="search" method="get" class="sa-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="<?php echo esc_attr( $sportsarena_uid ); ?>" class="screen-reader-text"><?php esc_html_e( 'بحث', 'sportsarena-theme' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $sportsarena_uid ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'ابحث في الموقع…', 'sportsarena-theme' ); ?>">
	<button type="submit" class="sa-button"><?php esc_html_e( 'بحث', 'sportsarena-theme' ); ?></button>
</form>
