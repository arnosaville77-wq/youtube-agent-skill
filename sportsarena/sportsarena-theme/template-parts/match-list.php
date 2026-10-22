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
	<section class="sa-group" data-sa-sport="<?php echo esc_attr( $sportsarena_slug ); ?>" data-sa-comp="<?php echo esc_attr( (string) $sportsarena_comp ); ?>">
		<div class="sa-group__head">
			<?php if ( $sportsarena_comp ) : ?>
				<?php
				$sportsarena_comp_title = get_the_title( $sportsarena_comp );
				$sportsarena_country    = sportsarena_theme_country_label( $sportsarena_comp );
				echo sportsarena_theme_fav_button( 'competition', $sportsarena_comp, $sportsarena_comp_title, get_permalink( $sportsarena_comp ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
				?>
				<h2 class="sa-group__title">
					<a href="<?php echo esc_url( get_permalink( $sportsarena_comp ) ); ?>">
						<?php echo sportsarena_competition_logo( $sportsarena_comp, 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
						<span class="sa-group__name">
							<?php if ( $sportsarena_country ) : ?>
								<span class="sa-group__country"><?php echo esc_html( $sportsarena_country ); ?>:</span>
							<?php endif; ?>
							<span><?php echo esc_html( $sportsarena_comp_title ); ?></span>
						</span>
					</a>
				</h2>
				<?php if ( 'football' === $sportsarena_slug || 'basketball' === $sportsarena_slug ) : ?>
					<a class="sa-group__table" href="<?php echo esc_url( sportsarena_competition_tab_url( $sportsarena_comp, 'standings' ) ); ?>"><?php esc_html_e( 'الترتيب', 'sportsarena-theme' ); ?></a>
				<?php endif; ?>
			<?php else : ?>
				<h2 class="sa-group__title"><?php esc_html_e( 'مباريات أخرى', 'sportsarena-theme' ); ?></h2>
			<?php endif; ?>
		</div>
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
