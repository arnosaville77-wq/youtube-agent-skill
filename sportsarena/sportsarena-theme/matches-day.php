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
		<header class="sa-page-head">
			<h1 class="sa-page-title"><?php echo esc_html( $sportsarena_title ); ?></h1>
			<p class="sa-page-desc">
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
		</header>
		<?php get_template_part( 'template-parts/date-picker', null, array( 'current' => $sportsarena_date ) ); ?>
		<?php sportsarena_theme_match_list( $sportsarena_groups, __( 'لا توجد مباريات في هذا اليوم.', 'sportsarena-theme' ) ); ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
