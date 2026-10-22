<?php
/**
 * Day pages: /matches-today/, /matches-tomorrow/, /matches-yesterday/,
 * /matches/YYYY-MM-DD/ (empty days are noindex, see the plugin SEO class).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sportsarena_day    = (string) get_query_var( 'sa_day' );
$sportsarena_date   = sportsarena_current_day();
$sportsarena_groups = $sportsarena_date ? \SportsArena\Core\Repository::matches_by_competition( $sportsarena_date ) : array();
$sportsarena_titles = array(
	'today'     => __( 'مباريات اليوم', 'sportsarena-theme' ),
	'tomorrow'  => __( 'مباريات الغد', 'sportsarena-theme' ),
	'yesterday' => __( 'مباريات الأمس', 'sportsarena-theme' ),
);
$sportsarena_pretty = $sportsarena_date ? wp_date( 'l j F Y', strtotime( $sportsarena_date . ' 12:00:00' ) ) : '';
/* translators: %s: date. */
$sportsarena_title = $sportsarena_titles[ $sportsarena_day ] ?? sprintf( __( 'مباريات يوم %s', 'sportsarena-theme' ), $sportsarena_pretty );
?>
<div class="sa-container sa-layout">
	<div class="sa-content">
		<?php
		get_template_part(
			'template-parts/match-center',
			null,
			array(
				'groups' => $sportsarena_groups,
				'date'   => (string) $sportsarena_date,
				'title'  => $sportsarena_title,
				'empty'  => __( 'لا توجد مباريات في هذا اليوم.', 'sportsarena-theme' ),
			)
		);
		?>
		<p class="sa-page-desc sa-mc__desc">
			<?php
			if ( sportsarena_theme_time_is_gmt() ) {
				/* translators: %s: date. */
				echo esc_html( sprintf( __( 'مواعيد ونتائج مباريات %s في كل البطولات. كل الأوقات بتوقيت جرينتش (GMT).', 'sportsarena-theme' ), $sportsarena_pretty ) );
			} else {
				/* translators: %s: date. */
				echo esc_html( sprintf( __( 'مواعيد ونتائج مباريات %s في كل البطولات. التوقيت يظهر بتوقيت جهازك تلقائياً.', 'sportsarena-theme' ), $sportsarena_pretty ) );
			}
			?>
		</p>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
