<?php
/**
 * Editor meta boxes: match data, team data, competition tables and news
 * relations. Synced fields can be edited manually (e.g. without an API).
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Admin;

use SportsArena\Core\Post_Types;
use SportsArena\Core\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Meta boxes.
 */
final class Meta_Boxes {

	const NONCE = 'sportsarena_meta';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register boxes.
	 *
	 * @return void
	 */
	public function add() {
		add_meta_box( 'sa-match', __( 'بيانات المباراة', 'sportsarena-core' ), array( $this, 'match_box' ), Post_Types::MATCH, 'normal', 'high' );
		add_meta_box( 'sa-team', __( 'بيانات الفريق', 'sportsarena-core' ), array( $this, 'team_box' ), Post_Types::TEAM, 'normal', 'high' );
		add_meta_box( 'sa-competition', __( 'الترتيب والهدافون', 'sportsarena-core' ), array( $this, 'competition_box' ), Post_Types::COMPETITION, 'normal', 'high' );
		add_meta_box( 'sa-relations', __( 'الفرق والبطولات المرتبطة', 'sportsarena-core' ), array( $this, 'relations_box' ), 'post', 'side' );
	}

	/**
	 * Options for a post type select.
	 *
	 * @param string $type     Post type.
	 * @param int    $selected Selected ID.
	 * @return void
	 */
	private function options( $type, $selected ) {
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 1000,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		echo '<option value="0">—</option>';
		foreach ( $ids as $id ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $id, selected( (int) $selected, (int) $id, false ), esc_html( get_the_title( $id ) ) );
		}
	}

	/**
	 * Match box.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function match_box( $post ) {
		wp_nonce_field( self::NONCE, 'sa_meta_nonce' );
		$m        = Repository::match( $post->ID );
		$kickoff  = $m['kickoff'] ? wp_date( 'Y-m-d\TH:i', $m['kickoff'] ) : '';
		$statuses = array(
			'scheduled' => __( 'لم تبدأ', 'sportsarena-core' ),
			'live'      => __( 'حية', 'sportsarena-core' ),
			'halftime'  => __( 'استراحة', 'sportsarena-core' ),
			'finished'  => __( 'انتهت', 'sportsarena-core' ),
			'postponed' => __( 'مؤجلة', 'sportsarena-core' ),
			'cancelled' => __( 'ملغاة', 'sportsarena-core' ),
		);
		$events   = '';
		foreach ( $m['events'] as $e ) {
			$events .= implode( '|', array( $e['minute'] ?? '', $e['team'] ?? '', $e['type'] ?? '', $e['player'] ?? '', $e['assist'] ?? '' ) ) . "\n";
		}
		$provider = (string) get_post_meta( $post->ID, '_sa_provider', true );
		?>
		<div class="sa-grid">
			<p><label><?php esc_html_e( 'الفريق المضيف', 'sportsarena-core' ); ?><br><select name="sa[home]"><?php $this->options( Post_Types::TEAM, $m['home'] ); ?></select></label></p>
			<p><label><?php esc_html_e( 'الفريق الضيف', 'sportsarena-core' ); ?><br><select name="sa[away]"><?php $this->options( Post_Types::TEAM, $m['away'] ); ?></select></label></p>
			<p><label><?php esc_html_e( 'البطولة', 'sportsarena-core' ); ?><br><select name="sa[competition]"><?php $this->options( Post_Types::COMPETITION, $m['competition'] ); ?></select></label></p>
			<p><label><?php esc_html_e( 'موعد البداية (توقيت الموقع)', 'sportsarena-core' ); ?><br><input type="datetime-local" name="sa[kickoff]" value="<?php echo esc_attr( $kickoff ); ?>"></label></p>
			<p><label><?php esc_html_e( 'الحالة', 'sportsarena-core' ); ?><br>
				<select name="sa[status]">
					<?php foreach ( $statuses as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $m['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label><?php esc_html_e( 'الدقيقة', 'sportsarena-core' ); ?><br><input type="number" min="0" max="200" name="sa[elapsed]" value="<?php echo esc_attr( (string) $m['elapsed'] ); ?>"></label></p>
			<p><label><?php esc_html_e( 'أهداف المضيف', 'sportsarena-core' ); ?><br><input type="number" min="0" name="sa[home_score]" value="<?php echo esc_attr( (string) $m['home_score'] ); ?>"></label></p>
			<p><label><?php esc_html_e( 'أهداف الضيف', 'sportsarena-core' ); ?><br><input type="number" min="0" name="sa[away_score]" value="<?php echo esc_attr( (string) $m['away_score'] ); ?>"></label></p>
			<p><label><?php esc_html_e( 'الملعب', 'sportsarena-core' ); ?><br><input type="text" name="sa[venue]" value="<?php echo esc_attr( $m['venue'] ); ?>"></label></p>
			<p><label><?php esc_html_e( 'المدينة', 'sportsarena-core' ); ?><br><input type="text" name="sa[city]" value="<?php echo esc_attr( $m['city'] ); ?>"></label></p>
			<p><label><?php esc_html_e( 'الجولة', 'sportsarena-core' ); ?><br><input type="text" name="sa[round]" value="<?php echo esc_attr( $m['round'] ); ?>"></label></p>
			<p><label><?php esc_html_e( 'الحكم', 'sportsarena-core' ); ?><br><input type="text" name="sa[referee]" value="<?php echo esc_attr( $m['referee'] ); ?>"></label></p>
		</div>
		<p><label><?php esc_html_e( 'القنوات الناقلة (نص فقط — بدون أي روابط بث)', 'sportsarena-core' ); ?><br>
			<input type="text" class="widefat" name="sa[tv]" value="<?php echo esc_attr( $m['tv'] ); ?>" placeholder="beIN Sports 1, SSC 1"></label></p>
		<p><label><?php esc_html_e( 'الأحداث (سطر لكل حدث): الدقيقة|home أو away|goal/own_goal/penalty/yellow/red/sub|اللاعب|صانع الهدف أو البديل', 'sportsarena-core' ); ?><br>
			<textarea class="widefat" rows="5" name="sa[events]" dir="auto"><?php echo esc_textarea( $events ); ?></textarea></label></p>
		<p class="description"><?php esc_html_e( 'اكتب «المعاينة/التحليل» في محرر المحتوى أعلاه. التشكيلات والإحصائيات تُجلب من المزوّد.', 'sportsarena-core' ); ?></p>
		<?php if ( $provider && 'demo' !== $provider ) : ?>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sportsarena_refresh_match&post=' . $post->ID ), 'sportsarena_refresh_match_' . $post->ID ) ); ?>"><?php esc_html_e( 'تحديث من المزوّد (الأحداث، التشكيلة، الإحصائيات، المواجهات)', 'sportsarena-core' ); ?></a>
				<code><?php echo esc_html( $provider . ' #' . get_post_meta( $post->ID, '_sa_external_id', true ) ); ?></code>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Team box.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function team_box( $post ) {
		wp_nonce_field( self::NONCE, 'sa_meta_nonce' );
		$fields = array(
			'name_en'    => __( 'الاسم بالإنجليزية', 'sportsarena-core' ),
			'short_name' => __( 'الاسم المختصر', 'sportsarena-core' ),
			'founded'    => __( 'سنة التأسيس', 'sportsarena-core' ),
			'venue'      => __( 'الملعب', 'sportsarena-core' ),
			'logo_url'   => __( 'رابط الشعار (يُعرض فقط إذا سمحت الإعدادات بشعارات المزوّد)', 'sportsarena-core' ),
		);
		echo '<div class="sa-grid">';
		foreach ( $fields as $key => $label ) {
			printf(
				'<p><label>%1$s<br><input type="text" class="widefat" name="sa[%2$s]" value="%3$s" %4$s></label></p>',
				esc_html( $label ),
				esc_attr( $key ),
				esc_attr( (string) get_post_meta( $post->ID, '_sa_' . $key, true ) ),
				in_array( $key, array( 'name_en', 'logo_url' ), true ) ? 'dir="ltr"' : ''
			);
		}
		echo '</div><p class="description">' . esc_html__( 'الصورة البارزة تُستخدم كشعار للفريق (ارفع شعاراً تملك حق استخدامه).', 'sportsarena-core' ) . '</p>';
	}

	/**
	 * Competition box.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function competition_box( $post ) {
		wp_nonce_field( self::NONCE, 'sa_meta_nonce' );
		$standings = '';
		foreach ( Repository::standings( $post->ID ) as $r ) {
			$team       = ! empty( $r['team']['post_id'] ) ? get_the_title( (int) $r['team']['post_id'] ) : ( $r['team']['name'] ?? '' );
			$standings .= implode( '|', array( $r['rank'], $team, $r['played'], $r['win'], $r['draw'], $r['lose'], $r['goals_for'], $r['goals_against'], $r['points'], $r['group'] ?? '' ) ) . "\n";
		}
		$scorers = '';
		foreach ( Repository::scorers( $post->ID ) as $r ) {
			$scorers .= implode( '|', array( $r['rank'], $r['player'], $r['team'], $r['goals'], $r['assists'] ) ) . "\n";
		}
		?>
		<div class="sa-grid">
			<p><label><?php esc_html_e( 'الاسم بالإنجليزية', 'sportsarena-core' ); ?><br><input type="text" dir="ltr" name="sa[name_en]" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_sa_name_en', true ) ); ?>"></label></p>
			<p><label><?php esc_html_e( 'الموسم', 'sportsarena-core' ); ?><br><input type="text" dir="ltr" name="sa[season]" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_sa_season', true ) ); ?>"></label></p>
			<p><label><?php esc_html_e( 'الأولوية في القوائم (الأصغر أولاً)', 'sportsarena-core' ); ?><br><input type="number" name="sa[priority]" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_sa_priority', true ) ); ?>"></label></p>
		</div>
		<p><label><?php esc_html_e( 'جدول الترتيب (سطر لكل فريق): المركز|الفريق|لعب|فاز|تعادل|خسر|له|عليه|النقاط|المجموعة', 'sportsarena-core' ); ?><br>
			<textarea class="widefat" rows="8" name="sa[standings]" dir="auto"><?php echo esc_textarea( $standings ); ?></textarea></label></p>
		<p><label><?php esc_html_e( 'الهدافون (سطر لكل لاعب): المركز|اللاعب|الفريق|الأهداف|التمريرات الحاسمة', 'sportsarena-core' ); ?><br>
			<textarea class="widefat" rows="6" name="sa[scorers]" dir="auto"><?php echo esc_textarea( $scorers ); ?></textarea></label></p>
		<p class="description"><?php esc_html_e( 'تُستبدل تلقائياً عند المزامنة مع المزوّد.', 'sportsarena-core' ); ?></p>
		<?php
	}

	/**
	 * News relations box.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function relations_box( $post ) {
		wp_nonce_field( self::NONCE, 'sa_meta_nonce' );
		foreach ( array(
			Post_Types::TEAM        => array( '_sa_team_id', __( 'الفرق', 'sportsarena-core' ) ),
			Post_Types::COMPETITION => array( '_sa_competition_id', __( 'البطولات', 'sportsarena-core' ) ),
		) as $type => $conf ) {
			$selected = array_map( 'intval', (array) get_post_meta( $post->ID, $conf[0] ) );
			$ids      = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => 1000,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);
			printf( '<p><label><strong>%s</strong><br>', esc_html( $conf[1] ) );
			printf( '<select multiple size="6" style="width:100%%" name="sa_rel[%s][]">', esc_attr( $type ) );
			foreach ( $ids as $id ) {
				printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $id, in_array( (int) $id, $selected, true ) ? 'selected' : '', esc_html( get_the_title( $id ) ) );
			}
			echo '</select></label></p>';
		}
		echo '<p class="description">' . esc_html__( 'إن تُركت فارغة تُكتشف الفرق والبطولات المذكورة في الخبر تلقائياً عند النشر.', 'sportsarena-core' ) . '</p>';
	}

	/**
	 * Parse "a|b|c" lines.
	 *
	 * @param string $text Text.
	 * @return array<int,string[]>
	 */
	private function lines( $text ) {
		$rows = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$rows[] = array_map( 'trim', explode( '|', $line ) );
			}
		}
		return $rows;
	}

	/**
	 * Save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['sa_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sa_meta_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Values are sanitized field by field below.
		$data = isset( $_POST['sa'] ) && is_array( $_POST['sa'] ) ? wp_unslash( $_POST['sa'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		switch ( $post->post_type ) {
			case Post_Types::MATCH:
				$this->save_match( $post_id, $data );
				break;
			case Post_Types::TEAM:
				foreach ( array( 'name_en', 'short_name', 'founded', 'venue' ) as $key ) {
					update_post_meta( $post_id, '_sa_' . $key, sanitize_text_field( $data[ $key ] ?? '' ) );
				}
				update_post_meta( $post_id, '_sa_logo_url', esc_url_raw( $data['logo_url'] ?? '' ) );
				break;
			case Post_Types::COMPETITION:
				$this->save_competition( $post_id, $data );
				break;
			case 'post':
				$rel = isset( $_POST['sa_rel'] ) && is_array( $_POST['sa_rel'] ) ? wp_unslash( $_POST['sa_rel'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint below.
				foreach ( array(
					Post_Types::TEAM        => '_sa_team_id',
					Post_Types::COMPETITION => '_sa_competition_id',
				) as $type => $key ) {
					delete_post_meta( $post_id, $key );
					foreach ( array_unique( array_filter( array_map( 'absint', (array) ( $rel[ $type ] ?? array() ) ) ) ) as $id ) {
						add_post_meta( $post_id, $key, $id );
					}
				}
				break;
		}
		Repository::bump_version();
	}

	/**
	 * Save match fields.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Raw data.
	 * @return void
	 */
	private function save_match( $post_id, array $data ) {
		$statuses = array( 'scheduled', 'live', 'halftime', 'finished', 'postponed', 'cancelled' );
		update_post_meta( $post_id, '_sa_home_team', absint( $data['home'] ?? 0 ) );
		update_post_meta( $post_id, '_sa_away_team', absint( $data['away'] ?? 0 ) );
		update_post_meta( $post_id, '_sa_competition', absint( $data['competition'] ?? 0 ) );
		if ( ! empty( $data['kickoff'] ) ) {
			$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', sanitize_text_field( $data['kickoff'] ), wp_timezone() );
			if ( $date ) {
				update_post_meta( $post_id, '_sa_kickoff', $date->getTimestamp() );
			}
		}
		$status = sanitize_key( $data['status'] ?? 'scheduled' );
		update_post_meta( $post_id, '_sa_status', in_array( $status, $statuses, true ) ? $status : 'scheduled' );
		foreach ( array( 'elapsed', 'home_score', 'away_score' ) as $key ) {
			$value = isset( $data[ $key ] ) && '' !== $data[ $key ] ? (string) absint( $data[ $key ] ) : '';
			update_post_meta( $post_id, '_sa_' . $key, $value );
		}
		foreach ( array( 'venue', 'city', 'round', 'referee', 'tv' ) as $key ) {
			// TV is plain text: any URL is stripped (no stream links, ever).
			$value = sanitize_text_field( $data[ $key ] ?? '' );
			if ( 'tv' === $key ) {
				$value = trim( (string) preg_replace( '#\b(?:https?://|www\.)\S+#i', '', $value ) );
			}
			update_post_meta( $post_id, '_sa_' . $key, $value );
		}
		$types  = array( 'goal', 'own_goal', 'penalty', 'missed_penalty', 'yellow', 'red', 'sub', 'var' );
		$events = array();
		foreach ( $this->lines( $data['events'] ?? '' ) as $cols ) {
			$type     = sanitize_key( $cols[2] ?? 'goal' );
			$events[] = array(
				'minute' => sanitize_text_field( $cols[0] ?? '' ),
				'team'   => 'away' === ( $cols[1] ?? '' ) ? 'away' : 'home',
				'type'   => in_array( $type, $types, true ) ? $type : 'goal',
				'detail' => '',
				'player' => sanitize_text_field( $cols[3] ?? '' ),
				'assist' => sanitize_text_field( $cols[4] ?? '' ),
			);
		}
		update_post_meta( $post_id, '_sa_events', $events ? wp_slash( wp_json_encode( $events ) ) : '' );
	}

	/**
	 * Save competition fields.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Raw data.
	 * @return void
	 */
	private function save_competition( $post_id, array $data ) {
		update_post_meta( $post_id, '_sa_name_en', sanitize_text_field( $data['name_en'] ?? '' ) );
		update_post_meta( $post_id, '_sa_season', sanitize_text_field( $data['season'] ?? '' ) );
		update_post_meta( $post_id, '_sa_priority', isset( $data['priority'] ) && '' !== $data['priority'] ? (int) $data['priority'] : 50 );

		$rows = array();
		foreach ( $this->lines( $data['standings'] ?? '' ) as $cols ) {
			$name   = sanitize_text_field( $cols[1] ?? '' );
			$team   = $name ? get_posts(
				array(
					'post_type'      => Post_Types::TEAM,
					'title'          => $name,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			) : array();
			$gf     = absint( $cols[6] ?? 0 );
			$ga     = absint( $cols[7] ?? 0 );
			$rows[] = array(
				'group'         => sanitize_text_field( $cols[9] ?? '' ),
				'rank'          => absint( $cols[0] ?? 0 ),
				'team'          => array(
					'external_id' => '',
					'name'        => $name,
					'logo'        => '',
					'post_id'     => $team ? (int) $team[0] : 0,
				),
				'played'        => absint( $cols[2] ?? 0 ),
				'win'           => absint( $cols[3] ?? 0 ),
				'draw'          => absint( $cols[4] ?? 0 ),
				'lose'          => absint( $cols[5] ?? 0 ),
				'goals_for'     => $gf,
				'goals_against' => $ga,
				'goal_diff'     => $gf - $ga,
				'points'        => absint( $cols[8] ?? 0 ),
				'form'          => '',
			);
		}
		update_post_meta( $post_id, '_sa_standings', $rows ? wp_slash( wp_json_encode( $rows ) ) : '' );

		$scorers = array();
		foreach ( $this->lines( $data['scorers'] ?? '' ) as $cols ) {
			$scorers[] = array(
				'rank'    => absint( $cols[0] ?? 0 ),
				'player'  => sanitize_text_field( $cols[1] ?? '' ),
				'team'    => sanitize_text_field( $cols[2] ?? '' ),
				'goals'   => absint( $cols[3] ?? 0 ),
				'assists' => absint( $cols[4] ?? 0 ),
				'played'  => 0,
			);
		}
		update_post_meta( $post_id, '_sa_scorers', $scorers ? wp_slash( wp_json_encode( $scorers ) ) : '' );
	}
}
