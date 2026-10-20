<?php
/**
 * Fallback template: news archive.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="sa-container sa-layout">
	<div class="sa-content">
		<header class="sa-page-head">
			<h1 class="sa-page-title">
				<?php
				if ( is_home() && ! is_front_page() ) {
					single_post_title();
				} elseif ( is_archive() ) {
					echo wp_kses_post( get_the_archive_title() );
				} else {
					esc_html_e( 'آخر الأخبار', 'sportsarena-theme' );
				}
				?>
			</h1>
			<?php the_archive_description( '<div class="sa-page-desc">', '</div>' ); ?>
		</header>
		<?php
		if ( is_category() || is_home() ) {
			get_template_part( 'template-parts/news-sections-nav' );
		}
		?>
		<?php if ( have_posts() ) : ?>
			<div class="sa-cards">
				<?php
				$sportsarena_i = 0;
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/post-card', null, array( 'eager' => 0 === $sportsarena_i++ ) );
					// In-feed ad after every 6 cards (not after the last one).
					if ( 0 === $sportsarena_i % 6 && $sportsarena_i < $wp_query->post_count ) {
						sportsarena_theme_ad( 'in_feed' );
					}
				endwhile;
				?>
			</div>
			<?php sportsarena_theme_pagination(); ?>
		<?php else : ?>
			<p class="sa-empty"><?php esc_html_e( 'لا توجد مقالات بعد.', 'sportsarena-theme' ); ?></p>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
