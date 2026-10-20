<?php
/**
 * AdSense readiness check: automated checks of the site against the
 * AdSense program policies and the usual review requirements.
 *
 * Each check returns pass / warn / fail with an explanation and a fix. The
 * check is informative: Google's review also looks at things a plugin
 * cannot measure (originality and value of the content, site age in some
 * countries, traffic sources).
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Admin;

use SportsArena\Core\Ads\Ads;
use SportsArena\Core\Options;
use SportsArena\Core\Pages;
use SportsArena\Core\Post_Types;
use SportsArena\Core\Seo\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * AdSense readiness check.
 */
final class Adsense_Check {

	const PASS = 'pass';
	const WARN = 'warn';
	const FAIL = 'fail';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
	}

	/**
	 * Submenu page.
	 *
	 * @return void
	 */
	public function menu() {
		add_submenu_page( Settings::SLUG, __( 'فحص جاهزية AdSense', 'sportsarena-core' ), __( 'فحص AdSense', 'sportsarena-core' ), 'manage_options', Settings::SLUG . '-adsense-check', array( $this, 'page' ) );
	}

	/**
	 * Build one result row.
	 *
	 * @param string $group   Group label.
	 * @param string $label   Check label.
	 * @param string $status  pass|warn|fail.
	 * @param string $message What was found.
	 * @param string $fix     How to fix (empty when passing).
	 * @return array{group:string,label:string,status:string,message:string,fix:string}
	 */
	private static function row( $group, $label, $status, $message, $fix = '' ) {
		return compact( 'group', 'label', 'status', 'message', 'fix' );
	}

	/**
	 * Run every check.
	 *
	 * @param bool $remote Also fetch /ads.txt over HTTP.
	 * @return array<int,array>
	 */
	public static function run( $remote = true ) {
		$rows    = array();
		$site    = __( 'الموقع', 'sportsarena-core' );
		$pages_g = __( 'الصفحات والمحتوى', 'sportsarena-core' );
		$ads_g   = __( 'الإعلانات', 'sportsarena-core' );
		$policy  = __( 'سياسات المحتوى', 'sportsarena-core' );
		$privacy = __( 'الخصوصية والموافقة', 'sportsarena-core' );
		$tech    = __( 'الأداء والتقنية', 'sportsarena-core' );

		// --- Site basics.
		$https  = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
		$rows[] = self::row( $site, __( 'شهادة HTTPS', 'sportsarena-core' ), $https ? self::PASS : self::FAIL, $https ? __( 'الموقع يعمل عبر HTTPS.', 'sportsarena-core' ) : __( 'رابط الموقع يبدأ بـ http.', 'sportsarena-core' ), $https ? '' : __( 'فعّل شهادة SSL من الاستضافة وغيّر رابط الموقع في الإعدادات > عام إلى https.', 'sportsarena-core' ) );

		$public = (bool) get_option( 'blog_public' );
		$rows[] = self::row( $site, __( 'ظهور الموقع لمحركات البحث', 'sportsarena-core' ), $public ? self::PASS : self::FAIL, $public ? __( 'محركات البحث مسموح لها بالفهرسة.', 'sportsarena-core' ) : __( 'خيار «منع محركات البحث من فهرسة الموقع» مفعّل.', 'sportsarena-core' ), $public ? '' : __( 'ألغِ تفعيله من الإعدادات > القراءة.', 'sportsarena-core' ) );

		$pretty = '' !== (string) get_option( 'permalink_structure' );
		$rows[] = self::row( $site, __( 'الروابط الدائمة', 'sportsarena-core' ), $pretty ? self::PASS : self::FAIL, $pretty ? __( 'روابط صديقة لمحركات البحث.', 'sportsarena-core' ) : __( 'الروابط بصيغة ?p=123.', 'sportsarena-core' ), $pretty ? '' : __( 'اختر «اسم المقالة» من الإعدادات > الروابط الدائمة.', 'sportsarena-core' ) );

		$tagline = (string) get_bloginfo( 'description' );
		$named   = '' !== trim( (string) get_bloginfo( 'name' ) ) && '' !== trim( $tagline ) && 'Just another WordPress site' !== $tagline;
		$rows[]  = self::row( $site, __( 'اسم الموقع ووصفه', 'sportsarena-core' ), $named ? self::PASS : self::WARN, $named ? __( 'الاسم والوصف مضبوطان.', 'sportsarena-core' ) : __( 'الوصف (الشعار) فارغ أو افتراضي.', 'sportsarena-core' ), $named ? '' : __( 'اكتب اسماً ووصفاً واضحين في الإعدادات > عام.', 'sportsarena-core' ) );

		// --- Required pages.
		$ids     = Pages::stored_ids();
		$labels  = array(
			'about'      => __( 'من نحن', 'sportsarena-core' ),
			'contact'    => __( 'اتصل بنا', 'sportsarena-core' ),
			'privacy'    => __( 'سياسة الخصوصية', 'sportsarena-core' ),
			'terms'      => __( 'الشروط والأحكام', 'sportsarena-core' ),
			'disclaimer' => __( 'إخلاء المسؤولية', 'sportsarena-core' ),
		);
		$missing = array();
		foreach ( $labels as $key => $label ) {
			if ( empty( $ids[ $key ] ) || 'publish' !== get_post_status( $ids[ $key ] ) ) {
				$missing[] = $label;
			}
		}
		$rows[] = self::row(
			$pages_g,
			__( 'الصفحات الإلزامية', 'sportsarena-core' ),
			$missing ? self::FAIL : self::PASS,
			/* translators: %s: list of pages. */
			$missing ? sprintf( __( 'صفحات ناقصة: %s', 'sportsarena-core' ), implode( wp_get_list_item_separator(), $missing ) ) : __( 'من نحن، اتصل بنا، الخصوصية، الشروط، إخلاء المسؤولية: منشورة.', 'sportsarena-core' ),
			$missing ? __( 'اضغط «إنشاء الصفحات الإلزامية» في SportsArena > الأدوات.', 'sportsarena-core' ) : ''
		);

		$privacy_id = $ids['privacy'] ?? (int) get_option( 'wp_page_for_privacy_policy' );
		$text       = $privacy_id ? (string) get_post_field( 'post_content', $privacy_id ) : '';
		$mentions   = $text && false !== stripos( $text, 'AdSense' ) && ( false !== stripos( $text, 'cookie' ) || false !== mb_strpos( $text, 'ملفات تعريف الارتباط' ) );
		$rows[]     = self::row( $privacy, __( 'سياسة الخصوصية تذكر AdSense والكوكيز', 'sportsarena-core' ), $mentions ? self::PASS : self::FAIL, $mentions ? __( 'الصفحة تذكر Google AdSense وملفات تعريف الارتباط والأطراف الثالثة.', 'sportsarena-core' ) : __( 'لم يُعثر على ذكر AdSense أو ملفات تعريف الارتباط.', 'sportsarena-core' ), $mentions ? '' : __( 'أضف فقرة عن Google AdSense وملفات تعريف الارتباط (القالب المُنشأ تلقائياً يتضمنها).', 'sportsarena-core' ) );

		$contact_id = $ids['contact'] ?? 0;
		$has_form   = $contact_id && ( has_shortcode( (string) get_post_field( 'post_content', $contact_id ), 'sportsarena_contact_form' ) || false !== stripos( (string) get_post_field( 'post_content', $contact_id ), '<form' ) || false !== stripos( (string) get_post_field( 'post_content', $contact_id ), '[contact-form' ) || false !== stripos( (string) get_post_field( 'post_content', $contact_id ), '[wpforms' ) );
		$rows[]     = self::row( $pages_g, __( 'وسيلة تواصل فعّالة', 'sportsarena-core' ), $has_form ? self::PASS : self::WARN, $has_form ? __( 'صفحة «اتصل بنا» تحتوي نموذج تواصل.', 'sportsarena-core' ) : __( 'لم يُعثر على نموذج في صفحة التواصل.', 'sportsarena-core' ), $has_form ? '' : __( 'أضف [sportsarena_contact_form] أو نموذج Contact Form 7 / WPForms، وتأكد أن البريد يصل (WP Mail SMTP).', 'sportsarena-core' ) );

		$footer = 'sportsarena-theme' === get_template() || has_nav_menu( 'footer' );
		$rows[] = self::row( $pages_g, __( 'روابط الصفحات في الفوتر والتنقل', 'sportsarena-core' ), $footer ? self::PASS : self::WARN, $footer ? __( 'القالب يعرض روابط الصفحات الإلزامية في الفوتر وقائمة تنقل واضحة.', 'sportsarena-core' ) : __( 'تعذّر التأكد من روابط الفوتر مع هذا القالب.', 'sportsarena-core' ), $footer ? '' : __( 'أضف الصفحات الإلزامية إلى قائمة ظاهرة في كل الصفحات.', 'sportsarena-core' ) );

		// --- Content.
		$posts  = (int) wp_count_posts( 'post' )->publish;
		$demo   = self::count_demo();
		$real   = max( 0, $posts - $demo['post'] );
		$status = $real >= 25 ? self::PASS : ( $real >= 10 ? self::WARN : self::FAIL );
		$rows[] = self::row(
			$pages_g,
			__( 'حجم المحتوى الأصلي', 'sportsarena-core' ),
			$status,
			/* translators: %d: number of articles. */
			sprintf( __( '%d مقالاً/خبراً منشوراً (بدون التجريبي).', 'sportsarena-core' ), $real ),
			self::PASS === $status ? '' : __( 'انشر 25–30 مقالاً أصلياً على الأقل قبل التقديم (أخبار، تحليلات، معاينات). صفحات المباريات وحدها لا تكفي لأن بياناتها مشتركة مع مواقع أخرى.', 'sportsarena-core' )
		);

		$words  = self::average_words();
		$status = $words >= 300 ? self::PASS : ( $words >= 150 ? self::WARN : self::FAIL );
		$rows[] = self::row(
			$pages_g,
			__( 'طول المقالات', 'sportsarena-core' ),
			$posts ? $status : self::FAIL,
			/* translators: %d: average words. */
			sprintf( __( 'متوسط طول آخر 20 مقالاً: %d كلمة.', 'sportsarena-core' ), $words ),
			self::PASS === $status ? '' : __( 'اكتب مقالات من 300 كلمة فأكثر بمعلومة مفيدة وتحليل، وتجنّب الأخبار القصيرة جداً أو المنسوخة.', 'sportsarena-core' )
		);

		$previews = self::match_previews();
		$rows[]   = self::row(
			$pages_g,
			__( 'محتوى أصلي في صفحات المباريات', 'sportsarena-core' ),
			$previews >= 5 ? self::PASS : self::WARN,
			/* translators: %d: number of matches. */
			sprintf( __( '%d مباراة تحتوي «معاينة/تحليل» مكتوبة.', 'sportsarena-core' ), $previews ),
			$previews >= 5 ? '' : __( 'اكتب معاينة نصية للمباريات المهمة: صفحات النتائج المجردة تُعتبر محتوى ضعيفاً.', 'sportsarena-core' )
		);

		$demo_total = array_sum( $demo );
		$rows[]     = self::row(
			$pages_g,
			__( 'حذف البيانات التجريبية', 'sportsarena-core' ),
			$demo_total ? self::FAIL : self::PASS,
			/* translators: %d: number of items. */
			$demo_total ? sprintf( __( '%d عنصراً تجريبياً ما زال منشوراً.', 'sportsarena-core' ), $demo_total ) : __( 'لا توجد بيانات تجريبية.', 'sportsarena-core' ),
			$demo_total ? __( 'اضغط «حذف البيانات التجريبية» في الأدوات.', 'sportsarena-core' ) : ''
		);

		$defaults = array();
		$hello    = get_page_by_path( 'hello-world', OBJECT, 'post' );
		if ( $hello && 'publish' === $hello->post_status ) {
			$defaults[] = 'Hello world';
		}
		$sample = get_page_by_path( 'sample-page' );
		if ( $sample && 'publish' === $sample->post_status ) {
			$defaults[] = 'Sample Page';
		}
		$rows[] = self::row(
			$pages_g,
			__( 'المحتوى الافتراضي لووردبريس', 'sportsarena-core' ),
			$defaults ? self::WARN : self::PASS,
			/* translators: %s: list of items. */
			$defaults ? sprintf( __( 'ما زال منشوراً: %s', 'sportsarena-core' ), implode( wp_get_list_item_separator(), $defaults ) ) : __( 'لا يوجد محتوى افتراضي.', 'sportsarena-core' ),
			$defaults ? __( 'احذف المقالة والصفحة الافتراضيتين.', 'sportsarena-core' ) : ''
		);

		// --- Content policies.
		$streams = self::scan( '/(\.m3u8|acestream|sopcast|yalla[ -]?shoot|kora[ -]?(?:live|star)|koora[ -]?live|live[ -]?stream|livestream|streaming link|رابط بث|روابط بث مباشر|بث مباشر مجاني)/iu', array( 'disclaimer', 'about', 'terms' ) );
		$rows[]  = self::row(
			$policy,
			__( 'لا بث مباشر ولا روابط بث', 'sportsarena-core' ),
			$streams ? self::FAIL : self::PASS,
			/* translators: %s: list of titles. */
			$streams ? sprintf( __( 'عبارات أو روابط بث في: %s', 'sportsarena-core' ), implode( wp_get_list_item_separator(), $streams ) ) : __( 'لم يُعثر على روابط أو عبارات بث في المحتوى.', 'sportsarena-core' ),
			$streams ? __( 'احذف أي رابط أو إشارة لبث المباريات: البث غير المرخّص يؤدي إلى رفض AdSense.', 'sportsarena-core' ) : ''
		);

		$betting = self::scan( '/(1xbet|bet365|betway|melbet|linebet|parimatch|sportingbet|كازينو|مراهنات رياضية مضمونة|توقعات مراهنات|اربح المال من المراهنة)/iu', array( 'disclaimer', 'about', 'terms' ) );
		$rows[]  = self::row(
			$policy,
			__( 'لا ترويج للمراهنات أو القمار', 'sportsarena-core' ),
			$betting ? self::FAIL : self::PASS,
			/* translators: %s: list of titles. */
			$betting ? sprintf( __( 'محتوى مراهنات في: %s', 'sportsarena-core' ), implode( wp_get_list_item_separator(), $betting ) ) : __( 'لم يُعثر على ترويج لمواقع المراهنات.', 'sportsarena-core' ),
			$betting ? __( 'احذف روابط وإعلانات مواقع المراهنات: تتعارض مع سياسات AdSense في أغلب الدول العربية.', 'sportsarena-core' ) : ''
		);

		$logos  = (bool) Options::get( 'allow_logos' );
		$rows[] = self::row(
			$policy,
			__( 'حقوق الشعارات والصور', 'sportsarena-core' ),
			$logos ? self::WARN : self::PASS,
			$logos ? __( 'شعارات الأندية والبطولات من المزوّد معروضة.', 'sportsarena-core' ) : __( 'تُعرض أعلام الدول وصور بديلة عامة فقط.', 'sportsarena-core' ),
			$logos ? __( 'الشعارات علامات تجارية لأصحابها: تأكد أن خطة المزوّد تسمح بعرضها، واستخدمها للتعريف فقط، واحذف أي شعار عند طلب صاحبه. صور اللاعبين من وكالات الصور تحتاج ترخيصاً.', 'sportsarena-core' ) : ''
		);

		// --- Ads.
		$publisher = Ads::publisher_id();
		$rows[]    = self::row( $ads_g, __( 'معرّف الناشر', 'sportsarena-core' ), $publisher ? self::PASS : self::FAIL, $publisher ? $publisher : __( 'غير مضبوط.', 'sportsarena-core' ), $publisher ? '' : __( 'أدخل ca-pub-... في SportsArena > الإعلانات (يُحقن السكربت في <head> للتحقق من الموقع).', 'sportsarena-core' ) );

		$rows[] = self::ads_txt_row( $ads_g, $publisher, $remote );

		$settings = Options::ads();
		$enabled  = array();
		foreach ( (array) $settings['slots'] as $slot => $config ) {
			if ( ! empty( $config['enabled'] ) ) {
				$enabled[] = $slot;
			}
		}
		$placed = ! empty( $settings['auto_ads'] ) || $enabled;
		$rows[] = self::row(
			$ads_g,
			__( 'أماكن الإعلانات', 'sportsarena-core' ),
			$placed ? self::PASS : self::WARN,
			! empty( $settings['auto_ads'] ) ? __( 'الإعلانات التلقائية مفعّلة.', 'sportsarena-core' ) : (
				/* translators: %d: number of slots. */
				$enabled ? sprintf( __( '%d خانة يدوية مفعّلة، لكل منها ارتفاع محجوز وعنوان «إعلان».', 'sportsarena-core' ), count( $enabled ) ) : __( 'لا توجد خانات مفعّلة بعد.', 'sportsarena-core' )
			),
			$placed ? '' : __( 'فعّل Auto Ads أو الخانات اليدوية بعد الموافقة. أثناء المراجعة يكفي سكربت التحقق.', 'sportsarena-core' )
		);

		$rows[] = self::row( $ads_g, __( 'سياسات وضع الإعلانات', 'sportsarena-core' ), self::PASS, __( 'لا إعلانات في صفحات 404 والبحث الفارغ والخصوصية والتواصل والشروط؛ لا إعلانات داخل قوائم المباريات أو ملاصقة للأزرار؛ لا تحديث تلقائي للصفحة (النتائج الحية تُحدَّث جزئياً)؛ لا نوافذ منبثقة.', 'sportsarena-core' ) );

		// --- Privacy & consent.
		$consent = Options::consent();
		if ( 'cmp' === $consent['mode'] ) {
			$rows[] = self::row( $privacy, __( 'منصة الموافقة (CMP)', 'sportsarena-core' ), self::PASS, __( 'وضع CMP خارجية معتمدة من Google.', 'sportsarena-core' ), __( 'تأكد أن رسالة GDPR منشورة في AdSense > الخصوصية والمراسلة.', 'sportsarena-core' ) );
		} elseif ( 'builtin' === $consent['mode'] ) {
			$rows[] = self::row( $privacy, __( 'منصة الموافقة (CMP)', 'sportsarena-core' ), self::WARN, __( 'اللافتة المدمجة مفعّلة (احتياطية).', 'sportsarena-core' ), __( 'لزوار أوروبا/بريطانيا/سويسرا تطلب Google منصة CMP معتمدة: فعّل رسالة GDPR المجانية في AdSense واختر وضع «CMP خارجية».', 'sportsarena-core' ) );
		} else {
			$rows[] = self::row( $privacy, __( 'منصة الموافقة (CMP)', 'sportsarena-core' ), self::FAIL, __( 'الموافقة على الكوكيز معطّلة.', 'sportsarena-core' ), __( 'فعّل CMP معتمدة أو اللافتة المدمجة من SportsArena > الموافقة على الكوكيز.', 'sportsarena-core' ) );
		}
		$mode_v2 = 'off' !== $consent['mode'] && ! empty( $consent['consent_mode'] );
		$rows[]  = self::row( $privacy, __( 'Google Consent Mode v2', 'sportsarena-core' ), $mode_v2 ? self::PASS : self::WARN, $mode_v2 ? __( 'القيم الافتراضية تُرسل قبل وسوم Google.', 'sportsarena-core' ) : __( 'غير مفعّل.', 'sportsarena-core' ), $mode_v2 ? '' : __( 'فعّله من صفحة الموافقة على الكوكيز.', 'sportsarena-core' ) );

		// --- Tech.
		$seo    = Seo::seo_plugin();
		$rows[] = self::row( $tech, __( 'خريطة الموقع وSEO', 'sportsarena-core' ), self::PASS, $seo ? __( 'إضافة SEO مكتشفة وتتولى خريطة الموقع، وSchema الرياضة من SportsArena.', 'sportsarena-core' ) : __( 'wp-sitemap.xml وnews-sitemap.xml وSchema وOpen Graph مفعّلة.', 'sportsarena-core' ), __( 'أرسل خريطة الموقع في Google Search Console.', 'sportsarena-core' ) );

		$cache  = defined( 'LSCWP_V' ) || defined( 'WP_ROCKET_VERSION' ) || defined( 'W3TC' ) || defined( 'WPCACHEHOME' ) || class_exists( 'autoptimizeMain' );
		$rows[] = self::row( $tech, __( 'التخزين المؤقت للصفحات', 'sportsarena-core' ), $cache ? self::PASS : self::WARN, $cache ? __( 'إضافة كاش مكتشفة.', 'sportsarena-core' ) : __( 'لم تُكتشف إضافة كاش.', 'sportsarena-core' ), $cache ? '' : __( 'ثبّت LiteSpeed Cache أو WP Rocket وفعّل ضغط gzip/brotli لسرعة أفضل.', 'sportsarena-core' ) );

		$cron   = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$rows[] = self::row( $tech, __( 'Cron حقيقي', 'sportsarena-core' ), $cron ? self::PASS : self::WARN, $cron ? __( 'WP-Cron يعمل من الخادم.', 'sportsarena-core' ) : __( 'WP-Cron يعتمد على الزيارات.', 'sportsarena-core' ), $cron ? '' : __( 'اتبع قسم Cron في README لتحديث النتائج بانتظام.', 'sportsarena-core' ) );

		return $rows;
	}

	/**
	 * Check /ads.txt.
	 *
	 * @param string $group     Group.
	 * @param string $publisher Publisher id.
	 * @param bool   $remote    Fetch over HTTP.
	 * @return array
	 */
	private static function ads_txt_row( $group, $publisher, $remote ) {
		$label = __( 'ملف ads.txt', 'sportsarena-core' );
		if ( ! $publisher ) {
			return self::row( $group, $label, self::FAIL, __( 'يحتاج معرّف الناشر أولاً.', 'sportsarena-core' ), __( 'أدخل معرّف الناشر وفعّل خيار ads.txt.', 'sportsarena-core' ) );
		}
		$expected = str_replace( 'ca-', '', $publisher );
		if ( ! $remote ) {
			$content = Ads::ads_txt();
			return self::row( $group, $label, str_contains( $content, $expected ) ? self::PASS : self::FAIL, $content ? $content : __( 'معطّل.', 'sportsarena-core' ) );
		}
		$response = wp_remote_get(
			home_url( '/ads.txt' ),
			array(
				'timeout'   => 8,
				'sslverify' => false,
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::row( $group, $label, self::WARN, $response->get_error_message(), __( 'افتح رابط /ads.txt بنفسك من المتصفح وتأكد أنه يعرض سطر google.com.', 'sportsarena-core' ) );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		$ok   = 200 === (int) wp_remote_retrieve_response_code( $response ) && str_contains( $body, $expected ) && str_contains( $body, 'f08c47fec0942fa0' );
		return self::row(
			$group,
			$label,
			$ok ? self::PASS : self::FAIL,
			/* translators: %s: URL. */
			$ok ? sprintf( __( '%s يحتوي سطر Google الصحيح.', 'sportsarena-core' ), home_url( '/ads.txt' ) ) : __( 'الملف غير موجود أو لا يحتوي معرّف الناشر.', 'sportsarena-core' ),
			$ok ? '' : __( 'فعّل ads.txt في الإعلانات واضغط «إعادة إنشاء ads.txt» في الأدوات، ثم امسح كاش الموقع.', 'sportsarena-core' )
		);
	}

	/**
	 * Count published demo items by type.
	 *
	 * @return array<string,int>
	 */
	private static function count_demo() {
		$out = array();
		foreach ( array( 'post', Post_Types::MATCH, Post_Types::TEAM, Post_Types::COMPETITION ) as $type ) {
			$out[ $type ] = count(
				get_posts(
					array(
						'post_type'      => $type,
						'post_status'    => 'publish',
						'posts_per_page' => 500,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'meta_key'       => '_sa_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Admin check.
						'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Admin check.
					)
				)
			);
		}
		return $out;
	}

	/**
	 * Average word count of the latest 20 non-demo posts.
	 *
	 * @return int
	 */
	private static function average_words() {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Admin check.
				'meta_query'     => array(
					array(
						'key'     => '_sa_demo',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		if ( ! $posts ) {
			return 0;
		}
		$total = 0;
		foreach ( $posts as $post ) {
			$text   = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
			$total += '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) );
		}
		return (int) round( $total / count( $posts ) );
	}

	/**
	 * Number of matches with an editor-written preview.
	 *
	 * @return int
	 */
	private static function match_previews() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin check.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND CHAR_LENGTH(post_content) > 200", Post_Types::MATCH ) );
	}

	/**
	 * Titles of published content (and TV channel fields) matching a pattern.
	 *
	 * @param string   $pattern   Regex.
	 * @param string[] $skip_keys Required page keys to skip (they mention these words to deny them).
	 * @return string[]
	 */
	private static function scan( $pattern, array $skip_keys ) {
		$skip  = array_filter( array_map( 'intval', array_intersect_key( Pages::stored_ids(), array_flip( $skip_keys ) ) ) );
		$found = array();
		$page  = 1;
		do {
			$posts = get_posts(
				array(
					'post_type'      => array( 'post', 'page', Post_Types::MATCH ),
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'paged'          => $page,
					'post__not_in'   => $skip, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Admin check.
				)
			);
			foreach ( $posts as $post ) {
				$haystack = $post->post_title . ' ' . $post->post_content . ' ' . (string) get_post_meta( $post->ID, '_sa_tv', true );
				if ( preg_match( $pattern, $haystack ) ) {
					$found[] = get_the_title( $post );
					if ( count( $found ) >= 10 ) {
						return $found;
					}
				}
			}
			++$page;
			$more = 200 === count( $posts );
		} while ( $more && $page <= 25 );
		return $found;
	}

	/**
	 * Admin page.
	 *
	 * @return void
	 */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rows   = self::run();
		$counts = array_count_values( wp_list_pluck( $rows, 'status' ) );
		$icons  = array(
			self::PASS => '✅',
			self::WARN => '⚠️',
			self::FAIL => '❌',
		);
		?>
		<div class="wrap sa-admin">
			<h1><?php esc_html_e( 'فحص جاهزية الموقع لـ Google AdSense', 'sportsarena-core' ); ?></h1>
			<p class="sa-check-summary">
				<?php
				/* translators: 1: passed, 2: warnings, 3: failures. */
				echo esc_html( sprintf( __( 'ناجح: %1$d · تحذيرات: %2$d · يجب إصلاحه: %3$d', 'sportsarena-core' ), $counts[ self::PASS ] ?? 0, $counts[ self::WARN ] ?? 0, $counts[ self::FAIL ] ?? 0 ) );
				?>
			</p>
			<p class="description"><?php esc_html_e( 'فحص آلي إرشادي. مراجعة Google تقيّم أيضاً أصالة المحتوى وقيمته للزائر، وقد تشترط بعض الدول عمراً أدنى للموقع.', 'sportsarena-core' ); ?></p>
			<table class="widefat striped sa-check">
				<thead><tr>
					<th><?php esc_html_e( 'الحالة', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'الفحص', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'النتيجة', 'sportsarena-core' ); ?></th>
					<th><?php esc_html_e( 'طريقة الإصلاح', 'sportsarena-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php $group = ''; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php if ( $row['group'] !== $group ) : ?>
						<?php $group = $row['group']; ?>
						<tr class="sa-check__group"><th colspan="4"><?php echo esc_html( $group ); ?></th></tr>
					<?php endif; ?>
					<tr class="sa-check--<?php echo esc_attr( $row['status'] ); ?>">
						<td><?php echo esc_html( $icons[ $row['status'] ] ); ?></td>
						<td><strong><?php echo esc_html( $row['label'] ); ?></strong></td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
						<td><?php echo esc_html( $row['fix'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
