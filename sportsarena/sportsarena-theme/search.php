<?php
/**
 * Search results (noindex; no ads when empty — handled by the plugin).
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
				/* translators: %s: search query. */
				printf( esc_html__( 'نتائج البحث عن: %s', 'sportsarena-theme' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
				?>
			</h1>
			<?php get_search_form(); ?>
		</header>
		<?php if ( have_posts() ) : ?>
			<ul class="sa-results">
				<?php
				while ( have_posts() ) :
					the_post();
					$sportsarena_type = get_post_type_object( get_post_type() );
					?>
					<li class="sa-result">
						<span class="sa-badge"><?php echo esc_html( $sportsarena_type ? $sportsarena_type->labels->singular_name : '' ); ?></span>
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php sportsarena_theme_pagination(); ?>
		<?php else : ?>
			<p class="sa-empty"><?php esc_html_e( 'لم نعثر على نتائج مطابقة. جرّب كلمات أخرى.', 'sportsarena-theme' ); ?></p>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
