<?php
/**
 * Match centre (home page and day pages): title, day navigation, status
 * tabs (all / live / finished / upcoming / favourites), sport filter and the
 * matches grouped by competition.
 * Args: groups (array), date (Y-m-d), title (string), tag (h1|h2), empty (string).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

$sportsarena_groups = (array) ( $args['groups'] ?? array() );
$sportsarena_date   = (string) ( $args['date'] ?? '' );
$sportsarena_tag    = 'h2' === ( $args['tag'] ?? 'h1' ) ? 'h2' : 'h1';
$sportsarena_day    = strtotime( $sportsarena_date . ' 12:00:00' );
$sportsarena_live   = 0;
$sportsarena_sports = array();
foreach ( $sportsarena_groups as $sportsarena_group ) {
	foreach ( $sportsarena_group['matches'] as $sportsarena_match_id ) {
		if ( sportsarena_is_live( sportsarena_match( $sportsarena_match_id ) ) ) {
			++$sportsarena_live;
		}
	}
	$sportsarena_terms = $sportsarena_group['competition'] ? get_the_terms( $sportsarena_group['competition'], 'sport' ) : false;
	if ( $sportsarena_terms && ! is_wp_error( $sportsarena_terms ) ) {
		$sportsarena_sports[ $sportsarena_terms[0]->slug ] = $sportsarena_terms[0]->name;
	}
}
$sportsarena_tabs = array(
	''          => __( 'الكل', 'sportsarena-theme' ),
	'live'      => __( 'مباشر', 'sportsarena-theme' ),
	'finished'  => __( 'النتائج', 'sportsarena-theme' ),
	'scheduled' => __( 'القادمة', 'sportsarena-theme' ),
	'fav'       => __( 'المفضلة', 'sportsarena-theme' ),
);
?>
<section class="sa-mc" data-sa-mc>
	<div class="sa-mc__head">
		<<?php echo esc_html( $sportsarena_tag ); ?> class="sa-mc__title"><?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?></<?php echo esc_html( $sportsarena_tag ); ?>>
		<?php if ( $sportsarena_date ) : ?>
			<nav class="sa-datenav" aria-label="<?php esc_attr_e( 'اختيار اليوم', 'sportsarena-theme' ); ?>">
				<a class="sa-datenav__step" href="<?php echo esc_url( sportsarena_day_url( gmdate( 'Y-m-d', $sportsarena_day - DAY_IN_SECONDS ) ) ); ?>" rel="nofollow">
					<?php echo sportsarena_theme_icon( 'chevron-prev', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
					<span class="screen-reader-text"><?php esc_html_e( 'اليوم السابق', 'sportsarena-theme' ); ?></span>
				</a>
				<label class="sa-datenav__day">
					<?php echo sportsarena_theme_icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
					<span><?php echo esc_html( wp_date( 'l j/n', $sportsarena_day ) ); ?></span>
					<span class="screen-reader-text"><?php esc_html_e( 'اختر تاريخاً', 'sportsarena-theme' ); ?></span>
					<input type="date" value="<?php echo esc_attr( $sportsarena_date ); ?>" data-sa-date>
				</label>
				<a class="sa-datenav__step" href="<?php echo esc_url( sportsarena_day_url( gmdate( 'Y-m-d', $sportsarena_day + DAY_IN_SECONDS ) ) ); ?>" rel="nofollow">
					<?php echo sportsarena_theme_icon( 'chevron-next', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
					<span class="screen-reader-text"><?php esc_html_e( 'اليوم التالي', 'sportsarena-theme' ); ?></span>
				</a>
			</nav>
		<?php endif; ?>
	</div>

	<div class="sa-mc__tabs" role="tablist" aria-label="<?php esc_attr_e( 'تصفية المباريات', 'sportsarena-theme' ); ?>">
		<?php foreach ( $sportsarena_tabs as $sportsarena_key => $sportsarena_label ) : ?>
			<button type="button" role="tab" class="sa-mc__tab<?php echo 'live' === $sportsarena_key ? ' sa-mc__tab--live' : ''; ?>" aria-selected="<?php echo '' === $sportsarena_key ? 'true' : 'false'; ?>" data-sa-status-filter="<?php echo esc_attr( $sportsarena_key ); ?>">
				<?php if ( 'fav' === $sportsarena_key ) : ?>
					<?php echo sportsarena_theme_icon( 'star', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
				<?php endif; ?>
				<?php echo esc_html( $sportsarena_label ); ?>
				<?php if ( 'live' === $sportsarena_key ) : ?>
					<span class="sa-mc__count" data-sa-live-count<?php echo $sportsarena_live ? '' : ' hidden'; ?>><?php echo esc_html( (string) $sportsarena_live ); ?></span>
				<?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>

	<?php if ( count( $sportsarena_sports ) > 1 ) : ?>
		<div class="sa-mc__sports" role="tablist" aria-label="<?php esc_attr_e( 'تصفية حسب الرياضة', 'sportsarena-theme' ); ?>">
			<button type="button" role="tab" class="sa-chip" aria-selected="true" data-sa-filter=""><?php esc_html_e( 'كل الرياضات', 'sportsarena-theme' ); ?></button>
			<?php foreach ( $sportsarena_sports as $sportsarena_slug => $sportsarena_name ) : ?>
				<button type="button" role="tab" class="sa-chip" aria-selected="false" data-sa-filter="<?php echo esc_attr( $sportsarena_slug ); ?>"><?php echo sportsarena_theme_sport_icon( $sportsarena_slug, 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?><?php echo esc_html( $sportsarena_name ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="sa-mc__list" data-sa-filter-target>
		<?php sportsarena_theme_match_list( $sportsarena_groups, (string) ( $args['empty'] ?? '' ) ); ?>
		<p class="sa-empty" data-sa-mc-empty hidden><?php esc_html_e( 'لا توجد مباريات في هذا التصنيف.', 'sportsarena-theme' ); ?></p>
		<p class="sa-empty" data-sa-mc-empty-fav hidden><?php esc_html_e( 'لم تضف مباريات أو بطولات إلى المفضلة بعد. اضغط النجمة بجانب أي مباراة أو بطولة.', 'sportsarena-theme' ); ?></p>
	</div>
</section>
