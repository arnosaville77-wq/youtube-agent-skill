<?php
/**
 * Day switcher: yesterday / today / tomorrow + calendar. Args: current (Y-m-d).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'sportsarena_day_url' ) ) {
	return;
}
$sportsarena_current = (string) ( $args['current'] ?? '' );
$sportsarena_days    = array(
	'yesterday' => __( 'أمس', 'sportsarena-theme' ),
	'today'     => __( 'اليوم', 'sportsarena-theme' ),
	'tomorrow'  => __( 'غداً', 'sportsarena-theme' ),
);
?>
<nav class="sa-days" aria-label="<?php esc_attr_e( 'اختيار اليوم', 'sportsarena-theme' ); ?>">
	<ul class="sa-days__list">
		<?php
		foreach ( $sportsarena_days as $sportsarena_key => $sportsarena_label ) :
			$sportsarena_date = \SportsArena\Core\Rewrites::resolve_day( $sportsarena_key );
			?>
			<li>
				<a href="<?php echo esc_url( sportsarena_day_url( $sportsarena_key ) ); ?>" <?php echo $sportsarena_current === $sportsarena_date ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $sportsarena_label ); ?>
					<small><?php echo esc_html( wp_date( 'j/n', strtotime( $sportsarena_date . ' 12:00:00' ) ) ); ?></small>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<label class="sa-days__calendar">
		<span class="screen-reader-text"><?php esc_html_e( 'اختر تاريخاً', 'sportsarena-theme' ); ?></span>
		<input type="date" value="<?php echo esc_attr( $sportsarena_current ? $sportsarena_current : wp_date( 'Y-m-d' ) ); ?>" data-sa-date>
	</label>
</nav>
