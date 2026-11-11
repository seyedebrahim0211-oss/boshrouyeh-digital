<?php
/**
 * Photo reports CPT, media meta (gallery, Aparat, caption), PR approval and front rendering.
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Content.
 */
final class BMNM_Content {

	const PT = 'bmn_report';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 20, 2 );
		add_filter( 'the_content', array( __CLASS__, 'render' ), 15 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'og_video' ), 5 );
		add_filter( 'render_block_boshrouyeh/share', static fn( $html ) => is_singular( bmnm_types() ) ? '' : $html );
		foreach ( bmnm_types() as $t ) {
			add_filter( "manage_{$t}_posts_columns", array( __CLASS__, 'cols' ) );
			add_action( "manage_{$t}_posts_custom_column", array( __CLASS__, 'col' ), 10, 2 );
		}
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_action( 'admin_post_bmnm_approve', array( __CLASS__, 'quick_approve' ) );
		add_filter( 'views_edit-post', array( __CLASS__, 'pending_view' ) );
		add_filter( 'views_edit-bmn_report', array( __CLASS__, 'pending_view' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'pending_filter' ) );
	}

	/**
	 * Register the photo-report type.
	 */
	public static function register() {
		register_post_type(
			self::PT,
			array(
				'labels'       => array(
					'name'          => 'گزارش‌های تصویری',
					'singular_name' => 'گزارش تصویری',
					'add_new'       => 'گزارش تصویری تازه',
					'add_new_item'  => 'افزودن گزارش تصویری',
					'edit_item'     => 'ویرایش گزارش تصویری',
					'all_items'     => 'همهٔ گزارش‌ها',
					'menu_name'     => 'گزارش تصویری',
					'search_items'  => 'جست‌وجوی گزارش',
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => 'reports',
				'rewrite'      => array( 'slug' => 'report', 'with_front' => false ),
				'menu_icon'    => 'dashicons-format-gallery',
				'menu_position' => 6,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
				'taxonomies'   => array( 'post_tag', 'category' ),
			)
		);
		foreach ( bmnm_types() as $t ) {
			foreach ( array( '_bmn_gallery', '_bmn_aparat', '_bmn_caption' ) as $k ) {
				register_post_meta(
					$t,
					$k,
					array(
						'type'          => 'string',
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => static fn( $allowed, $key, $id ) => current_user_can( 'edit_post', $id ),
					)
				);
			}
		}
	}

	/**
	 * PR state of a post: none|approved.
	 *
	 * @param int $id Post.
	 * @return bool
	 */
	public static function approved( $id ) {
		return 'approved' === get_post_meta( $id, '_bmn_pr', true );
	}

	/**
	 * Meta boxes.
	 */
	public static function boxes() {
		foreach ( bmnm_types() as $t ) {
			add_meta_box( 'bmnm_media', 'گالری تصاویر و ویدیوی آپارات', array( __CLASS__, 'box_media' ), $t, 'normal', 'high' );
			add_meta_box( 'bmnm_pr', 'روابط عمومی و فضای مجازی', array( __CLASS__, 'box_pr' ), $t, 'side', 'high' );
		}
	}

	/**
	 * Media box.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function box_media( $p ) {
		wp_nonce_field( 'bmnm_save', 'bmnm_nonce' );
		$ids = bmnm_gallery_ids( $p->ID );
		echo '<div class="bmnm-gallery-field"><input type="hidden" name="bmn_gallery" id="bmn_gallery" value="' . esc_attr( implode( ',', $ids ) ) . '"><ul class="bmnm-thumbs" id="bmnm-thumbs">';
		foreach ( $ids as $id ) {
			echo '<li data-id="' . (int) $id . '">' . wp_get_attachment_image( $id, 'thumbnail' ) . '<button type="button" class="bmnm-x" aria-label="حذف">×</button></li>';
		}
		echo '</ul><p><button type="button" class="button button-primary" id="bmnm-pick">افزودن / انتخاب تصاویر</button> <span class="description">ترتیب را با کشیدن تصاویر تغییر دهید. تصویر شاخص اولِ گالری نمایش داده می‌شود.</span></p></div>';
		echo '<p><label for="bmn_aparat"><strong>پیوند ویدیوی آپارات</strong></label><br><input type="url" class="widefat" dir="ltr" id="bmn_aparat" name="bmn_aparat" placeholder="https://www.aparat.com/v/xxxxx" value="' . esc_attr( (string) get_post_meta( $p->ID, '_bmn_aparat', true ) ) . '"></p>';
		$hash = bmnm_aparat_hash( (string) get_post_meta( $p->ID, '_bmn_aparat', true ) );
		if ( $hash ) {
			echo '<div style="max-width:480px">' . bmnm_aparat_html( $hash ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		}
	}

	/**
	 * PR / social box.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function box_pr( $p ) {
		$approved = self::approved( $p->ID );
		$by       = (int) get_post_meta( $p->ID, '_bmn_pr_by', true );
		echo '<div class="bmnm-pr">';
		if ( $approved ) {
			$u = get_userdata( $by );
			echo '<p class="bmnm-badge ok">✔ تأییدشدهٔ روابط عمومی' . ( $u ? ' — ' . esc_html( $u->display_name ) : '' ) . '<br><small>' . esc_html( bmnm_jdate( 'j F Y - H:i', (int) get_post_meta( $p->ID, '_bmn_pr_at', true ) ) ) . '</small></p>';
		} else {
			echo '<p class="bmnm-badge wait">⏳ در انتظار تأیید روابط عمومی</p>';
		}
		if ( current_user_can( 'bmnm_approve' ) ) {
			echo '<p><label><input type="checkbox" name="bmn_pr_approve" value="1" ' . checked( $approved, true, false ) . '> <strong>تأیید روابط عمومی</strong></label></p>';
		}
		$nets   = BMNM_Social::enabled_networks();
		$chosen = get_post_meta( $p->ID, '_bmn_social_nets', true );
		$chosen = is_array( $chosen ) ? $chosen : array_keys( $nets );
		if ( $nets ) {
			echo '<p><strong>ارسال خودکار به:</strong></p>';
			foreach ( $nets as $k => $label ) {
				echo '<label style="display:inline-block;margin-inline-end:10px"><input type="checkbox" name="bmn_social_nets[]" value="' . esc_attr( $k ) . '" ' . checked( in_array( $k, $chosen, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
			}
		} else {
			echo '<p class="description">هیچ کانالی فعال نیست. <a href="' . esc_url( admin_url( 'admin.php?page=bmnm-social' ) ) . '">تنظیم کانال‌ها</a></p>';
		}
		echo '<p><label for="bmn_caption"><strong>کپشن فضای مجازی</strong> <small>(خالی = خلاصهٔ خودکار)</small></label><textarea class="widefat" rows="4" id="bmn_caption" name="bmn_caption">' . esc_textarea( (string) get_post_meta( $p->ID, '_bmn_caption', true ) ) . '</textarea></p>';
		if ( 'publish' === $p->post_status ) {
			echo '<details><summary>پیش‌نمایش کپشن</summary><pre class="bmnm-pre">' . esc_html( bmnm_caption( $p ) ) . '</pre></details>';
		}
		$log = get_post_meta( $p->ID, '_bmn_social_log', true );
		if ( is_array( $log ) && $log ) {
			echo '<ul class="bmnm-netlog">';
			foreach ( $log as $net => $row ) {
				$cls = 'sent' === $row['s'] ? 'ok' : ( 'queued' === $row['s'] ? 'wait' : 'err' );
				echo '<li class="' . esc_attr( $cls ) . '"><b>' . esc_html( BMNM_Social::label( $net ) ) . ':</b> ' . esc_html( BMNM_Social::status_label( $row['s'] ) ) . ( ! empty( $row['m'] ) && 'sent' !== $row['s'] ? ' — <small>' . esc_html( $row['m'] ) . '</small>' : '' ) . '</li>';
			}
			echo '</ul>';
			if ( current_user_can( 'bmnm_social' ) && 'publish' === $p->post_status ) {
				echo '<label><input type="checkbox" name="bmn_social_resend" value="1"> ارسال دوباره به کانال‌های انتخاب‌شده</label>';
			}
		}
		echo '</div>';
	}

	/**
	 * Save meta + PR approval.
	 *
	 * @param int     $id Post.
	 * @param WP_Post $p  Post.
	 */
	public static function save( $id, $p ) {
		if ( ! in_array( $p->post_type, bmnm_types(), true ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) {
			return;
		}
		if ( ! isset( $_POST['bmnm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmnm_nonce'] ) ), 'bmnm_save' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$ids = isset( $_POST['bmn_gallery'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['bmn_gallery'] ) ) ) ) ) : array();
		update_post_meta( $id, '_bmn_gallery', implode( ',', array_slice( array_unique( $ids ), 0, 60 ) ) );
		$ap = isset( $_POST['bmn_aparat'] ) ? esc_url_raw( wp_unslash( $_POST['bmn_aparat'] ) ) : '';
		update_post_meta( $id, '_bmn_aparat', bmnm_aparat_hash( $ap ) ? $ap : '' );
		update_post_meta( $id, '_bmn_caption', isset( $_POST['bmn_caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bmn_caption'] ) ) : '' );
		$valid = array_keys( BMNM_Social::networks() );
		$nets  = isset( $_POST['bmn_social_nets'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['bmn_social_nets'] ) ), $valid ) ) : array();
		update_post_meta( $id, '_bmn_social_nets', $nets );

		if ( current_user_can( 'bmnm_approve' ) ) {
			$want = ! empty( $_POST['bmn_pr_approve'] );
			if ( $want && ! self::approved( $id ) ) {
				self::approve( $id );
			} elseif ( ! $want && self::approved( $id ) ) {
				delete_post_meta( $id, '_bmn_pr' );
				bmnm_log( 'pr', 'لغو تأیید: ' . get_the_title( $id ) );
			}
		}
		if ( ! empty( $_POST['bmn_social_resend'] ) && current_user_can( 'bmnm_social' ) ) {
			BMNM_Social::queue( $id, true );
		} else {
			BMNM_Social::maybe_queue( $id );
		}
	}

	/**
	 * Approve a post (records approver) and queue it if published.
	 *
	 * @param int $id Post.
	 */
	public static function approve( $id ) {
		update_post_meta( $id, '_bmn_pr', 'approved' );
		update_post_meta( $id, '_bmn_pr_by', get_current_user_id() );
		update_post_meta( $id, '_bmn_pr_at', time() );
		bmnm_log( 'pr', 'تأیید: ' . get_the_title( $id ) );
		do_action( 'bmnm_pr_approved', $id );
	}

	/**
	 * List columns.
	 *
	 * @param array $c Columns.
	 * @return array
	 */
	public static function cols( $c ) {
		$c['bmnm_pr'] = 'روابط عمومی / کانال‌ها';
		return $c;
	}

	/**
	 * Column value.
	 *
	 * @param string $col Column.
	 * @param int    $id  Post.
	 */
	public static function col( $col, $id ) {
		if ( 'bmnm_pr' !== $col ) {
			return;
		}
		echo self::approved( $id ) ? '<span class="bmnm-badge ok">تأییدشده</span>' : '<span class="bmnm-badge wait">منتظر تأیید</span>';
		$log = get_post_meta( $id, '_bmn_social_log', true );
		if ( is_array( $log ) ) {
			foreach ( $log as $net => $row ) {
				echo ' <span class="bmnm-dot ' . esc_attr( 'sent' === $row['s'] ? 'ok' : ( 'queued' === $row['s'] ? 'wait' : 'err' ) ) . '" title="' . esc_attr( BMNM_Social::status_label( $row['s'] ) ) . '">' . esc_html( BMNM_Social::label( $net ) ) . '</span>';
			}
		}
		$imgs = count( bmnm_images( $id ) );
		if ( $imgs > 1 ) {
			echo '<br><small>📷 ' . esc_html( bmnm_fa( $imgs ) ) . ' تصویر</small>';
		}
		if ( get_post_meta( $id, '_bmn_aparat', true ) ) {
			echo ' <small>🎬 ویدیو</small>';
		}
	}

	/**
	 * Row action: one-click approve.
	 *
	 * @param array   $a Actions.
	 * @param WP_Post $p Post.
	 * @return array
	 */
	public static function row_action( $a, $p ) {
		if ( in_array( $p->post_type, bmnm_types(), true ) && ! self::approved( $p->ID ) && current_user_can( 'bmnm_approve' ) && current_user_can( 'edit_post', $p->ID ) ) {
			$url             = wp_nonce_url( admin_url( 'admin-post.php?action=bmnm_approve&id=' . $p->ID ), 'bmnm_approve_' . $p->ID );
			$a['bmnm_ok']    = '<a href="' . esc_url( $url ) . '" style="color:#16737A;font-weight:700">تأیید و انتشار در کانال‌ها</a>';
		}
		return $a;
	}

	/**
	 * Handle one-click approve.
	 */
	public static function quick_approve() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'bmnm_approve_' . $id );
		if ( ! $id || ! current_user_can( 'bmnm_approve' ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		self::approve( $id );
		if ( 'publish' !== get_post_status( $id ) && current_user_can( 'publish_post', $id ) ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		}
		BMNM_Social::maybe_queue( $id );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php' ) );
		exit;
	}

	/**
	 * "Awaiting PR" view link.
	 *
	 * @param array $v Views.
	 * @return array
	 */
	public static function pending_view( $v ) {
		$screen = get_current_screen();
		$type   = $screen ? $screen->post_type : 'post';
		$n      = self::pending_count( $type );
		$url    = add_query_arg( array( 'post_type' => $type, 'bmnm_pr' => 'wait' ), admin_url( 'edit.php' ) );
		$cur    = isset( $_GET['bmnm_pr'] ) ? ' class="current"' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$v['bmnm_pr'] = '<a href="' . esc_url( $url ) . '"' . $cur . '>منتظر تأیید روابط عمومی <span class="count">(' . esc_html( bmnm_fa( $n ) ) . ')</span></a>';
		return $v;
	}

	/**
	 * Count posts awaiting PR.
	 *
	 * @param string|string[] $type Type(s).
	 * @return int
	 */
	public static function pending_count( $type = null ) {
		$q = new WP_Query(
			array(
				'post_type'      => $type ? $type : bmnm_types(),
				'post_status'    => array( 'publish', 'pending', 'draft', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_bmn_pr', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Filter admin list by PR state.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function pending_filter( $q ) {
		if ( is_admin() && $q->is_main_query() && isset( $_GET['bmnm_pr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$q->set( 'meta_query', array( array( 'key' => '_bmn_pr', 'compare' => 'NOT EXISTS' ) ) );
		}
	}

	/**
	 * Append video, gallery and share bar on single views.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function render( $content ) {
		if ( ! is_singular( bmnm_types() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$p    = get_post();
		$hash = bmnm_aparat_hash( (string) get_post_meta( $p->ID, '_bmn_aparat', true ) );
		$top  = $hash ? bmnm_aparat_html( $hash, get_the_title( $p ) ) : '';
		return $top . $content . self::gallery_html( $p->ID ) . self::share_html( $p );
	}

	/**
	 * Gallery grid with lightbox.
	 *
	 * @param int $id Post.
	 * @return string
	 */
	public static function gallery_html( $id ) {
		$ids = bmnm_gallery_ids( $id );
		if ( ! $ids ) {
			return '';
		}
		$out = '<section class="bmnm-gallery" aria-label="گزارش تصویری"><h2 class="bmnm-gallery-title">گزارش تصویری <span>' . esc_html( bmnm_fa( count( $ids ) ) ) . ' تصویر</span></h2><div class="bmnm-grid">';
		foreach ( $ids as $i => $att ) {
			$full = wp_get_attachment_image_url( $att, 'full' );
			$cap  = wp_get_attachment_caption( $att );
			$out .= '<a class="bmnm-item' . ( 0 === $i ? ' is-wide' : '' ) . '" href="' . esc_url( $full ) . '" data-cap="' . esc_attr( (string) $cap ) . '">' . wp_get_attachment_image( $att, 0 === $i ? 'large' : 'medium_large', false, array( 'loading' => 'lazy' ) ) . '</a>';
		}
		return $out . '</div></section>';
	}

	/**
	 * Share bar.
	 *
	 * @param WP_Post $p Post.
	 * @return string
	 */
	public static function share_html( $p ) {
		$url  = get_permalink( $p );
		$text = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
		$out  = '<div class="bmnm-share" role="group" aria-label="اشتراک‌گذاری"><span class="bmnm-share-lbl">هم‌رسانی:</span>';
		foreach ( bmnm_share_links( $url, $text ) as $k => $l ) {
			$out .= '<a class="bmnm-sh bmnm-sh-' . esc_attr( $k ) . '" href="' . esc_url( $l['url'] ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $l['label'] ) . '</a>';
		}
		$out .= '<button type="button" class="bmnm-sh bmnm-sh-copy" data-url="' . esc_attr( $url ) . '" data-text="' . esc_attr( $text ) . '">کپی پیوند / اشتراک</button></div>';
		return $out;
	}

	/**
	 * Open Graph video tag.
	 */
	public static function og_video() {
		if ( ! is_singular( bmnm_types() ) ) {
			return;
		}
		$hash = bmnm_aparat_hash( (string) get_post_meta( get_queried_object_id(), '_bmn_aparat', true ) );
		if ( $hash ) {
			echo '<meta property="og:video" content="' . esc_url( bmnm_aparat_embed( $hash ) ) . '">' . "\n";
		}
	}

	/**
	 * Front assets.
	 */
	public static function front_assets() {
		wp_enqueue_style( 'bmnm-front', BMNM_URL . 'assets/css/front.css', array(), BMNM_VERSION );
		wp_enqueue_script( 'bmnm-front', BMNM_URL . 'assets/js/front.js', array(), BMNM_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_localize_script(
			'bmnm-front',
			'BMNM',
			array(
				'api'   => esc_url_raw( rest_url( 'bmn/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'logged' => is_user_logged_in(),
				'login' => function_exists( 'bmn_option' ) && bmn_option( 'page_dashboard' ) ? get_permalink( (int) bmn_option( 'page_dashboard' ) ) : wp_login_url(),
			)
		);
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && in_array( $screen->post_type, bmnm_types(), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'bmnm-admin', BMNM_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), BMNM_VERSION, true );
		}
	}
}
