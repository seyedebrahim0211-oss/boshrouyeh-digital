<?php
/**
 * Admin integration: editor screen, list row actions, admin bar, block-editor button, new page.
 *
 * @package KheshtBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
final class KB_Admin {

	const SLUG = 'khesht-builder';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
		add_action( 'admin_post_kb_new', array( __CLASS__, 'new_page' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_editor_button' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'state' ), 10, 2 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Editor URL.
	 *
	 * @param int $id Post.
	 * @return string
	 */
	public static function edit_url( $id ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '&post=' . (int) $id );
	}

	/**
	 * Menu: list + editor under Pages.
	 */
	public static function menu() {
		add_submenu_page( 'edit.php?post_type=page', 'صفحه‌ساز خشت', 'صفحه‌ساز خشت', 'edit_pages', self::SLUG, array( __CLASS__, 'screen' ) );
	}

	/**
	 * Is the editor screen open?
	 *
	 * @return int Post id.
	 */
	private static function editing_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- capability-checked screen routing.
		return ( isset( $_GET['page'], $_GET['post'] ) && self::SLUG === $_GET['page'] ) ? absint( $_GET['post'] ) : 0;
	}

	/**
	 * Body class for full-screen mode.
	 *
	 * @param string $c Classes.
	 * @return string
	 */
	public static function body_class( $c ) {
		return self::editing_id() ? $c . ' kb-fullscreen' : $c;
	}

	/**
	 * Screen router.
	 */
	public static function screen() {
		$id = self::editing_id();
		if ( $id ) {
			self::editor( $id );
			return;
		}
		self::listing();
	}

	/**
	 * Builder pages list.
	 */
	private static function listing() {
		$posts = get_posts(
			array(
				'post_type'      => kb_post_types(),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'meta_key'       => '_kb_enabled', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		echo '<div class="wrap"><h1 class="wp-heading-inline">صفحه‌ساز خشت</h1> <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kb_new' ), 'kb_new' ) ) . '">برگهٔ جدید با صفحه‌ساز</a><hr class="wp-header-end">';
		echo '<p>برگه‌ها را با کشیدن و رها کردن بخش‌ها و ویجت‌های هویتی بسازید. از فهرست «برگه‌ها» هم گزینهٔ «ویرایش با خشت‌ساز» در دسترس است.</p>';
		echo '<table class="widefat striped"><thead><tr><th>عنوان</th><th>نوع</th><th>وضعیت</th><th></th></tr></thead><tbody>';
		if ( ! $posts ) {
			echo '<tr><td colspan="4">هنوز برگه‌ای با صفحه‌ساز ساخته نشده است.</td></tr>';
		}
		foreach ( $posts as $p ) {
			if ( ! current_user_can( 'edit_post', $p->ID ) ) {
				continue;
			}
			echo '<tr><td><strong>' . esc_html( $p->post_title ? $p->post_title : '(بی‌عنوان)' ) . '</strong></td><td>' . esc_html( get_post_type_object( $p->post_type )->labels->singular_name ) . '</td><td>' . esc_html( get_post_status_object( $p->post_status )->label ) . '</td><td><a class="button button-primary" href="' . esc_url( self::edit_url( $p->ID ) ) . '">ویرایش با خشت‌ساز</a> <a class="button" href="' . esc_url( (string) get_permalink( $p ) ) . '" target="_blank">مشاهده</a></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Create a draft page and open the builder.
	 */
	public static function new_page() {
		check_admin_referer( 'kb_new' );
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( '', 403 );
		}
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'برگهٔ جدید',
			)
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		update_post_meta( $id, '_kb_enabled', 1 );
		wp_safe_redirect( self::edit_url( $id ) );
		exit;
	}

	/**
	 * Row action.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_action( $actions, $post ) {
		if ( in_array( $post->post_type, kb_post_types(), true ) && current_user_can( 'edit_post', $post->ID ) ) {
			$actions['kb'] = '<a href="' . esc_url( self::edit_url( $post->ID ) ) . '"><strong>ویرایش با خشت‌ساز</strong></a>';
		}
		return $actions;
	}

	/**
	 * Post state label.
	 *
	 * @param array   $states States.
	 * @param WP_Post $post   Post.
	 * @return array
	 */
	public static function state( $states, $post ) {
		if ( kb_enabled( $post->ID ) ) {
			$states['kb'] = 'خشت‌ساز';
		}
		return $states;
	}

	/**
	 * Admin bar link on the front end.
	 *
	 * @param WP_Admin_Bar $bar Bar.
	 */
	public static function admin_bar( $bar ) {
		if ( is_admin() || ! is_singular( kb_post_types() ) ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( current_user_can( 'edit_post', $id ) ) {
			$bar->add_node(
				array(
					'id'    => 'kb-edit',
					'title' => 'ویرایش با خشت‌ساز',
					'href'  => self::edit_url( $id ),
				)
			);
		}
	}

	/**
	 * "Edit with Khesht Builder" button inside the block editor.
	 */
	public static function block_editor_button() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, kb_post_types(), true ) ) {
			return;
		}
		wp_enqueue_script( 'kb-gutenberg', KB_URL . 'assets/editor/gutenberg.js', array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-editor' ), KB_VERSION, true );
		wp_localize_script( 'kb-gutenberg', 'KBGutenberg', array( 'url' => admin_url( 'admin.php?page=' . self::SLUG . '&post=' ) ) );
	}

	/**
	 * Builder editor screen.
	 *
	 * @param int $id Post.
	 */
	private static function editor( $id ) {
		$post = get_post( $id );
		if ( ! $post || ! in_array( $post->post_type, kb_post_types(), true ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		wp_enqueue_media();
		wp_enqueue_style( 'kb-editor', KB_URL . 'assets/editor/editor.css', array(), KB_VERSION );
		wp_enqueue_script( 'kb-editor', KB_URL . 'assets/editor/editor.js', array(), KB_VERSION, true );

		$widgets = array();
		foreach ( KB_Widgets::all() as $type => $w ) {
			$fields = $w['fields'];
			$widgets[ $type ] = array(
				'label'  => $w['label'],
				'cat'    => $w['cat'],
				'icon'   => $w['icon'],
				'fields' => $fields,
			);
		}
		$icons = array();
		$set   = defined( 'BMN_CORE_DIR' ) ? require BMN_CORE_DIR . 'includes/icon-set.php' : array();
		foreach ( (array) $set as $k => $svg ) {
			$icons[ $k ] = $svg;
		}
		wp_localize_script(
			'kb-editor',
			'KB',
			array(
				'post'       => $id,
				'title'      => $post->post_title,
				'status'     => $post->post_status,
				'type'       => get_post_type_object( $post->post_type )->labels->singular_name,
				'rest'       => esc_url_raw( rest_url( 'kb/v1/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'canvas'     => add_query_arg(
					array(
						'kb_canvas' => 1,
						'kb_post'   => $id,
						'kb_nonce'  => wp_create_nonce( 'kb_canvas_' . $id ),
					),
					home_url( '/' )
				),
				'view'       => get_permalink( $id ),
				'preview'    => get_preview_post_link( $id ),
				'back'       => 'page' === $post->post_type ? admin_url( 'edit.php?post_type=page' ) : admin_url( 'edit.php?post_type=' . $post->post_type ),
				'gutenberg'  => get_edit_post_link( $id, 'raw' ),
				'widgets'    => $widgets,
				'common'     => KB_Widgets::common_fields(),
				'section'    => KB_Widgets::section_fields(),
				'categories' => KB_Widgets::categories(),
				'icons'      => $icons,
				'canPublish' => current_user_can( get_post_type_object( $post->post_type )->cap->publish_posts ),
				'logo'       => KB_URL . 'assets/img/logo-brick.svg',
			)
		);
		echo '<div id="kb-app" class="kb-app" dir="rtl"><div class="kb-boot"><img class="kb-boot__logo" src="' . esc_url( KB_URL . 'assets/img/logo-brick.svg' ) . '" alt="" width="96" height="96"><span class="kb-boot__star"></span><p>در حال آماده‌سازی صفحه‌ساز خشت…</p></div></div>';
	}
}
