<?php
/**
 * Home page, score-site layout: top competitions and countries on one side,
 * today's matches in the centre (status tabs, day navigation, grouped by
 * competition), big matches, latest news and the sidebar ad on the other.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sportsarena_today  = sportsarena_theme_core() ? \SportsArena\Core\Rewrites::resolve_day( 'today' ) : '';
$sportsarena_groups = $sportsarena_today ? \SportsArena\Core\Repository::matches_by_competition( $sportsarena_today ) : array();
$sportsarena_comps  = sportsarena_theme_core() ? \SportsArena\Core\Repository::competitions( 60 ) : array();
?>
<div class="sa-container sa-home">
	<div class="sa-home__main">
		<?php if ( sportsarena_theme_core() ) : ?>
			<?php
			get_template_part(
				'template-parts/match-center',
				null,
				array(
					'groups' => $sportsarena_groups,
					'date'   => $sportsarena_today,
					'title'  => __( 'مباريات اليوم', 'sportsarena-theme' ),
					'empty'  => __( 'لا توجد مباريات مسجلة اليوم. تصفح مباريات الغد.', 'sportsarena-theme' ),
				)
			);
			?>
		<?php endif; ?>

		<?php
		$sportsarena_news = new WP_Query(
			array(
				'posts_per_page'      => 6,
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
					while ( $sportsarena_news->have_posts() ) :
						$sportsarena_news->the_post();
						get_template_part( 'template-parts/post-card', null, array( 'eager' => false ) );
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>
	</div>

	<aside class="sa-home__nav" aria-label="<?php esc_attr_e( 'البطولات والدول', 'sportsarena-theme' ); ?>">
		<?php if ( $sportsarena_comps ) : ?>
			<section class="sa-navbox">
				<h2 class="sa-navbox__title"><?php echo sportsarena_theme_icon( 'trophy', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?><?php esc_html_e( 'أهم البطولات', 'sportsarena-theme' ); ?></h2>
				<ul class="sa-navbox__list" role="list">
					<?php foreach ( array_slice( $sportsarena_comps, 0, 12 ) as $sportsarena_comp ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $sportsarena_comp ) ); ?>"><?php echo sportsarena_competition_logo( $sportsarena_comp, 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?><span><?php echo esc_html( get_the_title( $sportsarena_comp ) ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
			<?php
			$sportsarena_by_country = array();
			foreach ( $sportsarena_comps as $sportsarena_comp ) {
				$sportsarena_label = sportsarena_theme_country_label( $sportsarena_comp );
				$sportsarena_key   = '' !== $sportsarena_label ? $sportsarena_label : __( 'بطولات دولية', 'sportsarena-theme' );
				if ( ! isset( $sportsarena_by_country[ $sportsarena_key ] ) ) {
					$sportsarena_by_country[ $sportsarena_key ] = array(
						'flag'  => '' !== $sportsarena_label ? sportsarena_flag( sportsarena_country_of( $sportsarena_comp ), 18 ) : sportsarena_theme_icon( 'trophy', 16 ),
						'comps' => array(),
					);
				}
				$sportsarena_by_country[ $sportsarena_key ]['comps'][] = $sportsarena_comp;
			}
			?>
			<section class="sa-navbox">
				<h2 class="sa-navbox__title"><?php esc_html_e( 'الدول', 'sportsarena-theme' ); ?></h2>
				<ul class="sa-navbox__list" role="list">
					<?php foreach ( $sportsarena_by_country as $sportsarena_country => $sportsarena_data ) : ?>
						<li>
							<details class="sa-navbox__country">
								<summary><?php echo $sportsarena_data['flag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helpers. ?><span><?php echo esc_html( $sportsarena_country ); ?></span></summary>
								<ul role="list">
									<?php foreach ( $sportsarena_data['comps'] as $sportsarena_comp ) : ?>
										<li><a href="<?php echo esc_url( get_permalink( $sportsarena_comp ) ); ?>"><?php echo esc_html( get_the_title( $sportsarena_comp ) ); ?></a></li>
									<?php endforeach; ?>
								</ul>
							</details>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</aside>

	<aside class="sa-home__side" aria-label="<?php esc_attr_e( 'الشريط الجانبي', 'sportsarena-theme' ); ?>">
		<?php if ( sportsarena_theme_core() ) : ?>
			<?php $sportsarena_big = \SportsArena\Core\Repository::big_matches( 'upcoming', 4 ); ?>
			<?php if ( $sportsarena_big ) : ?>
				<section class="sa-navbox">
					<h2 class="sa-navbox__title"><?php echo sportsarena_theme_icon( 'fire', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?><?php esc_html_e( 'المباريات الكبرى', 'sportsarena-theme' ); ?></h2>
					<ul class="sa-matches" role="list">
						<?php
						foreach ( $sportsarena_big as $sportsarena_match_id ) {
							sportsarena_theme_match_row( $sportsarena_match_id, true );
						}
						?>
					</ul>
					<a class="sa-more sa-navbox__more" href="<?php echo esc_url( sportsarena_big_matches_url() ); ?>"><?php esc_html_e( 'كل المباريات الكبرى ودوري الأبطال', 'sportsarena-theme' ); ?></a>
				</section>
			<?php endif; ?>
		<?php endif; ?>
		<?php
		$sportsarena_latest = new WP_Query(
			array(
				'posts_per_page'      => 5,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);
		if ( $sportsarena_latest->have_posts() ) :
			?>
			<section class="sa-navbox">
				<h2 class="sa-navbox__title"><?php echo sportsarena_theme_icon( 'news', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?><?php esc_html_e( 'آخر الأخبار', 'sportsarena-theme' ); ?></h2>
				<ul class="sa-navbox__list sa-navbox__list--news" role="list">
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
		<div class="sa-sidebar__sticky">
			<?php sportsarena_theme_ad( 'sidebar' ); ?>
		</div>
	</aside>
</div>
<?php
get_footer();
