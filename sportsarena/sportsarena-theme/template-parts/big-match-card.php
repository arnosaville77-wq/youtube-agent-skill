<?php
/**
 * Big match card: competition, both crests, score or kick-off time.
 * Args: id (int). Live scores are refreshed by the same updater as the rows.
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
<li class="sa-bigcard sa-row--<?php echo esc_attr( $sportsarena_m['status'] ); ?>" data-sa-match="<?php echo esc_attr( (string) $sportsarena_id ); ?>" <?php echo $sportsarena_poll ? 'data-sa-poll="1"' : ''; ?>>
	<a class="sa-bigcard__link" href="<?php echo esc_url( $sportsarena_m['url'] ); ?>">
		<?php if ( $sportsarena_m['competition'] ) : ?>
			<span class="sa-bigcard__comp"><?php echo sportsarena_competition_logo( $sportsarena_m['competition'], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?><span><?php echo esc_html( get_the_title( $sportsarena_m['competition'] ) ); ?></span></span>
		<?php endif; ?>
		<span class="sa-bigcard__teams">
			<span class="sa-bigcard__team">
				<?php echo sportsarena_team_logo( $sportsarena_m['home'], 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
				<span class="sa-bigcard__name"><?php echo esc_html( $sportsarena_m['home'] ? get_the_title( $sportsarena_m['home'] ) : '' ); ?></span>
			</span>
			<span class="sa-bigcard__mid">
				<span class="sa-bigcard__score" data-sa-score><?php echo sportsarena_theme_score_html( $sportsarena_m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?></span>
				<span class="sa-row__status<?php echo $sportsarena_live ? ' is-live' : ''; ?>" data-sa-status><?php echo esc_html( sportsarena_status_label( $sportsarena_m ) ); ?></span>
				<span class="sa-bigcard__date"><?php echo esc_html( wp_date( 'l j F', $sportsarena_m['kickoff'] ) ); ?></span>
			</span>
			<span class="sa-bigcard__team">
				<?php echo $sportsarena_m['away'] ? sportsarena_team_logo( $sportsarena_m['away'], 48 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
				<span class="sa-bigcard__name"><?php echo esc_html( $sportsarena_m['away'] ? get_the_title( $sportsarena_m['away'] ) : '' ); ?></span>
			</span>
		</span>
	</a>
</li>
