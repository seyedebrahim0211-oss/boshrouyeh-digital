<?php
/**
 * Install: tables, roles, capabilities, private upload dir, secrets.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
final class BMN_ES_Install {

	const DB_VERSION = '1.0';

	/**
	 * Activation.
	 */
	public static function activate() {
		self::tables();
		self::roles();
		self::private_dir();
		self::secrets();
		update_option( 'bmn_es_db', self::DB_VERSION );
		if ( class_exists( 'BMN_Payments' ) ) {
			BMN_Payments::rewrite();
		}
		flush_rewrite_rules();
	}

	/**
	 * Upgrade when the DB version changes.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'bmn_es_db' ) !== self::DB_VERSION ) {
			self::activate();
		}
	}

	/**
	 * Payments table.
	 */
	public static function tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = $wpdb->prefix . 'bmn_payments';
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				token varchar(64) NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				phone varchar(15) NOT NULL DEFAULT '',
				kind varchar(32) NOT NULL DEFAULT '',
				ref varchar(64) NOT NULL DEFAULT '',
				title varchar(190) NOT NULL DEFAULT '',
				amount bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(16) NOT NULL DEFAULT 'pending',
				gateway varchar(20) NOT NULL DEFAULT '',
				authority varchar(128) NOT NULL DEFAULT '',
				bank_ref varchar(64) NOT NULL DEFAULT '',
				card varchar(32) NOT NULL DEFAULT '',
				meta longtext NULL,
				created_at datetime NOT NULL,
				paid_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY token (token),
				KEY user_id (user_id),
				KEY status (status),
				KEY authority (authority)
			) {$charset};"
		);
	}

	/**
	 * Roles & caps.
	 */
	public static function roles() {
		add_role( 'bmn_citizen', __( 'شهروند', 'boshrouyeh-eservices' ), array( 'read' => true ) );
		$caps = array(
			'read'                        => true,
			'edit_bmn_requests'           => true,
			'edit_others_bmn_requests'    => true,
			'read_private_bmn_requests'   => true,
			'edit_private_bmn_requests'   => true,
			'edit_published_bmn_requests' => true,
			'publish_bmn_requests'        => true,
			'bmn_view_payments'           => true,
			'upload_files'                => true,
		);
		add_role( 'bmn_staff', __( 'کارمند شهرداری', 'boshrouyeh-eservices' ), $caps );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_keys( $caps ) as $cap ) {
				$admin->add_cap( $cap );
			}
			$admin->add_cap( 'delete_bmn_requests' );
			$admin->add_cap( 'delete_others_bmn_requests' );
			$admin->add_cap( 'delete_published_bmn_requests' );
			$admin->add_cap( 'delete_private_bmn_requests' );
			$admin->add_cap( 'bmn_manage_eservices' );
		}
	}

	/**
	 * Private upload directory (denied for direct HTTP access).
	 *
	 * @return string Absolute path.
	 */
	public static function private_dir() {
		if ( defined( 'BMN_PRIVATE_DIR' ) && BMN_PRIVATE_DIR ) {
			// Recommended: a directory outside the web root.
			$dir = untrailingslashit( (string) BMN_PRIVATE_DIR );
		} else {
			$up  = wp_upload_dir( null, false );
			$dir = trailingslashit( $up['basedir'] ) . 'bmn-private';
		}
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny static guard files.
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php // Silence.\n" );
		}
		if ( ! file_exists( $dir . '/web.config' ) ) {
			file_put_contents( $dir . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' );
		}
		// phpcs:enable
		return $dir;
	}

	/**
	 * Generate secrets once.
	 */
	public static function secrets() {
		if ( ! get_option( 'bmn_es_jwt_secret' ) ) {
			add_option( 'bmn_es_jwt_secret', bin2hex( random_bytes( 32 ) ), '', false );
		}
	}
}
