<?php
/**
 * Builder REST API (namespace kb/v1). Every route requires edit rights on the target post;
 * cookie auth + X-WP-Nonce (CSRF) is enforced by WordPress core for logged-in users.
 *
 * @package KheshtBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST.
 */
final class KB_Rest {

	const MAX_BYTES = 1048576;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Permission: can edit the post.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return bool
	 */
	public static function can_edit( $req ) {
		$id = (int) ( $req['id'] ?? $req['post_id'] ?? 0 );
		$p  = get_post( $id );
		return $p && in_array( $p->post_type, kb_post_types(), true ) && current_user_can( 'edit_post', $id );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		$id = array( 'id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ) );
		register_rest_route(
			'kb/v1',
			'/layout/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_layout' ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
					'args'                => $id,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_layout' ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
					'args'                => $id + array(
						'status' => array( 'type' => 'string', 'default' => 'keep', 'enum' => array( 'keep', 'draft', 'publish' ) ),
						'title'  => array( 'type' => 'string', 'default' => '', 'maxLength' => 200 ),
					),
				),
			)
		);
		register_rest_route(
			'kb/v1',
			'/render',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'render' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array( 'post_id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ) ),
			)
		);
		register_rest_route(
			'kb/v1',
			'/templates',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'templates' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
		register_rest_route(
			'kb/v1',
			'/templates/(?P<key>[a-z0-9\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'template' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
		register_rest_route(
			'kb/v1',
			'/disable/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'disable' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $id,
			)
		);
	}

	/**
	 * GET layout.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function get_layout( $req ) {
		$p = get_post( (int) $req['id'] );
		return rest_ensure_response(
			array(
				'layout'    => kb_get_layout( $p->ID ),
				'title'     => $p->post_title,
				'status'    => $p->post_status,
				'permalink' => get_permalink( $p ),
				'enabled'   => kb_enabled( $p->ID ),
			)
		);
	}

	/**
	 * Parse layout JSON body with a size guard.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return array|WP_Error
	 */
	private static function body_layout( $req ) {
		if ( strlen( (string) $req->get_body() ) > self::MAX_BYTES ) {
			return new WP_Error( 'kb_too_large', 'حجم صفحه بیش از حد مجاز است.', array( 'status' => 413 ) );
		}
		$layout = $req->get_param( 'layout' );
		return is_array( $layout ) ? KB_Sanitizer::layout( $layout ) : new WP_Error( 'kb_bad', 'ساختار صفحه نامعتبر است.', array( 'status' => 400 ) );
	}

	/**
	 * POST layout (save).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_layout( $req ) {
		$id     = (int) $req['id'];
		$layout = self::body_layout( $req );
		if ( is_wp_error( $layout ) ) {
			return $layout;
		}
		$prev = get_post_meta( $id, KB_META, true );
		if ( $prev ) {
			$hist   = get_post_meta( $id, '_kb_history', true );
			$hist   = is_array( $hist ) ? $hist : array();
			array_unshift( $hist, array( 't' => time(), 'u' => get_current_user_id(), 'l' => $prev ) );
			update_post_meta( $id, '_kb_history', array_slice( $hist, 0, 5 ) );
		}
		update_post_meta( $id, KB_META, wp_slash( wp_json_encode( $layout, JSON_UNESCAPED_UNICODE ) ) );
		update_post_meta( $id, '_kb_enabled', 1 );

		// Searchable / fallback snapshot of the static output.
		KB_Render::$editing = false;
		$snapshot = '<!-- khesht-builder -->' . wp_kses_post( KB_Render::layout( $layout ) );

		$update = array(
			'ID'           => $id,
			'post_content' => $snapshot,
		);
		$title = trim( sanitize_text_field( (string) $req['title'] ) );
		if ( '' !== $title ) {
			$update['post_title'] = $title;
		}
		if ( 'publish' === $req['status'] ) {
			if ( ! current_user_can( get_post_type_object( get_post_type( $id ) )->cap->publish_posts ) ) {
				return new WP_Error( 'kb_cap', 'اجازهٔ انتشار ندارید؛ پیش‌نویس ذخیره شد.', array( 'status' => 403 ) );
			}
			$update['post_status'] = 'publish';
		} elseif ( 'draft' === $req['status'] && 'publish' !== get_post_status( $id ) ) {
			$update['post_status'] = 'draft';
		}
		$res = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'kb_save', 'ذخیره ناموفق بود.', array( 'status' => 500 ) );
		}
		if ( 'page' === get_post_type( $id ) && self::theme_has_template() ) {
			update_post_meta( $id, '_wp_page_template', 'page-khesht-builder' );
		}
		return rest_ensure_response(
			array(
				'ok'        => true,
				'status'    => get_post_status( $id ),
				'permalink' => get_permalink( $id ),
				'saved_at'  => function_exists( 'bmn_jdate' ) ? bmn_jdate( 'H:i' ) : gmdate( 'H:i' ),
			)
		);
	}

	/**
	 * Does the active theme provide the full-width builder template?
	 *
	 * @return bool
	 */
	private static function theme_has_template() {
		return file_exists( get_stylesheet_directory() . '/templates/page-khesht-builder.html' ) || file_exists( get_template_directory() . '/templates/page-khesht-builder.html' );
	}

	/**
	 * POST render (editor preview) — sections array → HTML strings.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function render( $req ) {
		if ( strlen( (string) $req->get_body() ) > self::MAX_BYTES ) {
			return new WP_Error( 'kb_too_large', 'too large', array( 'status' => 413 ) );
		}
		$sections = $req->get_param( 'sections' );
		if ( ! is_array( $sections ) ) {
			return new WP_Error( 'kb_bad', 'bad', array( 'status' => 400 ) );
		}
		global $post;
		$post = get_post( (int) $req['post_id'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- preview context for dynamic widgets.
		setup_postdata( $post );
		KB_Render::$editing = true;
		$out = array();
		foreach ( array_slice( $sections, 0, KB_Sanitizer::MAX_SECTIONS ) as $sec ) {
			$clean = KB_Sanitizer::section( is_array( $sec ) ? $sec : array() );
			$out[] = array(
				'id'   => $clean['id'],
				'html' => KB_Render::section( $clean ),
			);
		}
		KB_Render::$editing = false;
		wp_reset_postdata();
		return rest_ensure_response( array( 'sections' => $out ) );
	}

	/**
	 * Template list.
	 *
	 * @return WP_REST_Response
	 */
	public static function templates() {
		$out = array();
		foreach ( KB_Templates::all() as $k => $t ) {
			$out[] = array( 'key' => $k, 'title' => $t['title'], 'desc' => $t['desc'], 'icon' => $t['icon'], 'count' => count( $t['sections'] ) );
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Single template.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function template( $req ) {
		$t = KB_Templates::get( (string) $req['key'] );
		return $t ? rest_ensure_response( $t ) : new WP_Error( 'kb_404', 'not found', array( 'status' => 404 ) );
	}

	/**
	 * Disable builder for a post (content snapshot remains).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function disable( $req ) {
		update_post_meta( (int) $req['id'], '_kb_enabled', 0 );
		if ( 'page-khesht-builder' === get_post_meta( (int) $req['id'], '_wp_page_template', true ) ) {
			delete_post_meta( (int) $req['id'], '_wp_page_template' );
		}
		return rest_ensure_response( array( 'ok' => true, 'edit' => get_edit_post_link( (int) $req['id'], 'raw' ) ) );
	}
}
