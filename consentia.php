<?php
/**
 * Plugin Name:       Consentia — Cookies & Consent Mode v2
 * Plugin URI:        https://github.com/afrappe/consentia
 * Description:       Banner de consentimiento de cookies compatible con la WP Consent API (Google Site Kit) y con Google Consent Mode v2. Banner aceptar/rechazar, modal de preferencias por categoría, panel de ajustes y registro de consentimientos.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            IntegraServices
 * Text Domain:       consentia
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Consentia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Acceso directo no permitido.
}

define( 'CONSENTIA_VERSION', '1.0.0' );
define( 'CONSENTIA_FILE', __FILE__ );
define( 'CONSENTIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONSENTIA_URL', plugin_dir_url( __FILE__ ) );
define( 'CONSENTIA_BASENAME', plugin_basename( __FILE__ ) );

require_once CONSENTIA_DIR . 'includes/helpers.php';
require_once CONSENTIA_DIR . 'includes/class-consentia-cmp.php';
require_once CONSENTIA_DIR . 'includes/class-consentia-consent-mode.php';
require_once CONSENTIA_DIR . 'includes/class-consentia-frontend.php';
require_once CONSENTIA_DIR . 'includes/class-consentia-settings.php';
require_once CONSENTIA_DIR . 'includes/class-consentia-log.php';

/**
 * Arranca todos los componentes.
 */
function consentia_boot() {
	Consentia_CMP::instance();
	Consentia_Consent_Mode::instance();
	Consentia_Frontend::instance();
	Consentia_Settings::instance();
	Consentia_Log::instance();
	Consentia_Log::maybe_upgrade();

	load_plugin_textdomain( 'consentia', false, dirname( CONSENTIA_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'consentia_boot' );

/**
 * Activación: crea la tabla del registro de consentimientos y guarda opciones
 * por defecto.
 */
function consentia_activate() {
	require_once CONSENTIA_DIR . 'includes/helpers.php';
	require_once CONSENTIA_DIR . 'includes/class-consentia-log.php';

	Consentia_Log::create_table();
	update_option( 'consentia_db_version', 1 );

	if ( false === get_option( CONSENTIA_OPTION ) ) {
		add_option( CONSENTIA_OPTION, consentia_default_settings() );
	}

	add_option( 'consentia_version', CONSENTIA_VERSION );
}
register_activation_hook( __FILE__, 'consentia_activate' );

/**
 * Enlace rápido a los ajustes desde la lista de plugins.
 *
 * @param array $links Enlaces existentes.
 * @return array
 */
function consentia_action_links( $links ) {
	$settings = '<a href="' . esc_url( admin_url( 'options-general.php?page=consentia' ) ) . '">' . esc_html__( 'Ajustes', 'consentia' ) . '</a>';
	array_unshift( $links, $settings );
	return $links;
}
add_filter( 'plugin_action_links_' . CONSENTIA_BASENAME, 'consentia_action_links' );
