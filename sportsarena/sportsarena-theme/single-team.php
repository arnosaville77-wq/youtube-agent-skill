<?php
/**
 * Team page: upcoming and past matches, related news.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$sportsarena_id      = get_the_ID();
	$sportsarena_country = get_the_terms( $sportsarena_id, 'country' );
	$sportsarena_venue   = (string) get_post_meta( $sportsarena_id, '_sa_venue', true );
	$sportsarena_founded = (string) get_post_meta( $sportsarena_id, '_sa_founded', true );
	?>
<div class="sa-container sa-layout">
	<article class="sa-content">
		<header class="sa-entity">
			<?php echo sportsarena_team_logo( $sportsarena_id, 72 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
			<div>
				<h1 class="sa-page-title"><?php the_title(); ?></h1>
				<p class="sa-entity__meta">
					<?php if ( $sportsarena_country && ! is_wp_error( $sportsarena_country ) ) : ?>
						<span><?php echo esc_html( $sportsarena_country[0]->name ); ?></span>
					<?php endif; ?>
					<?php if ( $sportsarena_venue ) : ?>
						<span><?php esc_html_e( 'الملعب:', 'sportsarena-theme' ); ?> <?php echo esc_html( $sportsarena_venue ); ?></span>
					<?php endif; ?>
					<?php if ( $sportsarena_founded ) : ?>
						<span><?php esc_html_e( 'التأسيس:', 'sportsarena-theme' ); ?> <?php echo esc_html( $sportsarena_founded ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		</header>

		<?php if ( get_the_content() ) : ?>
			<div class="sa-box sa-prose"><?php the_content(); ?></div>
		<?php endif; ?>

		<section class="sa-section">
			<h2 class="sa-section__title"><?php esc_html_e( 'المباريات القادمة', 'sportsarena-theme' ); ?></h2>
			<?php sportsarena_theme_match_rows( \SportsArena\Core\Repository::team_matches( $sportsarena_id, 'upcoming', 8 ), __( 'لا توجد مباريات قادمة مسجلة.', 'sportsarena-theme' ) ); ?>
		</section>

		<section class="sa-section">
			<h2 class="sa-section__title"><?php esc_html_e( 'نتائج المباريات السابقة', 'sportsarena-theme' ); ?></h2>
			<?php sportsarena_theme_match_rows( \SportsArena\Core\Repository::team_matches( $sportsarena_id, 'past', 10 ), __( 'لا توجد نتائج سابقة مسجلة.', 'sportsarena-theme' ) ); ?>
		</section>

		<?php
		$sportsarena_news = sportsarena_related_news( array( $sportsarena_id ), array(), 6 );
		if ( $sportsarena_news ) :
			?>
			<section class="sa-section">
				<h2 class="sa-section__title">
					<?php
					/* translators: %s: team. */
					echo esc_html( sprintf( __( 'آخر أخبار %s', 'sportsarena-theme' ), get_the_title() ) );
					?>
				</h2>
				<div class="sa-cards sa-cards--compact">
					<?php
					global $post;
					foreach ( $sportsarena_news as $sportsarena_news_id ) {
						$post = get_post( $sportsarena_news_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
						setup_postdata( $post );
						get_template_part( 'template-parts/post-card', null, array( 'compact' => true ) );
					}
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>
	</article>
	<?php get_sidebar(); ?>
</div>
	<?php
endwhile;

get_footer();
