<?php
/**
 * Global helpers (namespaced with bmn_ prefix; shared by the theme and sibling plugins).
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Convert Latin/Arabic digits to Persian digits.
 *
 * @param string|int|float $text Text.
 * @return string
 */
function bmn_fa_digits( $text ) {
	return strtr(
		(string) $text,
		array(
			'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
			'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
			'٠' => '۰', '١' => '۱', '٢' => '۲', '٣' => '۳', '٤' => '۴',
			'٥' => '۵', '٦' => '۶', '٧' => '۷', '٨' => '۸', '٩' => '۹',
		)
	);
}

/**
 * Convert Persian/Arabic digits to Latin (for parsing user input).
 *
 * @param string $text Text.
 * @return string
 */
function bmn_en_digits( $text ) {
	return strtr(
		(string) $text,
		array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		)
	);
}

/**
 * Format an amount in Toman with Persian digits and separators.
 *
 * @param int|float $amount Amount.
 * @return string
 */
function bmn_money( $amount ) {
	return bmn_fa_digits( number_format( (float) $amount, 0, '.', '٬' ) );
}

/**
 * Read a plugin option (settings page).
 *
 * @param string $key     Key.
 * @param mixed  $default Default.
 * @return mixed
 */
function bmn_option( $key, $default = '' ) {
	$opts = get_option( 'bmn_settings', array() );
	return ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) ? $opts[ $key ] : $default;
}

/**
 * Inline SVG icon (same set as the theme).
 *
 * @param string $name Icon key.
 * @param int    $size Size.
 * @param string $label Optional accessible label.
 * @return string
 */
function bmn_icon( $name, $size = 24, $label = '' ) {
	if ( function_exists( 'khesht_icon' ) ) {
		return khesht_icon( $name, array( 'size' => $size, 'label' => $label ) );
	}
	static $set = null;
	if ( null === $set ) {
		$set = require BMN_CORE_DIR . 'includes/icon-set.php';
	}
	$name = isset( $set[ $name ] ) ? $name : 'grid';
	$a11y = $label ? 'role="img" aria-label="' . esc_attr( $label ) . '"' : 'aria-hidden="true" focusable="false"';
	return sprintf(
		'<svg class="kh-icon kh-icon-%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" %3$s>%4$s</svg>',
		esc_attr( $name ),
		absint( $size ),
		$a11y,
		$set[ $name ]
	);
}

/**
 * Icon keys.
 *
 * @return string[]
 */
function bmn_icon_names() {
	$set = require BMN_CORE_DIR . 'includes/icon-set.php';
	return array_keys( $set );
}

/**
 * Typed meta getter.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key without prefix.
 * @param mixed  $default Default.
 * @return mixed
 */
function bmn_meta( $post_id, $key, $default = '' ) {
	$val = get_post_meta( $post_id, '_bmn_' . $key, true );
	return ( '' === $val || null === $val || false === $val ) ? $default : $val;
}

/**
 * Split a multiline meta value into trimmed non-empty lines.
 *
 * @param string $text Text.
 * @return string[]
 */
function bmn_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ) ) );
}

/**
 * Normalise Iranian mobile number to 09xxxxxxxxx, or '' if invalid.
 *
 * @param string $phone Raw input.
 * @return string
 */
function bmn_normalize_mobile( $phone ) {
	$p = preg_replace( '/\D+/', '', bmn_en_digits( (string) $phone ) );
	if ( str_starts_with( $p, '0098' ) ) {
		$p = '0' . substr( $p, 4 );
	} elseif ( str_starts_with( $p, '98' ) && strlen( $p ) === 12 ) {
		$p = '0' . substr( $p, 2 );
	} elseif ( str_starts_with( $p, '9' ) && strlen( $p ) === 10 ) {
		$p = '0' . $p;
	}
	return preg_match( '/^09\d{9}$/', $p ) ? $p : '';
}

/**
 * Client IP (REMOTE_ADDR only — proxies must be configured at the server level to avoid spoofing).
 *
 * @return string
 */
