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
				<a class="sa-brand__name" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
		</div>

		<button class="sa-icon-btn sa-nav-toggle" type="button" aria-controls="sa-nav" aria-expanded="false">
			<span class="screen-reader-text"><?php esc_html_e( 'القائمة', 'sportsarena-theme' ); ?></span>
			<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
		</button>

		<nav id="sa-nav" class="sa-nav" aria-label="<?php esc_attr_e( 'القائمة الرئيسية', 'sportsarena-theme' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'sa-nav__list',
						'depth'          => 2,
					)
				);
			} else {
				echo '<ul class="sa-nav__list">';
				echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'الرئيسية', 'sportsarena-theme' ) . '</a></li>';
				if ( sportsarena_theme_core() ) {
					echo '<li><a href="' . esc_url( sportsarena_day_url( 'today' ) ) . '">' . esc_html__( 'مباريات اليوم', 'sportsarena-theme' ) . '</a></li>';
					echo '<li><a href="' . esc_url( sportsarena_day_url( 'tomorrow' ) ) . '">' . esc_html__( 'مباريات الغد', 'sportsarena-theme' ) . '</a></li>';
					echo '<li><a href="' . esc_url( (string) get_post_type_archive_link( 'competition' ) ) . '">' . esc_html__( 'البطولات', 'sportsarena-theme' ) . '</a></li>';
				}
				$sportsarena_news = (int) get_option( 'page_for_posts' );
				if ( $sportsarena_news ) {
					echo '<li><a href="' . esc_url( get_permalink( $sportsarena_news ) ) . '">' . esc_html__( 'الأخبار', 'sportsarena-theme' ) . '</a></li>';
				}
				echo '</ul>';
			}
			?>
		</nav>

		<div class="sa-header__tools">
			<?php get_template_part( 'template-parts/quick-search' ); ?>
			<?php sportsarena_theme_language_switcher(); ?>
			<button class="sa-icon-btn sa-theme-toggle" type="button" aria-pressed="false">
				<span class="screen-reader-text"><?php esc_html_e( 'تبديل الوضع الداكن', 'sportsarena-theme' ); ?></span>
				<svg class="sa-i-moon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" fill="currentColor"/></svg>
				<svg class="sa-i-sun" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3M4.6 4.6l2.1 2.1M17.3 17.3l2.1 2.1M4.6 19.4l2.1-2.1M17.3 6.7l2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</button>
		</div>
	</div>

	<?php
	$sportsarena_sports = sportsarena_theme_sports();
	if ( $sportsarena_sports ) :
		?>
		<nav class="sa-sports" aria-label="<?php esc_attr_e( 'الرياضات', 'sportsarena-theme' ); ?>">
			<ul class="sa-container sa-sports__list">
				<?php foreach ( $sportsarena_sports as $sportsarena_sport ) : ?>
					<li><a href="<?php echo esc_url( get_term_link( $sportsarena_sport ) ); ?>" <?php echo is_tax( 'sport', $sportsarena_sport->term_id ) ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $sportsarena_sport->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<?php
	if ( sportsarena_theme_core() ) {
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
