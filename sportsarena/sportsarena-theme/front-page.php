<?php
/**
 * Home page: live strip (header), today's matches grouped by competition
 * with sport tabs, and the latest news.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sportsarena_today  = sportsarena_theme_core() ? \SportsArena\Core\Rewrites::resolve_day( 'today' ) : '';
$sportsarena_groups = $sportsarena_today ? \SportsArena\Core\Repository::matches_by_competition( $sportsarena_today ) : array();
$sportsarena_sports = array();
foreach ( $sportsarena_groups as $sportsarena_group ) {
	$sportsarena_terms = $sportsarena_group['competition'] ? get_the_terms( $sportsarena_group['competition'], 'sport' ) : false;
	if ( $sportsarena_terms && ! is_wp_error( $sportsarena_terms ) ) {
		$sportsarena_sports[ $sportsarena_terms[0]->slug ] = $sportsarena_terms[0]->name;
	}
}
?>
<div class="sa-container sa-layout">
	<div class="sa-content">
		<?php if ( sportsarena_theme_core() ) : ?>
			<section class="sa-section" aria-labelledby="sa-today-title">
				<div class="sa-section__head">
					<h1 id="sa-today-title" class="sa-section__title"><?php esc_html_e( 'مباريات اليوم', 'sportsarena-theme' ); ?></h1>
					<a class="sa-more" href="<?php echo esc_url( sportsarena_day_url( 'today' ) ); ?>"><?php esc_html_e( 'كل المباريات', 'sportsarena-theme' ); ?></a>
				</div>
				<?php get_template_part( 'template-parts/date-picker', null, array( 'current' => $sportsarena_today ) ); ?>

				<?php if ( count( $sportsarena_sports ) > 1 ) : ?>
					<div class="sa-tabs" role="tablist" aria-label="<?php esc_attr_e( 'تصفية حسب الرياضة', 'sportsarena-theme' ); ?>">
						<button type="button" role="tab" class="sa-tab" aria-selected="true" data-sa-filter=""><?php esc_html_e( 'الكل', 'sportsarena-theme' ); ?></button>
						<?php foreach ( $sportsarena_sports as $sportsarena_slug => $sportsarena_name ) : ?>
							<button type="button" role="tab" class="sa-tab" aria-selected="false" data-sa-filter="<?php echo esc_attr( $sportsarena_slug ); ?>"><?php echo esc_html( $sportsarena_name ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="sa-day-list" data-sa-filter-target>
					<?php sportsarena_theme_match_list( $sportsarena_groups, __( 'لا توجد مباريات مسجلة اليوم. تصفح مباريات الغد.', 'sportsarena-theme' ) ); ?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		$sportsarena_news = new WP_Query(
			array(
				'posts_per_page'      => 7,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => false,
			)
		);
		if ( $sportsarena_news->have_posts() ) :
			$sportsarena_news_page = sportsarena_theme_news_page();
			?>
			<section class="sa-section" aria-labelledby="sa-news-title">
				<div class="sa-section__head">
					<h2 id="sa-news-title" class="sa-section__title"><?php esc_html_e( 'أحدث الأخبار', 'sportsarena-theme' ); ?></h2>
					<?php if ( $sportsarena_news_page ) : ?>
						<a class="sa-more" href="<?php echo esc_url( get_permalink( $sportsarena_news_page ) ); ?>"><?php esc_html_e( 'المزيد', 'sportsarena-theme' ); ?></a>
					<?php endif; ?>
				</div>
				<?php get_template_part( 'template-parts/news-sections-nav' ); ?>
				<div class="sa-cards">
					<?php
					$sportsarena_i = 0;
					while ( $sportsarena_news->have_posts() ) :
						$sportsarena_news->the_post();
						get_template_part( 'template-parts/post-card', null, array( 'eager' => 0 === $sportsarena_i++ && ! $sportsarena_groups ) );
						if ( 6 === $sportsarena_i && $sportsarena_news->post_count > 6 ) {
							sportsarena_theme_ad( 'in_feed' );
						}
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