function bmn_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	/**
	 * Filter the detected client IP (e.g. behind a trusted reverse proxy).
	 *
	 * @param string $ip IP.
	 */
	$ip = (string) apply_filters( 'bmn_client_ip', $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Simple fixed-window rate limiter backed by transients.
 *
 * @param string $bucket Bucket name.
 * @param int    $limit  Max hits per window.
 * @param int    $window Window in seconds.
 * @return bool True if allowed (and counted), false if limited.
 */
function bmn_rate_limit( $bucket, $limit, $window ) {
	$key  = 'bmn_rl_' . md5( $bucket );
	$data = get_transient( $key );
	$now  = time();
	if ( ! is_array( $data ) || $data['reset'] <= $now ) {
		$data = array(
			'count' => 0,
			'reset' => $now + $window,
		);
	}
	if ( $data['count'] >= $limit ) {
		return false;
	}
	++$data['count'];
	set_transient( $key, $data, max( 1, $data['reset'] - $now ) );
	return true;
}

/**
 * Service mode labels.
 *
 * @return array<string,string>
 */
function bmn_service_modes() {
	return array(
		'online' => __( 'آنلاین', 'boshrouyeh-core' ),
		'semi'   => __( 'نیمه‌حضوری', 'boshrouyeh-core' ),
		'onsite' => __( 'حضوری', 'boshrouyeh-core' ),
	);
}

/**
 * Persian weekday names, Saturday-first.
 *
 * @return string[]
 */
function bmn_weekdays() {
	return array( 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه' );
}

/**
 * Brand illustration (fallback when a post has no featured image).
 *
 * @param int $post_id Post id.
 * @return string URL.
 */
function bmn_illustration_url( $post_id ) {
	$all  = array( 'badgir-house', 'mosque', 'fortress', 'watermill', 'ab-anbar', 'house', 'bridge', 'garden', 'desert', 'village', 'caravanserai', 'museum', 'news-city', 'night' );
	$name = (string) get_post_meta( $post_id, '_bmn_illustration', true );
	if ( ! in_array( $name, $all, true ) ) {
		$name = $all[ absint( crc32( (string) $post_id ) ) % count( $all ) ];
	}
	return BMN_CORE_URL . 'assets/illustrations/' . $name . '.svg';
}

/**
 * Featured image HTML or illustration fallback.
 *
 * @param int    $post_id Post id.
 * @param string $size    Size.
 * @return string
 */
function bmn_thumb( $post_id, $size = 'khesht-card' ) {
	$img = get_the_post_thumbnail( $post_id, $size, array( 'loading' => 'lazy' ) );
	if ( $img ) {
		return $img;
	}
	return '<img src="' . esc_url( bmn_illustration_url( $post_id ) ) . '" alt="" loading="lazy" width="400" height="300" class="bmn-illustration">';
}

add_filter( 'render_block_core/post-featured-image', 'bmn_featured_image_fallback', 10, 3 );
/**
 * Fill empty featured-image blocks with a brand illustration.
 *
 * @param string   $html     HTML.
 * @param array    $block    Block.
 * @param WP_Block $instance Instance.
 * @return string
 */
function bmn_featured_image_fallback( $html, $block, $instance ) {
	if ( '' !== trim( $html ) ) {
		return $html;
	}
	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	$types = array( 'post', 'bmn_place', 'bmn_event', 'bmn_notice', 'bmn_tender' );
	if ( ! $post_id || ! in_array( get_post_type( $post_id ), $types, true ) || ! apply_filters( 'bmn_use_illustration_fallback', true, $post_id ) ) {
		return $html;
	}
	$ratio = ! empty( $block['attrs']['aspectRatio'] ) ? 'aspect-ratio:' . esc_attr( $block['attrs']['aspectRatio'] ) . ';' : '';
	$img   = '<img src="' . esc_url( bmn_illustration_url( $post_id ) ) . '" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;' . $ratio . '">';
	if ( ! empty( $block['attrs']['isLink'] ) ) {
		$img = '<a href="' . esc_url( (string) get_permalink( $post_id ) ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
	}
	$class = 'wp-block-post-featured-image bmn-illustration-wrap' . ( ! empty( $block['attrs']['className'] ) ? ' ' . esc_attr( $block['attrs']['className'] ) : '' );
	return '<figure class="' . $class . '" style="' . $ratio . '">' . $img . '</figure>';
}

/**
 * REST-safe slug sanitizer (sanitize_title's 2nd arg is a fallback, which REST would fill with the request).
 *
 * @param mixed $value Value.
 * @return string
 */
function bmn_sanitize_slug( $value ) {
	return sanitize_title( is_scalar( $value ) ? (string) $value : '' );
}
