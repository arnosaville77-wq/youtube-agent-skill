<?php
/**
 * One match row (score-site layout): favourite star, kick-off time or
 * status, the two teams stacked, the two scores stacked.
 * Args: id (int), show_competition (bool).
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
$sportsarena_home = $sportsarena_m['home'] ? get_the_title( $sportsarena_m['home'] ) : get_the_title( $sportsarena_id );
$sportsarena_away = $sportsarena_m['away'] ? get_the_title( $sportsarena_m['away'] ) : '';
$sportsarena_hs   = $sportsarena_m['home_score'];
$sportsarena_as   = $sportsarena_m['away_score'];
$sportsarena_has  = null !== $sportsarena_hs && ! in_array( $sportsarena_m['status'], array( 'scheduled', 'postponed', 'cancelled' ), true );
$sportsarena_win  = '';
if ( $sportsarena_has && 'finished' === $sportsarena_m['status'] && (int) $sportsarena_hs !== (int) $sportsarena_as ) {
	$sportsarena_win = (int) $sportsarena_hs > (int) $sportsarena_as ? 'home' : 'away';
}
$sportsarena_short = $sportsarena_live && 'live' === $sportsarena_m['status'] && null !== $sportsarena_m['elapsed'] ? $sportsarena_m['elapsed'] . "'" : sportsarena_status_label( $sportsarena_m );
?>
<li class="sa-row sa-row--<?php echo esc_attr( $sportsarena_m['status'] ); ?>" data-sa-match="<?php echo esc_attr( (string) $sportsarena_id ); ?>" data-sa-teams="<?php echo esc_attr( $sportsarena_m['home'] . ',' . $sportsarena_m['away'] ); ?>" <?php echo $sportsarena_poll ? 'data-sa-poll="1"' : ''; ?>>
	<?php echo sportsarena_theme_fav_button( 'match', $sportsarena_id, trim( $sportsarena_home . ( $sportsarena_away ? ' - ' . $sportsarena_away : '' ) ), $sportsarena_m['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
	<a class="sa-row__link" href="<?php echo esc_url( $sportsarena_m['url'] ); ?>">
		<span class="sa-row__time">
			<span class="sa-row__kick"><?php echo sportsarena_time_html( $sportsarena_m['kickoff'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?></span>
			<span class="sa-row__status<?php echo $sportsarena_live ? ' is-live' : ''; ?>" data-sa-status="short"><?php echo esc_html( $sportsarena_short ); ?></span>
		</span>
		<span class="sa-row__teams">
			<span class="sa-row__team<?php echo 'home' === $sportsarena_win ? ' is-winner' : ''; ?>">
				<?php echo sportsarena_team_logo( $sportsarena_m['home'], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
				<span class="sa-row__name"><?php echo esc_html( $sportsarena_home ); ?></span>
			</span>
			<?php if ( $sportsarena_m['away'] ) : ?>
				<span class="sa-row__team<?php echo 'away' === $sportsarena_win ? ' is-winner' : ''; ?>">
					<?php echo sportsarena_team_logo( $sportsarena_m['away'], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
					<span class="sa-row__name"><?php echo esc_html( $sportsarena_away ); ?></span>
				</span>
			<?php endif; ?>
		</span>
		<span class="sa-row__score" data-sa-score>
			<span class="sa-score sa-score--stack">
				<span class="sa-score__home<?php echo 'home' === $sportsarena_win ? ' is-winner' : ''; ?>"><?php echo $sportsarena_has ? esc_html( (string) $sportsarena_hs ) : ''; ?></span>
				<span class="sa-score__sep" aria-hidden="true">-</span>
				<span class="sa-score__away<?php echo 'away' === $sportsarena_win ? ' is-winner' : ''; ?>"><?php echo $sportsarena_has ? esc_html( (string) (int) $sportsarena_as ) : ''; ?></span>
			</span>
		</span>
	</a>
	<?php if ( ! empty( $args['show_competition'] ) && $sportsarena_m['competition'] ) : ?>
		<span class="sa-row__meta">
			<?php echo esc_html( get_the_title( $sportsarena_m['competition'] ) ); ?> · <?php echo esc_html( wp_date( 'j F', $sportsarena_m['kickoff'] ) ); ?>
		</span>
	<?php endif; ?>
</li>
