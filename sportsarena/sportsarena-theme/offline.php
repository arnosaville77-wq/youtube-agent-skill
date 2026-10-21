<?php
/**
 * Offline page of the installed app (shown by the service worker when a
 * page was never opened before and there is no connection).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="sa-container">
	<section class="sa-offline">
		<span class="sa-offline__icon" aria-hidden="true"><?php echo sportsarena_theme_icon( 'ball', 46 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
		<h1 class="sa-page-title"><?php esc_html_e( 'لا يوجد اتصال بالإنترنت', 'sportsarena-theme' ); ?></h1>
		<p class="sa-page-desc"><?php esc_html_e( 'يمكنك فتح الصفحات التي زرتها من قبل. ستظهر النتائج الجديدة عند عودة الاتصال.', 'sportsarena-theme' ); ?></p>
		<p class="sa-offline__actions">
			<button type="button" class="sa-button" data-sa-retry><?php esc_html_e( 'إعادة المحاولة', 'sportsarena-theme' ); ?></button>
			<a class="sa-button sa-button--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'الرئيسية', 'sportsarena-theme' ); ?></a>
		</p>
	</section>
</div>
<?php
get_footer();
