<?php
/**
 * Not found (no ads on this page).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="sa-container">
	<div class="sa-content sa-404">
		<h1 class="sa-page-title"><?php esc_html_e( 'الصفحة غير موجودة', 'sportsarena-theme' ); ?></h1>
		<p><?php esc_html_e( 'ربما نُقلت الصفحة أو حُذفت. يمكنك البحث أو العودة إلى مباريات اليوم.', 'sportsarena-theme' ); ?></p>
		<?php get_search_form(); ?>
		<p>
			<a class="sa-button" href="<?php echo esc_url( function_exists( 'sportsarena_day_url' ) ? sportsarena_day_url( 'today' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'مباريات اليوم', 'sportsarena-theme' ); ?></a>
		</p>
	</div>
</div>
<?php
get_footer();
