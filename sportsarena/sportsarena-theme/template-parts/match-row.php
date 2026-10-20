<?php
/**
 * One match row. Args: id (int), show_competition (bool).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

$sportsarena_id = (int) ( $args['id'] ?? 0 );
if ( ! $sportsarena_id ) {
	return;
}
$sportsarena_m    = sportsarena_match( $sportsarena_id );
$sportsarena_live = sportsarena_is_live( $sportsarena_m );
$sportsarena_poll = sportsarena_theme_should_poll( $sportsarena_m );
?>
<li class="sa-row sa-row--<?php echo esc_attr( $sportsarena_m['status'] ); ?>" data-sa-match="<?php echo esc_attr( (string) $sportsarena_id ); ?>" <?php echo $sportsarena_poll ? 'data-sa-poll="1"' : ''; ?>>
	<a class="sa-row__link" href="<?php echo esc_url( $sportsarena_m['url'] ); ?>">
		<span class="sa-row__team sa-row__team--home">
			<?php echo sportsarena_team_logo( $sportsarena_m['home'], 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
			<span class="sa-row__name"><?php echo esc_html( $sportsarena_m['home'] ? get_the_title( $sportsarena_m['home'] ) : get_the_title( $sportsarena_id ) ); ?></span>
		</span>
		<span class="sa-row__center">
			<span class="sa-row__score" data-sa-score><?php echo sportsarena_theme_score_html( $sportsarena_m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?></span>
			<span class="sa-row__status<?php echo $sportsarena_live ? ' is-live' : ''; ?>" data-sa-status><?php echo esc_html( sportsarena_status_label( $sportsarena_m ) ); ?></span>
		</span>
		<span class="sa-row__team sa-row__team--away">
			<?php if ( $sportsarena_m['away'] ) : ?>
				<?php echo sportsarena_team_logo( $sportsarena_m['away'], 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
				<span class="sa-row__name"><?php echo esc_html( get_the_title( $sportsarena_m['away'] ) ); ?></span>
			<?php endif; ?>
		</span>
	</a>
	<?php if ( ! empty( $args['show_competition'] ) && $sportsarena_m['competition'] ) : ?>
		<span class="sa-row__meta">
			<?php echo esc_html( get_the_title( $sportsarena_m['competition'] ) ); ?> · <?php echo esc_html( wp_date( 'j F', $sportsarena_m['kickoff'] ) ); ?>
		</span>
	<?php endif; ?>
</li>
