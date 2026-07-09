<?php
/**
 * Desinstalación: elimina opciones y la tabla del registro.
 *
 * @package Consentia
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'consentia_settings' );
delete_option( 'consentia_version' );

global $wpdb;
$table = $wpdb->prefix . 'consentia_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
