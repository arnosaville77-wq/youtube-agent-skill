<?php
/**
 * Competition page with crawlable tabs:
 * /competition/premier-league/ (overview), /matches/, /standings/, /scorers/.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$sportsarena_id     = get_the_ID();
	$sportsarena_tab    = (string) get_query_var( 'sa_tab' );
	$sportsarena_tabs   = array(
		''          => __( 'نظرة عامة', 'sportsarena-theme' ),
		'matches'   => __( 'المباريات', 'sportsarena-theme' ),
		'standings' => __( 'الترتيب', 'sportsarena-theme' ),
		'scorers'   => __( 'الهدافون', 'sportsarena-theme' ),
	);
	$sportsarena_season = (string) get_post_meta( $sportsarena_id, '_sa_season', true );
	?>
<div class="sa-container sa-layout">
	<article class="sa-content">
		<header class="sa-entity">
			<div>
				<h1 class="sa-page-title">
					<?php
					the_title();
					if ( $sportsarena_tab ) {
						echo ' – ' . esc_html( $sportsarena_tabs[ $sportsarena_tab ] ?? '' );
					}
					?>
				</h1>
				<?php if ( $sportsarena_season ) : ?>
					<p class="sa-entity__meta"><span><?php esc_html_e( 'الموسم:', 'sportsarena-theme' ); ?> <?php echo esc_html( $sportsarena_season ); ?></span></p>
				<?php endif; ?>
			</div>
		</header>

		<nav class="sa-tabs sa-tabs--links" aria-label="<?php esc_attr_e( 'أقسام البطولة', 'sportsarena-theme' ); ?>">
			<?php foreach ( $sportsarena_tabs as $sportsarena_slug => $sportsarena_label ) : ?>
				<a class="sa-tab" href="<?php echo esc_url( sportsarena_competition_tab_url( $sportsarena_id, $sportsarena_slug ) ); ?>" <?php echo $sportsarena_slug === $sportsarena_tab ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $sportsarena_label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( '' === $sportsarena_tab ) : ?>
			<?php if ( get_the_content() ) : ?>
				<div class="sa-box sa-prose"><?php the_content(); ?></div>
			<?php endif; ?>
			<section class="sa-section">
				<h2 class="sa-section__title"><?php esc_html_e( 'المباريات القادمة', 'sportsarena-theme' ); ?></h2>
				<?php sportsarena_theme_match_rows( \SportsArena\Core\Repository::competition_matches( $sportsarena_id, 'upcoming', 6 ), __( 'لا توجد مباريات قادمة.', 'sportsarena-theme' ), false ); ?>
			</section>
			<section class="sa-section">
				<div class="sa-section__head">
					<h2 class="sa-section__title"><?php esc_html_e( 'جدول الترتيب', 'sportsarena-theme' ); ?></h2>
					<a class="sa-more" href="<?php echo esc_url( sportsarena_competition_tab_url( $sportsarena_id, 'standings' ) ); ?>"><?php esc_html_e( 'الجدول كاملاً', 'sportsarena-theme' ); ?></a>
				</div>
				<?php
				get_template_part(
					'template-parts/standings-table',
					null,
					array(
						'competition' => $sportsarena_id,
						'limit'       => 6,
					)
				);
				?>
			</section>
			<section class="sa-section">
				<div class="sa-section__head">
					<h2 class="sa-section__title"><?php esc_html_e( 'الهدافون', 'sportsarena-theme' ); ?></h2>
					<a class="sa-more" href="<?php echo esc_url( sportsarena_competition_tab_url( $sportsarena_id, 'scorers' ) ); ?>"><?php esc_html_e( 'القائمة كاملة', 'sportsarena-theme' ); ?></a>
				</div>
				<?php
				get_template_part(
					'template-parts/scorers-table',
					null,
					array(
						'competition' => $sportsarena_id,
						'limit'       => 5,
					)
				);
				?>
			</section>
		<?php elseif ( 'matches' === $sportsarena_tab ) : ?>
			<?php
			// Group past and upcoming matches by round.
			$sportsarena_ids    = array_merge(
				array_reverse( \SportsArena\Core\Repository::competition_matches( $sportsarena_id, 'past', 30 ) ),
				\SportsArena\Core\Repository::competition_matches( $sportsarena_id, 'upcoming', 30 )
			);
			$sportsarena_rounds = array();
			foreach ( array_unique( $sportsarena_ids ) as $sportsarena_match_id ) {
				$sportsarena_round                          = (string) get_post_meta( $sportsarena_match_id, '_sa_round', true );
				$sportsarena_rounds[ $sportsarena_round ][] = $sportsarena_match_id;
			}
			if ( ! $sportsarena_rounds ) {
				echo '<p class="sa-empty">' . esc_html__( 'لا توجد مباريات مسجلة.', 'sportsarena-theme' ) . '</p>';
			}
			foreach ( $sportsarena_rounds as $sportsarena_round => $sportsarena_list ) :
				?>
				<section class="sa-group">
					<h2 class="sa-group__title"><?php echo esc_html( '' !== $sportsarena_round ? sportsarena_round_label( $sportsarena_round ) : __( 'مباريات', 'sportsarena-theme' ) ); ?></h2>
					<?php sportsarena_theme_match_rows( $sportsarena_list, '', false ); ?>
				</section>
			<?php endforeach; ?>
		<?php elseif ( 'standings' === $sportsarena_tab ) : ?>
			<?php get_template_part( 'template-parts/standings-table', null, array( 'competition' => $sportsarena_id ) ); ?>
		<?php elseif ( 'scorers' === $sportsarena_tab ) : ?>
			<?php get_template_part( 'template-parts/scorers-table', null, array( 'competition' => $sportsarena_id ) ); ?>
		<?php endif; ?>

		<?php
		$sportsarena_news = sportsarena_related_news( array(), array( $sportsarena_id ), 4 );
		if ( $sportsarena_news && '' === $sportsarena_tab ) :
			?>
			<section class="sa-section">
				<h2 class="sa-section__title"><?php esc_html_e( 'آخر الأخبار', 'sportsarena-theme' ); ?></h2>
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
