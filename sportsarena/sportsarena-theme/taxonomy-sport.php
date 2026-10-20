<?php
/**
 * Sport page (/sport/basketball/): today's matches, competitions, news.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sportsarena_term   = get_queried_object();
$sportsarena_today  = \SportsArena\Core\Rewrites::resolve_day( 'today' );
$sportsarena_groups = \SportsArena\Core\Repository::matches_by_competition( $sportsarena_today, $sportsarena_term->slug );
$sportsarena_comps  = \SportsArena\Core\Repository::competitions( 20, $sportsarena_term->slug );
?>
<div class="sa-container sa-layout">
	<div class="sa-content">
		<header class="sa-page-head">
			<h1 class="sa-page-title"><?php single_term_title(); ?></h1>
			<?php the_archive_description( '<div class="sa-page-desc">', '</div>' ); ?>
		</header>

		<section class="sa-section">
			<h2 class="sa-section__title"><?php esc_html_e( 'مباريات اليوم', 'sportsarena-theme' ); ?></h2>
			<?php sportsarena_theme_match_list( $sportsarena_groups, __( 'لا توجد مباريات اليوم في هذه الرياضة.', 'sportsarena-theme' ) ); ?>
		</section>

		<?php if ( $sportsarena_comps ) : ?>
			<section class="sa-section">
				<h2 class="sa-section__title"><?php esc_html_e( 'البطولات', 'sportsarena-theme' ); ?></h2>
				<ul class="sa-chips">
					<?php foreach ( $sportsarena_comps as $sportsarena_comp ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $sportsarena_comp ) ); ?>"><?php echo esc_html( get_the_title( $sportsarena_comp ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php
		$sportsarena_news = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 6,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query -- Single term.
				'tax_query'      => array(
					array(
						'taxonomy' => 'sport',
						'terms'    => $sportsarena_term->term_id,
					),
				),
			)
		);
		if ( $sportsarena_news->have_posts() ) :
			?>
			<section class="sa-section">
				<h2 class="sa-section__title"><?php esc_html_e( 'آخر الأخبار', 'sportsarena-theme' ); ?></h2>
				<div class="sa-cards">
					<?php
					while ( $sportsarena_news->have_posts() ) :
						$sportsarena_news->the_post();
						get_template_part( 'template-parts/post-card' );
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
