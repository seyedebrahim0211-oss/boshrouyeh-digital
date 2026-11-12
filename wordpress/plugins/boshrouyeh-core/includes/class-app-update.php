<?php
/**
 * In-app update info: GET /bmn/v1/app/version
 *
 * Source of truth, in order: manual settings (app_latest + app_apk) → latest GitHub Release of the repo.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * App update endpoint.
 */
class BMN_App_Update {

	const REPO = 'seyedebrahim0211-oss/boshrouyeh-digital';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'bmn_min_app_version', array( __CLASS__, 'min_version' ) );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/app/version',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'version' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'platform' => array(
						'type'    => 'string',
						'enum'    => array( 'android', 'ios' ),
						'default' => 'android',
					),
				),
			)
		);
	}

	/**
	 * Minimum supported version (forces update below it).
	 *
	 * @param string $v Default.
	 * @return string
	 */
	public static function min_version( $v ) {
		$min = self::clean_version( (string) bmn_option( 'app_min', '' ) );
		return $min ? $min : $v;
	}

	/**
	 * Normalize "v1.2.3" → "1.2.3".
	 *
	 * @param string $v Version.
	 * @return string
	 */
	public static function clean_version( $v ) {
		$v = ltrim( trim( bmn_en_digits( $v ) ), 'vV' );
		return preg_match( '/^\d+(\.\d+){0,3}$/', $v ) ? $v : '';
	}

	/**
	 * Latest GitHub release (cached 30 min).
	 *
	 * @return array{version:string,url:string,notes:string,published:string}|null
	 */
	private static function github() {
		$repo = (string) bmn_option( 'app_github', '' );
		$repo = preg_match( '#^[\w.-]+/[\w.-]+$#', $repo ) ? $repo : self::REPO;
		$key  = 'bmn_gh_rel_' . md5( $repo );
		$hit  = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit ? $hit : null;
		}
		$res  = wp_safe_remote_get(
			'https://api.github.com/repos/' . $repo . '/releases/latest',
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);
		$data = array();
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$j = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( is_array( $j ) ) {
				$url = '';
				foreach ( (array) ( $j['assets'] ?? array() ) as $a ) {
					if ( isset( $a['browser_download_url'] ) && str_ends_with( (string) $a['name'], '.apk' ) ) {
						$url = (string) $a['browser_download_url'];
						break;
					}
				}
				$ver = self::clean_version( (string) ( $j['tag_name'] ?? '' ) );
				if ( $ver && $url ) {
					$data = array(
						'version'   => $ver,
						'url'       => $url,
						'notes'     => wp_strip_all_tags( (string) ( $j['body'] ?? '' ) ),
						'published' => (string) ( $j['published_at'] ?? '' ),
					);
				}
			}
		}
		set_transient( $key, $data, $data ? 30 * MINUTE_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
		return $data ? $data : null;
	}

	/**
	 * GET /app/version
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function version( WP_REST_Request $req ) {
		$latest = self::clean_version( (string) bmn_option( 'app_latest', '' ) );
		$url    = (string) bmn_option( 'app_apk', '' );
		$notes  = (string) bmn_option( 'app_notes', '' );
		$pub    = '';
		if ( 'ios' === $req['platform'] ) {
			$url = '';
		} elseif ( ! $latest || ! $url ) {
			$gh = self::github();
			if ( $gh && ( ! $latest || version_compare( $gh['version'], $latest, '>=' ) ) ) {
				$latest = $gh['version'];
				$url    = $gh['url'];
				$notes  = $notes ? $notes : $gh['notes'];
				$pub    = $gh['published'];
			}
		}
		$stores = array_filter(
			array(
				'bazaar' => (string) bmn_option( 'app_bazaar', '' ),
				'myket'  => (string) bmn_option( 'app_myket', '' ),
			)
		);
		$res = rest_ensure_response(
			array(
				'latest'    => $latest,
				'min'       => self::min_version( '1.0.0' ),
				'url'       => esc_url_raw( $url ),
				'notes'     => mb_substr( $notes, 0, 1500 ),
				'published' => $pub,
				'stores'    => (object) $stores,
			)
		);
		$res->header( 'Cache-Control', 'public, max-age=600' );
		return $res;
	}
}
