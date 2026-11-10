<?php
/**
 * Front end: content replacement, assets, and the editor canvas document.
 *
 * @package KheshtBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front.
 */
final class KB_Front {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'content' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'canvas' ), 0 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Is this the canvas request (verified)?
	 *
	 * @return int Post id or 0.
	 */
	public static function canvas_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
		if ( empty( $_GET['kb_canvas'] ) || empty( $_GET['kb_post'] ) ) {
			return 0;
		}
		$id    = absint( $_GET['kb_post'] );
		$nonce = isset( $_GET['kb_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['kb_nonce'] ) ) : '';
		// phpcs:enable
		return ( $id && wp_verify_nonce( $nonce, 'kb_canvas_' . $id ) && current_user_can( 'edit_post', $id ) ) ? $id : 0;
	}

	/**
	 * Replace content of builder-enabled posts with the live render.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function content( $content ) {
		$id = (int) get_the_ID();
		if ( ! $id || ! kb_enabled( $id ) || post_password_required( $id ) ) {
			return $content;
		}
		if ( is_singular() && (int) get_queried_object_id() !== $id ) {
			return $content;
		}
		static $depth = 0;
		if ( $depth > 0 ) {
			return $content;
		}
		++$depth;
		KB_Render::$editing = false;
		$html = KB_Render::layout( kb_get_layout( $id ) );
		--$depth;
		return $html;
	}

	/**
	 * Assets.
	 */
	public static function assets() {
		$canvas = self::canvas_post();
		if ( ! $canvas && ! ( is_singular() && kb_enabled( (int) get_queried_object_id() ) ) ) {
			return;
		}
		wp_enqueue_style( 'kb-front', KB_URL . 'assets/front/kb.css', array(), KB_VERSION );
		wp_enqueue_script( 'kb-front', KB_URL . 'assets/front/kb.js', array(), KB_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		// E-service styles are enqueued on demand during render; make sure they exist in the canvas and on builder pages.
		foreach ( array( 'bmn-es', 'bmn-leaflet' ) as $h ) {
			if ( wp_style_is( $h, 'registered' ) ) {
				wp_enqueue_style( $h );
			}
		}
		if ( $canvas ) {
			wp_enqueue_style( 'kb-canvas', KB_URL . 'assets/editor/canvas.css', array( 'kb-front' ), KB_VERSION );
		}
	}

	/**
	 * Body class.
	 *
	 * @param string[] $c Classes.
	 * @return string[]
	 */
	public static function body_class( $c ) {
		if ( is_singular() && kb_enabled( (int) get_queried_object_id() ) ) {
			$c[] = 'kb-built';
		}
		return $c;
	}

	/**
	 * Editor canvas document (same origin; sections are injected by the editor).
	 */
	public static function canvas() {
		$id = self::canvas_post();
		if ( ! $id ) {
			return;
		}
		show_admin_bar( false );
		nocache_headers();
		header( 'X-Frame-Options: SAMEORIGIN' );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
		<?php wp_head(); ?>
</head>
<body <?php body_class( 'kb-canvas khesht' ); ?>>
<main id="kb-root" class="kb-page" aria-label="canvas"></main>
		<?php wp_footer(); ?>
</body>
</html>
		<?php
		exit;
	}
}
