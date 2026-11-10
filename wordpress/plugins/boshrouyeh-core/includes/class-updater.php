<?php
/**
 * Updates the theme and plugins from the project's GitHub repository.
 *
 * Remote version = "Version:" header of the file on the main branch; package = asset on release "wp-latest"
 * (built by CI on every push). Works with WordPress' normal update screen and plugin auto-updates.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * GitHub updater.
 */
class BMN_Updater {

	const REPO = 'seyedebrahim0211-oss/boshrouyeh-digital';

	/**
	 * Components: slug => [type, main file in repo, installed basename].
	 *
	 * @return array
	 */
	private static function components() {
		return array(
			'boshrouyeh-core'      => array( 'plugin', 'wordpress/plugins/boshrouyeh-core/boshrouyeh-core.php', 'boshrouyeh-core/boshrouyeh-core.php' ),
			'boshrouyeh-eservices' => array( 'plugin', 'wordpress/plugins/boshrouyeh-eservices/boshrouyeh-eservices.php', 'boshrouyeh-eservices/boshrouyeh-eservices.php' ),
			'khesht-builder'       => array( 'plugin', 'wordpress/plugins/khesht-builder/khesht-builder.php', 'khesht-builder/khesht-builder.php' ),
			'boshrouyeh-khesht'    => array( 'theme', 'wordpress/themes/boshrouyeh-khesht/style.css', 'boshrouyeh-khesht' ),
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'plugins' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( __CLASS__, 'themes' ) );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source' ), 10, 4 );
	}

	/**
	 * Repo slug (filterable / overridable in settings).
	 *
	 * @return string
	 */
	private static function repo() {
		$r = (string) bmn_option( 'app_github', '' );
		return preg_match( '#^[\w.-]+/[\w.-]+$#', $r ) ? $r : self::REPO;
	}

	/**
	 * Remote version of a component (cached 6 h).
	 *
	 * @param string $path Repo path.
	 * @return string
	 */
	private static function remote_version( $path ) {
		$key = 'bmn_upd_' . md5( self::repo() . $path );
		$v   = get_transient( $key );
		if ( false !== $v ) {
			return (string) $v;
		}
		$res = wp_safe_remote_get( 'https://raw.githubusercontent.com/' . self::repo() . '/main/' . $path, array( 'timeout' => 8 ) );
		$v   = '';
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) && preg_match( '/^\s*\*?\s*Version:\s*([0-9.]+)/mi', (string) wp_remote_retrieve_body( $res ), $m ) ) {
			$v = $m[1];
		}
		set_transient( $key, $v, $v ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $v;
	}

	/**
	 * Package URL.
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	private static function package( $slug ) {
		return 'https://github.com/' . self::repo() . '/releases/download/wp-latest/' . $slug . '.zip';
	}

	/**
	 * Inject plugin updates.
	 *
	 * @param object $t Transient.
	 * @return object
	 */
	public static function plugins( $t ) {
		if ( ! is_object( $t ) ) {
			return $t;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		foreach ( self::components() as $slug => $c ) {
			if ( 'plugin' !== $c[0] || empty( $installed[ $c[2] ] ) ) {
				continue;
			}
			$remote = self::remote_version( $c[1] );
			if ( $remote && version_compare( $remote, $installed[ $c[2] ]['Version'], '>' ) ) {
				$t->response[ $c[2] ] = (object) array(
					'id'          => 'github.com/' . self::repo() . '/' . $slug,
					'slug'        => $slug,
					'plugin'      => $c[2],
					'new_version' => $remote,
					'url'         => 'https://github.com/' . self::repo(),
					'package'     => self::package( $slug ),
				);
			} else {
				unset( $t->response[ $c[2] ] );
			}
		}
		return $t;
	}

	/**
	 * Inject theme update.
	 *
	 * @param object $t Transient.
	 * @return object
	 */
	public static function themes( $t ) {
		if ( ! is_object( $t ) ) {
			return $t;
		}
		$c     = self::components()['boshrouyeh-khesht'];
		$theme = wp_get_theme( $c[2] );
		if ( ! $theme->exists() ) {
			return $t;
		}
		$remote = self::remote_version( $c[1] );
		if ( $remote && version_compare( $remote, (string) $theme->get( 'Version' ), '>' ) ) {
			$t->response[ $c[2] ] = array(
				'theme'       => $c[2],
				'new_version' => $remote,
				'url'         => 'https://github.com/' . self::repo(),
				'package'     => self::package( $c[2] ),
			);
		}
		return $t;
	}

	/**
	 * Make sure the extracted folder has the expected name.
	 *
	 * @param string      $source        Extracted path.
	 * @param string      $remote_source Remote dir.
	 * @param WP_Upgrader $upgrader      Upgrader.
	 * @param array       $extra         Hook extra.
	 * @return string|WP_Error
	 */
	public static function fix_source( $source, $remote_source, $upgrader, $extra = array() ) {
		global $wp_filesystem;
		$slug = '';
		if ( ! empty( $extra['plugin'] ) ) {
			$slug = dirname( (string) $extra['plugin'] );
		} elseif ( ! empty( $extra['theme'] ) ) {
			$slug = (string) $extra['theme'];
		}
		if ( ! $slug || ! isset( self::components()[ $slug ] ) || basename( untrailingslashit( $source ) ) === $slug ) {
			return $source;
		}
		$target = trailingslashit( $remote_source ) . $slug . '/';
		if ( $wp_filesystem && $wp_filesystem->move( $source, $target, true ) ) {
			return $target;
		}
		return $source;
	}
}
