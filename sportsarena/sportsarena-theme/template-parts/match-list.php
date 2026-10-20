<?php
/**
 * Matches grouped by competition. An ad slot is inserted after every
 * 3 competition groups (never inside a group, away from the links).
 * Args: groups (array), empty (string).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

$sportsarena_groups = (array) ( $args['groups'] ?? array() );
if ( ! $sportsarena_groups ) {
	if ( ! empty( $args['empty'] ) ) {
		echo '<p class="sa-empty">' . esc_html( $args['empty'] ) . '</p>';
	}
	return;
}
$sportsarena_total = count( $sportsarena_groups );
foreach ( $sportsarena_groups as $sportsarena_i => $sportsarena_group ) :
	$sportsarena_comp   = (int) $sportsarena_group['competition'];
	$sportsarena_sports = $sportsarena_comp ? get_the_terms( $sportsarena_comp, 'sport' ) : false;
	$sportsarena_slug   = $sportsarena_sports && ! is_wp_error( $sportsarena_sports ) ? $sportsarena_sports[0]->slug : '';
	?>
	<section class="sa-group" data-sa-sport="<?php echo esc_attr( $sportsarena_slug ); ?>">
		<h2 class="sa-group__title">
			<?php if ( $sportsarena_comp ) : ?>
				<a href="<?php echo esc_url( get_permalink( $sportsarena_comp ) ); ?>"><?php echo esc_html( get_the_title( $sportsarena_comp ) ); ?></a>
			<?php else : ?>
				<?php esc_html_e( 'مباريات أخرى', 'sportsarena-theme' ); ?>
			<?php endif; ?>
		</h2>
		<ul class="sa-matches" role="list">
			<?php
			foreach ( $sportsarena_group['matches'] as $sportsarena_match_id ) {
				sportsarena_theme_match_row( $sportsarena_match_id );
			}
			?>
		</ul>
	</section>
	<?php
	if ( 0 === ( $sportsarena_i + 1 ) % 3 && $sportsarena_i + 1 < $sportsarena_total ) {
		sportsarena_theme_ad( 'between_lists' );
	}
endforeach;
