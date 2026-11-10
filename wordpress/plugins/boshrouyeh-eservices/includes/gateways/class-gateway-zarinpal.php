<?php
/**
 * Zarinpal REST v4 gateway. Amounts are sent in Rials (Toman × 10).
 * Test against Zarinpal's sandbox before going live (set "zarinpal_sandbox").
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Zarinpal.
 */
final class BMN_Gateway_Zarinpal implements BMN_Gateway {

	/**
	 * Base host.
	 *
	 * @return string
	 */
	private function host() {
		return bmn_es_option( 'zarinpal_sandbox' ) ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
	}

	/**
	 * POST JSON.
	 *
	 * @param string $path Path.
	 * @param array  $body Body.
	 * @return array|WP_Error
	 */
	private function post( $path, $body ) {
		$res = wp_remote_post(
			$this->host() . $path,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return is_array( $json ) ? $json : new WP_Error( 'bmn_zp', 'bad response' );
	}

	/**
	 * Start.
	 *
	 * @param object $payment  Payment.
	 * @param string $callback Callback.
	 * @return array|WP_Error
	 */
	public function start( $payment, $callback ) {
		$r = $this->post(
			'/pg/v4/payment/request.json',
			array(
				'merchant_id'  => (string) bmn_es_option( 'zarinpal_merchant' ),
				'amount'       => (int) $payment->amount * 10,
				'currency'     => 'IRR',
				'callback_url' => $callback,
				'description'  => mb_substr( $payment->title, 0, 120 ),
				'metadata'     => array( 'mobile' => $payment->phone, 'order_id' => $payment->token ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( 100 !== (int) ( $r['data']['code'] ?? 0 ) || empty( $r['data']['authority'] ) ) {
			return new WP_Error( 'bmn_zp', 'request failed' );
		}
		$auth = (string) $r['data']['authority'];
		return array(
			'url'       => $this->host() . '/pg/StartPay/' . rawurlencode( $auth ),
			'method'    => 'GET',
			'fields'    => array(),
			'authority' => $auth,
		);
	}

	/**
	 * Verify.
	 *
	 * @param object $payment Payment.
	 * @param array  $input   Input (authority, status).
	 * @return array|WP_Error
	 */
	public function verify( $payment, $input ) {
		if ( 'OK' !== ( $input['status'] ?? '' ) || ! hash_equals( (string) $payment->authority, (string) ( $input['authority'] ?? '' ) ) ) {
			return array( 'ok' => false, 'bank_ref' => '', 'card' => '', 'message' => 'canceled' );
		}
		$r = $this->post(
			'/pg/v4/payment/verify.json',
			array(
				'merchant_id' => (string) bmn_es_option( 'zarinpal_merchant' ),
				'amount'      => (int) $payment->amount * 10,
				'authority'   => (string) $payment->authority,
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$code = (int) ( $r['data']['code'] ?? 0 );
		$ok   = in_array( $code, array( 100, 101 ), true );
		return array(
			'ok'       => $ok,
			'bank_ref' => $ok ? (string) ( $r['data']['ref_id'] ?? '' ) : '',
			'card'     => $ok ? (string) ( $r['data']['card_pan'] ?? '' ) : '',
			'message'  => (string) ( $r['data']['message'] ?? '' ),
		);
	}
}
