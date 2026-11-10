<?php
/**
 * Passwordless citizen authentication (SMS OTP) for the website (cookie) and the app (JWT).
 *
 * Security notes:
 *  - codes are random_int, 5 digits, valid 2 minutes, max 5 attempts, stored only as HMAC;
 *  - per-phone cooldown (120s), per-phone (5/h) and per-IP (15/h) limits;
 *  - accounts with any capability beyond "read" can never sign in by SMS;
 *  - refresh tokens are random, stored hashed, rotated on use, max 5 devices, revocable.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Auth.
 */
final class BMN_Auth {

	const OTP_TTL      = 120;
	const ACCESS_TTL   = HOUR_IN_SECONDS;
	const REFRESH_TTL  = 30 * DAY_IN_SECONDS;
	const MAX_ATTEMPTS = 5;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'determine_current_user', array( __CLASS__, 'bearer_user' ), 20 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'bearer_errors' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'keep_citizens_out' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		$ns = 'bmn/v1';
		register_rest_route(
			$ns,
			'/auth/otp',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'request_otp' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'phone' => array( 'type' => 'string', 'required' => true, 'maxLength' => 20 ) ),
			)
		);
		register_rest_route(
			$ns,
			'/auth/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'verify' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone'  => array( 'type' => 'string', 'required' => true, 'maxLength' => 20 ),
					'code'   => array( 'type' => 'string', 'required' => true, 'maxLength' => 10 ),
					'mode'   => array( 'type' => 'string', 'default' => 'cookie', 'enum' => array( 'cookie', 'token' ) ),
					'device' => array( 'type' => 'string', 'default' => '', 'maxLength' => 80, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
		register_rest_route(
			$ns,
			'/auth/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'refresh' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'refresh' => array( 'type' => 'string', 'required' => true, 'maxLength' => 200 ) ),
			)
		);
		register_rest_route(
			$ns,
			'/auth/logout',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'logout' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'refresh' => array( 'type' => 'string', 'default' => '', 'maxLength' => 200 ) ),
			)
		);
		register_rest_route(
			$ns,
			'/me',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'me' ),
					'permission_callback' => array( __CLASS__, 'logged_in' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'update_me' ),
					'permission_callback' => array( __CLASS__, 'logged_in' ),
					'args'                => array(
						'name'         => array( 'type' => 'string', 'maxLength' => 60, 'sanitize_callback' => 'sanitize_text_field' ),
						'neighborhood' => array( 'type' => 'string', 'maxLength' => 60, 'sanitize_callback' => 'bmn_sanitize_slug' ),
					),
				),
			)
		);
	}

	/**
	 * Permission: any logged-in user.
	 *
	 * @return bool|WP_Error
	 */
	public static function logged_in() {
		return is_user_logged_in() ? true : bmn_es_error( 'auth', __( 'ابتدا وارد شوید.', 'boshrouyeh-eservices' ), 401 );
	}

	/**
	 * Transient key for a phone.
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	private static function otp_key( $phone ) {
		return 'bmn_otp_' . substr( hash_hmac( 'sha256', $phone, wp_salt( 'nonce' ) ), 0, 40 );
	}

	/**
	 * Hash a code bound to the phone.
	 *
	 * @param string $phone Phone.
	 * @param string $code  Code.
	 * @return string
	 */
	private static function code_hash( $phone, $code ) {
		return hash_hmac( 'sha256', $phone . '|' . $code, wp_salt( 'auth' ) );
	}

	/**
	 * POST /auth/otp
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function request_otp( $req ) {
		$phone = bmn_normalize_mobile( (string) $req['phone'] );
		if ( ! $phone ) {
			return bmn_es_error( 'phone', __( 'شمارهٔ همراه معتبر نیست.', 'boshrouyeh-eservices' ) );
		}
		$ip = bmn_client_ip();
		if ( ! bmn_rate_limit( 'otp_ip_' . $ip, 15, HOUR_IN_SECONDS ) || ! bmn_rate_limit( 'otp_ph_' . $phone, 5, HOUR_IN_SECONDS ) ) {
			return bmn_es_error( 'rate', __( 'تعداد درخواست‌ها زیاد است؛ یک ساعت دیگر تلاش کنید.', 'boshrouyeh-eservices' ), 429 );
		}
		$key      = self::otp_key( $phone );
		$existing = get_transient( $key );
		if ( is_array( $existing ) && ( $existing['sent'] + self::OTP_TTL ) > time() ) {
			$wait = ( $existing['sent'] + self::OTP_TTL ) - time();
			return new WP_REST_Response(
				array(
					'sent'      => false,
					'resend_in' => $wait,
					/* translators: %s: seconds. */
					'message'   => sprintf( __( 'کد قبلاً ارسال شده؛ %s ثانیهٔ دیگر می‌توانید دوباره درخواست دهید.', 'boshrouyeh-eservices' ), bmn_fa_digits( $wait ) ),
				),
				429
			);
		}
		$code = (string) random_int( 10000, 99999 );
		set_transient(
			$key,
			array(
				'hash'  => self::code_hash( $phone, $code ),
				'sent'  => time(),
				'exp'   => time() + self::OTP_TTL,
				'tries' => 0,
			),
			self::OTP_TTL + 60
		);
		$sent = BMN_SMS::send_otp( $phone, $code );
		if ( is_wp_error( $sent ) ) {
			delete_transient( $key );
			return bmn_es_error( 'sms', __( 'ارسال پیامک ناموفق بود؛ دقایقی دیگر تلاش کنید.', 'boshrouyeh-eservices' ), 502 );
		}
		$out = array(
			'sent'      => true,
			'ttl'       => self::OTP_TTL,
			'resend_in' => self::OTP_TTL,
		);
		if ( BMN_SMS::is_dev() ) {
			$out['dev_code'] = $code; // Development only (log driver + WP_DEBUG).
		}
		return rest_ensure_response( $out );
	}

	/**
	 * POST /auth/verify
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function verify( $req ) {
		$phone = bmn_normalize_mobile( (string) $req['phone'] );
		$code  = preg_replace( '/\D/', '', bmn_en_digits( (string) $req['code'] ) );
		if ( ! $phone || strlen( $code ) !== 5 ) {
			return bmn_es_error( 'code', __( 'کد واردشده معتبر نیست.', 'boshrouyeh-eservices' ) );
		}
		if ( 'cookie' === $req['mode'] && ! wp_verify_nonce( (string) $req->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return bmn_es_error( 'nonce', __( 'نشست منقضی شده؛ صفحه را دوباره بارگذاری کنید.', 'boshrouyeh-eservices' ), 403 );
		}
		$key  = self::otp_key( $phone );
		$data = get_transient( $key );
		if ( ! is_array( $data ) || $data['exp'] < time() ) {
			delete_transient( $key );
			return bmn_es_error( 'expired', __( 'کد منقضی شده؛ دوباره درخواست کنید.', 'boshrouyeh-eservices' ) );
		}
		if ( $data['tries'] >= self::MAX_ATTEMPTS ) {
			delete_transient( $key );
			return bmn_es_error( 'attempts', __( 'تلاش‌های ناموفق زیاد بود؛ کد جدید بگیرید.', 'boshrouyeh-eservices' ), 429 );
		}
		if ( ! hash_equals( $data['hash'], self::code_hash( $phone, $code ) ) ) {
			++$data['tries'];
			set_transient( $key, $data, max( 1, $data['exp'] - time() + 60 ) );
			/* translators: %s: remaining attempts. */
			return bmn_es_error( 'wrong', sprintf( __( 'کد نادرست است. %s تلاش باقی مانده.', 'boshrouyeh-eservices' ), bmn_fa_digits( self::MAX_ATTEMPTS - $data['tries'] ) ) );
		}
		delete_transient( $key );

		$user = self::user_for_phone( $phone );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		do_action( 'bmn_citizen_login', $user->ID, $req['mode'] );

		if ( 'token' === $req['mode'] ) {
			return rest_ensure_response( self::issue_tokens( $user->ID, (string) $req['device'] ) + array( 'user' => self::profile( $user->ID ) ) );
		}
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		return rest_ensure_response(
			array(
				'ok'   => true,
				'user' => self::profile( $user->ID ),
			)
		);
	}

	/**
	 * Find or create the citizen account for a phone.
	 *
	 * @param string $phone Phone.
	 * @return WP_User|WP_Error
	 */
	private static function user_for_phone( $phone ) {
		$users = get_users(
			array(
				'meta_key'   => '_bmn_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $phone, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
			)
		);
		if ( $users ) {
			$user = $users[0];
			if ( user_can( $user, 'edit_posts' ) || user_can( $user, 'edit_bmn_requests' ) ) {
				return bmn_es_error( 'privileged', __( 'حساب کارکنان فقط از طریق صفحهٔ ورود مدیریت قابل استفاده است.', 'boshrouyeh-eservices' ), 403 );
			}
			return $user;
		}
		$login = 'c' . $phone;
		if ( username_exists( $login ) ) {
			$login .= '_' . wp_rand( 100, 999 );
		}
		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 40, true, true ),
				'display_name' => __( 'شهروند', 'boshrouyeh-eservices' ),
				'role'         => 'bmn_citizen',
			)
		);
		if ( is_wp_error( $id ) ) {
			return bmn_es_error( 'create', __( 'ساخت حساب ناموفق بود.', 'boshrouyeh-eservices' ), 500 );
		}
		update_user_meta( $id, '_bmn_phone', $phone );
		return get_user_by( 'id', $id );
	}

	/**
	 * Issue an access + refresh token pair.
	 *
	 * @param int    $user_id User.
	 * @param string $device  Device label.
	 * @return array
	 */
	private static function issue_tokens( $user_id, $device = '' ) {
		$tv      = (int) get_user_meta( $user_id, '_bmn_tv', true );
		$access  = BMN_JWT::encode( array( 'sub' => $user_id, 'typ' => 'access', 'tv' => $tv ), self::ACCESS_TTL );
		$secret  = bin2hex( random_bytes( 32 ) );
		$refresh = $user_id . '.' . $secret;
		$list    = get_user_meta( $user_id, '_bmn_refresh', true );
		$list    = is_array( $list ) ? array_filter( $list, fn( $r ) => $r['exp'] > time() ) : array();
		$list[]  = array(
			'h'   => hash( 'sha256', $secret ),
			'exp' => time() + self::REFRESH_TTL,
			'dev' => mb_substr( $device, 0, 80 ),
			'at'  => time(),
		);
		usort( $list, fn( $a, $b ) => $b['at'] <=> $a['at'] );
		update_user_meta( $user_id, '_bmn_refresh', array_slice( array_values( $list ), 0, 5 ) );
		return array(
			'access_token'  => $access,
			'refresh_token' => $refresh,
			'expires_in'    => self::ACCESS_TTL,
			'token_type'    => 'Bearer',
		);
	}

	/**
	 * Split & validate a refresh token; returns [user_id, index in list] or WP_Error.
	 *
	 * @param string $token Token.
	 * @return array|WP_Error
	 */
	private static function find_refresh( $token ) {
		if ( ! preg_match( '/^(\d+)\.([a-f0-9]{64})$/', (string) $token, $m ) ) {
			return bmn_es_error( 'refresh', __( 'نشست نامعتبر است؛ دوباره وارد شوید.', 'boshrouyeh-eservices' ), 401 );
		}
		$uid  = (int) $m[1];
		$list = get_user_meta( $uid, '_bmn_refresh', true );
		$hash = hash( 'sha256', $m[2] );
		if ( is_array( $list ) ) {
			foreach ( $list as $i => $r ) {
				if ( hash_equals( $r['h'], $hash ) && $r['exp'] > time() ) {
					return array( $uid, $i );
				}
			}
		}
		return bmn_es_error( 'refresh', __( 'نشست نامعتبر است؛ دوباره وارد شوید.', 'boshrouyeh-eservices' ), 401 );
	}

	/**
	 * POST /auth/refresh (rotating).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function refresh( $req ) {
		if ( ! bmn_rate_limit( 'refresh_' . bmn_client_ip(), 60, HOUR_IN_SECONDS ) ) {
			return bmn_es_error( 'rate', __( 'درخواست‌ها زیاد است.', 'boshrouyeh-eservices' ), 429 );
		}
		$found = self::find_refresh( (string) $req['refresh'] );
		if ( is_wp_error( $found ) ) {
			return $found;
		}
		list( $uid, $idx ) = $found;
		$list   = get_user_meta( $uid, '_bmn_refresh', true );
		$device = $list[ $idx ]['dev'] ?? '';
		unset( $list[ $idx ] );
		update_user_meta( $uid, '_bmn_refresh', array_values( $list ) );
		if ( ! get_user_by( 'id', $uid ) ) {
			return bmn_es_error( 'refresh', __( 'حساب یافت نشد.', 'boshrouyeh-eservices' ), 401 );
		}
		return rest_ensure_response( self::issue_tokens( $uid, $device ) );
	}

	/**
	 * POST /auth/logout
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function logout( $req ) {
		if ( $req['refresh'] ) {
			$found = self::find_refresh( (string) $req['refresh'] );
			if ( ! is_wp_error( $found ) ) {
				$list = get_user_meta( $found[0], '_bmn_refresh', true );
				unset( $list[ $found[1] ] );
				update_user_meta( $found[0], '_bmn_refresh', array_values( $list ) );
			}
		}
		if ( is_user_logged_in() && ! self::bearer_header() ) {
			wp_logout();
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Revoke every app session of a user (e.g. phone lost).
	 *
	 * @param int $user_id User.
	 */
	public static function revoke_all( $user_id ) {
		update_user_meta( $user_id, '_bmn_tv', (int) get_user_meta( $user_id, '_bmn_tv', true ) + 1 );
		delete_user_meta( $user_id, '_bmn_refresh' );
	}

	/**
	 * Authorization: Bearer header value.
	 *
	 * @return string
	 */
	private static function bearer_header() {
		$h = '';
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$h = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$h = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}
		return preg_match( '/^Bearer\s+([A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+)$/', $h, $m ) ? $m[1] : '';
	}

	/**
	 * Is this a request to our REST namespace?
	 *
	 * @return bool
	 */
	private static function is_our_rest() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return str_contains( $uri, '/' . rest_get_url_prefix() . '/bmn/v1/' ) || str_contains( rawurldecode( $uri ), 'rest_route=/bmn/v1/' );
	}

	/**
	 * Resolve user from Bearer token (only for /bmn/v1/*).
	 *
	 * @param int|false $user_id Current.
	 * @return int|false
	 */
	public static function bearer_user( $user_id ) {
		if ( $user_id || ! self::is_our_rest() ) {
			return $user_id;
		}
		$token = self::bearer_header();
		if ( ! $token ) {
			return $user_id;
		}
		$claims = BMN_JWT::decode( $token );
		if ( is_wp_error( $claims ) || 'access' !== ( $claims['typ'] ?? '' ) ) {
			$GLOBALS['bmn_bearer_error'] = true;
			return $user_id;
		}
		$uid = (int) $claims['sub'];
		if ( ! get_user_by( 'id', $uid ) || (int) get_user_meta( $uid, '_bmn_tv', true ) !== (int) ( $claims['tv'] ?? -1 ) ) {
			$GLOBALS['bmn_bearer_error'] = true;
			return $user_id;
		}
		return $uid;
	}

	/**
	 * Report invalid tokens explicitly so the app can refresh.
	 *
	 * @param mixed $result Result.
	 * @return mixed
	 */
	public static function bearer_errors( $result ) {
		if ( ! empty( $GLOBALS['bmn_bearer_error'] ) && self::is_our_rest() ) {
			return bmn_es_error( 'token_invalid', __( 'نشست منقضی شده است.', 'boshrouyeh-eservices' ), 401 );
		}
		return $result;
	}

	/**
	 * Profile DTO.
	 *
	 * @param int $uid User.
	 * @return array
	 */
	public static function profile( $uid ) {
		$u     = get_user_by( 'id', $uid );
		$phone = (string) get_user_meta( $uid, '_bmn_phone', true );
		return array(
			'id'           => $uid,
			'name'         => $u ? $u->display_name : '',
			'phone'        => $phone,
			'neighborhood' => (string) get_user_meta( $uid, '_bmn_neighborhood', true ),
		);
	}

	/**
	 * GET /me
	 *
	 * @return WP_REST_Response
	 */
	public static function me() {
		$uid = get_current_user_id();
		return rest_ensure_response(
			self::profile( $uid ) + array(
				'stats' => array(
					'requests' => BMN_Requests::count_for_user( $uid, array( 'request', 'appointment' ) ),
					'reports'  => BMN_Requests::count_for_user( $uid, array( 'report137' ) ),
					'payments' => BMN_Payments::count_for_user( $uid ),
				),
			)
		);
	}

	/**
	 * POST /me
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_me( $req ) {
		$uid = get_current_user_id();
		if ( null !== $req['name'] && '' !== trim( (string) $req['name'] ) ) {
			wp_update_user( array( 'ID' => $uid, 'display_name' => mb_substr( trim( (string) $req['name'] ), 0, 60 ) ) );
		}
		if ( null !== $req['neighborhood'] ) {
			$slug = (string) $req['neighborhood'];
			if ( '' !== $slug && ! term_exists( $slug, 'bmn_neighborhood' ) ) {
				return bmn_es_error( 'hood', __( 'محله نامعتبر است.', 'boshrouyeh-eservices' ) );
			}
			update_user_meta( $uid, '_bmn_neighborhood', $slug );
		}
		return rest_ensure_response( self::profile( $uid ) );
	}

	/**
	 * Citizens never see wp-admin.
	 */
	public static function keep_citizens_out() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		$u = wp_get_current_user();
		if ( in_array( 'bmn_citizen', (array) $u->roles, true ) && ! user_can( $u, 'edit_posts' ) ) {
			$page = (int) bmn_option( 'page_dashboard', 0 );
			wp_safe_redirect( $page ? get_permalink( $page ) : home_url( '/' ) );
			exit;
		}
	}

	/**
	 * Hide admin bar for citizens.
	 *
	 * @param bool $show Show.
	 * @return bool
	 */
	public static function admin_bar( $show ) {
		$u = wp_get_current_user();
		return ( $u && in_array( 'bmn_citizen', (array) $u->roles, true ) ) ? false : $show;
	}
}
