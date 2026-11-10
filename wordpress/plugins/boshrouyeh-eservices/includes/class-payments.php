<?php
/**
 * Online payments: records, gateway abstraction (sandbox, Zarinpal, Saman/SEP), callback verification
 * (idempotent, amount-checked) and printable receipts.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gateway contract.
 */
interface BMN_Gateway {
	/**
	 * Start a payment.
	 *
	 * @param object $payment Row.
	 * @param string $callback Callback URL.
	 * @return array|WP_Error { url, method: GET|POST, fields: array, authority: string }
	 */
	public function start( $payment, $callback );

	/**
	 * Verify after callback.
	 *
	 * @param object $payment Row.
	 * @param array  $input   Sanitised callback params (GET+POST).
	 * @return array|WP_Error { ok: bool, bank_ref: string, card: string, message: string }
	 */
	public function verify( $payment, $input );
}

require_once BMN_ES_DIR . 'includes/gateways/class-gateway-sandbox.php';
require_once BMN_ES_DIR . 'includes/gateways/class-gateway-zarinpal.php';
require_once BMN_ES_DIR . 'includes/gateways/class-gateway-sep.php';

/**
 * Payments.
 */
final class BMN_Payments {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 1 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bmn_payments';
	}

	/**
	 * Rewrite: /bmn-pay/{action}/{token}
	 */
	public static function rewrite() {
		add_rewrite_rule( '^bmn-pay/(callback|receipt|sandbox|go)/([a-f0-9]{32})/?$', 'index.php?bmn_pay=$matches[1]&bmn_pay_token=$matches[2]', 'top' );
	}

	/**
	 * Query vars.
	 *
	 * @param array $v Vars.
	 * @return array
	 */
	public static function vars( $v ) {
		$v[] = 'bmn_pay';
		$v[] = 'bmn_pay_token';
		return $v;
	}

	/**
	 * Gateway instance.
	 *
	 * @param string $key Key.
	 * @return BMN_Gateway
	 */
	public static function gateway( $key = '' ) {
		$key = $key ? $key : (string) bmn_es_option( 'gateway', 'sandbox' );
		$map = apply_filters(
			'bmn_gateways',
			array(
				'sandbox'  => 'BMN_Gateway_Sandbox',
				'zarinpal' => 'BMN_Gateway_Zarinpal',
				'sep'      => 'BMN_Gateway_SEP',
			)
		);
		$cls = $map[ $key ] ?? 'BMN_Gateway_Sandbox';
		return new $cls();
	}

	/**
	 * Active gateway key.
	 *
	 * @return string
	 */
	public static function gateway_key() {
		$k = (string) bmn_es_option( 'gateway', 'sandbox' );
		return in_array( $k, array( 'sandbox', 'zarinpal', 'sep' ), true ) ? $k : 'sandbox';
	}

	/**
	 * Fetch by token.
	 *
	 * @param string $token Token.
	 * @return object|null
	 */
	public static function get( $token ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $token ) ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token = %s', $token ) );
	}

	/**
	 * Update a payment row.
	 *
	 * @param int   $id   Id.
	 * @param array $data Data.
	 */
	private static function update( $id, $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/payments/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'start' ),
				'permission_callback' => array( 'BMN_Auth', 'logged_in' ),
				'args'                => array(
					'bill_id' => array( 'type' => 'string', 'required' => true, 'maxLength' => 40 ),
					'return'  => array( 'type' => 'string', 'default' => 'web', 'enum' => array( 'web', 'app' ) ),
				),
			)
		);
		register_rest_route(
			'bmn/v1',
			'/me/payments',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'mine' ),
				'permission_callback' => array( 'BMN_Auth', 'logged_in' ),
			)
		);
		register_rest_route(
			'bmn/v1',
			'/payments/(?P<token>[a-f0-9]{32})',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'show' ),
				'permission_callback' => array( 'BMN_Auth', 'logged_in' ),
			)
		);
	}

	/**
	 * POST /payments/start
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function start( $req ) {
		global $wpdb;
		$uid  = get_current_user_id();
		$bill = BMN_Dues::bill( (string) $req['bill_id'] );
		if ( ! $bill || (int) $bill['user'] !== $uid ) {
			return bmn_es_error( 'bill', __( 'صورت‌حساب منقضی شده؛ دوباره استعلام بگیرید.', 'boshrouyeh-eservices' ), 404 );
		}
		if ( $bill['total'] < 1000 ) {
			return bmn_es_error( 'amount', __( 'مبلغی برای پرداخت وجود ندارد.', 'boshrouyeh-eservices' ) );
		}
		if ( ! bmn_rate_limit( 'paystart_' . $uid, 10, HOUR_IN_SECONDS ) ) {
			return bmn_es_error( 'rate', __( 'تلاش‌های پرداخت زیاد است؛ کمی بعد تلاش کنید.', 'boshrouyeh-eservices' ), 429 );
		}
		$token = bin2hex( random_bytes( 16 ) );
		$gw    = self::gateway_key();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::table(),
			array(
				'token'      => $token,
				'user_id'    => $uid,
				'phone'      => (string) get_user_meta( $uid, '_bmn_phone', true ),
				'kind'       => 'dues_' . $bill['type'],
				'ref'        => $bill['identifier'],
				'title'      => mb_substr( $bill['title'], 0, 190 ),
				'amount'     => (int) $bill['total'],
				'status'     => 'pending',
				'gateway'    => $gw,
				'meta'       => wp_json_encode( array( 'demo' => $bill['demo'], 'return' => $req['return'] ) ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$payment = self::get( $token );
		if ( ! $payment ) {
			return bmn_es_error( 'db', __( 'ثبت پرداخت ناموفق بود.', 'boshrouyeh-eservices' ), 500 );
		}
		BMN_Dues::consume( (string) $req['bill_id'] );
		$go = self::gateway( $gw )->start( $payment, home_url( '/bmn-pay/callback/' . $token . '/' ) );
		if ( is_wp_error( $go ) ) {
			self::update( $payment->id, array( 'status' => 'failed' ) );
			return bmn_es_error( 'gateway', __( 'اتصال به درگاه بانکی برقرار نشد؛ دوباره تلاش کنید.', 'boshrouyeh-eservices' ), 502 );
		}
		$meta       = json_decode( (string) $payment->meta, true );
		$meta['go'] = array(
			'url'    => (string) $go['url'],
			'method' => (string) $go['method'],
			'fields' => (array) $go['fields'],
		);
		self::update(
			$payment->id,
			array(
				'authority' => mb_substr( (string) $go['authority'], 0, 128 ),
				'meta'      => wp_json_encode( $meta ),
			)
		);
		return rest_ensure_response(
			array(
				'token'  => $token,
				'url'    => $go['url'],
				'method' => $go['method'],
				'fields' => $go['fields'],
				// Single GET entry point for apps / external browsers (handles POST gateways too).
				'go'     => home_url( '/bmn-pay/go/' . $token . '/' ),
			)
		);
	}

	/**
	 * Bridge to the bank: GET redirect or auto-submitting POST form.
	 *
	 * @param object $payment Row.
	 */
	private static function go( $payment ) {
		$meta = json_decode( (string) $payment->meta, true );
		$go   = $meta['go'] ?? null;
		if ( 'pending' !== $payment->status || ! is_array( $go ) || empty( $go['url'] ) ) {
			wp_safe_redirect( home_url( '/bmn-pay/receipt/' . $payment->token . '/' ) );
			exit;
		}
		$url = esc_url_raw( $go['url'], array( 'https', 'http' ) );
		if ( 'POST' !== $go['method'] ) {
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- bank gateway host.
			exit;
		}
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html__( 'انتقال به درگاه بانک…', 'boshrouyeh-eservices' ) . '</title></head><body style="font-family:Tahoma;text-align:center;padding:40px"><p>' . esc_html__( 'در حال انتقال به درگاه بانک…', 'boshrouyeh-eservices' ) . '</p><form id="f" method="post" action="' . esc_url( $url ) . '">';
		foreach ( (array) $go['fields'] as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( (string) $k ) . '" value="' . esc_attr( (string) $v ) . '">';
		}
		echo '<noscript><button type="submit">' . esc_html__( 'ادامه', 'boshrouyeh-eservices' ) . '</button></noscript></form><script>document.getElementById("f").submit();</script></body></html>';
		exit;
	}

	/**
	 * Front routes: callback, receipt, sandbox bank page.
	 */
	public static function route() {
		$action = get_query_var( 'bmn_pay' );
		if ( ! $action ) {
			return;
		}
		$payment = self::get( (string) get_query_var( 'bmn_pay_token' ) );
		if ( ! $payment ) {
			status_header( 404 );
			wp_die( esc_html__( 'پرداخت یافت نشد.', 'boshrouyeh-eservices' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		if ( 'callback' === $action ) {
			self::callback( $payment );
		} elseif ( 'sandbox' === $action ) {
			BMN_Gateway_Sandbox::render_bank( $payment );
		} elseif ( 'go' === $action ) {
			self::go( $payment );
		} else {
			self::receipt( $payment );
		}
		exit;
	}

	/**
	 * Gateway callback: verify once, then redirect to receipt (or app deep link).
	 *
	 * @param object $payment Row.
	 */
	private static function callback( $payment ) {
		// phpcs:disable WordPress.Security.NonceVerification -- bank callback; integrity checked by gateway verify / HMAC.
		$input = array();
		foreach ( array_merge( $_GET, $_POST ) as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$input[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( (string) $v ) );
			}
		}
		// phpcs:enable
		$lock = 'bmn_paylock_' . $payment->token;
		if ( 'pending' === $payment->status && ! get_transient( $lock ) ) {
			set_transient( $lock, 1, 60 );
			$result = self::gateway( $payment->gateway )->verify( $payment, $input );
			if ( ! is_wp_error( $result ) && ! empty( $result['ok'] ) ) {
				self::update(
					$payment->id,
					array(
						'status'   => 'paid',
						'bank_ref' => mb_substr( (string) $result['bank_ref'], 0, 64 ),
						'card'     => mb_substr( (string) $result['card'], 0, 32 ),
						'paid_at'  => current_time( 'mysql', true ),
					)
				);
				if ( $payment->phone ) {
					/* translators: 1: amount, 2: bank ref. */
					BMN_SMS::send( $payment->phone, sprintf( __( 'شهرداری بشرویه: پرداخت %1$s تومان با موفقیت انجام شد. کد رهگیری: %2$s', 'boshrouyeh-eservices' ), bmn_money( $payment->amount ), $result['bank_ref'] ) );
				}
				do_action( 'bmn_payment_paid', $payment->id );
			} else {
				self::update( $payment->id, array( 'status' => 'failed' ) );
			}
			delete_transient( $lock );
		}
		$meta = json_decode( (string) $payment->meta, true );
		if ( isset( $meta['return'] ) && 'app' === $meta['return'] ) {
			$fresh = self::get( $payment->token );
			wp_redirect( 'boshrouyeh://payment?token=' . rawurlencode( $payment->token ) . '&status=' . rawurlencode( $fresh ? $fresh->status : 'failed' ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- app deep link.
			exit;
		}
		wp_safe_redirect( home_url( '/bmn-pay/receipt/' . $payment->token . '/' ) );
		exit;
	}

	/**
	 * Payment DTO.
	 *
	 * @param object $p Row.
	 * @return array
	 */
	public static function dto( $p ) {
		$labels = array(
			'pending'  => __( 'در انتظار پرداخت', 'boshrouyeh-eservices' ),
			'paid'     => __( 'پرداخت موفق', 'boshrouyeh-eservices' ),
			'failed'   => __( 'ناموفق', 'boshrouyeh-eservices' ),
			'canceled' => __( 'لغو شده', 'boshrouyeh-eservices' ),
		);
		$ts     = strtotime( ( $p->paid_at ? $p->paid_at : $p->created_at ) . ' UTC' );
		$meta   = json_decode( (string) $p->meta, true );
		return array(
			'token'        => $p->token,
			'title'        => $p->title,
			'ref'          => $p->ref,
			'amount'       => (int) $p->amount,
			'amount_fa'    => bmn_money( (int) $p->amount ),
			'status'       => $p->status,
			'status_label' => $labels[ $p->status ] ?? $p->status,
			'bank_ref'     => $p->bank_ref,
			'card'         => $p->card,
			'date_fa'      => bmn_jdate( 'j F Y - H:i', $ts ),
			'receipt_url'  => home_url( '/bmn-pay/receipt/' . $p->token . '/' ),
			'demo'         => ! empty( $meta['demo'] ) || 'sandbox' === $p->gateway,
		);
	}

	/**
	 * GET /me/payments
	 *
	 * @return WP_REST_Response
	 */
	public static function mine() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE user_id = %d AND status <> 'pending' ORDER BY id DESC LIMIT 50", get_current_user_id() ) );
		return rest_ensure_response( array_map( array( __CLASS__, 'dto' ), (array) $rows ) );
	}

	/**
	 * GET /payments/{token} (owner only).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function show( $req ) {
		$p = self::get( (string) $req['token'] );
		if ( ! $p || ( (int) $p->user_id !== get_current_user_id() && ! current_user_can( 'bmn_view_payments' ) ) ) {
			return bmn_es_error( 'not_found', __( 'پرداخت یافت نشد.', 'boshrouyeh-eservices' ), 404 );
		}
		return rest_ensure_response( self::dto( $p ) );
	}

	/**
	 * Count paid payments for a user.
	 *
	 * @param int $uid User.
	 * @return int
	 */
	public static function count_for_user( $uid ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE user_id = %d AND status = 'paid'", $uid ) );
	}

	/**
	 * Printable receipt page (token is the capability; 128-bit random).
	 *
	 * @param object $payment Row.
	 */
	private static function receipt( $payment ) {
		$d       = self::dto( $payment );
		$ok      = 'paid' === $d['status'];
		$logo    = function_exists( 'khesht_logo' ) ? khesht_logo( 64 ) : '';
		$fonts   = get_theme_file_uri( 'assets/fonts/vazirmatn-arabic-400-normal.woff2' );
		$display = get_theme_file_uri( 'assets/fonts/lalezar-arabic-400-normal.woff2' );
		$dash    = (int) bmn_option( 'page_dashboard', 0 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		?>
<!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title><?php echo esc_html( $ok ? __( 'رسید پرداخت', 'boshrouyeh-eservices' ) : __( 'نتیجهٔ پرداخت', 'boshrouyeh-eservices' ) ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
<style>
@font-face{font-family:Vazirmatn;src:url(<?php echo esc_url( $fonts ); ?>) format('woff2')}@font-face{font-family:Lalezar;src:url(<?php echo esc_url( $display ); ?>) format('woff2')}
body{margin:0;background:#F6EEE1;font-family:Vazirmatn,Tahoma,sans-serif;color:#2A211C;padding:24px 16px}
.r{max-width:560px;margin:0 auto;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 14px 40px rgba(31,42,68,.12)}
.h{background:#6E3B23;color:#F6EEE1;padding:20px 24px;display:flex;gap:14px;align-items:center}.h svg{color:#E2C9A3}.h b{font:400 1.5rem Lalezar,Vazirmatn}.h small{display:block;opacity:.8}
.s{padding:20px 24px;text-align:center;border-bottom:1px dashed #EADBC4}.s .i{width:64px;height:64px;border-radius:50%;display:inline-grid;place-items:center;font-size:32px;color:#fff;background:<?php echo $ok ? '#16737A' : '#B42318'; ?>}
.s h1{font:400 1.6rem Lalezar,Vazirmatn;margin:.6rem 0 0;color:<?php echo $ok ? '#16737A' : '#B42318'; ?>}
dl{margin:0;padding:12px 24px 20px}dl div{display:flex;justify-content:space-between;gap:1rem;padding:.6rem 0;border-bottom:1px solid #F3E6D2}dt{color:#6B5A4E}dd{margin:0;font-weight:700;text-align:left}
.t{font-size:1.3rem;color:#6E3B23}.demo{margin:0 24px 16px;background:#FBF0D9;border-inline-start:4px solid #D9A441;padding:10px 12px;border-radius:10px;font-size:.85rem}
.a{display:flex;gap:10px;padding:0 24px 24px;flex-wrap:wrap}.a a,.a button{flex:1;min-width:140px;text-align:center;border:0;border-radius:10px;padding:12px;font:700 .95rem Vazirmatn,Tahoma;cursor:pointer;text-decoration:none}
.p{background:#16737A;color:#fff}.o{background:#F3E6D2;color:#6E3B23}
.chain{height:18px;background:radial-gradient(ellipse 7px 4px at 50% 40%,transparent 60%,#A0673F 62% 75%,transparent 78%) 0 0/36px 18px repeat-x,linear-gradient(#16737A,#16737A) 0 13px/100% 2px no-repeat}
@media print{body{background:#fff;padding:0}.a{display:none}.r{box-shadow:none}}
</style></head><body>
<main class="r" role="main">
	<div class="h"><?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG. ?><div><b><?php echo esc_html( get_bloginfo( 'name' ) ); ?></b><small><?php esc_html_e( 'رسید الکترونیکی پرداخت', 'boshrouyeh-eservices' ); ?></small></div></div>
	<div class="chain" aria-hidden="true"></div>
	<div class="s"><span class="i" aria-hidden="true"><?php echo $ok ? '✓' : '!'; ?></span><h1><?php echo esc_html( $d['status_label'] ); ?></h1></div>
	<dl>
		<div><dt><?php esc_html_e( 'بابت', 'boshrouyeh-eservices' ); ?></dt><dd><?php echo esc_html( $d['title'] ); ?></dd></div>
		<div><dt><?php esc_html_e( 'مبلغ', 'boshrouyeh-eservices' ); ?></dt><dd class="t"><?php echo esc_html( $d['amount_fa'] ); ?> <?php esc_html_e( 'تومان', 'boshrouyeh-eservices' ); ?></dd></div>
		<?php if ( $d['bank_ref'] ) : ?><div><dt><?php esc_html_e( 'کد رهگیری بانک', 'boshrouyeh-eservices' ); ?></dt><dd dir="ltr"><?php echo esc_html( bmn_fa_digits( $d['bank_ref'] ) ); ?></dd></div><?php endif; ?>
		<?php if ( $d['card'] ) : ?><div><dt><?php esc_html_e( 'کارت', 'boshrouyeh-eservices' ); ?></dt><dd dir="ltr"><?php echo esc_html( bmn_fa_digits( $d['card'] ) ); ?></dd></div><?php endif; ?>
		<div><dt><?php esc_html_e( 'تاریخ', 'boshrouyeh-eservices' ); ?></dt><dd><?php echo esc_html( $d['date_fa'] ); ?></dd></div>
		<div><dt><?php esc_html_e( 'شناسهٔ پرداخت', 'boshrouyeh-eservices' ); ?></dt><dd dir="ltr"><?php echo esc_html( strtoupper( substr( $d['token'], 0, 12 ) ) ); ?></dd></div>
	</dl>
	<?php if ( $d['demo'] ) : ?><p class="demo"><?php esc_html_e( 'این تراکنش در حالت آزمایشی انجام شده و پولی جابه‌جا نشده است.', 'boshrouyeh-eservices' ); ?></p><?php endif; ?>
	<div class="a">
		<?php if ( $ok ) : ?><button type="button" class="p" onclick="window.print()"><?php esc_html_e( 'چاپ / ذخیرهٔ PDF', 'boshrouyeh-eservices' ); ?></button><?php endif; ?>
		<a class="o" href="<?php echo esc_url( $dash ? get_permalink( $dash ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'بازگشت به پیشخوان', 'boshrouyeh-eservices' ); ?></a>
	</div>
</main>
</body></html>
		<?php
	}
}
