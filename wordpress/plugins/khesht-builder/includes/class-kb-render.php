<?php
/**
 * Layout → HTML renderer (single source of truth for editor preview and front end).
 *
 * @package KheshtBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renderer.
 */
final class KB_Render {

	/**
	 * Editor preview mode adds data-kb-* hooks.
	 *
	 * @var bool
	 */
	public static $editing = false;

	/**
	 * Render a full layout.
	 *
	 * @param array $layout Sanitized layout.
	 * @return string
	 */
	public static function layout( $layout ) {
		$h = '';
		foreach ( $layout['sections'] as $sec ) {
			$h .= self::section( $sec );
		}
		return '<div class="kb-page">' . $h . '</div>';
	}

	/**
	 * Divider markup.
	 *
	 * @param string $kind Kind.
	 * @param string $pos  top|bottom.
	 * @return string
	 */
	private static function divider( $kind, $pos ) {
		return 'none' === $kind ? '' : '<div class="kb-sdiv kb-sdiv--' . esc_attr( $kind ) . ' kb-sdiv--' . esc_attr( $pos ) . '" aria-hidden="true"></div>';
	}

	/**
	 * Render a section.
	 *
	 * @param array $sec Sanitized section.
	 * @return string
	 */
	public static function section( $sec ) {
		$s     = $sec['settings'];
		$cls   = array( 'kb-section', 'kb-bg-' . $s['bg'], 'kb-pad-' . $s['pad'], 'kb-w-' . $s['width'] );
		$style = '';
		if ( $s['round'] ) {
			$cls[] = 'kb-round';
		}
		if ( $s['hide'] ) {
			$cls[] = 'kb-hide-' . $s['hide'];
		}
		if ( in_array( $s['bg'], array( 'night', 'qanat', 'brick' ), true ) || ( 'image' === $s['bg'] && $s['overlay'] ) ) {
			$cls[] = 'kb-dark';
		}
		if ( 'image' === $s['bg'] && $s['image'] ) {
			$url   = wp_get_attachment_image_url( (int) $s['image'], 'full' );
			$style = $url ? ' style="--kb-img:url(' . esc_url( $url ) . ')"' : '';
			if ( $s['overlay'] ) {
				$cls[] = 'kb-overlay';
			}
		}
		$attrs  = $s['anchor'] ? ' id="' . esc_attr( sanitize_title( $s['anchor'] ) ) . '"' : '';
		$attrs .= self::$editing ? ' data-kb-section="' . esc_attr( $sec['id'] ) . '"' : '';
		$h      = '<section class="' . esc_attr( implode( ' ', $cls ) ) . '"' . $attrs . $style . '>' . self::divider( $s['top'], 'top' );
		$h     .= '<div class="kb-section__inner"><div class="kb-row kb-gap-' . esc_attr( $s['gap'] ) . ' kb-valign-' . esc_attr( $s['valign'] ) . '">';
		foreach ( $sec['columns'] as $col ) {
			$h .= '<div class="kb-col kb-span-' . (int) $col['span'] . '"' . ( self::$editing ? ' data-kb-col="' . esc_attr( $col['id'] ) . '"' : '' ) . '>';
			foreach ( $col['widgets'] as $w ) {
				$h .= self::widget( $w );
			}
			if ( self::$editing && ! $col['widgets'] ) {
				$h .= '<div class="kb-empty-col" data-kb-empty>ویجت را اینجا رها کنید</div>';
			}
			$h .= '</div>';
		}
		return $h . '</div></div>' . self::divider( $s['bottom'], 'bottom' ) . '</section>';
	}

	/**
	 * Render a widget.
	 *
	 * @param array $w Sanitized widget.
	 * @return string
	 */
	public static function widget( $w ) {
		$all = KB_Widgets::all();
		if ( ! isset( $all[ $w['type'] ] ) ) {
			return '';
		}
		$s     = $w['settings'];
		$inner = (string) call_user_func( $all[ $w['type'] ]['render'], $s, $w['type'] );
		if ( '' === $inner && ! self::$editing ) {
			return '';
		}
		$cls = array( 'kb-widget', 'kb-wt-' . $w['type'], 'kb-align-' . $s['_align'], 'kb-space-' . $s['_space'] );
		if ( 'none' !== $s['_anim'] ) {
			$cls[] = 'kb-anim kb-anim-' . $s['_anim'];
		}
		if ( $s['_hide'] ) {
			$cls[] = 'kb-hide-' . $s['_hide'];
		}
		foreach ( preg_split( '/\s+/', (string) $s['_class'] ) as $c ) {
			if ( '' !== $c ) {
				$cls[] = sanitize_html_class( $c );
			}
		}
		$attrs  = $s['_id'] ? ' id="' . esc_attr( sanitize_title( $s['_id'] ) ) . '"' : '';
		$attrs .= self::$editing ? ' data-kb-widget="' . esc_attr( $w['id'] ) . '" draggable="true"' : '';
		return '<div class="' . esc_attr( implode( ' ', $cls ) ) . '"' . $attrs . '>' . $inner . '</div>';
	}
}
