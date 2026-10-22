<?php
/**
 * Sidebar: sticky ad on desktop, competitions and latest news.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<aside class="sa-sidebar" aria-label="<?php esc_attr_e( 'الشريط الجانبي', 'sportsarena-theme' ); ?>">
	<?php if ( is_active_sidebar( 'sidebar-1' ) ) : ?>
		<?php dynamic_sidebar( 'sidebar-1' ); ?>
	<?php else : ?>
		<?php if ( sportsarena_theme_core() ) : ?>
			<?php $sportsarena_comps = \SportsArena\Core\Repository::competitions( 12 ); ?>
			<?php if ( $sportsarena_comps ) : ?>
				<section class="sa-widget">
					<h2 class="sa-widget__title"><?php esc_html_e( 'البطولات', 'sportsarena-theme' ); ?></h2>
					<ul class="sa-links">
						<?php foreach ( $sportsarena_comps as $sportsarena_comp ) : ?>
							<li><a class="sa-links__logo" href="<?php echo esc_url( get_permalink( $sportsarena_comp ) ); ?>"><?php echo sportsarena_competition_logo( $sportsarena_comp, 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?><span><?php echo esc_html( get_the_title( $sportsarena_comp ) ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		<?php endif; ?>
		<?php
		$sportsarena_latest = new WP_Query(
			array(
				'posts_per_page'      => 5,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'post__not_in'        => is_singular( 'post' ) ? array( get_queried_object_id() ) : array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Single ID.
			)
		);
		if ( $sportsarena_latest->have_posts() ) :
			?>
			<section class="sa-widget">
				<h2 class="sa-widget__title"><?php esc_html_e( 'آخر الأخبار', 'sportsarena-theme' ); ?></h2>
				<ul class="sa-links">
					<?php
					while ( $sportsarena_latest->have_posts() ) :
						$sportsarena_latest->the_post();
						?>
						<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
					<?php endwhile; ?>
				</ul>
			</section>
			<?php
			wp_reset_postdata();
		endif;
		?>
	<?php endif; ?>
	<div class="sa-sidebar__sticky">
		<?php sportsarena_theme_ad( 'sidebar' ); ?>
	</div>
</aside>
