<?php
/**
 * Required pages for AdSense review: About, Contact, Privacy, Terms, Disclaimer.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Required pages.
 */
final class Pages {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// The theme calls sportsarena_create_required_pages() on activation;
		// this keeps the stored IDs in sync if a page is deleted.
		add_action( 'before_delete_post', array( $this, 'forget_page' ) );
	}

	/**
	 * Remove a deleted page from the stored map.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function forget_page( $post_id ) {
		$ids = self::stored_ids();
		$key = array_search( (int) $post_id, $ids, true );
		if ( false !== $key ) {
			unset( $ids[ $key ] );
			update_option( Options::PAGES, $ids );
		}
	}

	/**
	 * Stored page IDs (default language).
	 *
	 * @return array<string,int>
	 */
	public static function stored_ids() {
		return array_map( 'intval', (array) get_option( Options::PAGES, array() ) );
	}

	/**
	 * Page IDs for the current request. With Polylang the translation in the
	 * current language is returned (footer links, ad exclusions).
	 *
	 * @return array<string,int>
	 */
	public static function ids() {
		$ids = self::stored_ids();
		if ( function_exists( 'pll_get_post' ) && ! is_admin() ) {
			foreach ( $ids as $key => $id ) {
				$translated  = (int) pll_get_post( $id );
				$ids[ $key ] = $translated ? $translated : $id;
			}
		}
		return $ids;
	}

	/**
	 * Whether Polylang's public API is available.
	 *
	 * @return bool
	 */
	private static function polylang() {
		return function_exists( 'pll_languages_list' ) && function_exists( 'pll_set_post_language' ) && function_exists( 'pll_save_post_translations' ) && function_exists( 'pll_default_language' );
	}

	/**
	 * Create any missing required page. Existing pages are never overwritten.
	 *
	 * @return array<string,int>
	 */
	public static function create_required_pages() {
		if ( self::polylang() && pll_languages_list() ) {
			return self::create_multilingual_pages();
		}
		$ids = self::stored_ids();
		foreach ( self::definitions() as $key => $page ) {
			if ( ! empty( $ids[ $key ] ) && get_post( $ids[ $key ] ) && 'trash' !== get_post_status( $ids[ $key ] ) ) {
				continue;
			}
			$existing = get_page_by_path( $page['slug'] );
			if ( $existing && 'publish' === $existing->post_status ) {
				$ids[ $key ] = (int) $existing->ID;
				continue;
			}
			if ( $existing && in_array( $existing->post_status, array( 'draft', 'auto-draft', 'pending' ), true ) ) {
				// e.g. the unpublished default "Privacy Policy" draft created by WordPress.
				wp_update_post(
					array(
						'ID'           => $existing->ID,
						'post_title'   => $page['title'],
						'post_content' => self::fill( $page['content'] ),
						'post_status'  => 'publish',
					)
				);
				$ids[ $key ] = (int) $existing->ID;
				continue;
			}
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_content' => self::fill( $page['content'] ),
				)
			);
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$ids[ $key ] = (int) $page_id;
				self::set_template( (int) $page_id, $page );
			}
		}
		update_option( Options::PAGES, $ids );
		if ( ! empty( $ids['privacy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', $ids['privacy'] );
		}
		return $ids;
	}

	/**
	 * Polylang: one page per language (content generated in that language's
	 * locale), linked together as translations. Existing translations are kept.
	 *
	 * @return array<string,int>
	 */
	private static function create_multilingual_pages() {
		$ids     = self::stored_ids();
		$default = (string) pll_default_language( 'slug' );
		$slugs   = (array) pll_languages_list( array( 'fields' => 'slug' ) );
		$locales = (array) pll_languages_list( array( 'fields' => 'locale' ) );

		foreach ( array_keys( self::definitions() ) as $key ) {
			$translations = array();
			if ( ! empty( $ids[ $key ] ) && get_post( $ids[ $key ] ) && function_exists( 'pll_get_post_translations' ) ) {
				$translations = (array) pll_get_post_translations( $ids[ $key ] );
			}
			foreach ( $slugs as $i => $lang ) {
				if ( ! empty( $translations[ $lang ] ) ) {
					continue;
				}
				$switched = switch_to_locale( (string) ( $locales[ $i ] ?? get_locale() ) );
				$page     = self::definitions()[ $key ];
				$page_id  = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $page['title'],
						'post_name'    => $lang === $default ? $page['slug'] : $page['slug'] . '-' . $lang,
						'post_content' => self::fill( $page['content'] ),
					)
				);
				if ( $switched ) {
					restore_previous_locale();
				}
				if ( $page_id && ! is_wp_error( $page_id ) ) {
					self::set_template( (int) $page_id, $page );
					pll_set_post_language( $page_id, $lang );
					$translations[ $lang ] = (int) $page_id;
				}
			}
			if ( $translations ) {
				pll_save_post_translations( $translations );
				$ids[ $key ] = (int) ( $translations[ $default ] ?? reset( $translations ) );
			}
		}
		update_option( Options::PAGES, $ids );
		if ( ! empty( $ids['privacy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', $ids['privacy'] );
		}
		return $ids;
	}

	/**
	 * Assign the page template of a definition (e.g. the news hub).
	 *
	 * @param int   $page_id Page ID.
	 * @param array $page    Definition.
	 * @return void
	 */
	private static function set_template( $page_id, array $page ) {
		if ( ! empty( $page['template'] ) ) {
			update_post_meta( $page_id, '_wp_page_template', $page['template'] );
		}
	}

	/**
	 * Replace placeholders.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private static function fill( $content ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return strtr(
			$content,
			array(
				'{site}'   => get_bloginfo( 'name' ),
				'{domain}' => (string) $host,
				'{email}'  => (string) get_option( 'admin_email' ),
				'{date}'   => wp_date( 'j F Y' ),
			)
		);
	}

	/**
	 * Page definitions with editable template content (block markup), in the
	 * current site language. Placeholders: {site} = site name, {domain} =
	 * domain, {email} = admin email, {date} = today's date.
	 *
	 * @return array<string,array{title:string,slug:string,content:string,template?:string}>
	 */
	public static function definitions() {
		$p  = static function ( $text ) {
			return "<!-- wp:paragraph -->\n<p>" . $text . "</p>\n<!-- /wp:paragraph -->\n\n";
		};
		$h  = static function ( $text ) {
			return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . $text . "</h2>\n<!-- /wp:heading -->\n\n";
		};
		$ul = static function ( array $items ) {
			$li = '';
			foreach ( $items as $item ) {
				$li .= '<li>' . $item . '</li>';
			}
			return "<!-- wp:list -->\n<ul>" . $li . "</ul>\n<!-- /wp:list -->\n\n";
		};

		return array(
			'news'       => array(
				'title'    => __( 'الأخبار', 'sportsarena-core' ),
				'slug'     => 'news',
				'template' => 'template-news.php',
				'content'  => $p( __( 'آخر الأخبار والتحليلات من عالم كرة القدم وباقي الرياضات، مقسّمة حسب الرياضة.', 'sportsarena-core' ) ),
			),
			'about'      => array(
				'title'   => __( 'من نحن', 'sportsarena-core' ),
				'slug'    => 'about',
				'content' => $p( __( '<strong>{site}</strong> موقع رياضي عربي يقدّم نتائج المباريات ومواعيدها وجداول الترتيب والإحصائيات والأخبار الرياضية لكرة القدم وباقي الرياضات، بصورة سريعة ودقيقة.', 'sportsarena-core' ) )
					. $h( __( 'ماذا نقدّم؟', 'sportsarena-core' ) )
					. $ul(
						array(
							__( 'نتائج المباريات وتحديثها أثناء اللعب (نصياً فقط، دون أي بث).', 'sportsarena-core' ),
							__( 'مواعيد المباريات بتوقيتك المحلي والقنوات الناقلة كمعلومة نصية.', 'sportsarena-core' ),
							__( 'جداول الترتيب وقوائم الهدافين للدوريات والبطولات.', 'sportsarena-core' ),
							__( 'الأخبار والتحليلات والمعاينات التي يكتبها فريق التحرير.', 'sportsarena-core' ),
						)
					)
					. $h( __( 'مصادر البيانات', 'sportsarena-core' ) )
					. $p( __( 'نعتمد على مزوّدي بيانات رياضية مرخّصين، ونراجع المحتوى التحريري قبل نشره. إن لاحظت خطأً، يسعدنا تواصلك معنا عبر صفحة «اتصل بنا».', 'sportsarena-core' ) )
					. $h( __( 'التزامنا', 'sportsarena-core' ) )
					. $p( __( 'لا يقدّم {site} أي بث مباشر للمباريات ولا روابط بث، ويحترم حقوق الملكية الفكرية لجميع الجهات.', 'sportsarena-core' ) ),
			),
			'contact'    => array(
				'title'   => __( 'اتصل بنا', 'sportsarena-core' ),
				'slug'    => 'contact',
				'content' => $p( __( 'يسعدنا تواصلك مع فريق {site} للاستفسارات أو الملاحظات أو تصحيح المعلومات أو الإعلان. املأ النموذج التالي وسنرد عليك في أقرب وقت.', 'sportsarena-core' ) )
					. "<!-- wp:shortcode -->\n[sportsarena_contact_form]\n<!-- /wp:shortcode -->\n\n"
					. $p( __( 'يمكنك أيضاً مراسلتنا على البريد: {email}', 'sportsarena-core' ) ),
			),
			'privacy'    => array(
				'title'   => __( 'سياسة الخصوصية', 'sportsarena-core' ),
				'slug'    => 'privacy-policy',
				'content' => $p( __( 'آخر تحديث: {date}', 'sportsarena-core' ) )
					. $p( __( 'توضح هذه السياسة كيف يجمع موقع {site} ({domain}) المعلومات ويستخدمها ويحميها عند زيارتك له.', 'sportsarena-core' ) )
					. $h( __( 'المعلومات التي نجمعها', 'sportsarena-core' ) )
					. $ul(
						array(
							__( 'معلومات تقدّمها طوعاً، مثل الاسم والبريد الإلكتروني عند استخدام نموذج التواصل.', 'sportsarena-core' ),
							__( 'معلومات تقنية تُجمع تلقائياً، مثل نوع المتصفح والجهاز والصفحات التي تزورها، لأغراض الإحصاء وتحسين الموقع.', 'sportsarena-core' ),
						)
					)
					. $h( __( 'ملفات تعريف الارتباط (Cookies)', 'sportsarena-core' ) )
					. $p( __( 'يستخدم الموقع ملفات تعريف الارتباط لحفظ تفضيلاتك (مثل الوضع الداكن) ولقياس الزيارات ولعرض الإعلانات. يمكنك التحكم في ملفات تعريف الارتباط أو حذفها من إعدادات متصفحك، وسنطلب موافقتك قبل استخدام ملفات الإعلانات المخصّصة حيث يقتضي القانون ذلك (مثل المنطقة الاقتصادية الأوروبية والمملكة المتحدة وسويسرا).', 'sportsarena-core' ) )
					. $h( __( 'إعلانات Google AdSense', 'sportsarena-core' ) )
					. $p( __( 'نستخدم خدمة Google AdSense لعرض الإعلانات. تستخدم Google وشركاؤها ملفات تعريف الارتباط (ومنها ملف DoubleClick) لعرض إعلانات استناداً إلى زياراتك السابقة لهذا الموقع أو لمواقع أخرى على الإنترنت.', 'sportsarena-core' ) )
					. $ul(
						array(
							__( 'يتيح استخدام Google لملفات تعريف الارتباط الإعلانية لها ولشركائها عرض الإعلانات لك استناداً إلى زيارتك لمواقعنا و/أو مواقع أخرى.', 'sportsarena-core' ),
							__( 'يمكنك إيقاف الإعلانات المخصّصة من خلال <a href="https://adssettings.google.com" rel="nofollow noopener" target="_blank">إعدادات الإعلانات في Google</a>، أو زيارة <a href="https://www.aboutads.info" rel="nofollow noopener" target="_blank">aboutads.info</a> لإيقاف ملفات تعريف الارتباط لدى موردي الطرف الثالث.', 'sportsarena-core' ),
							__( 'لمعرفة المزيد عن كيفية استخدام Google للبيانات: <a href="https://policies.google.com/technologies/partner-sites" rel="nofollow noopener" target="_blank">كيف تستخدم Google المعلومات من المواقع التي تستخدم خدماتها</a>.', 'sportsarena-core' ),
						)
					)
					. $h( __( 'أطراف ثالثة', 'sportsarena-core' ) )
					. $p( __( 'قد يحتوي الموقع على خدمات لأطراف ثالثة مثل Google (الإعلانات والإحصاءات والخطوط) ومزوّدي البيانات الرياضية. لهذه الجهات سياسات خصوصية مستقلة، ولا نتحكم في ممارساتها. ننصحك بالاطلاع على سياساتها.', 'sportsarena-core' ) )
					. $h( __( 'كيف نستخدم المعلومات', 'sportsarena-core' ) )
					. $ul(
						array(
							__( 'تشغيل الموقع وتحسينه وتحليل استخدامه.', 'sportsarena-core' ),
							__( 'الرد على رسائلك واستفساراتك.', 'sportsarena-core' ),
							__( 'عرض الإعلانات وقياس أدائها وفق موافقتك.', 'sportsarena-core' ),
						)
					)
					. $h( __( 'حقوقك', 'sportsarena-core' ) )
					. $p( __( 'يحق لك طلب الاطلاع على بياناتك أو تصحيحها أو حذفها، وسحب موافقتك على ملفات تعريف الارتباط في أي وقت عبر رابط «إعدادات الخصوصية» أسفل الصفحة. للتواصل: {email}', 'sportsarena-core' ) )
					. $h( __( 'خصوصية الأطفال', 'sportsarena-core' ) )
					. $p( __( 'لا يستهدف الموقع الأطفال دون 13 عاماً، ولا نجمع بياناتهم عن قصد.', 'sportsarena-core' ) )
					. $h( __( 'التعديلات', 'sportsarena-core' ) )
					. $p( __( 'قد نحدّث هذه السياسة من وقت لآخر، وسيُنشر أي تعديل في هذه الصفحة مع تاريخ التحديث.', 'sportsarena-core' ) ),
			),
			'terms'      => array(
				'title'   => __( 'الشروط والأحكام', 'sportsarena-core' ),
				'slug'    => 'terms',
				'content' => $p( __( 'باستخدامك موقع {site} فإنك توافق على الشروط التالية:', 'sportsarena-core' ) )
					. $h( __( 'استخدام المحتوى', 'sportsarena-core' ) )
					. $p( __( 'المحتوى التحريري في الموقع (الأخبار والتحليلات) محمي بحقوق النشر، ولا يجوز نسخه أو إعادة نشره دون إذن كتابي مسبق مع ذكر المصدر.', 'sportsarena-core' ) )
					. $h( __( 'دقة المعلومات', 'sportsarena-core' ) )
					. $p( __( 'نبذل جهدنا لعرض النتائج والمواعيد بدقة، لكنها تُقدَّم «كما هي» وقد تتأخر أو تتغير. لا يتحمل الموقع مسؤولية أي قرار يُتخذ بناءً عليها.', 'sportsarena-core' ) )
					. $h( __( 'السلوك المقبول', 'sportsarena-core' ) )
					. $ul(
						array(
							__( 'عدم إساءة استخدام الموقع أو محاولة اختراقه أو تعطيله.', 'sportsarena-core' ),
							__( 'عدم استخدام أدوات آلية لسحب البيانات بشكل مكثف.', 'sportsarena-core' ),
							__( 'الالتزام بالقوانين السارية عند استخدام الموقع.', 'sportsarena-core' ),
						)
					)
					. $h( __( 'الروابط الخارجية', 'sportsarena-core' ) )
					. $p( __( 'قد يحتوي الموقع على روابط لمواقع أخرى لا نتحكم في محتواها ولا نتحمل مسؤوليته.', 'sportsarena-core' ) )
					. $h( __( 'التعديل', 'sportsarena-core' ) )
					. $p( __( 'يحق لإدارة الموقع تعديل هذه الشروط في أي وقت، ويُعد استمرار استخدامك للموقع قبولاً بها.', 'sportsarena-core' ) ),
			),
			'disclaimer' => array(
				'title'   => __( 'إخلاء المسؤولية', 'sportsarena-core' ),
				'slug'    => 'disclaimer',
				'content' => $p( __( 'المعلومات المنشورة على {site} لأغراض إعلامية عامة فقط.', 'sportsarena-core' ) )
					. $ul(
						array(
							__( 'لا يقدّم الموقع أي بث مباشر للمباريات ولا يستضيف أو يوفّر روابط بث، ويعرض النتائج والإحصائيات نصياً فقط.', 'sportsarena-core' ),
							__( 'أسماء الأندية والبطولات والعلامات التجارية مملوكة لأصحابها، واستخدامها للتعريف فقط.', 'sportsarena-core' ),
							__( 'البيانات الرياضية مصدرها مزوّدون خارجيون وقد تحتوي على أخطاء أو تأخير.', 'sportsarena-core' ),
							__( 'الموقع غير مرتبط رسمياً بأي اتحاد أو نادٍ أو بطولة.', 'sportsarena-core' ),
							__( 'لا يروّج الموقع للمراهنات أو القمار بأي شكل.', 'sportsarena-core' ),
						)
					)
					. $p( __( 'للإبلاغ عن خطأ أو انتهاك لحقوق الملكية، راسلنا عبر صفحة «اتصل بنا».', 'sportsarena-core' ) ),
			),
		);
	}
}
