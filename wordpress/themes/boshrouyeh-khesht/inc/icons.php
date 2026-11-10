<?php
/**
 * Icon helper.
 *
 * @package BoshrouyehKhesht
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return a safe inline SVG icon from the Khesht set.
 *
 * @param string $name  Icon key.
 * @param array  $args  { size:int, class:string, label:string }.
 * @return string
 */
function khesht_icon( $name, $args = array() ) {
	static $set = null;
	if ( null === $set ) {
		$set = require __DIR__ . '/icon-set.php';
	}
	$name = sanitize_key( $name );
	if ( ! isset( $set[ $name ] ) ) {
		$name = 'grid';
	}
	$size  = isset( $args['size'] ) ? absint( $args['size'] ) : 24;
	$class = 'kh-icon kh-icon-' . $name . ( empty( $args['class'] ) ? '' : ' ' . sanitize_html_class( $args['class'] ) );
	$a11y  = empty( $args['label'] )
		? 'aria-hidden="true" focusable="false"'
		: 'role="img" aria-label="' . esc_attr( $args['label'] ) . '"';

	return sprintf(
		'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" %3$s>%4$s</svg>',
		esc_attr( $class ),
		$size,
		$a11y,
		$set[ $name ] // Static, trusted markup from icon-set.php.
	);
}

/**
 * List available icon keys.
 *
 * @return string[]
 */
function khesht_icon_names() {
	$set = require __DIR__ . '/icon-set.php';
	return array_keys( $set );
}
