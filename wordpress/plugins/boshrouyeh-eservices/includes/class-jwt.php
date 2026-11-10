<?php
/**
 * Minimal HS256 JWT (no external dependency). Constant-time signature check, exp/nbf/iss enforced.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * JWT.
 */
final class BMN_JWT {

	/**
	 * Secret: constant BMN_JWT_SECRET (recommended, in wp-config) or generated option.
	 *
	 * @return string
	 */
	private static function secret() {
		if ( defined( 'BMN_JWT_SECRET' ) && strlen( (string) BMN_JWT_SECRET ) >= 32 ) {
			return (string) BMN_JWT_SECRET;
		}
		$s = (string) get_option( 'bmn_es_jwt_secret' );
		if ( strlen( $s ) < 32 ) {
			$s = bin2hex( random_bytes( 32 ) );
			update_option( 'bmn_es_jwt_secret', $s, false );
		}
		return $s . wp_salt( 'auth' );
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	private static function b64( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Base64url decode.
	 *
	 * @param string $data Data.
	 * @return string|false
	 */
	private static function unb64( $data ) {
		$pad = strlen( $data ) % 4;
		if ( $pad ) {
			$data .= str_repeat( '=', 4 - $pad );
		}
		return base64_decode( strtr( $data, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Encode a payload.
	 *
	 * @param array $claims Claims.
	 * @param int   $ttl    Seconds.
	 * @return string
	 */
	public static function encode( $claims, $ttl ) {
		$now     = time();
		$payload = array_merge(
			array(
				'iss' => home_url( '/' ),
				'iat' => $now,
				'nbf' => $now - 5,
				'exp' => $now + (int) $ttl,
			),
			$claims
		);
		$head    = self::b64( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) );
		$body    = self::b64( wp_json_encode( $payload ) );
		$sig     = self::b64( hash_hmac( 'sha256', $head . '.' . $body, self::secret(), true ) );
		return $head . '.' . $body . '.' . $sig;
	}

	/**
	 * Decode & verify.
	 *
	 * @param string $token Token.
	 * @return array|WP_Error Claims.
	 */
	public static function decode( $token ) {
		$parts = explode( '.', (string) $token );
		if ( 3 !== count( $parts ) ) {
			return new WP_Error( 'bmn_jwt', 'malformed' );
		}
		list( $h, $b, $s ) = $parts;
		$header = json_decode( (string) self::unb64( $h ), true );
		if ( ! is_array( $header ) || ( $header['alg'] ?? '' ) !== 'HS256' ) {
			return new WP_Error( 'bmn_jwt', 'alg' );
		}
		$expected = self::b64( hash_hmac( 'sha256', $h . '.' . $b, self::secret(), true ) );
		if ( ! hash_equals( $expected, $s ) ) {
			return new WP_Error( 'bmn_jwt', 'signature' );
		}
		$claims = json_decode( (string) self::unb64( $b ), true );
		$now    = time();
		if ( ! is_array( $claims ) || ( $claims['exp'] ?? 0 ) < $now || ( $claims['nbf'] ?? 0 ) > $now + 5 || ( $claims['iss'] ?? '' ) !== home_url( '/' ) ) {
			return new WP_Error( 'bmn_jwt', 'expired' );
		}
		return $claims;
	}
}
