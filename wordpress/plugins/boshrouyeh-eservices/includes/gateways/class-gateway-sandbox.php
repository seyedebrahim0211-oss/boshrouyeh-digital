<?php
/**
 * Sandbox gateway — simulates a bank page for testing. Never moves money; clearly labelled.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sandbox.
 */
final class BMN_Gateway_Sandbox implements BMN_Gateway {

	/**
	 * HMAC binding the simulated result to the payment.
	 *
	 * @param object $p      Payment.
	 * @param string $result ok|fail.
	 * @return string
	 */
	private static function sig( $p, $result ) {
		return hash_hmac( 'sha256', $p->token . '|' . $p->amount . '|' . $result, wp_salt( 'nonce' ) );
	}

	/**
	 * Start.
	 *
	 * @param object $payment  Payment.
	 * @param string $callback Callback.
	 * @return array
	 */
	public function start( $payment, $callback ) {
		return array(
			'url'       => home_url( '/bmn-pay/sandbox/' . $payment->token . '/' ),
			'method'    => 'GET',
			'fields'    => array(),
			'authority' => 'SBX-' . strtoupper( substr( $payment->token, 0, 10 ) ),
		);
	}

	/**
	 * Verify.
	 *
	 * @param object $payment Payment.
	 * @param array  $input   Input.
	 * @return array
	 */
	public function verify( $payment, $input ) {
		$result = ( $input['result'] ?? '' ) === 'ok' ? 'ok' : 'fail';
		$ok     = 'ok' === $result && hash_equals( self::sig( $payment, 'ok' ), (string) ( $input['sig'] ?? '' ) );
		return array(
			'ok'       => $ok,
			'bank_ref' => $ok ? (string) random_int( 100000000, 999999999 ) : '',
			'card'     => $ok ? '6037-99**-****-1234' : '',
			'message'  => $ok ? 'OK' : 'canceled',
		);
	}

	/**
	 * Simulated bank page.
	 *
	 * @param object $p Payment.
	 */
	public static function render_bank( $p ) {
		if ( 'sandbox' !== $p->gateway || 'pending' !== $p->status ) {
			wp_safe_redirect( home_url( '/bmn-pay/receipt/' . $p->token . '/' ) );
			exit;
		}
		$cb = home_url( '/bmn-pay/callback/' . $p->token . '/' );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>درگاه آزمایشی</title>
<style>body{margin:0;font-family:Vazirmatn,Tahoma,sans-serif;background:#eef1f5;display:grid;place-items:center;min-height:100vh;padding:16px}.b{background:#fff;border-radius:16px;max-width:420px;width:100%;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.08)}.w{background:#FBF0D9;border:1px solid #D9A441;border-radius:10px;padding:10px;font-size:.85rem;margin-bottom:16px}h1{font-size:1.2rem}.m{font-size:1.6rem;font-weight:800;color:#16737A}form{display:inline}button{border:0;border-radius:10px;padding:12px 18px;font:700 1rem Vazirmatn,Tahoma;cursor:pointer;margin-top:12px}.ok{background:#16737A;color:#fff}.no{background:#eee;color:#333}</style></head>
<body><div class="b"><div class="w">درگاه آزمایشی — هیچ پولی جابه‌جا نمی‌شود. در محیط واقعی، درگاه بانکی را در تنظیمات فعال کنید.</div>
<h1><?php echo esc_html( $p->title ); ?></h1><p>مبلغ قابل پرداخت:</p><p class="m"><?php echo esc_html( bmn_money( $p->amount ) ); ?> تومان</p>
<form method="post" action="<?php echo esc_url( $cb ); ?>"><input type="hidden" name="result" value="ok"><input type="hidden" name="sig" value="<?php echo esc_attr( self::sig( $p, 'ok' ) ); ?>"><button class="ok" type="submit">پرداخت موفق (شبیه‌سازی)</button></form>
<form method="post" action="<?php echo esc_url( $cb ); ?>"><input type="hidden" name="result" value="fail"><button class="no" type="submit">انصراف</button></form>
</div></body></html>
		<?php
	}
}
