<?php
/**
 * Live matches strip under the header (updated by the live updater).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

$sportsarena_live = \SportsArena\Core\Repository::live_matches( 15 );
if ( ! $sportsarena_live ) {
	return;
}
?>
<div class="sa-ticker" aria-label="<?php esc_attr_e( 'المباريات الجارية الآن', 'sportsarena-theme' ); ?>">
	<div class="sa-container sa-ticker__inner">
		<span class="sa-ticker__label"><span class="sa-dot" aria-hidden="true"></span><?php esc_html_e( 'مباشر', 'sportsarena-theme' ); ?></span>
		<ul class="sa-ticker__list" role="list">
			<?php
			foreach ( $sportsarena_live as $sportsarena_id ) :
				$sportsarena_m = sportsarena_match( $sportsarena_id );
				?>
				<li class="sa-ticker__item" data-sa-match="<?php echo esc_attr( (string) $sportsarena_id ); ?>" data-sa-poll="1">
					<a href="<?php echo esc_url( $sportsarena_m['url'] ); ?>">
						<span class="sa-ticker__team"><?php echo esc_html( get_the_title( $sportsarena_m['home'] ) ); ?></span>
						<span class="sa-ticker__score" data-sa-score><?php echo sportsarena_theme_score_html( $sportsarena_m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?></span>
						<span class="sa-ticker__team"><?php echo esc_html( $sportsarena_m['away'] ? get_the_title( $sportsarena_m['away'] ) : '' ); ?></span>
						<span class="sa-ticker__status" data-sa-status><?php echo esc_html( sportsarena_status_label( $sportsarena_m ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
