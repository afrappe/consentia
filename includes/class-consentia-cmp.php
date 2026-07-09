<?php
/**
 * Integración con la WP Consent API.
 *
 * Registra este plugin como gestor de consentimiento (CMP) y declara el tipo
 * de consentimiento (optin/optout). Con esto, Google Site Kit lo reconoce y
 * mapea el consentimiento a las señales de Consent Mode v2.
 *
 * @package Consentia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Consentia_CMP
 */
class Consentia_CMP {

	/**
	 * Instancia única.
	 *
	 * @var Consentia_CMP|null
	 */
	private static $instance = null;

	/**
	 * Devuelve la instancia única.
	 *
	 * @return Consentia_CMP
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
		// Declara este plugin como CMP compatible con la WP Consent API.
		add_filter( 'wp_consent_api_registered_' . CONSENTIA_BASENAME, '__return_true' );

		// Declara el tipo de consentimiento (optin por defecto).
		add_filter( 'wp_get_consent_type', array( $this, 'consent_type' ) );

		// Aviso si falta la WP Consent API (necesaria para Site Kit).
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
	}

	/**
	 * Devuelve el tipo de consentimiento configurado.
	 *
	 * @param string $type Valor entrante (de otros filtros).
	 * @return string 'optin' | 'optout'
	 */
	public function consent_type( $type ) {
		$configured = consentia_get( 'consent_type', 'optin' );
		return in_array( $configured, array( 'optin', 'optout' ), true ) ? $configured : 'optin';
	}

	/**
	 * Muestra un aviso en el admin si la WP Consent API no está activa.
	 */
	public function dependency_notice() {
		if ( consentia_wp_consent_api_active() ) {
			return;
		}
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		$install = wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=wp-consent-api' ),
			'install-plugin_wp-consent-api'
		);
		$search = self_admin_url( 'plugin-install.php?s=wp-consent-api&tab=search&type=term' );
		?>
		<div class="notice notice-warning">
			<p>
				<strong>Consentia:</strong>
				<?php esc_html_e( 'para que Google Site Kit reconozca el consentimiento necesitas también el plugin gratuito «WP Consent API».', 'consentia' ); ?>
				<a href="<?php echo esc_url( $install ); ?>"><?php esc_html_e( 'Instalar ahora', 'consentia' ); ?></a>
				<?php esc_html_e( 'o', 'consentia' ); ?>
				<a href="<?php echo esc_url( $search ); ?>"><?php esc_html_e( 'buscarlo', 'consentia' ); ?></a>.
				<?php esc_html_e( 'El banner funciona sin él, pero Site Kit lo requiere.', 'consentia' ); ?>
			</p>
		</div>
		<?php
	}
}
