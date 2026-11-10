<?php
/**
 * Municipal dues inquiry (renovation, vehicle, business). The municipality's revenue system is
 * plugged in via the `bmn_dues_provider` filter; a clearly-labelled demo provider ships for testing.
 * Bills are kept server-side — the client never supplies an amount.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provider contract.
 */
interface BMN_Dues_Provider {
	/**
	 * Inquiry.
	 *
	 * @param string $type       renovation|vehicle|business.
	 * @param string $identifier Normalised identifier.
	 * @param int    $user_id    Citizen.
	 * @return array|WP_Error { title, subtitle, items: [ [label, amount] ], discount, total, demo }
	 */
	public function inquiry( $type, $identifier, $user_id );
}

/**
 * Demo provider — deterministic sample amounts, flagged demo=true and shown as such in the UI.
 */
final class BMN_Dues_Demo_Provider implements BMN_Dues_Provider {
	/**
	 * Inquiry.
	 *
	 * @param string $type       Type.
	 * @param string $identifier Identifier.
	 * @param int    $user_id    User.
	 * @return array
	 */
	public function inquiry( $type, $identifier, $user_id ) {
		$seed = abs( crc32( $type . $identifier ) );
		$year = bmn_jdate( 'Y' ); // Persian digits.
		$k    = 1000;
		switch ( $type ) {
			case 'vehicle':
				$items = array( array( "عوارض سالانهٔ خودرو {$year}", ( 180 + $seed % 220 ) * $k ) );
				$title = 'خودروی متعلق به کد ملی ' . bmn_fa_digits( substr( $identifier, 0, 3 ) . '****' . substr( $identifier, -3 ) );
				$sub   = "صورت‌حساب سال {$year}";
				break;
			case 'business':
				$items = array( array( "عوارض کسب و پیشه {$year}", ( 400 + $seed % 900 ) * $k ), array( 'عوارض تابلو', ( 60 + $seed % 90 ) * $k ) );
				$title = 'پروانهٔ کسب ' . bmn_fa_digits( $identifier );
				$sub   = "صورت‌حساب سال {$year}";
				break;
			default:
				$items = array( array( "عوارض نوسازی {$year}", ( 500 + $seed % 700 ) * $k ), array( 'عوارض سطح شهر', ( 250 + $seed % 300 ) * $k ) );
				$title = 'ملک مسکونی | کد ' . bmn_fa_digits( $identifier );
				$sub   = "صورت‌حساب سال {$year}";
		}
		$sum      = array_sum( array_column( $items, 1 ) );
		$discount = (int) ( round( $sum * 0.1 / 1000 ) * 1000 );
		return array(
			'title'    => $title,
			'subtitle' => $sub,
			'items'    => $items,
			'discount' => $discount,
			'discount_label' => 'تخفیف خوش‌حسابی',
			'total'    => $sum - $discount,
			'demo'     => true,
		);
	}
}

/**
 * Dues.
 */
final class BMN_Dues {

