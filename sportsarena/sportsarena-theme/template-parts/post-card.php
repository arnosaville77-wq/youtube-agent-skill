<?php
/**
 * News card. Args: eager (bool) for the first (LCP) card.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'sa-card' ); ?>>
	<a class="sa-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php echo sportsarena_theme_card_image( get_the_ID(), 'sportsarena-card', ! empty( $args['eager'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core / static markup. ?>
	</a>
	<div class="sa-card__body">
		<h3 class="sa-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<?php sportsarena_theme_posted_on(); ?>
		<?php if ( empty( $args['compact'] ) ) : ?>
			<p class="sa-card__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
		<?php endif; ?>
	</div>
</article>
