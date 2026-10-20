<?php
/**
 * Site footer with the required pages (About, Contact, Privacy, Terms, Disclaimer).
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

$sportsarena_consent = get_option( 'sportsarena_consent', array() );
?>
</main>

<footer class="sa-footer">
	<div class="sa-container sa-footer__inner">
		<div class="sa-footer__about">
			<a class="sa-brand__name" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
			<p><?php bloginfo( 'description' ); ?></p>
			<p class="sa-footer__note"><?php esc_html_e( 'نعرض النتائج والمواعيد والإحصائيات والأخبار فقط، ولا نقدّم أي بث مباشر أو روابط بث للمباريات.', 'sportsarena-theme' ); ?></p>
		</div>
		<nav class="sa-footer__nav" aria-label="<?php esc_attr_e( 'روابط الموقع', 'sportsarena-theme' ); ?>">
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'sa-footer__menu',
						'depth'          => 1,
					)
				);
			} else {
				sportsarena_theme_required_links();
			}
			?>
			<?php if ( empty( $sportsarena_consent['mode'] ) || 'builtin' === $sportsarena_consent['mode'] ) : ?>
				<p><a href="#" class="sa-consent-open"><?php esc_html_e( 'إعدادات الخصوصية', 'sportsarena-theme' ); ?></a></p>
			<?php endif; ?>
		</nav>
	</div>
	<div class="sa-container sa-footer__copy">
		<?php
		/* translators: 1: year, 2: site name. */
		printf( esc_html__( '© %1$s %2$s. جميع الحقوق محفوظة.', 'sportsarena-theme' ), esc_html( wp_date( 'Y' ) ), esc_html( get_bloginfo( 'name' ) ) );
		?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
