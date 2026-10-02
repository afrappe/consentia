<?php
/**
 * Funciones de apoyo: opciones, categorías de consentimiento y utilidades.
 *
 * @package Consentia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clave de la opción de ajustes.
 */
const CONSENTIA_OPTION = 'consentia_settings';

/**
 * Versión del "aviso" de consentimiento. Súbela para volver a pedir
 * consentimiento a todos (p. ej. si cambian las cookies usadas).
 */
const CONSENTIA_CONSENT_VERSION = 1;

/**
 * Categorías de consentimiento de la WP Consent API.
 *
 * `functional` es siempre necesaria (no se puede desactivar). El resto son
 * opcionales. Cada una mapea a señales de Google Consent Mode v2 (Site Kit
 * hace ese mapeo automáticamente; el modo directo del plugin lo replica).
 *
 * @return array
 */
function consentia_categories() {
	static $categories = null;
	static $categories_locale = null;
	$current_locale = get_locale();
	if ( null === $categories || $categories_locale !== $current_locale ) {
		$categories_locale = $current_locale;
		$categories = array(
			'functional'           => array(
				'label'    => __( 'Necesarias', 'consentia' ),
				'desc'     => __( 'Imprescindibles para el funcionamiento del sitio. Siempre activas.', 'consentia' ),
				'required' => true,
				'gcm'      => array( 'functionality_storage', 'security_storage' ),
			),
			'preferences'          => array(
				'label'    => __( 'Preferencias', 'consentia' ),
				'desc'     => __( 'Recuerdan tus preferencias (idioma, región, personalización).', 'consentia' ),
				'required' => false,
				'gcm'      => array( 'personalization_storage' ),
			),
			'statistics'           => array(
				'label'    => __( 'Estadísticas', 'consentia' ),
				'desc'     => __( 'Analítica que nos ayuda a entender cómo se usa el sitio.', 'consentia' ),
				'required' => false,
				'gcm'      => array( 'analytics_storage' ),
			),
			'statistics-anonymous' => array(
				'label'    => __( 'Estadísticas anónimas', 'consentia' ),
				'desc'     => __( 'Analítica sin datos que te identifiquen.', 'consentia' ),
				'required' => false,
				'gcm'      => array(),
			),
			'marketing'            => array(
				'label'    => __( 'Marketing', 'consentia' ),
				'desc'     => __( 'Publicidad y seguimiento para medir campañas y mostrar anuncios relevantes.', 'consentia' ),
				'required' => false,
				'gcm'      => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
			),
		);
	}
	return $categories;
}

/**
 * Valores por defecto de los ajustes.
 *
 * @return array
 */
function consentia_default_settings() {
	static $settings = null;
	if ( null === $settings ) {
		$settings = array(
			// Comportamiento.
			'consent_type'        => 'optin',   // optin (GDPR/Consent Mode) u optout.
			'categories_enabled'  => array( 'functional', 'statistics', 'marketing' ),
			'reconsent_version'   => CONSENTIA_CONSENT_VERSION,

			// Consent Mode v2 directo (déjalo OFF si usas el Consent Mode de Site Kit).
			'emit_consent_mode'   => 0,
			'wait_for_update'     => 500,
			'url_passthrough'     => 1,
			'ads_data_redaction'  => 1,

			// Registro.
			'log_enabled'         => 1,

			// Textos.
			'title'               => __( 'Usamos cookies', 'consentia' ),
			'message'             => __( 'Utilizamos cookies propias y de terceros para analítica y marketing. Puedes aceptar todas, rechazarlas o configurar tus preferencias.', 'consentia' ),
			'btn_accept'          => __( 'Aceptar todo', 'consentia' ),
			'btn_reject'          => __( 'Rechazar', 'consentia' ),
			'btn_prefs'           => __( 'Preferencias', 'consentia' ),
			'btn_save'            => __( 'Guardar preferencias', 'consentia' ),
			'privacy_text'        => __( 'Política de privacidad', 'consentia' ),
			'privacy_url'         => '',

			// Apariencia.
			'position'            => 'bottom',  // bottom | box-left | box-right.
			'color_primary'       => '#002c57',
			'color_bg'            => '#ffffff',
			'color_text'          => '#181c1e',
		);
	}
	return $settings;
}

/**
 * Lee todos los ajustes fusionados con los valores por defecto.
 *
 * @return array
 */
function consentia_get_settings() {
	$saved = get_option( CONSENTIA_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, consentia_default_settings() );
}

/**
 * Lee un ajuste individual.
 *
 * @param string $key     Clave.
 * @param mixed  $default Valor por defecto.
 * @return mixed
 */
function consentia_get( $key, $default = '' ) {
	$s = consentia_get_settings();
	return array_key_exists( $key, $s ) ? $s[ $key ] : $default;
}

/**
 * Categorías activas (siempre incluye `functional`).
 *
 * @return array Subconjunto de consentia_categories().
 */
function consentia_active_categories() {
	$all     = consentia_categories();
	$enabled = (array) consentia_get( 'categories_enabled', array() );
	$enabled[] = 'functional';
	$out = array();
	foreach ( $all as $slug => $data ) {
		if ( in_array( $slug, $enabled, true ) ) {
			$out[ $slug ] = $data;
		}
	}
	return $out;
}

/**
 * ¿Está activa la WP Consent API? (necesaria para Site Kit).
 *
 * @return bool
 */
function consentia_wp_consent_api_active() {
	return function_exists( 'wp_has_consent' ) || function_exists( 'wp_set_consent' );
}

/**
 * Mapa categoría => señales de Google Consent Mode, solo de las categorías
 * activas. Se pasa al JS para el modo directo.
 *
 * @return array
 */
function consentia_gcm_map() {
	$map = array();
	foreach ( consentia_active_categories() as $slug => $data ) {
		if ( ! empty( $data['gcm'] ) ) {
			$map[ $slug ] = $data['gcm'];
		}
	}
	return $map;
}
