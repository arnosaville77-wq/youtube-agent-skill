<?php
/**
 * Match page: score, status, local time, events, lineups, statistics,
 * head-to-head, TV channel (text only), editor preview and related links.
 *
 * Never embeds or links to a live stream.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$sportsarena_id   = get_the_ID();
	$sportsarena_m    = sportsarena_match( $sportsarena_id );
	$sportsarena_comp = $sportsarena_m['competition'];
	$sportsarena_home = $sportsarena_m['home'];
	$sportsarena_away = $sportsarena_m['away'];
	$sportsarena_live = sportsarena_is_live( $sportsarena_m );
	$sportsarena_poll = sportsarena_theme_should_poll( $sportsarena_m );
	$sportsarena_tz   = (string) ( get_option( 'sportsarena_settings', array() )['timezone_label'] ?? '' );
	?>
<div class="sa-container sa-layout">
	<article class="sa-content sa-match" data-sa-match="<?php echo esc_attr( (string) $sportsarena_id ); ?>" <?php echo $sportsarena_poll ? 'data-sa-poll="1"' : ''; ?>>

		<header class="sa-scoreboard sa-scoreboard--<?php echo esc_attr( $sportsarena_m['status'] ); ?>">
			<?php if ( $sportsarena_comp ) : ?>
				<p class="sa-scoreboard__comp">
					<a href="<?php echo esc_url( get_permalink( $sportsarena_comp ) ); ?>"><?php echo esc_html( get_the_title( $sportsarena_comp ) ); ?></a>
					<?php if ( $sportsarena_m['round'] ) : ?>
						<span> · <?php echo esc_html( sportsarena_round_label( $sportsarena_m['round'] ) ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<h1 class="sa-scoreboard__title"><?php the_title(); ?></h1>

			<div class="sa-scoreboard__teams">
				<div class="sa-scoreboard__team">
					<?php echo sportsarena_team_logo( $sportsarena_home, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
					<?php if ( $sportsarena_home ) : ?>
						<a href="<?php echo esc_url( get_permalink( $sportsarena_home ) ); ?>"><?php echo esc_html( get_the_title( $sportsarena_home ) ); ?></a>
					<?php endif; ?>
				</div>
				<div class="sa-scoreboard__center">
					<div class="sa-scoreboard__score" data-sa-score><?php echo sportsarena_theme_score_html( $sportsarena_m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?></div>
					<div class="sa-scoreboard__status<?php echo $sportsarena_live ? ' is-live' : ''; ?>" data-sa-status><?php echo esc_html( sportsarena_status_label( $sportsarena_m ) ); ?></div>
				</div>
				<div class="sa-scoreboard__team">
					<?php if ( $sportsarena_away ) : ?>
						<?php echo sportsarena_team_logo( $sportsarena_away, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
						<a href="<?php echo esc_url( get_permalink( $sportsarena_away ) ); ?>"><?php echo esc_html( get_the_title( $sportsarena_away ) ); ?></a>
					<?php endif; ?>
				</div>
			</div>

			<dl class="sa-facts">
				<div>
					<dt><?php esc_html_e( 'الموعد', 'sportsarena-theme' ); ?></dt>
					<dd>
						<?php echo sportsarena_time_html( $sportsarena_m['kickoff'], 'l j F Y - H:i' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
						<small class="sa-time-original">
							<?php
							/* translators: 1: time, 2: timezone label. */
							echo esc_html( sprintf( __( 'التوقيت الأصلي: %1$s %2$s', 'sportsarena-theme' ), wp_date( 'H:i', $sportsarena_m['kickoff'] ), $sportsarena_tz ? $sportsarena_tz : wp_timezone_string() ) );
							?>
						</small>
					</dd>
				</div>
				<?php if ( $sportsarena_m['venue'] ) : ?>
					<div><dt><?php esc_html_e( 'الملعب', 'sportsarena-theme' ); ?></dt><dd><?php echo esc_html( trim( $sportsarena_m['venue'] . ( $sportsarena_m['city'] ? wp_get_list_item_separator() . $sportsarena_m['city'] : '' ) ) ); ?></dd></div>
				<?php endif; ?>
				<?php if ( $sportsarena_m['referee'] ) : ?>
					<div><dt><?php esc_html_e( 'الحكم', 'sportsarena-theme' ); ?></dt><dd><?php echo esc_html( $sportsarena_m['referee'] ); ?></dd></div>
				<?php endif; ?>
				<?php if ( $sportsarena_m['tv'] ) : ?>
					<div><dt><?php esc_html_e( 'القناة الناقلة', 'sportsarena-theme' ); ?></dt><dd><?php echo esc_html( $sportsarena_m['tv'] ); ?></dd></div>
				<?php endif; ?>
			</dl>
		</header>

		<nav class="sa-jump" aria-label="<?php esc_attr_e( 'أقسام الصفحة', 'sportsarena-theme' ); ?>">
			<?php
			if ( $sportsarena_m['events'] ) :
				?>
				<a href="#sa-events"><?php esc_html_e( 'الأحداث', 'sportsarena-theme' ); ?></a><?php endif; ?>
			<?php
			if ( $sportsarena_m['lineups'] ) :
				?>
				<a href="#sa-lineups"><?php esc_html_e( 'التشكيلة', 'sportsarena-theme' ); ?></a><?php endif; ?>
			<?php
			if ( $sportsarena_m['stats'] ) :
				?>
				<a href="#sa-stats"><?php esc_html_e( 'الإحصائيات', 'sportsarena-theme' ); ?></a><?php endif; ?>
			<a href="#sa-h2h"><?php esc_html_e( 'المواجهات السابقة', 'sportsarena-theme' ); ?></a>
			<?php
			if ( get_the_content() ) :
				?>
				<a href="#sa-preview"><?php esc_html_e( 'المعاينة', 'sportsarena-theme' ); ?></a><?php endif; ?>
		</nav>

		<?php if ( $sportsarena_m['events'] ) : ?>
			<section id="sa-events" class="sa-box">
				<h2 class="sa-box__title"><?php esc_html_e( 'الأهداف والبطاقات', 'sportsarena-theme' ); ?></h2>
				<ol class="sa-events">
					<?php foreach ( $sportsarena_m['events'] as $sportsarena_event ) : ?>
						<?php
						if ( 'sub' === ( $sportsarena_event['type'] ?? '' ) ) {
							continue; // Substitutions are listed with the lineups.
						}
						?>
						<li class="sa-event sa-event--<?php echo esc_attr( $sportsarena_event['team'] ?? 'home' ); ?> sa-event--<?php echo esc_attr( $sportsarena_event['type'] ?? '' ); ?>">
							<span class="sa-event__min"><?php echo esc_html( ( $sportsarena_event['minute'] ?? '' ) . "'" ); ?></span>
							<span class="sa-event__icon" aria-hidden="true"></span>
							<span class="sa-event__text">
								<strong><?php echo esc_html( $sportsarena_event['player'] ?? '' ); ?></strong>
								<span class="sa-event__type"><?php echo esc_html( sportsarena_event_label( $sportsarena_event['type'] ?? '' ) ); ?></span>
								<?php if ( ! empty( $sportsarena_event['assist'] ) && in_array( $sportsarena_event['type'], array( 'goal', 'penalty' ), true ) ) : ?>
									<span class="sa-event__assist">
										<?php
										/* translators: %s: player. */
										echo esc_html( sprintf( __( 'صناعة: %s', 'sportsarena-theme' ), $sportsarena_event['assist'] ) );
										?>
									</span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
			</section>
		<?php endif; ?>

		<?php if ( $sportsarena_m['lineups'] ) : ?>
			<section id="sa-lineups" class="sa-box">
				<h2 class="sa-box__title"><?php esc_html_e( 'التشكيلة', 'sportsarena-theme' ); ?></h2>
				<div class="sa-lineups">
					<?php foreach ( $sportsarena_m['lineups'] as $sportsarena_lineup ) : ?>
						<?php $sportsarena_team_id = 'away' === ( $sportsarena_lineup['team'] ?? '' ) ? $sportsarena_away : $sportsarena_home; ?>
						<div class="sa-lineup">
							<h3><?php echo esc_html( get_the_title( $sportsarena_team_id ) ); ?> <small dir="ltr"><?php echo esc_html( $sportsarena_lineup['formation'] ?? '' ); ?></small></h3>
							<ol class="sa-lineup__list">
								<?php foreach ( (array) ( $sportsarena_lineup['start'] ?? array() ) as $sportsarena_player ) : ?>
									<li><?php echo esc_html( $sportsarena_player ); ?></li>
								<?php endforeach; ?>
							</ol>
							<?php if ( ! empty( $sportsarena_lineup['subs'] ) ) : ?>
								<h4><?php esc_html_e( 'البدلاء', 'sportsarena-theme' ); ?></h4>
								<ul class="sa-lineup__list sa-lineup__list--subs">
									<?php foreach ( (array) $sportsarena_lineup['subs'] as $sportsarena_player ) : ?>
										<li><?php echo esc_html( $sportsarena_player ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<?php if ( ! empty( $sportsarena_lineup['coach'] ) ) : ?>
								<p class="sa-lineup__coach"><?php esc_html_e( 'المدرب:', 'sportsarena-theme' ); ?> <?php echo esc_html( $sportsarena_lineup['coach'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $sportsarena_m['stats'] ) : ?>
			<section id="sa-stats" class="sa-box">
				<h2 class="sa-box__title"><?php esc_html_e( 'إحصائيات المباراة', 'sportsarena-theme' ); ?></h2>
				<ul class="sa-stats" role="list">
					<?php
					foreach ( $sportsarena_m['stats'] as $sportsarena_stat ) :
						$sportsarena_h     = (float) str_replace( '%', '', (string) $sportsarena_stat['home'] );
						$sportsarena_a     = (float) str_replace( '%', '', (string) $sportsarena_stat['away'] );
						$sportsarena_total = $sportsarena_h + $sportsarena_a;
						$sportsarena_pct   = $sportsarena_total > 0 ? round( 100 * $sportsarena_h / $sportsarena_total ) : 50;
						?>
						<li class="sa-stat">
							<span class="sa-stat__val"><?php echo esc_html( (string) $sportsarena_stat['home'] ); ?></span>
							<span class="sa-stat__label"><?php echo esc_html( sportsarena_stat_label( (string) $sportsarena_stat['type'] ) ); ?></span>
							<span class="sa-stat__val"><?php echo esc_html( (string) $sportsarena_stat['away'] ); ?></span>
							<span class="sa-stat__bar" style="--sa-pct:<?php echo esc_attr( (string) $sportsarena_pct ); ?>%" aria-hidden="true"></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<section id="sa-h2h" class="sa-box">
			<h2 class="sa-box__title"><?php esc_html_e( 'المواجهات السابقة', 'sportsarena-theme' ); ?></h2>
			<?php if ( $sportsarena_m['h2h'] ) : ?>
				<ul class="sa-h2h" role="list">
					<?php foreach ( $sportsarena_m['h2h'] as $sportsarena_game ) : ?>
						<li>
							<span class="sa-h2h__date"><?php echo esc_html( wp_date( 'j/n/Y', (int) $sportsarena_game['date'] ) ); ?></span>
							<span><?php echo esc_html( $sportsarena_game['home'] ); ?></span>
							<strong dir="ltr"><?php echo esc_html( $sportsarena_game['score'] ); ?></strong>
							<span><?php echo esc_html( $sportsarena_game['away'] ); ?></span>
							<small><?php echo esc_html( $sportsarena_game['comp'] ); ?></small>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<?php
				$sportsarena_h2h = ( $sportsarena_home && $sportsarena_away ) ? \SportsArena\Core\Repository::head_to_head( $sportsarena_home, $sportsarena_away, $sportsarena_id ) : array();
				sportsarena_theme_match_rows( $sportsarena_h2h, __( 'لا توجد مواجهات سابقة مسجلة بين الفريقين.', 'sportsarena-theme' ) );
				?>
			<?php endif; ?>
		</section>

		<?php if ( get_the_content() ) : ?>
			<section id="sa-preview" class="sa-box sa-prose">
				<h2 class="sa-box__title"><?php esc_html_e( 'معاينة وتحليل', 'sportsarena-theme' ); ?></h2>
				<?php the_content(); ?>
			</section>
		<?php endif; ?>

		<?php sportsarena_theme_ad( 'match_bottom' ); ?>

		<?php
		$sportsarena_news = sportsarena_related_news( array( $sportsarena_home, $sportsarena_away ), array( $sportsarena_comp ), 4 );
		if ( $sportsarena_news ) :
			?>
			<section class="sa-section">
				<h2 class="sa-section__title"><?php esc_html_e( 'أخبار مرتبطة', 'sportsarena-theme' ); ?></h2>
				<div class="sa-cards sa-cards--compact">
					<?php
					global $post;
					foreach ( $sportsarena_news as $sportsarena_news_id ) {
						$post = get_post( $sportsarena_news_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
						setup_postdata( $post );
						get_template_part( 'template-parts/post-card', null, array( 'compact' => true ) );
					}
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		if ( $sportsarena_comp ) :
			$sportsarena_more = array_values( array_diff( \SportsArena\Core\Repository::competition_matches( $sportsarena_comp, 'upcoming', 6 ), array( $sportsarena_id ) ) );
			if ( $sportsarena_more ) :
				?>
				<section class="sa-section">
					<h2 class="sa-section__title">
						<?php
						/* translators: %s: competition. */
						echo esc_html( sprintf( __( 'مباريات قادمة في %s', 'sportsarena-theme' ), get_the_title( $sportsarena_comp ) ) );
						?>
					</h2>
					<?php sportsarena_theme_match_rows( array_slice( $sportsarena_more, 0, 5 ) ); ?>
					<p><a class="sa-more" href="<?php echo esc_url( sportsarena_competition_tab_url( $sportsarena_comp, 'standings' ) ); ?>"><?php esc_html_e( 'جدول الترتيب', 'sportsarena-theme' ); ?></a></p>
				</section>
				<?php
			endif;
		endif;
		?>
	</article>
	<?php get_sidebar(); ?>
</div>
	<?php
endwhile;

get_footer();
