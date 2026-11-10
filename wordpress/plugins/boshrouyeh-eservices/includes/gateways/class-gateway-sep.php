<?php
/**
 * Saman Electronic Payment (SEP) gateway — token flow + VerifyTransaction with amount check.
 * Amounts in Rials. Validate with the bank's current documentation before going live.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEP.
 */
final class BMN_Gateway_SEP implements BMN_Gateway {

	/**
	 * POST JSON.
	 *
	 * @param string $url  URL.
	 * @param array  $body Body.
	 * @return array|WP_Error
	 */
	private function post( $url, $body ) {
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return is_array( $json ) ? $json : new WP_Error( 'bmn_sep', 'bad response' );
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
			'https://sep.shaparak.ir/onlinepg/onlinepg',
			array(
				'action'      => 'token',
				'TerminalId'  => (string) bmn_es_option( 'sep_terminal' ),
				'Amount'      => (int) $payment->amount * 10,
				'ResNum'      => $payment->token,
				'RedirectUrl' => $callback,
				'CellNumber'  => $payment->phone,
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( 1 !== (int) ( $r['status'] ?? 0 ) || empty( $r['token'] ) ) {
			return new WP_Error( 'bmn_sep', 'token failed' );
		}
		return array(
			'url'       => 'https://sep.shaparak.ir/OnlinePG/OnlinePG',
			'method'    => 'POST',
			'fields'    => array(
				'Token'     => (string) $r['token'],
				'GetMethod' => 'true',
			),
			'authority' => (string) $r['token'],
		);
	}

	/**
	 * Verify.
	 *
	 * @param object $payment Payment.
	 * @param array  $input   Callback params (state, status, refnum, resnum, securepan).
	 * @return array|WP_Error
	 */
	public function verify( $payment, $input ) {
		if ( '2' !== (string) ( $input['status'] ?? '' ) || ! hash_equals( (string) $payment->token, (string) ( $input['resnum'] ?? '' ) ) || empty( $input['refnum'] ) ) {
			return array( 'ok' => false, 'bank_ref' => '', 'card' => '', 'message' => 'canceled' );
		}
		$r = $this->post(
			'https://sep.shaparak.ir/verifyTxnRandomSessionkey/ipg/VerifyTransaction',
			array(
				'RefNum'         => (string) $input['refnum'],
				'TerminalNumber' => (int) bmn_es_option( 'sep_terminal' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$amount = (int) ( $r['TransactionDetail']['OrginalAmount'] ?? 0 );
		$ok     = ! empty( $r['Success'] ) && 0 === (int) ( $r['ResultCode'] ?? -1 ) && (int) $payment->amount * 10 === $amount;
		return array(
			'ok'       => $ok,
			'bank_ref' => $ok ? (string) ( $r['TransactionDetail']['RRN'] ?? $input['refnum'] ) : '',
			'card'     => $ok ? (string) ( $r['TransactionDetail']['MaskedPan'] ?? ( $input['securepan'] ?? '' ) ) : '',
			'message'  => (string) ( $r['ResultDescription'] ?? '' ),
		);
	}
}
