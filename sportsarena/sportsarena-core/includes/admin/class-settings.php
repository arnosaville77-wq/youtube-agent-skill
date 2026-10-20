<?php
/**
 * Dashboard settings: data providers, ads, consent, log and tools.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Admin;

use SportsArena\Core\Data\Http_Client;
use SportsArena\Core\Data\Provider_Registry;
use SportsArena\Core\Logger;
use SportsArena\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Settings pages.
 */
final class Settings {

	const SLUG = 'sportsarena';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SPORTSARENA_CORE_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Admin menu.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page( __( 'SportsArena', 'sportsarena-core' ), __( 'SportsArena', 'sportsarena-core' ), 'manage_options', self::SLUG, array( $this, 'page_general' ), 'dashicons-chart-bar', 58 );
		add_submenu_page( self::SLUG, __( 'البيانات والمزامنة', 'sportsarena-core' ), __( 'البيانات والمزامنة', 'sportsarena-core' ), 'manage_options', self::SLUG, array( $this, 'page_general' ) );
		add_submenu_page( self::SLUG, __( 'الإعلانات', 'sportsarena-core' ), __( 'الإعلانات', 'sportsarena-core' ), 'manage_options', self::SLUG . '-ads', array( $this, 'page_ads' ) );
		add_submenu_page( self::SLUG, __( 'الموافقة على الكوكيز', 'sportsarena-core' ), __( 'الموافقة على الكوكيز', 'sportsarena-core' ), 'manage_options', self::SLUG . '-consent', array( $this, 'page_consent' ) );
		add_submenu_page( self::SLUG, __( 'السجل', 'sportsarena-core' ), __( 'السجل', 'sportsarena-core' ), 'manage_options', self::SLUG . '-log', array( $this, 'page_log' ) );
		add_submenu_page( self::SLUG, __( 'الأدوات', 'sportsarena-core' ), __( 'الأدوات', 'sportsarena-core' ), 'manage_options', self::SLUG . '-tools', array( $this, 'page_tools' ) );
	}

	/**
	 * Plugin list link.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'الإعدادات', 'sportsarena-core' ) . '</a>' );
		return $links;
	}

	/**
	 * Admin CSS.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( str_contains( (string) $hook, self::SLUG ) || in_array( get_current_screen()->post_type ?? '', array( 'match', 'team', 'competition', 'post' ), true ) ) {
			wp_enqueue_style( 'sportsarena-admin', SPORTSARENA_CORE_URL . 'assets/css/admin.css', array(), SPORTSARENA_CORE_VERSION );
		}
	}

	/**
	 * Register settings with sanitizers.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			'sportsarena_general',
			Options::GENERAL,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_general' ),
			)
		);
		register_setting(
			'sportsarena_ads',
			Options::ADS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_ads' ),
			)
		);
		register_setting(
			'sportsarena_consent',
			Options::CONSENT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_consent' ),
			)
		);
	}

	/**
	 * Sanitize general settings. Empty key fields keep the stored key so the
	 * secret never has to be printed back into the form.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_general( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = wp_parse_args( (array) get_option( Options::GENERAL, array() ), Options::general_defaults() );
		$out     = array();

		foreach ( array( 'api_football_key', 'api_basketball_key', 'thesportsdb_key' ) as $key ) {
			$value = isset( $input[ $key ] ) ? trim( sanitize_text_field( $input[ $key ] ) ) : '';
			if ( ! empty( $input[ $key . '_clear' ] ) ) {
				$out[ $key ] = '';
			} else {
				$out[ $key ] = '' !== $value ? preg_replace( '/[^A-Za-z0-9_\-]/', '', $value ) : $current[ $key ];
			}
		}
		foreach ( array( 'api_football_leagues', 'api_basketball_leagues' ) as $key ) {
			$out[ $key ] = implode( ',', array_filter( array_map( 'absint', Options::csv( $input[ $key ] ?? '' ) ) ) );
		}
		$out['thesportsdb_sports'] = implode( ',', array_map( 'sanitize_text_field', Options::csv( $input['thesportsdb_sports'] ?? '' ) ) );
		$out['daily_limit']        = max( 0, absint( $input['daily_limit'] ?? 100 ) );
		$out['minute_limit']       = max( 0, absint( $input['minute_limit'] ?? 10 ) );
		$out['live_interval']      = max( 60, absint( $input['live_interval'] ?? 60 ) );
		$out['timezone_label']     = sanitize_text_field( $input['timezone_label'] ?? '' );
		$alternates                = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $input['alternate_sites'] ?? '' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( 2 === count( $parts ) && preg_match( '/^([a-z]{2,3}(-[A-Za-z]{2,4})?|x-default)$/', $parts[0] ) && wp_http_validate_url( $parts[1] ) ) {
				$alternates[] = $parts[0] . '=' . esc_url_raw( $parts[1] );
			}
		}
		$out['alternate_sites'] = implode( "\n", $alternates );
		foreach ( array( 'sync_enabled', 'allow_logos', 'news_sitemap', 'delete_on_uninstall' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}
		Provider_Registry::reset();
		return $out;
	}

	/**
	 * Sanitize ads settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_ads( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = Options::ads();
		$pub     = trim( sanitize_text_field( $input['publisher_id'] ?? '' ) );
		if ( '' !== $pub && ! str_starts_with( $pub, 'ca-' ) ) {
			$pub = 'ca-' . $pub;
		}
		if ( '' !== $pub && ! preg_match( '/^ca-pub-\d{10,20}$/', $pub ) ) {
			add_settings_error( Options::ADS, 'publisher', __( 'معرّف الناشر غير صالح. الصيغة الصحيحة: ca-pub-1234567890123456', 'sportsarena-core' ) );
			$pub = $current['publisher_id'];
		}
		$out = array(
			'publisher_id' => $pub,
			'auto_ads'     => empty( $input['auto_ads'] ) ? 0 : 1,
			'ads_txt'      => empty( $input['ads_txt'] ) ? 0 : 1,
			'slots'        => array(),
		);
		foreach ( array_keys( Options::ad_slots() ) as $slot ) {
			$raw  = (array) ( $input['slots'][ $slot ] ?? array() );
			$html = $current['slots'][ $slot ]['html'] ?? '';
			if ( current_user_can( 'unfiltered_html' ) ) {
				$html = trim( (string) ( $raw['html'] ?? '' ) );
			}
			$out['slots'][ $slot ] = array(
				'enabled' => empty( $raw['enabled'] ) ? 0 : 1,
				'slot_id' => preg_replace( '/\D/', '', (string) ( $raw['slot_id'] ?? '' ) ),
				'html'    => $html,
			);
		}
		return $out;
	}

	/**
	 * Sanitize consent settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_consent( $input ) {
		$input = is_array( $input ) ? $input : array();
		$mode  = sanitize_key( $input['mode'] ?? 'builtin' );
		return array(
			'mode'         => in_array( $mode, array( 'builtin', 'cmp', 'off' ), true ) ? $mode : 'builtin',
			'consent_mode' => empty( $input['consent_mode'] ) ? 0 : 1,
			'eea_only'     => empty( $input['eea_only'] ) ? 0 : 1,
		);
	}

	/**
	 * Text input row.
	 *
	 * @param string $option Option name.
	 * @param string $key    Key.
	 * @param string $label  Label.
	 * @param mixed  $value  Value.
	 * @param string $help   Help text.
	 * @param string $type   Input type.
	 * @return void
	 */
	private function row( $option, $key, $label, $value, $help = '', $type = 'text' ) {
		$id = $option . '-' . $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php if ( 'checkbox' === $type ) : ?>
					<label><input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $option . '[' . $key . ']' ); ?>" value="1" <?php checked( (int) $value, 1 ); ?>> <?php echo esc_html( $help ); ?></label>
				<?php else : ?>
					<input type="<?php echo esc_attr( $type ); ?>" class="regular-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $option . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" dir="ltr">
					<?php if ( $help ) : ?>
						<p class="description"><?php echo wp_kses_post( $help ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Masked secret input: the stored key is never printed.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 * @return void
	 */
	private function secret_row( $key, $label, $help ) {
		$id     = Options::GENERAL . '-' . $key;
		$stored = (string) Options::get( $key );
		$const  = defined( 'SPORTSARENA_' . strtoupper( $key ) );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php if ( $const ) : ?>
					<p><strong><?php esc_html_e( 'معرّف عبر ثابت في wp-config.php.', 'sportsarena-core' ); ?></strong></p>
				<?php else : ?>
					<input type="password" class="regular-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( Options::GENERAL . '[' . $key . ']' ); ?>" value="" autocomplete="new-password" dir="ltr" placeholder="<?php echo esc_attr( $stored ? '•••••••• ' . substr( $stored, -4 ) : '' ); ?>">
					<?php if ( $stored ) : ?>
						<label><input type="checkbox" name="<?php echo esc_attr( Options::GENERAL . '[' . $key . '_clear]' ); ?>" value="1"> <?php esc_html_e( 'حذف المفتاح المحفوظ', 'sportsarena-core' ); ?></label>
					<?php endif; ?>
				<?php endif; ?>
				<p class="description"><?php echo wp_kses_post( $help ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Data & sync page.
	 *
	 * @return void
	 */
	public function page_general() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = wp_parse_args( (array) get_option( Options::GENERAL, array() ), Options::general_defaults() );
		$n = Options::GENERAL;
		?>
		<div class="wrap sa-admin">
			<h1><?php esc_html_e( 'SportsArena: البيانات والمزامنة', 'sportsarena-core' ); ?></h1>
			<?php settings_errors(); ?>
			<?php $this->provider_status(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'sportsarena_general' ); ?>
				<h2><?php esc_html_e( 'مزوّدو البيانات', 'sportsarena-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->row( $n, 'sync_enabled', __( 'تفعيل المزامنة', 'sportsarena-core' ), $o['sync_enabled'], __( 'تشغيل مهام WP-Cron لجلب البيانات من المزوّدين.', 'sportsarena-core' ), 'checkbox' );
					$this->secret_row( 'api_football_key', __( 'مفتاح API-Football', 'sportsarena-core' ), __( 'من لوحة <a href="https://dashboard.api-football.com/" target="_blank" rel="noopener">dashboard.api-football.com</a>. نفس المفتاح يعمل لكل رياضات api-sports.io.', 'sportsarena-core' ) );
					$this->row( $n, 'api_football_leagues', __( 'معرّفات دوريات كرة القدم', 'sportsarena-core' ), $o['api_football_leagues'], __( 'مفصولة بفواصل (مثال: 39 الإنجليزي، 140 الإسباني، 2 دوري الأبطال، 307 السعودي، 233 المصري). اتركه فارغاً لجلب كل الدوريات (غير مستحسن).', 'sportsarena-core' ) );
					$this->secret_row( 'api_basketball_key', __( 'مفتاح API-Basketball (اختياري)', 'sportsarena-core' ), __( 'إن تُرك فارغاً يُستخدم مفتاح API-Football.', 'sportsarena-core' ) );
					$this->row( $n, 'api_basketball_leagues', __( 'معرّفات دوريات السلة', 'sportsarena-core' ), $o['api_basketball_leagues'], __( 'مثال: 12 (NBA)، 120 (Euroleague). اتركه فارغاً لتعطيل السلة.', 'sportsarena-core' ) );
					$this->secret_row( 'thesportsdb_key', __( 'مفتاح TheSportsDB', 'sportsarena-core' ), __( 'للتنس والفورمولا 1 وكرة اليد والطائرة والفنون القتالية. <a href="https://www.thesportsdb.com/pricing" target="_blank" rel="noopener">thesportsdb.com</a>', 'sportsarena-core' ) );
					$this->row( $n, 'thesportsdb_sports', __( 'رياضات TheSportsDB', 'sportsarena-core' ), $o['thesportsdb_sports'], 'Tennis, Motorsport, Handball, Volleyball, Fighting, Basketball, Soccer' );
					?>
				</table>
				<h2><?php esc_html_e( 'حدود الطلبات والتوقيت', 'sportsarena-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->row( $n, 'daily_limit', __( 'الحد اليومي لكل مزوّد', 'sportsarena-core' ), $o['daily_limit'], __( 'عدد الطلبات المسموح بها يومياً (الخطة المجانية لـ API-Football: 100). 0 = بلا حد.', 'sportsarena-core' ), 'number' );
					$this->row( $n, 'minute_limit', __( 'الحد في الدقيقة', 'sportsarena-core' ), $o['minute_limit'], __( 'حسب خطتك (المجانية: 10).', 'sportsarena-core' ), 'number' );
					$this->row( $n, 'live_interval', __( 'فاصل تحديث المباريات الحية (ثانية)', 'sportsarena-core' ), $o['live_interval'], __( 'لا يقل عن 60. يعمل فقط أثناء وجود مباريات حية.', 'sportsarena-core' ), 'number' );
					$this->row( $n, 'timezone_label', __( 'اسم التوقيت الأصلي', 'sportsarena-core' ), $o['timezone_label'], __( 'يظهر بجانب التوقيت الأصلي، مثال: «بتوقيت مكة». المنطقة الزمنية نفسها من الإعدادات > عام.', 'sportsarena-core' ) );
					?>
				</table>
				<h2><?php esc_html_e( 'خيارات أخرى', 'sportsarena-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->row( $n, 'allow_logos', __( 'عرض شعارات المزوّد', 'sportsarena-core' ), $o['allow_logos'], __( 'فعّل فقط إذا كانت خطة مزوّد البيانات تمنحك ترخيص عرض الشعارات. وإلا تُعرض صورة بديلة عامة.', 'sportsarena-core' ), 'checkbox' );
					$this->row( $n, 'news_sitemap', __( 'Google News Sitemap', 'sportsarena-core' ), $o['news_sitemap'], __( 'إنشاء /news-sitemap.xml لأخبار آخر 48 ساعة.', 'sportsarena-core' ), 'checkbox' );
					?>
					<tr>
						<th scope="row"><label for="sa-alternate-sites"><?php esc_html_e( 'مواقع اللغات الأخرى (hreflang)', 'sportsarena-core' ); ?></label></th>
						<td>
							<textarea id="sa-alternate-sites" class="large-text code" rows="4" dir="ltr" name="<?php echo esc_attr( $n . '[alternate_sites]' ); ?>" placeholder="en=https://en.example.com&#10;fr=https://fr.example.com&#10;x-default=https://en.example.com"><?php echo esc_textarea( (string) $o['alternate_sites'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'سطر لكل لغة: رمز اللغة=رابط الموقع. يضيف وسوم hreflang لصفحات المباريات والفرق والبطولات والأيام بين نسخ الموقع بلغات مختلفة.', 'sportsarena-core' ); ?></p>
						</td>
					</tr>
					<?php
					$this->row( $n, 'delete_on_uninstall', __( 'حذف الإعدادات عند الحذف', 'sportsarena-core' ), $o['delete_on_uninstall'], __( 'حذف الإعدادات والجداول عند حذف الإضافة (المحتوى نفسه لا يُحذف).', 'sportsarena-core' ), 'checkbox' );
					?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Provider status table (quota usage, next cron runs).
	 *
	 * @return void
	 */
	private function provider_status() {
		?>
		<table class="widefat striped sa-status">
			<thead>
				<tr>
					<th><?php esc_html_e( 'المزوّد', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'الحالة', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'طلبات اليوم', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'المتبقي لدى المزوّد', 'sportsarena-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( Provider_Registry::all() as $provider ) : ?>
				<?php $state = ( new Http_Client( $provider->get_id() ) )->quota_state(); ?>
				<tr>
					<td><?php echo esc_html( $provider->get_label() ); ?></td>
					<td><?php echo $provider->is_configured() ? '✅ ' . esc_html__( 'مُعد', 'sportsarena-core' ) : '— ' . esc_html__( 'بدون مفتاح', 'sportsarena-core' ); ?></td>
					<td><?php echo esc_html( (string) $state['count'] ); ?></td>
					<td><?php echo esc_html( null === $state['remaining'] ? '—' : (string) $state['remaining'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">
			<?php
			foreach ( array( 'sportsarena_sync_live', 'sportsarena_sync_daily', 'sportsarena_sync_tables' ) as $hook ) {
				$next = wp_next_scheduled( $hook );
				/* translators: 1: cron hook, 2: date. */
				echo esc_html( sprintf( __( '%1$s: التشغيل التالي %2$s', 'sportsarena-core' ), $hook, $next ? wp_date( 'Y-m-d H:i:s', $next ) : '—' ) ) . '<br>';
			}
			if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
				echo '<strong>' . esc_html__( 'تنبيه: WP-Cron يعمل بزيارات الموقع. للحصول على تحديث حي منتظم فعّل cron حقيقياً على الخادم (راجع README).', 'sportsarena-core' ) . '</strong>';
			}
			?>
		</p>
		<?php
	}

	/**
	 * Ads page.
	 *
	 * @return void
	 */
	public function page_ads() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = Options::ads();
		$n = Options::ADS;
		?>
		<div class="wrap sa-admin">
			<h1><?php esc_html_e( 'الإعلانات (Google AdSense)', 'sportsarena-core' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'sportsarena_ads' ); ?>
				<table class="form-table" role="presentation">
					<?php
					$this->row( $n, 'publisher_id', __( 'معرّف الناشر (Publisher ID)', 'sportsarena-core' ), $o['publisher_id'], __( 'مثال: ca-pub-1234567890123456. يُحقن سكربت AdSense مرة واحدة في &lt;head&gt;.', 'sportsarena-core' ) );
					$this->row( $n, 'auto_ads', __( 'Auto Ads', 'sportsarena-core' ), $o['auto_ads'], __( 'استخدم الإعلانات التلقائية بدلاً من الخانات اليدوية (فعّلها أيضاً من لوحة AdSense).', 'sportsarena-core' ), 'checkbox' );
					$this->row( $n, 'ads_txt', __( 'ads.txt', 'sportsarena-core' ), $o['ads_txt'], __( 'إنشاء ملف ads.txt تلقائياً في جذر الموقع.', 'sportsarena-core' ), 'checkbox' );
					?>
				</table>
				<?php if ( \SportsArena\Core\Ads\Ads::ads_txt() ) : ?>
					<p><?php esc_html_e( 'محتوى ads.txt:', 'sportsarena-core' ); ?> <a href="<?php echo esc_url( home_url( '/ads.txt' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( home_url( '/ads.txt' ) ); ?></a></p>
					<pre dir="ltr" class="sa-pre"><?php echo esc_html( \SportsArena\Core\Ads\Ads::ads_txt() ); ?></pre>
				<?php endif; ?>
				<h2><?php esc_html_e( 'الخانات اليدوية', 'sportsarena-core' ); ?></h2>
				<p class="description"><?php esc_html_e( 'أدخل رقم الوحدة الإعلانية (data-ad-slot) من AdSense. لكل خانة ارتفاع محجوز لمنع اهتزاز التخطيط (CLS) وعنوان «إعلان».', 'sportsarena-core' ); ?></p>
				<table class="widefat striped sa-slots">
					<thead>
						<tr>
							<th><?php esc_html_e( 'الخانة', 'sportsarena-core' ); ?></th>
							<th><?php esc_html_e( 'تفعيل', 'sportsarena-core' ); ?></th>
							<th><?php esc_html_e( 'رقم الوحدة (data-ad-slot)', 'sportsarena-core' ); ?></th>
							<th><?php esc_html_e( 'كود مخصص (اختياري)', 'sportsarena-core' ); ?></th>
							<th><?php esc_html_e( 'الارتفاع المحجوز', 'sportsarena-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( Options::ad_slots() as $slot => $def ) : ?>
						<?php $s = $o['slots'][ $slot ]; ?>
						<tr>
							<td><strong><?php echo esc_html( $def['label'] ); ?></strong><br><code><?php echo esc_html( $slot ); ?></code></td>
							<td><input type="checkbox" name="<?php echo esc_attr( $n . '[slots][' . $slot . '][enabled]' ); ?>" value="1" <?php checked( (int) $s['enabled'], 1 ); ?> aria-label="<?php esc_attr_e( 'تفعيل', 'sportsarena-core' ); ?>"></td>
							<td><input type="text" dir="ltr" inputmode="numeric" name="<?php echo esc_attr( $n . '[slots][' . $slot . '][slot_id]' ); ?>" value="<?php echo esc_attr( $s['slot_id'] ); ?>" placeholder="1234567890"></td>
							<td>
								<?php if ( current_user_can( 'unfiltered_html' ) ) : ?>
									<textarea dir="ltr" rows="2" cols="30" name="<?php echo esc_attr( $n . '[slots][' . $slot . '][html]' ); ?>"><?php echo esc_textarea( $s['html'] ); ?></textarea>
								<?php else : ?>
									<em><?php esc_html_e( 'يتطلب صلاحية unfiltered_html', 'sportsarena-core' ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( sprintf( '%dpx / %dpx', $def['mobile'], $def['desktop'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'لا تظهر الإعلانات في: صفحة 404، نتائج البحث الفارغة، صفحات الخصوصية والتواصل والشروط وإخلاء المسؤولية.', 'sportsarena-core' ); ?></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Consent page.
	 *
	 * @return void
	 */
	public function page_consent() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = Options::consent();
		$n = Options::CONSENT;
		?>
		<div class="wrap sa-admin">
			<h1><?php esc_html_e( 'الموافقة على ملفات تعريف الارتباط', 'sportsarena-core' ); ?></h1>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'تشترط Google استخدام منصة إدارة موافقة (CMP) معتمدة من Google لزوار المنطقة الاقتصادية الأوروبية والمملكة المتحدة وسويسرا. الأسهل: فعّل رسالة GDPR من AdSense > الخصوصية والمراسلة، ثم اختر وضع «CMP خارجية» هنا.', 'sportsarena-core' ); ?>
			</p></div>
			<form method="post" action="options.php">
				<?php settings_fields( 'sportsarena_consent' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'الوضع', 'sportsarena-core' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[mode]" value="cmp" <?php checked( $o['mode'], 'cmp' ); ?>> <?php esc_html_e( 'CMP خارجية معتمدة من Google (موصى به)', 'sportsarena-core' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[mode]" value="builtin" <?php checked( $o['mode'], 'builtin' ); ?>> <?php esc_html_e( 'لافتة الموافقة المدمجة (احتياطية)', 'sportsarena-core' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[mode]" value="off" <?php checked( $o['mode'], 'off' ); ?>> <?php esc_html_e( 'تعطيل', 'sportsarena-core' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<?php
					$this->row( $n, 'consent_mode', __( 'Google Consent Mode v2', 'sportsarena-core' ), $o['consent_mode'], __( 'إرسال القيم الافتراضية (ad_storage, ad_user_data, ad_personalization, analytics_storage) قبل أي وسم من Google.', 'sportsarena-core' ), 'checkbox' );
					$this->row( $n, 'eea_only', __( 'أوروبا فقط', 'sportsarena-core' ), $o['eea_only'], __( 'الرفض الافتراضي لزوار أوروبا/بريطانيا/سويسرا فقط، والسماح لباقي الدول.', 'sportsarena-core' ), 'checkbox' );
					?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Log page.
	 *
	 * @return void
	 */
	public function page_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
		$rows  = Logger::recent( 300, in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : '' );
		?>
		<div class="wrap sa-admin">
			<h1><?php esc_html_e( 'سجل المزامنة والأخطاء', 'sportsarena-core' ); ?></h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-log' ) ); ?>"><?php esc_html_e( 'الكل', 'sportsarena-core' ); ?></a> |
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-log&level=error' ) ); ?>"><?php esc_html_e( 'الأخطاء', 'sportsarena-core' ); ?></a> |
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-log&level=warning' ) ); ?>"><?php esc_html_e( 'التحذيرات', 'sportsarena-core' ); ?></a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sportsarena_clear_log">
				<?php wp_nonce_field( 'sportsarena_clear_log' ); ?>
				<?php submit_button( __( 'مسح السجل', 'sportsarena-core' ), 'delete', 'submit', false ); ?>
			</form>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'الوقت (UTC)', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'المستوى', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'المصدر', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'الرسالة', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'التفاصيل', 'sportsarena-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'لا توجد سجلات.', 'sportsarena-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr class="sa-log-<?php echo esc_attr( $row->level ); ?>">
						<td dir="ltr"><?php echo esc_html( $row->created_at ); ?></td>
						<td><?php echo esc_html( $row->level ); ?></td>
						<td><?php echo esc_html( $row->source ); ?></td>
						<td><?php echo esc_html( $row->message ); ?></td>
						<td dir="ltr"><code><?php echo esc_html( (string) $row->context ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Tools page.
	 *
	 * @return void
	 */
	public function page_tools() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.
		$done = isset( $_GET['sa_done'] ) ? sanitize_key( wp_unslash( $_GET['sa_done'] ) ) : '';
		$tool = static function ( $action, $label, $description, $extra = array(), $button_class = 'secondary' ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-tool">
				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
				<?php foreach ( $extra as $key => $value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<?php endforeach; ?>
				<?php wp_nonce_field( $action ); ?>
				<?php submit_button( $label, $button_class, 'submit', false ); ?>
				<span class="description"><?php echo esc_html( $description ); ?></span>
			</form>
			<?php
		};
		?>
		<div class="wrap sa-admin">
			<h1><?php esc_html_e( 'أدوات SportsArena', 'sportsarena-core' ); ?></h1>
			<?php if ( $done ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تم تنفيذ العملية. راجع السجل للتفاصيل.', 'sportsarena-core' ); ?></p></div>
			<?php endif; ?>
			<h2><?php esc_html_e( 'البيانات التجريبية', 'sportsarena-core' ); ?></h2>
			<?php
			$tool( 'sportsarena_seed', __( 'إنشاء بيانات تجريبية', 'sportsarena-core' ), __( 'بطولات وفرق ومباريات (أمس/اليوم/غداً) وأخبار لعرض الموقع قبل ربط الـ API.', 'sportsarena-core' ), array(), 'primary' );
			$tool( 'sportsarena_unseed', __( 'حذف البيانات التجريبية', 'sportsarena-core' ), __( 'يحذف فقط العناصر المعلَّمة كتجريبية.', 'sportsarena-core' ), array(), 'delete' );
			?>
			<h2><?php esc_html_e( 'المزامنة اليدوية', 'sportsarena-core' ); ?></h2>
			<?php
			$tool( 'sportsarena_sync_now', __( 'مزامنة مباريات اليوم والغد', 'sportsarena-core' ), __( 'تستهلك طلبات من رصيدك.', 'sportsarena-core' ), array( 'job' => 'daily' ) );
			$tool( 'sportsarena_sync_now', __( 'مزامنة الترتيب والهدافين', 'sportsarena-core' ), __( 'طلبان لكل بطولة متابعة.', 'sportsarena-core' ), array( 'job' => 'tables' ) );
			$tool( 'sportsarena_sync_now', __( 'تحديث المباريات الحية', 'sportsarena-core' ), __( 'يعمل فقط عند وجود مباريات حية.', 'sportsarena-core' ), array( 'job' => 'live' ) );
			?>
			<h2><?php esc_html_e( 'الصفحات والملفات', 'sportsarena-core' ); ?></h2>
			<?php
			$tool( 'sportsarena_create_pages', __( 'إنشاء الصفحات الإلزامية', 'sportsarena-core' ), __( 'من نحن، اتصل بنا، الخصوصية، الشروط، إخلاء المسؤولية (لا يستبدل الموجود).', 'sportsarena-core' ) );
			$tool( 'sportsarena_write_ads_txt', __( 'إعادة إنشاء ads.txt', 'sportsarena-core' ), __( 'يكتب الملف في جذر الموقع إن أمكن.', 'sportsarena-core' ) );
			$tool( 'sportsarena_flush', __( 'تحديث الروابط الدائمة والكاش', 'sportsarena-core' ), __( 'استخدمه إذا ظهرت صفحة 404 لروابط المباريات.', 'sportsarena-core' ) );
			?>
		</div>
		<?php
	}
}