	const BILL_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Types.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'renovation' => array( __( 'عوارض نوسازی و سطح شهر', 'boshrouyeh-eservices' ), __( 'کد نوسازی یا شناسهٔ ملک', 'boshrouyeh-eservices' ), '12-04-0318-002' ),
			'vehicle'    => array( __( 'عوارض سالانهٔ خودرو', 'boshrouyeh-eservices' ), __( 'کد ملی مالک خودرو', 'boshrouyeh-eservices' ), '0012345679' ),
			'business'   => array( __( 'عوارض کسب و پیشه', 'boshrouyeh-eservices' ), __( 'شمارهٔ پروانهٔ کسب', 'boshrouyeh-eservices' ), '123456' ),
		);
	}

	/**
	 * Provider instance.
	 *
	 * @return BMN_Dues_Provider
	 */
	public static function provider() {
		$p = apply_filters( 'bmn_dues_provider', null );
		return $p instanceof BMN_Dues_Provider ? $p : new BMN_Dues_Demo_Provider();
	}

	/**
	 * Iranian national code checksum.
	 *
	 * @param string $code 10 digits.
	 * @return bool
	 */
	public static function valid_national_code( $code ) {
		if ( ! preg_match( '/^\d{10}$/', $code ) || preg_match( '/^(\d)\1{9}$/', $code ) ) {
			return false;
		}
		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $code[ $i ] * ( 10 - $i );
		}
		$r = $sum % 11;
		$c = (int) $code[9];
		return ( $r < 2 && $c === $r ) || ( $r >= 2 && $c === 11 - $r );
	}

	/**
	 * Normalise and validate identifier.
	 *
	 * @param string $type Type.
	 * @param string $raw  Raw.
	 * @return string|WP_Error
	 */
	public static function normalize( $type, $raw ) {
		$v = trim( bmn_en_digits( (string) $raw ) );
		switch ( $type ) {
			case 'vehicle':
				$v = preg_replace( '/\D/', '', $v );
				return self::valid_national_code( $v ) ? $v : bmn_es_error( 'identifier', __( 'کد ملی معتبر نیست.', 'boshrouyeh-eservices' ) );
			case 'business':
				$v = preg_replace( '/\D/', '', $v );
				return preg_match( '/^\d{4,12}$/', $v ) ? $v : bmn_es_error( 'identifier', __( 'شمارهٔ پروانه باید ۴ تا ۱۲ رقم باشد.', 'boshrouyeh-eservices' ) );
			default:
				$v = preg_replace( '/[\s_\/]+/', '-', $v );
				return preg_match( '/^\d{1,4}(-\d{1,5}){1,5}$/', $v ) || preg_match( '/^\d{6,20}$/', $v ) ? $v : bmn_es_error( 'identifier', __( 'کد نوسازی را مانند نمونه وارد کنید: ۱۲-۰۴-۰۳۱۸-۰۰۲', 'boshrouyeh-eservices' ) );
		}
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/dues/inquiry',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'inquiry' ),
				'permission_callback' => array( 'BMN_Auth', 'logged_in' ),
				'args'                => array(
					'type'       => array( 'type' => 'string', 'required' => true, 'enum' => array_keys( self::types() ) ),
					'identifier' => array( 'type' => 'string', 'required' => true, 'maxLength' => 40 ),
				),
			)
		);
	}

	/**
	 * POST /dues/inquiry
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function inquiry( $req ) {
		$uid = get_current_user_id();
		if ( ! bmn_rate_limit( 'dues_' . $uid, 30, HOUR_IN_SECONDS ) ) {
			return bmn_es_error( 'rate', __( 'تعداد استعلام‌ها زیاد است؛ کمی بعد تلاش کنید.', 'boshrouyeh-eservices' ), 429 );
		}
		$type = (string) $req['type'];
		$id   = self::normalize( $type, (string) $req['identifier'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$bill = self::provider()->inquiry( $type, $id, $uid );
		if ( is_wp_error( $bill ) ) {
			return $bill;
		}
		$bill_id = bin2hex( random_bytes( 12 ) );
		$record  = array(
			'user'       => $uid,
			'type'       => $type,
			'identifier' => $id,
			'title'      => self::types()[ $type ][0] . ' — ' . $bill['title'],
			'total'      => max( 0, (int) $bill['total'] ),
			'demo'       => ! empty( $bill['demo'] ),
		);
		set_transient( 'bmn_bill_' . $bill_id, $record, self::BILL_TTL );
		$res = rest_ensure_response(
			array(
				'bill_id'        => $bill_id,
				'type'           => $type,
				'title'          => $bill['title'],
				'subtitle'       => $bill['subtitle'] ?? '',
				'items'          => array_map(
					fn( $i ) => array(
						'label'     => (string) $i[0],
						'amount'    => (int) $i[1],
						'amount_fa' => bmn_money( (int) $i[1] ),
					),
					$bill['items']
				),
				'discount'       => (int) ( $bill['discount'] ?? 0 ),
				'discount_fa'    => bmn_money( (int) ( $bill['discount'] ?? 0 ) ),
				'discount_label' => (string) ( $bill['discount_label'] ?? '' ),
				'total'          => $record['total'],
				'total_fa'       => bmn_money( $record['total'] ),
				'currency'       => 'تومان',
				'demo'           => $record['demo'],
				'expires_in'     => self::BILL_TTL,
			)
		);
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/**
	 * Load a bill (for payments).
	 *
	 * @param string $bill_id Id.
	 * @return array|null
	 */
	public static function bill( $bill_id ) {
		if ( ! preg_match( '/^[a-f0-9]{24}$/', (string) $bill_id ) ) {
			return null;
		}
		$b = get_transient( 'bmn_bill_' . $bill_id );
		return is_array( $b ) ? $b : null;
	}

	/**
	 * Consume a bill after a payment record is created.
	 *
	 * @param string $bill_id Id.
	 */
	public static function consume( $bill_id ) {
		delete_transient( 'bmn_bill_' . $bill_id );
	}
}
