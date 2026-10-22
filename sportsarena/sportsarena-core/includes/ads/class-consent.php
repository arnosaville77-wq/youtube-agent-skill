<?php
/**
 * Google Consent Mode v2 defaults + a simple fallback consent banner.
 *
 * Recommended setup: a Google-certified CMP (for example AdSense
 * "Privacy & messaging", which is free) for visitors from the EEA, the UK
 * and Switzerland. Select the "cmp" mode then: this class only prints the
 * Consent Mode defaults and leaves the banner to the CMP.
 *
 * @package SportsArena\Core
 */

namespace SportsArena\Core\Ads;

use SportsArena\Core\Options;
use SportsArena\Core\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Consent.
 */
final class Consent {

	const COOKIE = 'sa_consent';

	/**
	 * EEA + UK + CH ISO 3166-1 alpha-2 codes (Consent Mode "region").
	 */
	const REGIONS = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH' );

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_head', array( $this, 'defaults' ), 0 );
		add_action( 'wp_footer', array( $this, 'banner' ), 50 );
	}

	/**
	 * Print Consent Mode v2 defaults before any Google tag.
	 *
	 * @return void
	 */
	public function defaults() {
		$settings = Options::consent();
		if ( 'off' === $settings['mode'] || empty( $settings['consent_mode'] ) ) {
			return;
		}
		$denied  = array(
			'ad_storage'         => 'denied',
			'ad_user_data'       => 'denied',
			'ad_personalization' => 'denied',
			'analytics_storage'  => 'denied',
			'wait_for_update'    => 500,
		);
		$granted = array(
			'ad_storage'         => 'granted',
			'ad_user_data'       => 'granted',
			'ad_personalization' => 'granted',
			'analytics_storage'  => 'granted',
		);

		$js = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}';
		if ( ! empty( $settings['eea_only'] ) ) {
			// Denied by default in the EEA/UK/CH, granted elsewhere.
			$js .= 'gtag("consent","default",' . wp_json_encode( array_merge( $denied, array( 'region' => self::REGIONS ) ) ) . ');';
			$js .= 'gtag("consent","default",' . wp_json_encode( $granted ) . ');';
		} else {
			$js .= 'gtag("consent","default",' . wp_json_encode( $denied ) . ');';
		}
		$js .= 'gtag("set","ads_data_redaction",true);';
		// Re-apply a previous choice from the fallback banner.
		if ( 'builtin' === $settings['mode'] ) {
			$js .= '(function(){var m=document.cookie.match(/(?:^|; )' . self::COOKIE . '=(granted|denied)/);if(m){var v=m[1];gtag("consent","update",{ad_storage:v,ad_user_data:v,ad_personalization:v,analytics_storage:v});}})();';
		}
		wp_print_inline_script_tag( $js, array( 'id' => 'sa-consent-defaults' ) );
	}

	/**
	 * Fallback banner (mode "builtin").
	 *
	 * With "eea_only" the banner is shown to visitors whose browser timezone
	 * is European (a client-side heuristic, no IP lookup). A certified CMP is
	 * still the recommended solution for EEA/UK traffic.
	 *
	 * @return void
	 */
	public function banner() {
		$settings = Options::consent();
		if ( 'builtin' !== $settings['mode'] ) {
			return;
		}
		$pages   = Pages::ids();
		$privacy = ! empty( $pages['privacy'] ) ? get_permalink( $pages['privacy'] ) : get_privacy_policy_url();
		?>
		<div id="sa-consent" class="sa-consent" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'الموافقة على ملفات تعريف الارتباط', 'sportsarena-core' ); ?>" hidden data-eea-only="<?php echo esc_attr( ! empty( $settings['eea_only'] ) ? '1' : '0' ); ?>">
			<p>
				<?php esc_html_e( 'نستخدم ملفات تعريف الارتباط لتحسين تجربتك ولعرض إعلانات مخصّصة وقياس أدائها عبر Google. يمكنك القبول أو الرفض في أي وقت.', 'sportsarena-core' ); ?>
				<?php if ( $privacy ) : ?>
					<a href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'سياسة الخصوصية', 'sportsarena-core' ); ?></a>
				<?php endif; ?>
			</p>
			<div class="sa-consent__actions">
				<button type="button" class="sa-button" data-sa-consent="granted"><?php esc_html_e( 'قبول', 'sportsarena-core' ); ?></button>
				<button type="button" class="sa-button sa-button--ghost" data-sa-consent="denied"><?php esc_html_e( 'رفض', 'sportsarena-core' ); ?></button>
			</div>
		</div>
		<?php
		$js = <<<'JS'
(function(){
	var box=document.getElementById('sa-consent');if(!box){return;}
	var COOKIE='sa_consent';
	function has(){return /(?:^|; )sa_consent=(granted|denied)/.test(document.cookie);}
	function europe(){try{var tz=Intl.DateTimeFormat().resolvedOptions().timeZone||'';return /^Europe\//.test(tz)||/^Atlantic\/(Reykjavik|Canary|Madeira|Azores|Faroe)/.test(tz);}catch(e){return true;}}
	function show(){box.hidden=false;}
	function set(v){
		document.cookie=COOKIE+'='+v+';path=/;max-age='+(180*24*3600)+';SameSite=Lax'+(location.protocol==='https:'?';Secure':'');
		if(typeof gtag==='function'){gtag('consent','update',{ad_storage:v,ad_user_data:v,ad_personalization:v,analytics_storage:v});}
		box.hidden=true;
	}
	box.addEventListener('click',function(e){var b=e.target.closest('[data-sa-consent]');if(b){set(b.getAttribute('data-sa-consent'));}});
	document.addEventListener('click',function(e){if(e.target.closest('.sa-consent-open')){e.preventDefault();show();}});
	if(!has()&&(box.getAttribute('data-eea-only')!=='1'||europe())){show();}
})();
JS;
		wp_print_inline_script_tag( $js, array( 'id' => 'sa-consent-banner' ) );
	}
}
