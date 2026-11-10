<?php
/**
 * Security hardening. Complements (does not replace) a WAF such as Wordfence, SSL and backups.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Security.
 */
final class BMN_Security {

	const MAX_FAILS    = 5;
	const LOCK_SECONDS = 900;

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! apply_filters( 'bmn_security_enabled', true ) ) {
			return;
		}
		// Hide versions.
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );

		// XML-RPC off (brute-force & pingback DDoS vector).
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_headers', array( __CLASS__, 'strip_pingback' ) );
		add_filter( 'pings_open', '__return_false' );

		// User enumeration.
		add_action( 'template_redirect', array( __CLASS__, 'block_author_scan' ), 1 );
		add_filter( 'rest_endpoints', array( __CLASS__, 'limit_user_endpoints' ) );
		add_filter( 'oembed_response_data', array( __CLASS__, 'oembed_hide_author' ) );

		// Login hardening.
		add_filter( 'authenticate', array( __CLASS__, 'check_lock' ), 30, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'record_failure' ) );
		add_action( 'wp_login', array( __CLASS__, 'clear_failures' ), 10, 1 );
		add_filter( 'login_errors', array( __CLASS__, 'generic_login_error' ) );

		// Headers.
		add_action( 'send_headers', array( __CLASS__, 'headers' ) );
		add_action( 'login_init', array( __CLASS__, 'headers' ) );
		add_action( 'admin_init', array( __CLASS__, 'headers' ) );
	}

	/**
	 * Remove X-Pingback.
	 *
	 * @param array $headers Headers.
	 * @return array
	 */
	public static function strip_pingback( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Stop ?author=N scans for visitors.
	 */
	public static function block_author_scan() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check.
		if ( ! is_user_logged_in() && ( isset( $_GET['author'] ) || is_author() ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * Hide /wp/v2/users from anonymous visitors.
	 *
	 * @param array $endpoints Endpoints.
	 * @return array
	 */
	public static function limit_user_endpoints( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	/**
	 * Remove author data from oEmbed.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	public static function oembed_hide_author( $data ) {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}

	/**
	 * Lock key for IP.
	 *
	 * @return string
	 */
	private static function key() {
		return 'bmn_login_' . md5( bmn_client_ip() );
	}

	/**
	 * Refuse authentication while locked.
	 *
	 * @param WP_User|WP_Error|null $user     User.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public static function check_lock( $user, $username, $password ) {
		unset( $password );
		if ( '' === (string) $username ) {
			return $user;
		}
		$data = get_transient( self::key() );
		if ( is_array( $data ) && $data['fails'] >= self::MAX_FAILS ) {
			return new WP_Error( 'bmn_locked', __( 'به دلیل تلاش‌های ناموفق متعدد، ورود موقتاً مسدود شد. ۱۵ دقیقه دیگر تلاش کنید.', 'boshrouyeh-core' ) );
		}
		return $user;
	}

	/**
	 * Count a failure.
	 */
	public static function record_failure() {
		$data = get_transient( self::key() );
		$data = is_array( $data ) ? $data : array( 'fails' => 0 );
		++$data['fails'];
		set_transient( self::key(), $data, self::LOCK_SECONDS );
	}

	/**
	 * Reset after success.
	 */
	public static function clear_failures() {
		delete_transient( self::key() );
	}

	/**
	 * Generic error (don't reveal whether username exists).
	 *
	 * @param string $error Error.
	 * @return string
	 */
	public static function generic_login_error( $error ) {
		if ( str_contains( (string) $error, 'bmn_locked' ) || str_contains( (string) $error, '۱۵ دقیقه' ) ) {
			return $error;
		}
		return esc_html__( 'نام کاربری یا گذرواژه نادرست است.', 'boshrouyeh-core' );
	}

	/**
	 * Security headers.
	 */
	public static function headers() {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(self), geolocation=(self), microphone=(), payment=(self), interest-cohort=()' );
		header( 'Cross-Origin-Opener-Policy: same-origin-allow-popups' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}
	}
}

// Disallow theme/plugin file editor in wp-admin unless explicitly configured.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}
