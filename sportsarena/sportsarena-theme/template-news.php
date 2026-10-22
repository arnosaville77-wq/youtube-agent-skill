<?php
/**
 * Template Name: الأخبار (حسب الرياضة)
 *
 * News hub: latest stories, then one section per sport category (football,
 * transfers, basketball, tennis, F1...) and analysis. An in-feed ad slot is
 * placed between every two sections, never inside a section.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sportsarena_sections = class_exists( '\SportsArena\Core\News_Sections' ) ? \SportsArena\Core\News_Sections::terms() : array();
$sportsarena_seen     = array();
?>
<div class="sa-container sa-layout">
	<div class="sa-content">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<header class="sa-page-head">
				<h1 class="sa-page-title"><?php the_title(); ?></h1>
				<?php if ( get_the_content() ) : ?>
					<div class="sa-page-desc"><?php the_content(); ?></div>
				<?php endif; ?>
			</header>
		<?php endwhile; ?>

		<?php get_template_part( 'template-parts/news-sections-nav', null, array( 'sections' => $sportsarena_sections ) ); ?>

		<?php
		$sportsarena_latest = new WP_Query(
			array(
				'posts_per_page'      => 5,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => false,
			)
		);
		if ( $sportsarena_latest->have_posts() ) :
			?>
			<section class="sa-section" aria-labelledby="sa-latest">
				<h2 id="sa-latest" class="sa-section__title"><?php esc_html_e( 'آخر الأخبار', 'sportsarena-theme' ); ?></h2>
				<div class="sa-cards sa-cards--lead">
					<?php
					$sportsarena_i = 0;
					while ( $sportsarena_latest->have_posts() ) :
						$sportsarena_latest->the_post();
						$sportsarena_seen[] = get_the_ID();
						get_template_part( 'template-parts/post-card', null, array( 'eager' => 0 === $sportsarena_i++ ) );
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
			<?php
		endif;

		$sportsarena_shown = 0;
		foreach ( $sportsarena_sections as $sportsarena_term ) :
			$sportsarena_query = new WP_Query(
				array(
					'cat'                 => $sportsarena_term->term_id,
					'posts_per_page'      => 4,
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'post__not_in'        => $sportsarena_seen, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- At most 5 IDs.
				)
			);
			if ( ! $sportsarena_query->have_posts() ) {
				continue;
			}
			if ( $sportsarena_shown && 0 === $sportsarena_shown % 2 ) {
				sportsarena_theme_ad( 'in_feed' );
			}
			++$sportsarena_shown;
			?>
			<section class="sa-section" aria-labelledby="sa-sec-<?php echo esc_attr( $sportsarena_term->slug ); ?>">
				<div class="sa-section__head">
					<h2 id="sa-sec-<?php echo esc_attr( $sportsarena_term->slug ); ?>" class="sa-section__title"><?php echo esc_html( $sportsarena_term->name ); ?></h2>
					<a class="sa-more" href="<?php echo esc_url( get_term_link( $sportsarena_term ) ); ?>"><?php esc_html_e( 'المزيد', 'sportsarena-theme' ); ?></a>
				</div>
				<div class="sa-cards">
					<?php
					while ( $sportsarena_query->have_posts() ) :
						$sportsarena_query->the_post();
						get_template_part( 'template-parts/post-card', null, array( 'compact' => true ) );
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
			<?php
		endforeach;
		?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
