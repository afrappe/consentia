<?php
/**
 * Google Consent Mode v2 (modo directo, opcional).
 *
 * Emite el snippet de `gtag('consent','default', ...)` con TODO denegado antes
 * de cargar cualquier etiqueta de Google. Úsalo SOLO si NO estás usando el
 * Consent Mode de Site Kit (que ya hace esto), para no duplicar señales.
 *
 * @package Consentia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Consentia_Consent_Mode
 */
class Consentia_Consent_Mode {

	/**
	 * Instancia única.
	 *
	 * @var Consentia_Consent_Mode|null
	 */
	private static $instance = null;

	/**
	 * Devuelve la instancia única.
	 *
	 * @return Consentia_Consent_Mode
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Prioridad 1: antes que cualquier etiqueta de Google.
		add_action( 'wp_head', array( $this, 'print_default' ), 1 );
	}

	/**
	 * ¿Está activado el modo directo?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) consentia_get( 'emit_consent_mode', 0 );
	}

	/**
	 * Imprime el gtag consent default (denegado) y la función gtag stub.
	 */
	public function print_default() {
		if ( ! self::enabled() ) {
			return;
		}

		$wait            = (int) consentia_get( 'wait_for_update', 500 );
		$url_passthrough = (bool) consentia_get( 'url_passthrough', 1 );
		$ads_redaction   = (bool) consentia_get( 'ads_data_redaction', 1 );

		$default = array(
			'ad_storage'               => 'denied',
			'ad_user_data'             => 'denied',
			'ad_personalization'       => 'denied',
			'analytics_storage'        => 'denied',
			'functionality_storage'    => 'granted',
			'security_storage'         => 'granted',
			'personalization_storage'  => 'denied',
			'wait_for_update'          => $wait,
		);
		?>
<!-- Consentia: Google Consent Mode v2 (default) -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', <?php echo wp_json_encode( $default ); ?>);
<?php if ( $ads_redaction ) : ?>
gtag('set', 'ads_data_redaction', true);
<?php endif; ?>
<?php if ( $url_passthrough ) : ?>
gtag('set', 'url_passthrough', true);
<?php endif; ?>
</script>
<!-- /Consentia -->
		<?php
	}
}
