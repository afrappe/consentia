<?php
/**
 * Página de ajustes del plugin.
 *
 * @package Consentia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Consentia_Settings
 */
class Consentia_Settings {

	/**
	 * Instancia única.
	 *
	 * @var Consentia_Settings|null
	 */
	private static $instance = null;

	/**
	 * Devuelve la instancia única.
	 *
	 * @return Consentia_Settings
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
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Encola el CSS del admin solo en las páginas del plugin.
	 *
	 * @param string $hook Página actual.
	 */
	public function assets( $hook ) {
		if ( in_array( $hook, array( 'settings_page_consentia', 'settings_page_consentia-log' ), true ) ) {
			wp_enqueue_style( 'consentia-admin', CONSENTIA_URL . 'assets/css/admin.css', array(), CONSENTIA_VERSION );
		}
	}

	/**
	 * Menú.
	 */
	public function menu() {
		add_options_page(
			__( 'Consentia', 'consentia' ),
			__( 'Consentia', 'consentia' ),
			'manage_options',
			'consentia',
			array( $this, 'render' )
		);
	}

	/**
	 * Registro del ajuste.
	 */
	public function register() {
		register_setting(
			'consentia_group',
			CONSENTIA_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => consentia_default_settings(),
			)
		);
	}

	/**
	 * Saneamiento de todos los campos.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	public function sanitize( $input ) {
		$d     = consentia_default_settings();
		$clean = array();

		$clean['consent_type'] = in_array( ( $input['consent_type'] ?? '' ), array( 'optin', 'optout' ), true ) ? $input['consent_type'] : 'optin';

		$valid_cats = array_keys( consentia_categories() );
		$cats       = isset( $input['categories_enabled'] ) ? (array) $input['categories_enabled'] : array();
		$clean['categories_enabled'] = array_values( array_intersect( array_map( 'sanitize_key', $cats ), $valid_cats ) );

		$clean['emit_consent_mode']  = empty( $input['emit_consent_mode'] ) ? 0 : 1;
		$clean['url_passthrough']    = empty( $input['url_passthrough'] ) ? 0 : 1;
		$clean['ads_data_redaction'] = empty( $input['ads_data_redaction'] ) ? 0 : 1;
		$clean['log_enabled']        = empty( $input['log_enabled'] ) ? 0 : 1;

		$wait = isset( $input['wait_for_update'] ) ? absint( $input['wait_for_update'] ) : 500;
		$clean['wait_for_update'] = max( 0, min( 5000, $wait ) );

		$clean['reconsent_version'] = isset( $input['reconsent_version'] ) ? absint( $input['reconsent_version'] ) : CONSENTIA_CONSENT_VERSION;

		$position = $input['position'] ?? 'bottom';
		$clean['position'] = in_array( $position, array( 'bottom', 'box-left', 'box-right' ), true ) ? $position : 'bottom';

		foreach ( array( 'color_primary', 'color_bg', 'color_text' ) as $c ) {
			$val = isset( $input[ $c ] ) ? sanitize_hex_color( $input[ $c ] ) : '';
			$clean[ $c ] = $val ? $val : $d[ $c ];
		}

		$clean['privacy_url'] = isset( $input['privacy_url'] ) ? esc_url_raw( $input['privacy_url'] ) : '';

		$textareas = array( 'message' );
		$texts     = array( 'title', 'btn_accept', 'btn_reject', 'btn_prefs', 'btn_save', 'privacy_text' );
		foreach ( $textareas as $t ) {
			$clean[ $t ] = isset( $input[ $t ] ) ? sanitize_textarea_field( wp_unslash( $input[ $t ] ) ) : $d[ $t ];
		}
		foreach ( $texts as $t ) {
			$clean[ $t ] = isset( $input[ $t ] ) ? sanitize_text_field( wp_unslash( $input[ $t ] ) ) : $d[ $t ];
		}

		return wp_parse_args( $clean, $d );
	}

	/**
	 * Renderiza la página.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = consentia_get_settings();
		$cats = consentia_categories();
		$sitekit = defined( 'GOOGLESITEKIT_VERSION' ) || is_plugin_active( 'google-site-kit/google-site-kit.php' );
		?>
		<div class="wrap consentia-settings">
			<h1><?php esc_html_e( 'Consentia — Ajustes', 'consentia' ); ?></h1>

			<div class="consentia-status">
				<p>
					<strong><?php esc_html_e( 'Estado de integración:', 'consentia' ); ?></strong>
					<?php if ( consentia_wp_consent_api_active() ) : ?>
						<span class="consentia-ok">✓ <?php esc_html_e( 'WP Consent API activa', 'consentia' ); ?></span>
					<?php else : ?>
						<span class="consentia-warn">✗ <?php esc_html_e( 'WP Consent API NO instalada (necesaria para Site Kit)', 'consentia' ); ?></span>
					<?php endif; ?>
					&nbsp;·&nbsp;
					<?php if ( $sitekit ) : ?>
						<span class="consentia-ok">✓ <?php esc_html_e( 'Site Kit detectado', 'consentia' ); ?></span>
					<?php else : ?>
						<span><?php esc_html_e( 'Site Kit no detectado', 'consentia' ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( $sitekit ) : ?>
					<p class="description"><?php esc_html_e( 'Con Site Kit: activa su «Consent Mode» y deja aquí «Emitir Consent Mode directo» en OFF (Site Kit ya emite las señales).', 'consentia' ); ?></p>
				<?php endif; ?>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'consentia_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Comportamiento', 'consentia' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Modelo de consentimiento', 'consentia' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $this->n( 'consent_type' ) ); ?>" value="optin" <?php checked( $s['consent_type'], 'optin' ); ?> /> <?php esc_html_e( 'Opt-in (denegado hasta aceptar — recomendado, GDPR/Consent Mode)', 'consentia' ); ?></label><br />
							<label><input type="radio" name="<?php echo esc_attr( $this->n( 'consent_type' ) ); ?>" value="optout" <?php checked( $s['consent_type'], 'optout' ); ?> /> <?php esc_html_e( 'Opt-out (concedido hasta rechazar)', 'consentia' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Categorías activas', 'consentia' ); ?></th>
						<td>
							<?php foreach ( $cats as $slug => $cat ) : ?>
								<?php if ( ! empty( $cat['required'] ) ) : ?>
									<label><input type="checkbox" checked disabled /> <?php echo esc_html( $cat['label'] ); ?> <em>(<?php esc_html_e( 'siempre', 'consentia' ); ?>)</em></label><br />
								<?php else : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $this->n( 'categories_enabled' ) ); ?>[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $s['categories_enabled'], true ) ); ?> /> <?php echo esc_html( $cat['label'] ); ?></label><br />
								<?php endif; ?>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Registrar consentimientos', 'consentia' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $this->n( 'log_enabled' ) ); ?>" value="1" <?php checked( $s['log_enabled'], 1 ); ?> /> <?php esc_html_e( 'Guardar prueba de cada consentimiento (IP cifrada).', 'consentia' ); ?></label>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=consentia-log' ) ); ?>"><?php esc_html_e( 'Ver registro', 'consentia' ); ?></a></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Google Consent Mode v2 (modo directo)', 'consentia' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Emitir Consent Mode directo', 'consentia' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $this->n( 'emit_consent_mode' ) ); ?>" value="1" <?php checked( $s['emit_consent_mode'], 1 ); ?> /> <?php esc_html_e( 'El plugin emite gtag(\'consent\',\'default\') denegado.', 'consentia' ); ?></label>
							<p class="description"><?php esc_html_e( '⚠️ Actívalo SOLO si NO usas el Consent Mode de Site Kit, para no duplicar señales.', 'consentia' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'wait_for_update (ms)', 'consentia' ); ?></th>
						<td><input type="number" min="0" max="5000" name="<?php echo esc_attr( $this->n( 'wait_for_update' ) ); ?>" value="<?php echo esc_attr( $s['wait_for_update'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Opciones avanzadas', 'consentia' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $this->n( 'url_passthrough' ) ); ?>" value="1" <?php checked( $s['url_passthrough'], 1 ); ?> /> url_passthrough</label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $this->n( 'ads_data_redaction' ) ); ?>" value="1" <?php checked( $s['ads_data_redaction'], 1 ); ?> /> ads_data_redaction</label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Textos', 'consentia' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row( 'title', __( 'Título', 'consentia' ), $s );
					$this->textarea_row( 'message', __( 'Mensaje', 'consentia' ), $s );
					$this->text_row( 'btn_accept', __( 'Botón aceptar', 'consentia' ), $s );
					$this->text_row( 'btn_reject', __( 'Botón rechazar', 'consentia' ), $s );
					$this->text_row( 'btn_prefs', __( 'Botón preferencias', 'consentia' ), $s );
					$this->text_row( 'btn_save', __( 'Botón guardar', 'consentia' ), $s );
					$this->text_row( 'privacy_text', __( 'Texto enlace privacidad', 'consentia' ), $s );
					$this->text_row( 'privacy_url', __( 'URL política de privacidad', 'consentia' ), $s, __( 'Vacío = usa la de WordPress.', 'consentia' ) );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Apariencia', 'consentia' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Posición', 'consentia' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $this->n( 'position' ) ); ?>">
								<option value="bottom" <?php selected( $s['position'], 'bottom' ); ?>><?php esc_html_e( 'Barra inferior', 'consentia' ); ?></option>
								<option value="box-left" <?php selected( $s['position'], 'box-left' ); ?>><?php esc_html_e( 'Caja abajo-izquierda', 'consentia' ); ?></option>
								<option value="box-right" <?php selected( $s['position'], 'box-right' ); ?>><?php esc_html_e( 'Caja abajo-derecha', 'consentia' ); ?></option>
							</select>
						</td>
					</tr>
					<?php
					$this->color_row( 'color_primary', __( 'Color primario (botón)', 'consentia' ), $s );
					$this->color_row( 'color_bg', __( 'Fondo', 'consentia' ), $s );
					$this->color_row( 'color_text', __( 'Texto', 'consentia' ), $s );
					?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * name="option[key]".
	 *
	 * @param string $key Clave.
	 * @return string
	 */
	private function n( $key ) {
		return CONSENTIA_OPTION . '[' . $key . ']';
	}

	/**
	 * Fila de texto.
	 *
	 * @param string $key   Clave.
	 * @param string $label Etiqueta.
	 * @param array  $s     Ajustes.
	 * @param string $desc  Descripción.
	 */
	private function text_row( $key, $label, $s, $desc = '' ) {
		?>
		<tr>
			<th scope="row"><label for="c-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" id="c-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->n( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" class="regular-text" />
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Fila de textarea.
	 *
	 * @param string $key   Clave.
	 * @param string $label Etiqueta.
	 * @param array  $s     Ajustes.
	 */
	private function textarea_row( $key, $label, $s ) {
		?>
		<tr>
			<th scope="row"><label for="c-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><textarea id="c-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->n( $key ) ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $s[ $key ] ); ?></textarea></td>
		</tr>
		<?php
	}

	/**
	 * Fila de color.
	 *
	 * @param string $key   Clave.
	 * @param string $label Etiqueta.
	 * @param array  $s     Ajustes.
	 */
	private function color_row( $key, $label, $s ) {
		?>
		<tr>
			<th scope="row"><label for="c-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input type="color" id="c-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->n( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" /></td>
		</tr>
		<?php
	}
}
