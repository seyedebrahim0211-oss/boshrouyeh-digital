<?php
/**
 * Template helpers used inside PHP patterns.
 *
 * @package BoshrouyehKhesht
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asset URL inside the theme.
 *
 * @param string $path Relative path.
 * @return string
 */
function khesht_asset( $path ) {
	return esc_url( KHESHT_URI . '/assets/' . ltrim( $path, '/' ) );
}

/**
 * Inline logo SVG (uses currentColor so it follows the surface colour).
 *
 * @param int    $size  Pixel size.
 * @param string $label Accessible label.
 * @return string
 */
function khesht_logo( $size = 56, $label = '' ) {
	static $svg = null;
	if ( null === $svg ) {
		$svg = (string) file_get_contents( KHESHT_DIR . '/assets/svg/logo.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
	}
	$label = $label ? $label : __( 'نشان شهرداری بشرویه', 'boshrouyeh-khesht' );
	$out   = preg_replace(
		'/<svg /',
		sprintf( '<svg width="%1$d" height="%1$d" class="kh-logo" ', absint( $size ) ),
		$svg,
		1
	);
	return (string) preg_replace( '/aria-label="[^"]*"/', 'aria-label="' . esc_attr( $label ) . '"', (string) $out, 1 );
}

/**
 * Jalali "today" string — delegates to the core plugin when present.
 *
 * @return string
 */
function khesht_today() {
	if ( function_exists( 'bmn_jdate' ) ) {
		return bmn_jdate( 'l j F Y' );
	}
	return wp_date( get_option( 'date_format' ) );
}

/**
 * Site option with fallback (set from the core plugin settings page).
 *
 * @param string $key     Key.
 * @param string $default Default.
 * @return string
 */
function khesht_opt( $key, $default = '' ) {
	if ( function_exists( 'bmn_option' ) ) {
		$val = bmn_option( $key );
		if ( '' !== $val && null !== $val ) {
			return (string) $val;
		}
	}
	return $default;
}

/**
 * Persian digits.
 *
 * @param string|int $text Text.
 * @return string
 */
function khesht_fa( $text ) {
	if ( function_exists( 'bmn_fa_digits' ) ) {
		return bmn_fa_digits( (string) $text );
	}
	return strtr( (string) $text, array_combine( range( 0, 9 ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ) ) );
}

/**
 * Permalink of a page by its stored option id, else by path.
 *
 * @param string $key  Option key in core settings (e.g. page_dashboard).
 * @param string $path Fallback slug.
 * @return string
 */
function khesht_page_url( $key, $path ) {
	$id = (int) khesht_opt( $key, '0' );
	if ( $id && get_post_status( $id ) === 'publish' ) {
		return (string) get_permalink( $id );
	}
	return home_url( '/' . trim( $path, '/' ) . '/' );
}
