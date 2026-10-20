<?php
/**
 * News article (in-article ad after the 2nd paragraph is added by the plugin).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$sportsarena_teams = array_map( 'intval', (array) get_post_meta( get_the_ID(), '_sa_team_id' ) );
	$sportsarena_comps = array_map( 'intval', (array) get_post_meta( get_the_ID(), '_sa_competition_id' ) );
	?>
<div class="sa-container sa-layout">
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'sa-content sa-article' ); ?>>
		<header class="sa-article__head">
			<h1 class="sa-page-title"><?php the_title(); ?></h1>
			<p class="sa-article__meta">
				<?php sportsarena_theme_posted_on(); ?>
				<span class="sa-meta"> · <?php the_author(); ?></span>
			</p>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="sa-article__media">
				<?php
				the_post_thumbnail(
					'sportsarena-hero',
					array(
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					)
				);
				?>
				<?php if ( get_the_post_thumbnail_caption() ) : ?>
					<figcaption><?php the_post_thumbnail_caption(); ?></figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>

		<div class="sa-prose">
			<?php
			the_content();
			wp_link_pages();
			?>
		</div>

		<?php if ( $sportsarena_teams || $sportsarena_comps ) : ?>
			<section class="sa-section">
				<h2 class="sa-section__title"><?php esc_html_e( 'مرتبط بهذا الخبر', 'sportsarena-theme' ); ?></h2>
				<ul class="sa-chips">
					<?php foreach ( array_merge( $sportsarena_comps, $sportsarena_teams ) as $sportsarena_rel ) : ?>
						<?php if ( 'publish' === get_post_status( $sportsarena_rel ) ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( $sportsarena_rel ) ); ?>"><?php echo esc_html( get_the_title( $sportsarena_rel ) ); ?></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
				<?php
				if ( sportsarena_theme_core() && $sportsarena_teams ) {
					$sportsarena_next = \SportsArena\Core\Repository::team_matches( $sportsarena_teams[0], 'upcoming', 3 );
					sportsarena_theme_match_rows( $sportsarena_next );
				}
				?>
			</section>
		<?php endif; ?>

		<?php the_tags( '<p class="sa-tags">', '', '</p>' ); ?>

		<?php
		if ( function_exists( 'sportsarena_related_news' ) ) :
			$sportsarena_related = sportsarena_related_news( $sportsarena_teams, $sportsarena_comps, 4, get_the_ID() );
			if ( $sportsarena_related ) :
				?>
				<section class="sa-section">
					<h2 class="sa-section__title"><?php esc_html_e( 'أخبار ذات صلة', 'sportsarena-theme' ); ?></h2>
					<div class="sa-cards sa-cards--compact">
						<?php
						global $post;
						$sportsarena_current = $post;
						foreach ( $sportsarena_related as $sportsarena_news_id ) {
							$post = get_post( $sportsarena_news_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
							setup_postdata( $post );
							get_template_part( 'template-parts/post-card', null, array( 'compact' => true ) );
						}
						$post = $sportsarena_current; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore.
						setup_postdata( $post );
						?>
					</div>
				</section>
				<?php
			endif;
		endif;
		?>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</article>
	<?php get_sidebar(); ?>
</div>
	<?php
endwhile;

get_footer();
