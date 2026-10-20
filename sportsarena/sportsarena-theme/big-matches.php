<?php
/**
 * Big matches page (/big-matches/): upcoming and live big matches, the UEFA
 * Champions League (next fixtures and latest results) and recent big results.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sportsarena_upcoming = \SportsArena\Core\Repository::big_matches( 'upcoming', 12 );
$sportsarena_past     = \SportsArena\Core\Repository::big_matches( 'past', 8 );
$sportsarena_ucl      = sportsarena_champions_league_id();
?>
<div class="sa-container sa-layout">
	<div class="sa-content">
		<header class="sa-page-head">
			<h1 class="sa-page-title"><?php esc_html_e( 'المباريات الكبرى', 'sportsarena-theme' ); ?></h1>
			<p class="sa-page-desc"><?php esc_html_e( 'مواجهات أشهر الأندية العربية والأوروبية ومباريات دوري أبطال أوروبا في مكان واحد.', 'sportsarena-theme' ); ?></p>
		</header>

		<section class="sa-section" aria-labelledby="sa-big-upcoming">
			<div class="sa-section__head">
				<h2 id="sa-big-upcoming" class="sa-section__title"><?php esc_html_e( 'القادمة والمباشرة', 'sportsarena-theme' ); ?></h2>
			</div>
			<?php if ( $sportsarena_upcoming ) : ?>
				<ul class="sa-bigcards" role="list">
					<?php
					foreach ( $sportsarena_upcoming as $sportsarena_match_id ) {
						get_template_part( 'template-parts/big-match-card', null, array( 'id' => $sportsarena_match_id ) );
					}
					?>
				</ul>
			<?php else : ?>
				<p class="sa-empty"><?php esc_html_e( 'لا توجد مباريات كبرى في الأيام القادمة.', 'sportsarena-theme' ); ?></p>
			<?php endif; ?>
		</section>

		<?php sportsarena_theme_ad( 'between_lists' ); ?>

		<?php if ( $sportsarena_ucl ) : ?>
			<section class="sa-section sa-ucl" aria-labelledby="sa-ucl-title">
				<div class="sa-section__head">
					<h2 id="sa-ucl-title" class="sa-section__title sa-ucl__title"><?php echo sportsarena_competition_logo( $sportsarena_ucl, 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?><span><?php echo esc_html( get_the_title( $sportsarena_ucl ) ); ?></span></h2>
					<a class="sa-more" href="<?php echo esc_url( sportsarena_competition_tab_url( $sportsarena_ucl, 'standings' ) ); ?>"><?php esc_html_e( 'الترتيب', 'sportsarena-theme' ); ?></a>
				</div>
				<h3 class="sa-subtitle"><?php esc_html_e( 'المباريات القادمة', 'sportsarena-theme' ); ?></h3>
				<?php sportsarena_theme_match_rows( \SportsArena\Core\Repository::competition_matches( $sportsarena_ucl, 'upcoming', 8 ), __( 'لا توجد مباريات قادمة مسجلة.', 'sportsarena-theme' ), false ); ?>
				<h3 class="sa-subtitle"><?php esc_html_e( 'آخر النتائج', 'sportsarena-theme' ); ?></h3>
				<?php sportsarena_theme_match_rows( \SportsArena\Core\Repository::competition_matches( $sportsarena_ucl, 'past', 6 ), __( 'لا توجد نتائج بعد.', 'sportsarena-theme' ), false ); ?>
				<p><a class="sa-more" href="<?php echo esc_url( sportsarena_competition_tab_url( $sportsarena_ucl, 'matches' ) ); ?>"><?php esc_html_e( 'كل مباريات دوري الأبطال', 'sportsarena-theme' ); ?></a></p>
			</section>
		<?php endif; ?>

		<?php if ( $sportsarena_past ) : ?>
			<section class="sa-section" aria-labelledby="sa-big-past">
				<div class="sa-section__head">
					<h2 id="sa-big-past" class="sa-section__title"><?php esc_html_e( 'نتائج المباريات الكبرى الأخيرة', 'sportsarena-theme' ); ?></h2>
				</div>
				<?php sportsarena_theme_match_rows( $sportsarena_past ); ?>
			</section>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
