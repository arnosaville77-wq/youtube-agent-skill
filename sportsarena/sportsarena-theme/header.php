<?php
/**
 * Site header.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sa-skip" href="#sa-main"><?php esc_html_e( 'تخطَّ إلى المحتوى', 'sportsarena-theme' ); ?></a>

<header class="sa-header">
	<div class="sa-container sa-header__bar">
		<div class="sa-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="sa-brand__name" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="sa-brand__mark" aria-hidden="true"><?php echo sportsarena_theme_sport_icon( 'football', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
					<span class="sa-brand__text"><?php bloginfo( 'name' ); ?></span>
				</a>
			<?php endif; ?>
		</div>

		<div class="sa-header__tools">
			<details class="sa-pop sa-pop--search">
				<summary class="sa-icon-btn" title="<?php esc_attr_e( 'بحث', 'sportsarena-theme' ); ?>">
					<?php echo sportsarena_theme_icon( 'search', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
					<span class="screen-reader-text"><?php esc_html_e( 'بحث', 'sportsarena-theme' ); ?></span>
				</summary>
				<div class="sa-pop__panel sa-pop__panel--search">
					<?php get_template_part( 'template-parts/quick-search' ); ?>
				</div>
			</details>
			<?php sportsarena_theme_language_switcher(); ?>
			<details class="sa-pop sa-profile">
				<summary class="sa-icon-btn sa-profile__toggle" title="<?php esc_attr_e( 'مفضلتي', 'sportsarena-theme' ); ?>">
					<?php echo sportsarena_theme_icon( 'user', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
					<span class="sa-profile__count" data-sa-fav-count hidden></span>
					<span class="screen-reader-text"><?php esc_html_e( 'مفضلتي', 'sportsarena-theme' ); ?></span>
				</summary>
				<div class="sa-pop__panel sa-profile__panel">
					<p class="sa-profile__title"><?php echo sportsarena_theme_icon( 'star', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?> <?php esc_html_e( 'مفضلتي', 'sportsarena-theme' ); ?></p>
					<div class="sa-profile__list" data-sa-fav-list></div>
					<p class="sa-profile__empty" data-sa-fav-empty><?php esc_html_e( 'اضغط النجمة بجانب أي مباراة أو بطولة لتجدها هنا بسرعة.', 'sportsarena-theme' ); ?></p>
					<button class="sa-profile__row sa-theme-toggle" type="button" aria-pressed="false">
						<span><?php esc_html_e( 'الوضع الداكن', 'sportsarena-theme' ); ?></span>
						<?php echo sportsarena_theme_icon( 'moon', 18 ) . sportsarena_theme_icon( 'sun', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
					</button>
					<p class="sa-profile__note"><?php esc_html_e( 'بدون تسجيل: المفضلة محفوظة في متصفحك فقط.', 'sportsarena-theme' ); ?></p>
				</div>
			</details>
		</div>
	</div>

	<nav class="sa-menu" aria-label="<?php esc_attr_e( 'القائمة الرئيسية', 'sportsarena-theme' ); ?>">
		<ul class="sa-container sa-menu__list">
			<?php foreach ( sportsarena_theme_sports() as $sportsarena_sport ) : ?>
				<li><a href="<?php echo esc_url( get_term_link( $sportsarena_sport ) ); ?>" <?php echo is_tax( 'sport', $sportsarena_sport->term_id ) ? 'aria-current="page"' : ''; ?>><?php echo sportsarena_theme_sport_icon( $sportsarena_sport->slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?><span><?php echo esc_html( $sportsarena_sport->name ); ?></span></a></li>
			<?php endforeach; ?>
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'items_wrap'     => '%3$s',
						'depth'          => 1,
					)
				);
			} else {
				$sportsarena_links = array();
				if ( sportsarena_theme_core() ) {
					$sportsarena_links[] = array( sportsarena_big_matches_url(), __( 'المباريات الكبرى', 'sportsarena-theme' ), 'fire', (bool) get_query_var( 'sa_big' ) );
					$sportsarena_ucl     = sportsarena_champions_league_id();
					if ( $sportsarena_ucl ) {
						$sportsarena_links[] = array( get_permalink( $sportsarena_ucl ), __( 'دوري الأبطال', 'sportsarena-theme' ), 'trophy', is_single( $sportsarena_ucl ) );
					}
					$sportsarena_links[] = array( (string) get_post_type_archive_link( 'competition' ), __( 'البطولات', 'sportsarena-theme' ), 'list', is_post_type_archive( 'competition' ) );
				}
				$sportsarena_news = sportsarena_theme_news_page();
				if ( $sportsarena_news ) {
					$sportsarena_links[] = array( get_permalink( $sportsarena_news ), __( 'الأخبار', 'sportsarena-theme' ), 'news', is_page( $sportsarena_news ) );
				}
				foreach ( $sportsarena_links as $sportsarena_link ) {
					printf(
						'<li><a href="%1$s"%2$s>%3$s<span>%4$s</span></a></li>',
						esc_url( $sportsarena_link[0] ),
						$sportsarena_link[3] ? ' aria-current="page"' : '',
						sportsarena_theme_icon( $sportsarena_link[2], 22 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
						esc_html( $sportsarena_link[1] )
					);
				}
			}
			?>
		</ul>
	</nav>

	<?php
	if ( sportsarena_theme_core() && ! is_front_page() && ! get_query_var( 'sa_day' ) ) {
		get_template_part( 'template-parts/live-ticker' );
	}
	?>
</header>

<div class="sa-container">
	<?php sportsarena_theme_ad( 'header' ); ?>
	<?php
	if ( function_exists( 'sportsarena_breadcrumbs' ) && ! is_front_page() ) {
		sportsarena_breadcrumbs();
	}
	?>
</div>

<main id="sa-main" class="sa-main" tabindex="-1">
