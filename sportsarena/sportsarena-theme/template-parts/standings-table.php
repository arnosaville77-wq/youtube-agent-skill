<?php
/**
 * Standings table. Args: competition (int), limit (int, 0 = all).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

$sportsarena_rows  = \SportsArena\Core\Repository::standings( (int) ( $args['competition'] ?? 0 ) );
$sportsarena_limit = (int) ( $args['limit'] ?? 0 );
if ( ! $sportsarena_rows ) {
	echo '<p class="sa-empty">' . esc_html__( 'جدول الترتيب غير متوفر حالياً.', 'sportsarena-theme' ) . '</p>';
	return;
}
$sportsarena_groups = array();
foreach ( $sportsarena_rows as $sportsarena_row ) {
	$sportsarena_groups[ (string) ( $sportsarena_row['group'] ?? '' ) ][] = $sportsarena_row;
}
foreach ( $sportsarena_groups as $sportsarena_group => $sportsarena_list ) :
	if ( $sportsarena_limit ) {
		$sportsarena_list = array_slice( $sportsarena_list, 0, $sportsarena_limit );
	}
	?>
	<div class="sa-table-wrap">
		<table class="sa-table sa-standings">
			<?php if ( '' !== $sportsarena_group && count( $sportsarena_groups ) > 1 ) : ?>
				<caption><?php echo esc_html( $sportsarena_group ); ?></caption>
			<?php endif; ?>
			<thead>
				<tr>
					<th scope="col">#</th>
					<th scope="col" class="sa-table__team"><?php esc_html_e( 'الفريق', 'sportsarena-theme' ); ?></th>
					<th scope="col"><abbr title="<?php esc_attr_e( 'لعب', 'sportsarena-theme' ); ?>"><?php esc_html_e( 'ل', 'sportsarena-theme' ); ?></abbr></th>
					<th scope="col"><abbr title="<?php esc_attr_e( 'فاز', 'sportsarena-theme' ); ?>"><?php esc_html_e( 'ف', 'sportsarena-theme' ); ?></abbr></th>
					<th scope="col"><abbr title="<?php esc_attr_e( 'تعادل', 'sportsarena-theme' ); ?>"><?php esc_html_e( 'ت', 'sportsarena-theme' ); ?></abbr></th>
					<th scope="col"><abbr title="<?php esc_attr_e( 'خسر', 'sportsarena-theme' ); ?>"><?php esc_html_e( 'خ', 'sportsarena-theme' ); ?></abbr></th>
					<th scope="col" class="sa-hide-sm"><abbr title="<?php esc_attr_e( 'له / عليه', 'sportsarena-theme' ); ?>">+/-</abbr></th>
					<th scope="col"><abbr title="<?php esc_attr_e( 'فارق الأهداف', 'sportsarena-theme' ); ?>"><?php esc_html_e( 'فارق', 'sportsarena-theme' ); ?></abbr></th>
					<th scope="col"><?php esc_html_e( 'النقاط', 'sportsarena-theme' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $sportsarena_list as $sportsarena_row ) : ?>
				<?php $sportsarena_team = (int) ( $sportsarena_row['team']['post_id'] ?? 0 ); ?>
				<tr>
					<td><?php echo esc_html( (string) $sportsarena_row['rank'] ); ?></td>
					<td class="sa-table__team">
						<?php if ( $sportsarena_team ) : ?>
							<a href="<?php echo esc_url( get_permalink( $sportsarena_team ) ); ?>"><?php echo sportsarena_team_logo( $sportsarena_team, 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?> <?php echo esc_html( get_the_title( $sportsarena_team ) ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $sportsarena_row['team']['name'] ?? '' ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) $sportsarena_row['played'] ); ?></td>
					<td><?php echo esc_html( (string) $sportsarena_row['win'] ); ?></td>
					<td><?php echo esc_html( (string) $sportsarena_row['draw'] ); ?></td>
					<td><?php echo esc_html( (string) $sportsarena_row['lose'] ); ?></td>
					<td class="sa-hide-sm" dir="ltr"><?php echo esc_html( $sportsarena_row['goals_for'] . ':' . $sportsarena_row['goals_against'] ); ?></td>
					<td dir="ltr"><?php echo esc_html( (string) $sportsarena_row['goal_diff'] ); ?></td>
					<td><strong><?php echo esc_html( (string) $sportsarena_row['points'] ); ?></strong></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
endforeach;
