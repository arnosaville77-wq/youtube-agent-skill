<?php
/**
 * Lightweight contact form: [sportsarena_contact_form].
 *
 * Protected by a nonce, a honeypot field, a minimum fill time and a per-IP
 * rate limit. Messages are sent with wp_mail() to the admin email.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Contact form.
 */
final class Contact_Form {

	const ACTION = 'sportsarena_contact';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'sportsarena_contact_form', array( $this, 'render' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Render the form.
	 *
	 * @return string
	 */
	public function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag after redirect.
		$status = isset( $_GET['sa_contact'] ) ? sanitize_key( wp_unslash( $_GET['sa_contact'] ) ) : '';
		$notice = '';
		if ( 'sent' === $status ) {
			$notice = '<p class="sa-notice sa-notice--success" role="status">' . esc_html__( 'شكراً لتواصلك، تم إرسال رسالتك بنجاح.', 'sportsarena-core' ) . '</p>';
		} elseif ( 'error' === $status ) {
			$notice = '<p class="sa-notice sa-notice--error" role="alert">' . esc_html__( 'تعذّر إرسال الرسالة. تأكد من الحقول وحاول مرة أخرى.', 'sportsarena-core' ) . '</p>';
		} elseif ( 'limit' === $status ) {
			$notice = '<p class="sa-notice sa-notice--error" role="alert">' . esc_html__( 'أرسلت عدة رسائل خلال وقت قصير، حاول لاحقاً.', 'sportsarena-core' ) . '</p>';
		}

		ob_start();
		echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		?>
		<form class="sa-contact" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="sa_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<input type="hidden" name="sa_back" value="<?php echo esc_url( get_permalink() ); ?>">
			<?php wp_nonce_field( self::ACTION, 'sa_contact_nonce' ); ?>
			<p class="sa-hp" aria-hidden="true">
				<label><?php esc_html_e( 'اترك هذا الحقل فارغاً', 'sportsarena-core' ); ?> <input type="text" name="sa_website" tabindex="-1" autocomplete="off"></label>
			</p>
			<p>
				<label for="sa-name"><?php esc_html_e( 'الاسم', 'sportsarena-core' ); ?></label>
				<input id="sa-name" type="text" name="sa_name" required maxlength="100" autocomplete="name">
			</p>
			<p>
				<label for="sa-email"><?php esc_html_e( 'البريد الإلكتروني', 'sportsarena-core' ); ?></label>
				<input id="sa-email" type="email" name="sa_email" required maxlength="150" autocomplete="email" dir="ltr">
			</p>
			<p>
				<label for="sa-subject"><?php esc_html_e( 'الموضوع', 'sportsarena-core' ); ?></label>
				<input id="sa-subject" type="text" name="sa_subject" maxlength="150">
			</p>
			<p>
				<label for="sa-message"><?php esc_html_e( 'الرسالة', 'sportsarena-core' ); ?></label>
				<textarea id="sa-message" name="sa_message" rows="6" required maxlength="5000"></textarea>
			</p>
			<p><button type="submit" class="sa-button"><?php esc_html_e( 'إرسال', 'sportsarena-core' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle a submission.
	 *
	 * @return void
	 */
	public function handle() {
		$back = isset( $_POST['sa_back'] ) ? esc_url_raw( wp_unslash( $_POST['sa_back'] ) ) : home_url( '/' );
		$back = wp_validate_redirect( $back, home_url( '/' ) );

		if ( ! isset( $_POST['sa_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sa_contact_nonce'] ) ), self::ACTION ) ) {
			$this->redirect( $back, 'error' );
		}

		// Honeypot and minimum fill time (3 seconds) against bots.
		$elapsed = time() - ( isset( $_POST['sa_ts'] ) ? absint( $_POST['sa_ts'] ) : 0 );
		if ( ! empty( $_POST['sa_website'] ) || $elapsed < 3 ) {
			$this->redirect( $back, 'sent' );
		}

		$ip_hash = md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$key     = 'sa_contact_' . $ip_hash;
		$count   = (int) get_transient( $key );
		if ( $count >= 3 ) {
			$this->redirect( $back, 'limit' );
		}

		$name    = isset( $_POST['sa_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_name'] ) ) : '';
		$email   = isset( $_POST['sa_email'] ) ? sanitize_email( wp_unslash( $_POST['sa_email'] ) ) : '';
		$subject = isset( $_POST['sa_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_subject'] ) ) : '';
		$message = isset( $_POST['sa_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sa_message'] ) ) : '';

		if ( '' === $name || ! is_email( $email ) || '' === $message ) {
			$this->redirect( $back, 'error' );
		}

		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );

		$sent = wp_mail(
			get_option( 'admin_email' ),
			/* translators: 1: site name, 2: subject. */
			sprintf( __( '[%1$s] رسالة تواصل: %2$s', 'sportsarena-core' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $subject ? $subject : __( 'بدون موضوع', 'sportsarena-core' ) ),
			/* translators: 1: name, 2: email, 3: message. */
			sprintf( __( "الاسم: %1\$s\nالبريد: %2\$s\n\n%3\$s", 'sportsarena-core' ), $name, $email, $message ),
			array( 'Reply-To: ' . $name . ' <' . $email . '>' )
		);

		$this->redirect( $back, $sent ? 'sent' : 'error' );
	}

	/**
	 * Redirect back with a status flag.
	 *
	 * @param string $url    Back URL.
	 * @param string $status Status.
	 * @return never
	 */
	private function redirect( $url, $status ) {
		wp_safe_redirect( add_query_arg( 'sa_contact', $status, $url ) );
		exit;
	}
}
