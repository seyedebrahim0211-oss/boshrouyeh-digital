<?php
/**
 * Strict, schema-driven layout sanitizer. Unknown widgets/fields are dropped; every value is
 * coerced to its declared type; limits stop oversized payloads.
 *
 * @package KheshtBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizer.
 */
final class KB_Sanitizer {

	const MAX_SECTIONS = 60;
	const MAX_COLUMNS  = 4;
	const MAX_WIDGETS  = 40; // Per column.
	const SPANS        = array( 3, 4, 6, 8, 9, 12 );

	/**
	 * Element id.
	 *
	 * @param mixed $id Id.
	 * @return string
	 */
	public static function id( $id ) {
		$id = is_string( $id ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( $id ) ) : '';
		return ( strlen( $id ) >= 4 && strlen( $id ) <= 16 ) ? $id : substr( md5( uniqid( '', true ) ), 0, 8 );
	}

	/**
	 * Sanitize a whole layout.
	 *
	 * @param array $in Raw layout.
	 * @return array
	 */
	public static function layout( $in ) {
		$out = array(
			'version'  => 1,
			'sections' => array(),
		);
		$sections = isset( $in['sections'] ) && is_array( $in['sections'] ) ? array_slice( $in['sections'], 0, self::MAX_SECTIONS ) : array();
		foreach ( $sections as $sec ) {
			if ( is_array( $sec ) ) {
				$out['sections'][] = self::section( $sec );
			}
		}
		return $out;
	}

	/**
	 * Section.
	 *
	 * @param array $sec Raw.
	 * @return array
	 */
	public static function section( $sec ) {
		$cols = isset( $sec['columns'] ) && is_array( $sec['columns'] ) ? array_slice( $sec['columns'], 0, self::MAX_COLUMNS ) : array();
		$out  = array(
			'id'       => self::id( $sec['id'] ?? '' ),
			'settings' => self::fields( KB_Widgets::section_fields(), $sec['settings'] ?? array() ),
			'columns'  => array(),
		);
		foreach ( $cols as $col ) {
			if ( ! is_array( $col ) ) {
				continue;
			}
			$span    = (int) ( $col['span'] ?? 12 );
			$widgets = array();
			foreach ( array_slice( (array) ( $col['widgets'] ?? array() ), 0, self::MAX_WIDGETS ) as $w ) {
				$clean = is_array( $w ) ? self::widget( $w ) : null;
				if ( $clean ) {
					$widgets[] = $clean;
				}
			}
			$out['columns'][] = array(
				'id'      => self::id( $col['id'] ?? '' ),
				'span'    => in_array( $span, self::SPANS, true ) ? $span : 12,
				'widgets' => $widgets,
			);
		}
		if ( ! $out['columns'] ) {
			$out['columns'][] = array( 'id' => self::id( '' ), 'span' => 12, 'widgets' => array() );
		}
		return $out;
	}

	/**
	 * Widget.
	 *
	 * @param array $w Raw.
	 * @return array|null
	 */
	public static function widget( $w ) {
		$all  = KB_Widgets::all();
		$type = is_string( $w['type'] ?? null ) ? $w['type'] : '';
		if ( ! isset( $all[ $type ] ) ) {
			return null;
		}
		$schema = $all[ $type ]['fields'] + KB_Widgets::common_fields();
		return array(
			'id'       => self::id( $w['id'] ?? '' ),
			'type'     => $type,
			'settings' => self::fields( $schema, $w['settings'] ?? array() ),
		);
	}

	/**
	 * Sanitize settings by schema (defaults fill gaps).
	 *
	 * @param array $schema Schema.
	 * @param mixed $in     Raw.
	 * @return array
	 */
	public static function fields( $schema, $in ) {
		$in  = is_array( $in ) ? $in : array();
		$out = array();
		foreach ( $schema as $key => $f ) {
			$out[ $key ] = array_key_exists( $key, $in ) ? self::value( $f, $in[ $key ] ) : self::value( $f, $f['default'] ?? null );
		}
		return $out;
	}

	/**
	 * Single value.
	 *
	 * @param array $f Field.
	 * @param mixed $v Raw value.
	 * @return mixed
	 */
	public static function value( $f, $v ) {
		switch ( $f['type'] ) {
			case 'text':
				return mb_substr( sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ), 0, 300 );
			case 'textarea':
				return mb_substr( sanitize_textarea_field( is_scalar( $v ) ? (string) $v : '' ), 0, 3000 );
			case 'richtext':
				return mb_substr( wp_kses( is_scalar( $v ) ? (string) $v : '', KB_Widgets::kses() ), 0, 20000 );
			case 'url':
				$v = is_scalar( $v ) ? trim( (string) $v ) : '';
				if ( '' === $v || '#' === $v ) {
					return $v;
				}
				if ( str_starts_with( $v, '/' ) && ! str_starts_with( $v, '//' ) ) {
					// Site-relative path; dangerous chars stripped, re-escaped with esc_url() on output.
					return mb_substr( '/' . ltrim( (string) preg_replace( '/[\s"\'<>\\\\^`{|}]/u', '', $v ), '/' ), 0, 500 );
				}
				return esc_url_raw( $v, array( 'http', 'https', 'mailto', 'tel' ) );
			case 'number':
				$n = is_numeric( $v ) ? (float) $v : (float) ( $f['default'] ?? 0 );
				if ( isset( $f['min'] ) ) {
					$n = max( (float) $f['min'], $n );
				}
				if ( isset( $f['max'] ) ) {
					$n = min( (float) $f['max'], $n );
				}
				return ( isset( $f['step'] ) && 'any' === $f['step'] ) ? $n : (int) round( $n );
			case 'toggle':
				return (bool) $v;
			case 'select':
			case 'icon':
				$v = is_scalar( $v ) ? (string) $v : '';
				return array_key_exists( $v, $f['options'] ) ? $v : (string) ( $f['default'] ?? array_key_first( $f['options'] ) );
			case 'image':
				$id = absint( $v );
				return ( $id && 'attachment' === get_post_type( $id ) ) ? $id : 0;
			case 'gallery':
				$ids = array_slice( array_filter( array_map( 'absint', is_array( $v ) ? $v : array() ) ), 0, 60 );
				return array_values( array_filter( $ids, fn( $id ) => 'attachment' === get_post_type( $id ) ) );
			case 'jdate':
				if ( is_numeric( $v ) ) {
					return max( 0, (int) $v );
				}
				return ( is_string( $v ) && class_exists( 'BMN_Jalali' ) ) ? BMN_Jalali::parse( $v ) : 0;
			case 'repeater':
				$rows = is_array( $v ) ? array_slice( array_values( $v ), 0, (int) ( $f['max'] ?? 20 ) ) : array();
				$out  = array();
				foreach ( $rows as $row ) {
					if ( is_array( $row ) ) {
						$out[] = self::fields( $f['fields'], $row );
					}
				}
				return $out;
		}
		return null;
	}
}
