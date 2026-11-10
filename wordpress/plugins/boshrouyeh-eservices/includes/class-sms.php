<?php
/**
 * SMS gateway abstraction (Iranian providers). Drivers: log (development), kavenegar, smsir.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * SMS.
 */
final class BMN_SMS {

	/**
	 * Active driver key.
	 *
	 * @return string
	 */
	public static function driver() {
		$d = defined( 'BMN_SMS_DRIVER' ) ? (string) BMN_SMS_DRIVER : (string) bmn_es_option( 'sms_driver', 'log' );
		return in_array( $d, array( 'log', 'kavenegar', 'smsir' ), true ) ? $d : 'log';
	}

	/**
	 * Development mode = log driver while WP_DEBUG is on. Only then is the OTP echoed back.
	 *
	 * @return bool
	 */
	public static function is_dev() {
		return 'log' === self::driver() && defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Send an OTP using a provider "verify/lookup" template (fast, whitelisted by operators).
	 *
	 * @param string $phone Mobile (09xxxxxxxxx).
	 * @param string $code  Code.
	 * @return true|WP_Error
	 */
	public static function send_otp( $phone, $code ) {
		switch ( self::driver() ) {
			case 'kavenegar':
				$key = rawurlencode( (string) bmn_es_option( 'kavenegar_key' ) );
				$url = add_query_arg(
					array(
						'receptor' => $phone,
						'token'    => $code,
						'template' => (string) bmn_es_option( 'kavenegar_template', 'otp' ),
					),
					"https://api.kavenegar.com/v1/{$key}/verify/lookup.json"
				);
				return self::check( wp_remote_get( $url, array( 'timeout' => 10 ) ), 'kavenegar' );
			case 'smsir':
				return self::check(
					wp_remote_post(
						'https://api.sms.ir/v1/send/verify',
						array(
							'timeout' => 10,
							'headers' => array(
								'Content-Type' => 'application/json',
								'Accept'       => 'application/json',
								'x-api-key'    => (string) bmn_es_option( 'smsir_key' ),
							),
							'body'    => wp_json_encode(
								array(
									'mobile'     => $phone,
									'templateId' => (int) bmn_es_option( 'smsir_template' ),
									'parameters' => array( array( 'name' => 'CODE', 'value' => $code ) ),
								)
							),
						)
					),
					'smsir'
				);
			default:
				if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[bmn-sms] OTP for %s***%s generated (dev log driver).', substr( $phone, 0, 4 ), substr( $phone, -2 ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				return true;
		}
	}

	/**
	 * Send a plain notification message.
	 *
	 * @param string $phone   Mobile.
	 * @param string $message Text.
	 * @return true|WP_Error
	 */
	public static function send( $phone, $message ) {
		$phone = bmn_normalize_mobile( $phone );
		if ( ! $phone ) {
			return new WP_Error( 'bmn_sms_phone', 'invalid phone' );
		}
		switch ( self::driver() ) {
			case 'kavenegar':
				$key = rawurlencode( (string) bmn_es_option( 'kavenegar_key' ) );
				return self::check(
					wp_remote_post(
						"https://api.kavenegar.com/v1/{$key}/sms/send.json",
						array(
							'timeout' => 10,
							'body'    => array(
								'receptor' => $phone,
								'message'  => $message,
								'sender'   => (string) bmn_es_option( 'sms_sender' ),
							),
						)
					),
					'kavenegar'
				);
			case 'smsir':
				return self::check(
					wp_remote_post(
						'https://api.sms.ir/v1/send/bulk',
						array(
							'timeout' => 10,
							'headers' => array(
								'Content-Type' => 'application/json',
								'x-api-key'    => (string) bmn_es_option( 'smsir_key' ),
							),
							'body'    => wp_json_encode(
								array(
									'lineNumber'  => (string) bmn_es_option( 'sms_sender' ),
									'messageText' => $message,
									'mobiles'     => array( $phone ),
								)
							),
						)
					),
					'smsir'
				);
			default:
				do_action( 'bmn_sms_logged', $phone, $message );
				return true;
		}
	}

	/**
	 * Validate provider response.
	 *
	 * @param array|WP_Error $res      Response.
	 * @param string         $provider Provider.
	 * @return true|WP_Error
	 */
	private static function check( $res, $provider ) {
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$ok   = 'kavenegar' === $provider
			? ( 200 === $code && isset( $body['return']['status'] ) && 200 === (int) $body['return']['status'] )
			: ( 200 === $code && isset( $body['status'] ) && 1 === (int) $body['status'] );
		return $ok ? true : new WP_Error( 'bmn_sms_failed', 'SMS provider error (' . $provider . ' ' . $code . ')' );
	}
}
