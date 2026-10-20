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
		$ids = self::ids();
		$key = array_search( (int) $post_id, $ids, true );
		if ( false !== $key ) {
			unset( $ids[ $key ] );
			update_option( Options::PAGES, $ids );
		}
	}

	/**
	 * Stored page IDs.
	 *
	 * @return array<string,int>
	 */
	public static function ids() {
		return array_map( 'intval', (array) get_option( Options::PAGES, array() ) );
	}

	/**
	 * Create any missing required page. Existing pages are never overwritten.
	 *
	 * @return array<string,int>
	 */
	public static function create_required_pages() {
		$ids = self::ids();
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
			}
		}
		update_option( Options::PAGES, $ids );
		if ( ! empty( $ids['privacy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', $ids['privacy'] );
		}
		return $ids;
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
	 * Page definitions with editable Arabic template content (block markup).
	 *
	 * @return array<string,array{title:string,slug:string,content:string}>
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
			'about'      => array(
				'title'   => 'من نحن',
				'slug'    => 'about',
				'content' => $p( '<strong>{site}</strong> موقع رياضي عربي يقدّم نتائج المباريات ومواعيدها وجداول الترتيب والإحصائيات والأخبار الرياضية لكرة القدم وباقي الرياضات، بصورة سريعة ودقيقة.' )
					. $h( 'ماذا نقدّم؟' )
					. $ul(
						array(
							'نتائج المباريات وتحديثها أثناء اللعب (نصياً فقط، دون أي بث).',
							'مواعيد المباريات بتوقيتك المحلي والقنوات الناقلة كمعلومة نصية.',
							'جداول الترتيب وقوائم الهدافين للدوريات والبطولات.',
							'الأخبار والتحليلات والمعاينات التي يكتبها فريق التحرير.',
						)
					)
					. $h( 'مصادر البيانات' )
					. $p( 'نعتمد على مزوّدي بيانات رياضية مرخّصين، ونراجع المحتوى التحريري قبل نشره. إن لاحظت خطأً، يسعدنا تواصلك معنا عبر صفحة «اتصل بنا».' )
					. $h( 'التزامنا' )
					. $p( 'لا يقدّم {site} أي بث مباشر للمباريات ولا روابط بث، ويحترم حقوق الملكية الفكرية لجميع الجهات.' ),
			),
			'contact'    => array(
				'title'   => 'اتصل بنا',
				'slug'    => 'contact',
				'content' => $p( 'يسعدنا تواصلك مع فريق {site} للاستفسارات أو الملاحظات أو تصحيح المعلومات أو الإعلان. املأ النموذج التالي وسنرد عليك في أقرب وقت.' )
					. "<!-- wp:shortcode -->\n[sportsarena_contact_form]\n<!-- /wp:shortcode -->\n\n"
					. $p( 'يمكنك أيضاً مراسلتنا على البريد: {email}' ),
			),
			'privacy'    => array(
				'title'   => 'سياسة الخصوصية',
				'slug'    => 'privacy-policy',
				'content' => $p( 'آخر تحديث: {date}' )
					. $p( 'توضح هذه السياسة كيف يجمع موقع {site} ({domain}) المعلومات ويستخدمها ويحميها عند زيارتك له.' )
					. $h( 'المعلومات التي نجمعها' )
					. $ul(
						array(
							'معلومات تقدّمها طوعاً، مثل الاسم والبريد الإلكتروني عند استخدام نموذج التواصل.',
							'معلومات تقنية تُجمع تلقائياً، مثل نوع المتصفح والجهاز والصفحات التي تزورها، لأغراض الإحصاء وتحسين الموقع.',
						)
					)
					. $h( 'ملفات تعريف الارتباط (Cookies)' )
					. $p( 'يستخدم الموقع ملفات تعريف الارتباط لحفظ تفضيلاتك (مثل الوضع الداكن) ولقياس الزيارات ولعرض الإعلانات. يمكنك التحكم في ملفات تعريف الارتباط أو حذفها من إعدادات متصفحك، وسنطلب موافقتك قبل استخدام ملفات الإعلانات المخصّصة حيث يقتضي القانون ذلك (مثل المنطقة الاقتصادية الأوروبية والمملكة المتحدة وسويسرا).' )
					. $h( 'إعلانات Google AdSense' )
					. $p( 'نستخدم خدمة Google AdSense لعرض الإعلانات. تستخدم Google وشركاؤها ملفات تعريف الارتباط (ومنها ملف DoubleClick) لعرض إعلانات استناداً إلى زياراتك السابقة لهذا الموقع أو لمواقع أخرى على الإنترنت.' )
					. $ul(
						array(
							'يتيح استخدام Google لملفات تعريف الارتباط الإعلانية لها ولشركائها عرض الإعلانات لك استناداً إلى زيارتك لمواقعنا و/أو مواقع أخرى.',
							'يمكنك إيقاف الإعلانات المخصّصة من خلال <a href="https://adssettings.google.com" rel="nofollow noopener" target="_blank">إعدادات الإعلانات في Google</a>، أو زيارة <a href="https://www.aboutads.info" rel="nofollow noopener" target="_blank">aboutads.info</a> لإيقاف ملفات تعريف الارتباط لدى موردي الطرف الثالث.',
							'لمعرفة المزيد عن كيفية استخدام Google للبيانات: <a href="https://policies.google.com/technologies/partner-sites" rel="nofollow noopener" target="_blank">كيف تستخدم Google المعلومات من المواقع التي تستخدم خدماتها</a>.',
						)
					)
					. $h( 'أطراف ثالثة' )
					. $p( 'قد يحتوي الموقع على خدمات لأطراف ثالثة مثل Google (الإعلانات والإحصاءات والخطوط) ومزوّدي البيانات الرياضية. لهذه الجهات سياسات خصوصية مستقلة، ولا نتحكم في ممارساتها. ننصحك بالاطلاع على سياساتها.' )
					. $h( 'كيف نستخدم المعلومات' )
					. $ul(
						array(
							'تشغيل الموقع وتحسينه وتحليل استخدامه.',
							'الرد على رسائلك واستفساراتك.',
							'عرض الإعلانات وقياس أدائها وفق موافقتك.',
						)
					)
					. $h( 'حقوقك' )
					. $p( 'يحق لك طلب الاطلاع على بياناتك أو تصحيحها أو حذفها، وسحب موافقتك على ملفات تعريف الارتباط في أي وقت عبر رابط «إعدادات الخصوصية» أسفل الصفحة. للتواصل: {email}' )
					. $h( 'خصوصية الأطفال' )
					. $p( 'لا يستهدف الموقع الأطفال دون 13 عاماً، ولا نجمع بياناتهم عن قصد.' )
					. $h( 'التعديلات' )
					. $p( 'قد نحدّث هذه السياسة من وقت لآخر، وسيُنشر أي تعديل في هذه الصفحة مع تاريخ التحديث.' ),
			),
			'terms'      => array(
				'title'   => 'الشروط والأحكام',
				'slug'    => 'terms',
				'content' => $p( 'باستخدامك موقع {site} فإنك توافق على الشروط التالية:' )
					. $h( 'استخدام المحتوى' )
					. $p( 'المحتوى التحريري في الموقع (الأخبار والتحليلات) محمي بحقوق النشر، ولا يجوز نسخه أو إعادة نشره دون إذن كتابي مسبق مع ذكر المصدر.' )
					. $h( 'دقة المعلومات' )
					. $p( 'نبذل جهدنا لعرض النتائج والمواعيد بدقة، لكنها تُقدَّم «كما هي» وقد تتأخر أو تتغير. لا يتحمل الموقع مسؤولية أي قرار يُتخذ بناءً عليها.' )
					. $h( 'السلوك المقبول' )
					. $ul(
						array(
							'عدم إساءة استخدام الموقع أو محاولة اختراقه أو تعطيله.',
							'عدم استخدام أدوات آلية لسحب البيانات بشكل مكثف.',
							'الالتزام بالقوانين السارية عند استخدام الموقع.',
						)
					)
					. $h( 'الروابط الخارجية' )
					. $p( 'قد يحتوي الموقع على روابط لمواقع أخرى لا نتحكم في محتواها ولا نتحمل مسؤوليته.' )
					. $h( 'التعديل' )
					. $p( 'يحق لإدارة الموقع تعديل هذه الشروط في أي وقت، ويُعد استمرار استخدامك للموقع قبولاً بها.' ),
			),
			'disclaimer' => array(
				'title'   => 'إخلاء المسؤولية',
				'slug'    => 'disclaimer',
				'content' => $p( 'المعلومات المنشورة على {site} لأغراض إعلامية عامة فقط.' )
					. $ul(
						array(
							'لا يقدّم الموقع أي بث مباشر للمباريات ولا يستضيف أو يوفّر روابط بث، ويعرض النتائج والإحصائيات نصياً فقط.',
							'أسماء الأندية والبطولات والعلامات التجارية مملوكة لأصحابها، واستخدامها للتعريف فقط.',
							'البيانات الرياضية مصدرها مزوّدون خارجيون وقد تحتوي على أخطاء أو تأخير.',
							'الموقع غير مرتبط رسمياً بأي اتحاد أو نادٍ أو بطولة.',
							'لا يروّج الموقع للمراهنات أو القمار بأي شكل.',
						)
					)
					. $p( 'للإبلاغ عن خطأ أو انتهاك لحقوق الملكية، راسلنا عبر صفحة «اتصل بنا».' ),
			),
		);
	}
}
