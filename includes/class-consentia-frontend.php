<?php
/**
 * Frontend: banner, modal de preferencias y carga de assets.
 *
 * @package Consentia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Consentia_Frontend
 */
class Consentia_Frontend {

	/**
	 * Instancia única.
	 *
	 * @var Consentia_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Devuelve la instancia única.
	 *
	 * @return Consentia_Frontend
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 20 );
		add_action( 'wp_head', array( $this, 'inline_colors' ), 5 );
	}

	/**
	 * Variables de color en línea (para no depender del cache del CSS).
	 */
	public function inline_colors() {
		$primary = consentia_get( 'color_primary', '#002c57' );
		$bg      = consentia_get( 'color_bg', '#ffffff' );
		$text    = consentia_get( 'color_text', '#181c1e' );
		printf(
			'<style id="consentia-vars">:root{--consentia-primary:%s;--consentia-bg:%s;--consentia-text:%s;}</style>' . "\n",
			esc_html( $this->sanitize_color( $primary, '#002c57' ) ),
			esc_html( $this->sanitize_color( $bg, '#ffffff' ) ),
			esc_html( $this->sanitize_color( $text, '#181c1e' ) )
		);
	}

	/**
	 * Encola CSS y JS. El JS depende de la WP Consent API si está presente.
	 */
	public function enqueue() {
		wp_enqueue_style( 'consentia', CONSENTIA_URL . 'assets/css/consentia.css', array(), CONSENTIA_VERSION );

		$deps = array();
		if ( wp_script_is( 'wp-consent-api', 'registered' ) ) {
			$deps[] = 'wp-consent-api';
		}

		wp_enqueue_script( 'consentia', CONSENTIA_URL . 'assets/js/consentia.js', $deps, CONSENTIA_VERSION, true );

		$categories = array();
		foreach ( consentia_active_categories() as $slug => $data ) {
			$categories[ $slug ] = array(
				'label'    => $data['label'],
				'required' => ! empty( $data['required'] ),
			);
		}

		wp_localize_script(
			'consentia',
			'ConsentiaData',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'consentia_log' ),
				'consentType'      => consentia_get( 'consent_type', 'optin' ),
				'version'          => (int) consentia_get( 'reconsent_version', CONSENTIA_CONSENT_VERSION ),
				'categories'       => $categories,
				'gcmMap'           => consentia_gcm_map(),
				'emitConsentMode'  => (bool) Consentia_Consent_Mode::enabled(),
				'hasConsentApi'    => consentia_wp_consent_api_active(),
				'logEnabled'       => (bool) consentia_get( 'log_enabled', 1 ),
				'cookieName'       => 'consentia_status',
				'cookieDays'       => 180,
			)
		);
	}

	/**
	 * Renderiza el banner y el modal en el pie de página.
	 */
	public function render() {
		// No lo mostramos en el editor de bloques ni en peticiones AMP básicas.
		if ( is_admin() ) {
			return;
		}

		$s          = consentia_get_settings();
		$categories = consentia_active_categories();
		$privacy    = $s['privacy_url'] ? $s['privacy_url'] : get_privacy_policy_url();
		?>
		<div id="consentia" class="consentia consentia--<?php echo esc_attr( $s['position'] ); ?>" hidden>

			<!-- Banner -->
			<div class="consentia__banner" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'Aviso de cookies', 'consentia' ); ?>" data-consentia-banner>
				<div class="consentia__banner-body">
					<?php if ( $s['title'] ) : ?>
						<h2 class="consentia__title"><?php echo esc_html( $s['title'] ); ?></h2>
					<?php endif; ?>
					<p class="consentia__text">
						<?php echo esc_html( $s['message'] ); ?>
						<?php if ( $privacy ) : ?>
							<a href="<?php echo esc_url( $privacy ); ?>" class="consentia__link" target="_blank" rel="noopener"><?php echo esc_html( $s['privacy_text'] ); ?></a>
						<?php endif; ?>
					</p>
				</div>
				<div class="consentia__banner-actions">
					<button type="button" class="consentia__btn consentia__btn--ghost" data-consentia-action="prefs"><?php echo esc_html( $s['btn_prefs'] ); ?></button>
					<button type="button" class="consentia__btn consentia__btn--ghost" data-consentia-action="reject"><?php echo esc_html( $s['btn_reject'] ); ?></button>
					<button type="button" class="consentia__btn consentia__btn--primary" data-consentia-action="accept"><?php echo esc_html( $s['btn_accept'] ); ?></button>
				</div>
			</div>

			<!-- Modal de preferencias -->
			<div class="consentia__modal" data-consentia-modal hidden>
				<div class="consentia__modal-backdrop" data-consentia-action="close-prefs"></div>
				<div class="consentia__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="consentia-prefs-title">
					<div class="consentia__modal-head">
						<h2 id="consentia-prefs-title" class="consentia__title"><?php echo esc_html( $s['btn_prefs'] ); ?></h2>
						<button type="button" class="consentia__close" data-consentia-action="close-prefs" aria-label="<?php esc_attr_e( 'Cerrar', 'consentia' ); ?>">&times;</button>
					</div>
					<div class="consentia__modal-body">
						<?php foreach ( $categories as $slug => $data ) : ?>
							<div class="consentia__cat">
								<label class="consentia__cat-head">
									<span class="consentia__cat-title"><?php echo esc_html( $data['label'] ); ?></span>
									<?php if ( ! empty( $data['required'] ) ) : ?>
										<span class="consentia__badge"><?php esc_html_e( 'Siempre activas', 'consentia' ); ?></span>
										<input type="checkbox" checked disabled data-consentia-cat="<?php echo esc_attr( $slug ); ?>" />
									<?php else : ?>
										<span class="consentia__switch">
											<input type="checkbox" data-consentia-cat="<?php echo esc_attr( $slug ); ?>" />
											<span class="consentia__slider" aria-hidden="true"></span>
										</span>
									<?php endif; ?>
								</label>
								<p class="consentia__cat-desc"><?php echo esc_html( $data['desc'] ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="consentia__modal-actions">
						<button type="button" class="consentia__btn consentia__btn--ghost" data-consentia-action="reject"><?php echo esc_html( $s['btn_reject'] ); ?></button>
						<button type="button" class="consentia__btn consentia__btn--ghost" data-consentia-action="save"><?php echo esc_html( $s['btn_save'] ); ?></button>
						<button type="button" class="consentia__btn consentia__btn--primary" data-consentia-action="accept"><?php echo esc_html( $s['btn_accept'] ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<!-- Botón flotante para reabrir preferencias -->
		<button type="button" class="consentia__reopen" data-consentia-action="prefs" aria-label="<?php esc_attr_e( 'Preferencias de cookies', 'consentia' ); ?>" hidden>
			<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-4-4 4 4 0 0 1-4-4 4 4 0 0 1-2-2zm-3 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-1 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm7 1a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3z"/></svg>
		</button>
		<?php
	}

	/**
	 * Sanea un color hex.
	 *
	 * @param string $color   Valor.
	 * @param string $default Por defecto.
	 * @return string
	 */
	private function sanitize_color( $color, $default ) {
		$color = sanitize_hex_color( $color );
		return $color ? $color : $default;
	}
}
