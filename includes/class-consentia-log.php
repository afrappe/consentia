<?php
/**
 * Registro (log) de consentimientos: prueba de cumplimiento.
 *
 * @package Consentia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Consentia_Log
 */
class Consentia_Log {

	/**
	 * Instancia única.
	 *
	 * @var Consentia_Log|null
	 */
	private static $instance = null;

	/**
	 * Devuelve la instancia única.
	 *
	 * @return Consentia_Log
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Nombre de la tabla.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'consentia_log';
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_consentia_log', array( $this, 'ajax_record' ) );
		add_action( 'wp_ajax_nopriv_consentia_log', array( $this, 'ajax_record' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_consentia_export', array( $this, 'export_csv' ) );
	}

	/**
	 * Crea la tabla del registro (en activación).
	 */
	public static function create_table() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			consent LONGTEXT NOT NULL,
			consent_version SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY ip_hash_created_at (ip_hash, created_at)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Aplica las actualizaciones de la base de datos.
	 */
	public static function maybe_upgrade() {
		if ( 1 !== (int) get_option( 'consentia_db_version', 0 ) ) {
			self::create_table();
			update_option( 'consentia_db_version', 1 );
		}
	}

	/**
	 * Recibe y guarda un consentimiento vía AJAX.
	 */
	public function ajax_record() {
		check_ajax_referer( 'consentia_log', 'nonce' );

		if ( ! consentia_get( 'log_enabled', 1 ) ) {
			wp_send_json_success( array( 'logged' => false ) );
		}

		$raw = isset( $_POST['consent'] ) ? wp_unslash( $_POST['consent'] ) : '';
		$decoded = json_decode( is_string( $raw ) ? $raw : '', true );
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}

		// Sanea el mapa categoría => allow/deny.
		$clean = array();
		foreach ( $decoded as $cat => $val ) {
			$cat = sanitize_key( $cat );
			$clean[ $cat ] = ( 'allow' === $val ) ? 'allow' : 'deny';
		}

		$version = isset( $_POST['version'] ) ? absint( $_POST['version'] ) : 0;
		$ip_hash = $this->ip_hash();
		if ( empty( $ip_hash ) ) {
			wp_send_json_error( array( 'message' => 'Unable to determine request source' ), 400 );
		}

		global $wpdb;
		$table = self::table();

		// Serialize each IP's count-and-insert section to enforce the limit.
		$lock_name = 'consentia_' . substr( $ip_hash, 0, 54 );
		$lock      = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name )
		);
		if ( '1' !== (string) $lock ) {
			wp_send_json_error( array( 'message' => 'Too many requests' ), 429 );
		}

		$time_ago     = gmdate( 'Y-m-d H:i:s', time() - 60 );
		$recent_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ip_hash = %s AND created_at >= %s",
				$ip_hash,
				$time_ago
			)
		);

		if ( $recent_count >= 25 ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_send_json_error( array( 'message' => 'Too many requests' ), 429 );
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'created_at'      => current_time( 'mysql', true ),
				'ip_hash'         => $ip_hash,
				'consent'         => wp_json_encode( $clean ),
				'consent_version' => $version,
				'user_agent'      => substr( sanitize_text_field( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' ), 0, 255 ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		wp_send_json_success( array( 'logged' => true ) );
	}

	/**
	 * Hash irreversible de la IP (privacidad): sha256(IP + salt del sitio).
	 * No guardamos la IP en claro.
	 *
	 * @return string
	 */
	private function ip_hash() {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		if ( '' === $ip ) {
			return '';
		}
		return hash( 'sha256', $ip . wp_salt( 'auth' ) );
	}

	/**
	 * Menú de administración (bajo Ajustes → Consentia).
	 */
	public function menu() {
		add_submenu_page(
			'options-general.php',
			__( 'Registro de consentimientos', 'consentia' ),
			__( 'Consentia · Registro', 'consentia' ),
			'manage_options',
			'consentia-log',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Página del registro.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$table = self::table();

		$per_page = 50;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset   = ( $paged - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore

		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=consentia_export' ), 'consentia_export' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Consentia — Registro de consentimientos', 'consentia' ); ?></h1>
			<p>
				<?php
				/* translators: %d: total de registros. */
				printf( esc_html__( 'Total: %d registros. La IP se guarda cifrada (hash) por privacidad.', 'consentia' ), (int) $total );
				?>
				&nbsp; <a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Exportar CSV', 'consentia' ); ?></a>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fecha (UTC)', 'consentia' ); ?></th>
						<th><?php esc_html_e( 'Consentimiento', 'consentia' ); ?></th>
						<th><?php esc_html_e( 'Versión', 'consentia' ); ?></th>
						<th><?php esc_html_e( 'IP (hash)', 'consentia' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $rows ) : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td><code><?php echo esc_html( $this->format_consent( $row->consent ) ); ?></code></td>
								<td><?php echo esc_html( $row->consent_version ); ?></td>
								<td><small><?php echo esc_html( substr( $row->ip_hash, 0, 12 ) ); ?>…</small></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="4"><?php esc_html_e( 'Sin registros todavía.', 'consentia' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
			$pages = (int) ceil( $total / $per_page );
			if ( $pages > 1 ) {
				echo '<p class="tablenav-pages" style="margin-top:12px">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $pages,
						)
					)
				);
				echo '</p>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Formatea el JSON de consentimiento como "cat:allow, cat:deny".
	 *
	 * @param string $json JSON almacenado.
	 * @return string
	 */
	private function format_consent( $json ) {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$parts = array();
		foreach ( $data as $cat => $val ) {
			$parts[] = $cat . ':' . $val;
		}
		return implode( ', ', $parts );
	}

	/**
	 * Exporta el registro completo a CSV.
	 */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'consentia' ) );
		}
		check_admin_referer( 'consentia_export' );

		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC", ARRAY_A ); // phpcs:ignore

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=consentia-log-' . gmdate( 'Ymd-His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'id', 'created_at_utc', 'consent', 'consent_version', 'ip_hash', 'user_agent' ) );
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$row_data = array( $r['id'], $r['created_at'], $r['consent'], $r['consent_version'], $r['ip_hash'], $r['user_agent'] );
				foreach ( $row_data as $k => $v ) {
					$v = (string) $v;
					if ( isset( $v[0] ) && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) {
						$row_data[ $k ] = "'" . $v;
					}
				}
				fputcsv( $out, $row_data );
			}
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
