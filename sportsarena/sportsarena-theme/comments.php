<?php
/**
 * Comments.
 *
 * @package SportsArena\Theme
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="sa-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="sa-section__title">
			<?php
			/* translators: %s: number of comments. */
			printf( esc_html( _n( '%s تعليق', '%s تعليقات', get_comments_number(), 'sportsarena-theme' ) ), esc_html( number_format_i18n( get_comments_number() ) ) );
			?>
		</h2>
		<ol class="sa-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 40,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php
	// Form labels come from the theme translations, so the form always
	// matches the site language even before the WordPress language pack.
	$sportsarena_commenter = wp_get_current_commenter();
	$sportsarena_required  = ' <span class="required" aria-hidden="true">*</span>';
	$sportsarena_fields    = array(
		'author' => '<p class="comment-form-author"><label for="author">' . esc_html__( 'الاسم', 'sportsarena-theme' ) . $sportsarena_required . '</label><input id="author" name="author" type="text" value="' . esc_attr( $sportsarena_commenter['comment_author'] ) . '" size="30" maxlength="245" autocomplete="name" required></p>',
		'email'  => '<p class="comment-form-email"><label for="email">' . esc_html__( 'البريد الإلكتروني', 'sportsarena-theme' ) . $sportsarena_required . '</label><input id="email" name="email" type="email" value="' . esc_attr( $sportsarena_commenter['comment_author_email'] ) . '" size="30" maxlength="100" autocomplete="email" dir="ltr" required></p>',
		'url'    => '<p class="comment-form-url"><label for="url">' . esc_html__( 'الموقع الإلكتروني', 'sportsarena-theme' ) . '</label><input id="url" name="url" type="url" value="' . esc_attr( $sportsarena_commenter['comment_author_url'] ) . '" size="30" maxlength="200" autocomplete="url" dir="ltr"></p>',
	);
	if ( get_option( 'show_comments_cookies_opt_in' ) ) {
		$sportsarena_fields['cookies'] = '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . ( empty( $sportsarena_commenter['comment_author_email'] ) ? '' : ' checked' ) . '> <label for="wp-comment-cookies-consent">' . esc_html__( 'احفظ اسمي وبريدي وموقعي في هذا المتصفح لاستخدامها في المرة القادمة.', 'sportsarena-theme' ) . '</label></p>';
	}
	comment_form(
		array(
			'fields'               => $sportsarena_fields,
			'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'التعليق', 'sportsarena-theme' ) . $sportsarena_required . '</label><textarea id="comment" name="comment" cols="45" rows="6" maxlength="65525" required></textarea></p>',
			'comment_notes_before' => '<p class="comment-notes">' . esc_html__( 'لن يُنشر بريدك الإلكتروني. الحقول المطلوبة عليها علامة *', 'sportsarena-theme' ) . '</p>',
			'title_reply'          => __( 'أضف تعليقاً', 'sportsarena-theme' ),
			/* translators: %s: comment author. */
			'title_reply_to'       => __( 'الرد على %s', 'sportsarena-theme' ),
			'cancel_reply_link'    => __( 'إلغاء الرد', 'sportsarena-theme' ),
			'label_submit'         => __( 'نشر التعليق', 'sportsarena-theme' ),
		)
	);
	?>
</section>
