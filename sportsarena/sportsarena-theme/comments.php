<?php
/**
 * Comments.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="sa-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="sa-section__title">
			<?php
			/* translators: %s: number of comments. */
			printf( esc_html( _n( '%s تعليق', '%s تعليقات', get_comments_number(), 'sportsarena-theme' ) ), esc_html( number_format_i18n( get_comments_number() ) ) );
			?>
		</h2>
		<ol class="sa-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 40,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php comment_form(); ?>
</section>
