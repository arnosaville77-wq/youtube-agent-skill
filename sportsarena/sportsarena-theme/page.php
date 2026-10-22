<?php
/**
 * Static page.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
<div class="sa-container sa-layout">
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'sa-content sa-article' ); ?>>
		<header class="sa-article__head">
			<h1 class="sa-page-title"><?php the_title(); ?></h1>
		</header>
		<div class="sa-prose">
			<?php
			the_content();
			wp_link_pages();
			?>
		</div>
	</article>
	<?php get_sidebar(); ?>
</div>
	<?php
endwhile;

get_footer();
