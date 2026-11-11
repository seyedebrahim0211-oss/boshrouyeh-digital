<?php
/**
 * Messenger → website: channel webhooks (Bale, Telegram, Rubika) and a manual "forward" form (all incl. Eitaa).
 * Everything lands as "pending" so PR approves before it goes live.
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inbound.
 */
final class BMNM_Inbound {

	const OPT = 'bmnm_inbound';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 11 );
		add_action( 'admin_post_bmnm_forward', array( __CLASS__, 'handle_forward' ) );
		add_action( 'admin_post_bmnm_webhooks', array( __CLASS__, 'register_webhooks' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Options (+ webhook key).
	 *
	 * @return array
	 */
	public static function opts() {
		$o = get_option( self::OPT, array() );
		$o = is_array( $o ) ? $o : array();
		if ( empty( $o['key'] ) || ! preg_match( '/^[A-Za-z0-9]{32}$/', (string) $o['key'] ) ) {
			$o['key'] = wp_generate_password( 32, false, false );
			update_option( self::OPT, $o, false );
		}
		return $o;
	}

	/**
	 * Save options from the social settings form.
	 *
	 * @param array  $in   Raw.
	 * @param string $type Post type.
	 */
	public static function save_opts( $in, $type ) {
		$o         = self::opts();
		$o['type'] = $type;
		foreach ( array( 'bale', 'telegram', 'rubika' ) as $k ) {
			$o[ $k ] = array(
				'on'   => ! empty( $in[ $k ]['on'] ) ? 1 : 0,
				'chat' => isset( $in[ $k ]['chat'] ) ? preg_replace( '/[^A-Za-z0-9@_\-]/', '', (string) $in[ $k ]['chat'] ) : '',
			);
		}
		update_option( self::OPT, $o, false );
	}

	/**
	 * Webhook URL.
	 *
	 * @param string $net Network.
	 * @return string
	 */
	public static function hook_url( $net ) {
		return rest_url( 'bmn/v1/inbound/' . $net . '/' . self::opts()['key'] );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/inbound/(?P<net>bale|telegram|rubika)/(?P<key>[A-Za-z0-9]{32})',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'webhook' ),
				'permission_callback' => array( __CLASS__, 'check_key' ),
			)
		);
	}

	/**
	 * Key check (constant time).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return bool
	 */
	public static function check_key( $req ) {
		$o = self::opts();
		return hash_equals( (string) $o['key'], (string) $req['key'] ) && ! empty( $o[ (string) $req['net'] ]['on'] );
	}

	/**
	 * Source chat configured for a network.
	 *
	 * @param string $net Net.
	 * @return string
	 */
	private static function source_chat( $net ) {
		$o = self::opts();
		$c = (string) ( $o[ $net ]['chat'] ?? '' );
		return $c ? $c : BMNM_Social::net( $net, 'chat' );
	}

	/**
	 * Webhook receiver. Always answers 200 so the platform does not retry forever.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function webhook( $req ) {
		$net = (string) $req['net'];
		$u   = $req->get_json_params();
		if ( ! is_array( $u ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}
		if ( 'telegram' === $net ) {
			$secret = (string) $req->get_header( 'x_telegram_bot_api_secret_token' );
			if ( $secret && ! hash_equals( self::opts()['key'], $secret ) ) {
				return rest_ensure_response( array( 'ok' => true ) );
			}
		}
		try {
			if ( 'rubika' === $net ) {
				self::from_rubika( $u );
			} else {
				self::from_botapi( $net, $u );
			}
		} catch ( Throwable $e ) {
			bmnm_log( 'inbound', BMNM_Social::label( $net ) . ' خطا: ' . $e->getMessage() );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Telegram / Bale update.
	 *
	 * @param string $net Net.
	 * @param array  $u   Update.
	 */
	private static function from_botapi( $net, $u ) {
		$m = $u['channel_post'] ?? null;
		if ( ! is_array( $m ) ) {
			return; // Only channel posts; edits and private chats are ignored.
		}
		$chat  = $m['chat'] ?? array();
		$want  = self::source_chat( $net );
		$match = ( (string) ( $chat['id'] ?? '' ) === $want ) || ( ! empty( $chat['username'] ) && strtolower( '@' . $chat['username'] ) === strtolower( $want ) );
		if ( ! $want || ! $match ) {
			return;
		}
		$uid = $net . ':' . ( $chat['id'] ?? '' ) . ':' . ( $m['message_id'] ?? '' );
		if ( self::seen( $uid ) ) {
			return;
		}
		$text  = (string) ( $m['text'] ?? ( $m['caption'] ?? '' ) );
		$atts  = array();
		$base  = 'bale' === $net ? 'https://tapi.bale.ai/' : 'https://api.telegram.org/';
		$token = BMNM_Social::net( $net, 'token' );
		if ( ! empty( $m['photo'] ) && is_array( $m['photo'] ) ) {
			$big = end( $m['photo'] );
			$res = wp_remote_post( $base . 'bot' . $token . '/getFile', array( 'timeout' => 20, 'body' => array( 'file_id' => (string) ( $big['file_id'] ?? '' ) ) ) );
			$d   = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( ! empty( $d['result']['file_path'] ) ) {
				$att = self::sideload( $base . 'file/bot' . $token . '/' . ltrim( (string) $d['result']['file_path'], '/' ), 'msg-' . ( $m['message_id'] ?? 0 ) . '.jpg' );
				if ( $att ) {
					$atts[] = $att;
				}
			}
		}
		$group = isset( $m['media_group_id'] ) ? $net . ':' . $m['media_group_id'] : '';
		self::store( $net, $uid, $text, $atts, $group );
	}

	/**
	 * Rubika update.
	 *
	 * @param array $u Update.
	 */
	private static function from_rubika( $u ) {
		$up = $u['update'] ?? $u['inline_message'] ?? null;
		if ( ! is_array( $up ) || 'NewMessage' !== ( $up['type'] ?? 'NewMessage' ) ) {
			return;
		}
		$want = self::source_chat( 'rubika' );
		if ( ! $want || (string) ( $up['chat_id'] ?? '' ) !== $want ) {
			return;
		}
		$m   = $up['new_message'] ?? array();
		$uid = 'rubika:' . $want . ':' . ( $m['message_id'] ?? md5( wp_json_encode( $m ) ) );
		if ( self::seen( $uid ) ) {
			return;
		}
		$atts = array();
		if ( ! empty( $m['file']['file_id'] ) ) {
			$res = wp_remote_post(
				'https://botapi.rubika.ir/v3/' . rawurlencode( BMNM_Social::net( 'rubika', 'token' ) ) . '/getFile',
				array( 'timeout' => 20, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'file_id' => (string) $m['file']['file_id'] ) ) )
			);
			$d   = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( ! empty( $d['data']['download_url'] ) ) {
				$att = self::sideload( (string) $d['data']['download_url'], sanitize_file_name( (string) ( $m['file']['file_name'] ?? 'rubika.jpg' ) ) );
				if ( $att ) {
					$atts[] = $att;
				}
			}
		}
		self::store( 'rubika', $uid, (string) ( $m['text'] ?? '' ), $atts, '' );
	}

	/**
	 * Deduplicate.
	 *
	 * @param string $uid Unique id.
	 * @return bool
	 */
	private static function seen( $uid ) {
		$q = get_posts( array( 'post_type' => bmnm_types(), 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_bmn_inbound_id', 'meta_value' => md5( $uid ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		return (bool) $q;
	}

	/**
	 * Download an image into the media library (images only).
	 *
	 * @param string $url  URL.
	 * @param string $name File name.
	 * @return int Attachment id or 0.
	 */
	private static function sideload( $url, $name ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return 0;
		}
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $info || ! in_array( $info['mime'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			wp_delete_file( $tmp );
			return 0;
		}
		$ext = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' )[ $info['mime'] ];
		$id  = media_handle_sideload( array( 'name' => preg_replace( '/\.[a-z0-9]+$/i', '', $name ) . '.' . $ext, 'tmp_name' => $tmp ), 0 );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}
		return (int) $id;
	}

	/**
	 * Create (or extend, for albums) a pending post.
	 *
	 * @param string $net   Net.
	 * @param string $uid   Message uid.
	 * @param string $text  Caption.
	 * @param int[]  $atts  Attachments.
	 * @param string $group Media group key.
	 */
	private static function store( $net, $uid, $text, $atts, $group ) {
		if ( $group ) {
			$ex = get_posts( array( 'post_type' => bmnm_types(), 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_bmn_mgroup', 'meta_value' => md5( $group ), 'date_query' => array( array( 'after' => '2 hours ago' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $ex ) {
				$id      = (int) $ex[0];
				$gallery = array_merge( bmnm_gallery_ids( $id ), $atts );
				update_post_meta( $id, '_bmn_gallery', implode( ',', array_unique( $gallery ) ) );
				if ( ! has_post_thumbnail( $id ) && $atts ) {
					set_post_thumbnail( $id, $atts[0] );
				}
				if ( trim( $text ) && '' === trim( (string) get_post_field( 'post_content', $id ) ) ) {
					$parsed = self::parse( $text );
					wp_update_post( array( 'ID' => $id, 'post_title' => $parsed['title'], 'post_content' => $parsed['content'], 'post_excerpt' => $parsed['excerpt'] ) );
					self::apply_parsed( $id, $parsed );
				}
				add_post_meta( $id, '_bmn_inbound_id', md5( $uid ) );
				return;
			}
		}
		if ( '' === trim( $text ) && ! $atts ) {
			return;
		}
		$id = self::create( self::parse( $text ), $atts, $net, (string) ( self::opts()['type'] ?? 'post' ), 'pending' );
		if ( $id ) {
			add_post_meta( $id, '_bmn_inbound_id', md5( $uid ) );
			if ( $group ) {
				update_post_meta( $id, '_bmn_mgroup', md5( $group ) );
			}
			bmnm_log( 'inbound', 'دریافت از ' . BMNM_Social::label( $net ) . ': ' . get_the_title( $id ) );
		}
	}

	/**
	 * Parse a messenger caption into title/body/tags/video.
	 *
	 * @param string $text Text.
	 * @return array{title:string,content:string,excerpt:string,tags:string[],aparat:string}
	 */
	public static function parse( $text ) {
		$text   = str_replace( array( "\r\n", "\r" ), "\n", wp_strip_all_tags( (string) $text ) );
		$aparat = bmnm_find_aparat( $text );
		preg_match_all( '/#([\p{L}\p{N}_]+)/u', $text, $tm );
		$tags = array_values( array_unique( array_filter( array_map( static fn( $t ) => trim( str_replace( '_', ' ', $t ) ), $tm[1] ), static fn( $t ) => 'شهرداری بشرویه' !== $t ) ) );
		$text = preg_replace( '/#[\p{L}\p{N}_]+/u', '', $text );
		$text = preg_replace( '#\S*(?:🎬|📌|🔗)\s*#u', '', $text );
		$text = preg_replace( '#https?://(?:www\.)?aparat\.com/v/[A-Za-z0-9]+#i', '', $text );
		$text = preg_replace( '/^\s*(?:@\w+|🆔.*|➖+|—+|_{3,})\s*$/mu', '', $text );
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ), 'strlen' ) );
		$title = $lines ? array_shift( $lines ) : 'خبر بدون عنوان';
		$title = trim( preg_replace( '/^[\p{So}\p{Sk}\s🔸🔹▪️◾️⭕️*]+/u', '', $title ) );
		if ( mb_strlen( $title ) > 140 ) {
			array_unshift( $lines, $title );
			$title = wp_trim_words( $title, 14, '…' );
		}
		$content = '';
		foreach ( $lines as $l ) {
			$content .= '<!-- wp:paragraph --><p>' . make_clickable( esc_html( $l ) ) . "</p><!-- /wp:paragraph -->\n";
		}
		return array(
			'title'   => $title ? $title : 'خبر بدون عنوان',
			'content' => $content,
			'excerpt' => $lines ? wp_trim_words( $lines[0], 35, '…' ) : '',
			'tags'    => array_slice( $tags, 0, 8 ),
			'aparat'  => $aparat,
		);
	}

	/**
	 * Apply tags/video to a post.
	 *
	 * @param int   $id     Post.
	 * @param array $parsed Parsed.
	 */
	private static function apply_parsed( $id, $parsed ) {
		if ( $parsed['tags'] ) {
			wp_set_post_tags( $id, $parsed['tags'], true );
		}
		if ( $parsed['aparat'] ) {
			update_post_meta( $id, '_bmn_aparat', 'https://www.aparat.com/v/' . $parsed['aparat'] );
		}
	}

	/**
	 * Create post from parsed data.
	 *
	 * @param array  $parsed Parsed.
	 * @param int[]  $atts   Attachments.
	 * @param string $net    Source.
	 * @param string $type   Post type.
	 * @param string $status Status.
	 * @return int
	 */
	public static function create( $parsed, $atts, $net, $type, $status ) {
		$author = get_current_user_id();
		if ( ! $author ) {
			$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
			$author = $admins ? (int) $admins[0] : 0;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => in_array( $type, bmnm_types(), true ) ? $type : 'post',
				'post_status'  => $status,
				'post_title'   => $parsed['title'],
				'post_content' => $parsed['content'],
				'post_excerpt' => $parsed['excerpt'],
				'post_author'  => $author,
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		self::apply_parsed( $id, $parsed );
		if ( $atts ) {
			set_post_thumbnail( $id, $atts[0] );
			update_post_meta( $id, '_bmn_gallery', implode( ',', $atts ) );
		}
		if ( $net && isset( BMNM_Social::networks()[ $net ] ) ) {
			update_post_meta( $id, '_bmn_inbound', $net );
			add_post_meta( $id, '_bmn_social_skip', $net );
		}
		return (int) $id;
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_submenu_page( 'bmnm-hub', 'انتقال از پیام‌رسان به سایت', 'انتقال از پیام‌رسان', 'edit_posts', 'bmnm-forward', array( __CLASS__, 'forward_page' ) );
	}

	/**
	 * Assets for the forward page.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( false !== strpos( $hook, 'bmnm-forward' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'bmnm-admin', BMNM_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), BMNM_VERSION, true );
		}
	}

	/**
	 * Manual forward page.
	 */
	public static function forward_page() {
		echo '<div class="wrap bmnm-wrap"><div class="bmnm-hero"><h1>انتقال از پیام‌رسان به سایت</h1><p>متن پست کانال (بله، ایتا، تلگرام یا روبیکا) را بچسبانید و تصاویر را اضافه کنید؛ عنوان، متن، برچسب‌ها و ویدیوی آپارات خودکار تشخیص داده می‌شود و پیش‌نویس برای تأیید روابط عمومی ساخته می‌شود.</p></div><hr class="wp-header-end">';
		echo '<form class="bmnm-panel" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmnm_forward">';
		wp_nonce_field( 'bmnm_forward' );
		echo '<p><label><strong>متن / کپشن پست</strong><br><textarea name="text" rows="12" class="large-text" required placeholder="خط اول = عنوان&#10;بقیه = متن خبر&#10;#هشتگ‌ها = برچسب&#10;لینک آپارات = ویدیو"></textarea></label></p>';
		echo '<div class="bmnm-gallery-field"><input type="hidden" name="bmn_gallery" id="bmn_gallery" value=""><ul class="bmnm-thumbs" id="bmnm-thumbs"></ul><p><button type="button" class="button" id="bmnm-pick">افزودن تصاویر</button></p></div>';
		echo '<p class="bmnm-inline"><label>منبع: <select name="net"><option value="">—</option>';
		foreach ( BMNM_Social::networks() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></label> <label>نوع: <select name="type"><option value="post">خبر</option><option value="bmn_report">گزارش تصویری</option></select></label> ';
		echo '<label>پیوند آپارات (اختیاری): <input type="url" dir="ltr" name="aparat" class="regular-text"></label></p>';
		echo '<p><button class="button button-primary button-large">ساخت پیش‌نویس و ویرایش</button></p></form></div>';
	}

	/**
	 * Handle manual forward.
	 */
	public static function handle_forward() {
		check_admin_referer( 'bmnm_forward' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		$text   = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		$parsed = self::parse( $text );
		$ap     = isset( $_POST['aparat'] ) ? bmnm_aparat_hash( esc_url_raw( wp_unslash( $_POST['aparat'] ) ) ) : '';
		if ( $ap ) {
			$parsed['aparat'] = $ap;
		}
		$atts = isset( $_POST['bmn_gallery'] ) ? array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['bmn_gallery'] ) ) ) ), 'wp_attachment_is_image' ) ) : array();
		$net  = isset( $_POST['net'] ) ? sanitize_key( $_POST['net'] ) : '';
		$type = isset( $_POST['type'] ) && 'bmn_report' === $_POST['type'] ? 'bmn_report' : 'post';
		$id   = self::create( $parsed, $atts, $net, $type, current_user_can( 'publish_posts' ) ? 'draft' : 'pending' );
		if ( ! $id ) {
			wp_die( 'ساخت پیش‌نویس ناموفق بود.' );
		}
		bmnm_log( 'inbound', 'انتقال دستی: ' . $parsed['title'] );
		wp_safe_redirect( (string) get_edit_post_link( $id, 'url' ) );
		exit;
	}

	/**
	 * Register webhooks on the platforms.
	 */
	public static function register_webhooks() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		check_admin_referer( 'bmnm_webhooks' );
		$o   = self::opts();
		$out = array();
		foreach ( array( 'bale', 'telegram', 'rubika' ) as $net ) {
			$token = BMNM_Social::net( $net, 'token' );
			if ( empty( $o[ $net ]['on'] ) || ! $token ) {
				continue;
			}
			if ( 'rubika' === $net ) {
				$r = wp_remote_post( 'https://botapi.rubika.ir/v3/' . rawurlencode( $token ) . '/updateBotEndpoints', array( 'timeout' => 20, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'url' => self::hook_url( $net ), 'type' => 'ReceiveUpdate' ) ) ) );
				$d = is_wp_error( $r ) ? null : json_decode( (string) wp_remote_retrieve_body( $r ), true );
				$out[ $net ] = ( 'OK' === ( $d['status'] ?? '' ) ) ? 'ok' : ( is_wp_error( $r ) ? $r->get_error_message() : 'رد شد' );
			} else {
				$base = 'bale' === $net ? 'https://tapi.bale.ai/bot' : 'https://api.telegram.org/bot';
				$body = array( 'url' => self::hook_url( $net ), 'allowed_updates' => wp_json_encode( array( 'channel_post' ) ) );
				if ( 'telegram' === $net ) {
					$body['secret_token'] = $o['key'];
				}
				$r = wp_remote_post( $base . $token . '/setWebhook', array( 'timeout' => 20, 'body' => $body ) );
				$d = is_wp_error( $r ) ? null : json_decode( (string) wp_remote_retrieve_body( $r ), true );
				$out[ $net ] = ! empty( $d['ok'] ) ? 'ok' : ( is_wp_error( $r ) ? $r->get_error_message() : (string) ( $d['description'] ?? 'رد شد' ) );
			}
		}
		if ( ! $out ) {
			$out['bale'] = 'دریافت خودکار برای هیچ شبکه‌ای فعال نیست.';
		}
		set_transient( 'bmnm_test_' . get_current_user_id(), $out, 300 );
		wp_safe_redirect( admin_url( 'admin.php?page=bmnm-social&test=1' ) );
		exit;
	}
}
