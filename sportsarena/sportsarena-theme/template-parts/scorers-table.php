<?php
/**
 * Top scorers table. Args: competition (int), limit (int).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

$sportsarena_rows = \SportsArena\Core\Repository::scorers( (int) ( $args['competition'] ?? 0 ) );
if ( ! empty( $args['limit'] ) ) {
	$sportsarena_rows = array_slice( $sportsarena_rows, 0, (int) $args['limit'] );
}
if ( ! $sportsarena_rows ) {
	echo '<p class="sa-empty">' . esc_html__( 'قائمة الهدافين غير متوفرة حالياً.', 'sportsarena-theme' ) . '</p>';
	return;
}
?>
<div class="sa-table-wrap">
	<table class="sa-table sa-scorers">
		<thead>
			<tr>
				<th scope="col">#</th>
				<th scope="col" class="sa-table__team"><?php esc_html_e( 'اللاعب', 'sportsarena-theme' ); ?></th>
				<th scope="col" class="sa-table__team"><?php esc_html_e( 'الفريق', 'sportsarena-theme' ); ?></th>
				<th scope="col"><?php esc_html_e( 'الأهداف', 'sportsarena-theme' ); ?></th>
				<th scope="col" class="sa-hide-sm"><?php esc_html_e( 'صناعة', 'sportsarena-theme' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $sportsarena_rows as $sportsarena_row ) : ?>
			<tr>
				<td><?php echo esc_html( (string) $sportsarena_row['rank'] ); ?></td>
				<td class="sa-table__team"><?php echo esc_html( sportsarena_text( $sportsarena_row['player'] ) ); ?></td>
				<td class="sa-table__team"><?php echo esc_html( sportsarena_text( $sportsarena_row['team'] ) ); ?></td>
				<td><strong><?php echo esc_html( (string) $sportsarena_row['goals'] ); ?></strong></td>
				<td class="sa-hide-sm"><?php echo esc_html( (string) $sportsarena_row['assists'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
