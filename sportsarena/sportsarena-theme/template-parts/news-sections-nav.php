<?php
/**
 * Links to the news sections (one per sport). Args: sections (WP_Term[]).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

$sportsarena_sections = (array) ( $args['sections'] ?? array() );
if ( ! $sportsarena_sections && class_exists( '\SportsArena\Core\News_Sections' ) ) {
	$sportsarena_sections = \SportsArena\Core\News_Sections::terms();
}
$sportsarena_sections = array_filter(
	$sportsarena_sections,
	static function ( $term ) {
		return $term->count > 0;
	}
);
if ( ! $sportsarena_sections ) {
	return;
}
$sportsarena_current = is_category() ? get_queried_object_id() : 0;
?>
<nav class="sa-tabs sa-tabs--links" aria-label="<?php esc_attr_e( 'أقسام الأخبار', 'sportsarena-theme' ); ?>">
	<?php foreach ( $sportsarena_sections as $sportsarena_term ) : ?>
		<a class="sa-tab" href="<?php echo esc_url( get_term_link( $sportsarena_term ) ); ?>" <?php echo (int) $sportsarena_term->term_id === (int) $sportsarena_current ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $sportsarena_term->name ); ?></a>
	<?php endforeach; ?>
</nav>
