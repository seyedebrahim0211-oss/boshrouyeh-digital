<?php
/**
 * Citizen requests: 137 reports (photo + location), general service requests, mayor appointments,
 * feedback. Tracking codes, status timeline, SMS notifications and a staff workflow in wp-admin.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Requests.
 */
final class BMN_Requests {

	const PT        = 'bmn_request';
	const MAX_BYTES = 6291456; // 6 MB.

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'add_meta_boxes_' . self::PT, array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_' . self::PT, array( __CLASS__, 'save_admin' ), 10, 2 );
		add_filter( 'manage_' . self::PT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::PT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_query' ) );
		add_action( 'admin_post_bmn_request_photo', array( __CLASS__, 'admin_photo' ) );
	}

	/**
	 * Types.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'report137'   => __( 'گزارش ۱۳۷', 'boshrouyeh-eservices' ),
			'request'     => __( 'درخواست خدمت', 'boshrouyeh-eservices' ),
			'appointment' => __( 'نوبت ملاقات', 'boshrouyeh-eservices' ),
			'feedback'    => __( 'انتقاد و پیشنهاد', 'boshrouyeh-eservices' ),
			'complaint'   => __( 'شکایت از عملکرد (بازرسی)', 'boshrouyeh-eservices' ),
		);
	}

	/**
	 * 137 categories.
	 *
	 * @return array
	 */
	public static function categories() {
		return apply_filters(
			'bmn_137_categories',
			array(
				'lighting'    => __( 'روشنایی', 'boshrouyeh-eservices' ),
				'waste'       => __( 'پسماند', 'boshrouyeh-eservices' ),
				'asphalt'     => __( 'آسفالت', 'boshrouyeh-eservices' ),
				'green'       => __( 'فضای سبز', 'boshrouyeh-eservices' ),
				'obstruction' => __( 'سد معبر', 'boshrouyeh-eservices' ),
				'water'       => __( 'آب‌گرفتگی', 'boshrouyeh-eservices' ),
				'other'       => __( 'سایر', 'boshrouyeh-eservices' ),
			)
		);
	}

	/**
	 * Statuses: key => [label, progress (0-4), chip class].
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'new'      => array( __( 'ثبت شد', 'boshrouyeh-eservices' ), 1, 'kh-chip--review' ),
			'review'   => array( __( 'در حال بررسی', 'boshrouyeh-eservices' ), 2, 'kh-chip--review' ),
			'need'     => array( __( 'نیاز به مدرک', 'boshrouyeh-eservices' ), 2, 'kh-chip--need' ),
			'progress' => array( __( 'در حال انجام', 'boshrouyeh-eservices' ), 3, 'kh-chip--fast' ),
			'done'     => array( __( 'انجام شد', 'boshrouyeh-eservices' ), 4, 'kh-chip--done' ),
			'rejected' => array( __( 'رد شد', 'boshrouyeh-eservices' ), 4, 'kh-chip--closed' ),
		);
	}

	/**
	 * Register CPT (staff-only).
	 */
	public static function register() {
		register_post_type(
			self::PT,
			array(
				'labels'          => array(
					'name'          => __( 'درخواست‌ها و گزارش‌ها', 'boshrouyeh-eservices' ),
					'singular_name' => __( 'درخواست', 'boshrouyeh-eservices' ),
					'edit_item'     => __( 'رسیدگی به درخواست', 'boshrouyeh-eservices' ),
					'menu_name'     => __( 'درخواست‌ها / ۱۳۷', 'boshrouyeh-eservices' ),
					'search_items'  => __( 'جست‌وجو (کد پیگیری، متن)', 'boshrouyeh-eservices' ),
					'not_found'     => __( 'درخواستی نیست.', 'boshrouyeh-eservices' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-format-chat',
				'menu_position'   => 4,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => array( 'bmn_request', 'bmn_requests' ),
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}

	/**
	 * REST routes.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/requests',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create' ),
				'permission_callback' => '__return_true', // Checked per type inside.
			)
		);
		register_rest_route(
			'bmn/v1',
			'/requests/track',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'track' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'code'   => array( 'type' => 'string', 'required' => true, 'maxLength' => 20 ),
					'phone4' => array( 'type' => 'string', 'default' => '', 'maxLength' => 8 ),
				),
			)
		);
		register_rest_route(
			'bmn/v1',
			'/me/requests',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'mine' ),
				'permission_callback' => array( 'BMN_Auth', 'logged_in' ),
			)
		);
		register_rest_route(
			'bmn/v1',
			'/requests/(?P<id>\d+)/photo',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'photo' ),
				'permission_callback' => array( 'BMN_Auth', 'logged_in' ),
				'args'                => array( 'id' => array( 'type' => 'integer', 'required' => true ) ),
			)
		);
	}

	/**
	 * Unique numeric tracking code.
	 *
	 * @return string
	 */
	private static function tracking_code() {
		do {
			$code = (string) random_int( 1000000000, 9999999999 );
		} while ( self::find_by_code( $code ) );
		return $code;
	}

	/**
	 * Find request by tracking code.
	 *
	 * @param string $code Code.
	 * @return WP_Post|null
	 */
	public static function find_by_code( $code ) {
		$q = get_posts(
			array(
				'post_type'      => self::PT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_bmn_tracking', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		);
		return $q ? $q[0] : null;
	}

	/**
	 * POST /requests
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create( $req ) {
		$type = sanitize_key( (string) $req->get_param( 'type' ) );
		if ( ! array_key_exists( $type, self::types() ) ) {
			return bmn_es_error( 'type', __( 'نوع درخواست نامعتبر است.', 'boshrouyeh-eservices' ) );
		}
		$uid = get_current_user_id();
		if ( 'feedback' !== $type && ! $uid ) {
			return bmn_es_error( 'auth', __( 'ابتدا با شمارهٔ همراه وارد شوید.', 'boshrouyeh-eservices' ), 401 );
		}
		// Cookie-authenticated browsers must send the REST nonce (CSRF); WP core enforces it for logged-in users.
		if ( '' !== (string) $req->get_param( 'website' ) ) { // Honeypot.
			return bmn_es_error( 'spam', __( 'درخواست نامعتبر.', 'boshrouyeh-eservices' ) );
		}
		$bucket = $uid ? 'req_u_' . $uid : 'req_ip_' . bmn_client_ip();
		if ( ! bmn_rate_limit( $bucket, $uid ? 10 : 4, HOUR_IN_SECONDS ) ) {
			return bmn_es_error( 'rate', __( 'تعداد درخواست‌ها زیاد است؛ کمی بعد تلاش کنید.', 'boshrouyeh-eservices' ), 429 );
		}

		$desc = trim( sanitize_textarea_field( (string) $req->get_param( 'description' ) ) );
		$len  = mb_strlen( $desc );
		if ( $len < 5 || $len > 2000 ) {
			return bmn_es_error( 'desc', __( 'توضیحات باید بین ۵ تا ۲۰۰۰ نویسه باشد.', 'boshrouyeh-eservices' ) );
		}

		$meta = array(
			'type'   => $type,
			'status' => 'new',
		);
		$title = self::types()[ $type ];

		if ( 'report137' === $type ) {
			$cat = sanitize_key( (string) $req->get_param( 'category' ) );
			if ( ! array_key_exists( $cat, self::categories() ) ) {
				return bmn_es_error( 'category', __( 'موضوع گزارش را انتخاب کنید.', 'boshrouyeh-eservices' ) );
			}
			$meta['category'] = $cat;
			$title           .= ' — ' . self::categories()[ $cat ];
		}
		$lat = $req->get_param( 'lat' );
		$lng = $req->get_param( 'lng' );
		if ( null !== $lat && '' !== $lat && null !== $lng && '' !== $lng ) {
			$lat = (float) bmn_en_digits( (string) $lat );
			$lng = (float) bmn_en_digits( (string) $lng );
			if ( $lat < 24 || $lat > 40.5 || $lng < 44 || $lng > 63.5 ) {
				return bmn_es_error( 'location', __( 'موقعیت انتخاب‌شده خارج از محدودهٔ مجاز است.', 'boshrouyeh-eservices' ) );
			}
			$meta['lat'] = round( $lat, 6 );
			$meta['lng'] = round( $lng, 6 );
		}
		$address = mb_substr( sanitize_text_field( (string) $req->get_param( 'address' ) ), 0, 200 );
		if ( $address ) {
			$meta['address'] = $address;
		}
		$service = absint( $req->get_param( 'service_id' ) );
		if ( $service && 'bmn_service' === get_post_type( $service ) ) {
			$meta['service_id'] = $service;
			$title              = get_the_title( $service );
		}
		if ( 'appointment' === $type ) {
			$subject = mb_substr( sanitize_text_field( (string) $req->get_param( 'subject' ) ), 0, 120 );
			$date    = BMN_Jalali::parse( (string) $req->get_param( 'preferred_date' ) );
			if ( ! $subject ) {
				return bmn_es_error( 'subject', __( 'موضوع ملاقات را بنویسید.', 'boshrouyeh-eservices' ) );
			}
			$title .= ' — ' . $subject;
			if ( $date ) {
				$meta['preferred'] = $date;
			}
		}
		if ( 'feedback' === $type ) {
			$kind = sanitize_key( (string) $req->get_param( 'kind' ) );
			$kinds = array( 'critic' => 'انتقاد', 'suggest' => 'پیشنهاد', 'thanks' => 'قدردانی' );
			$meta['kind']   = isset( $kinds[ $kind ] ) ? $kind : 'suggest';
			$title         .= ' — ' . $kinds[ $meta['kind'] ];
			$rating = absint( $req->get_param( 'rating' ) );
			if ( $rating >= 1 && $rating <= 5 ) {
				$meta['rating'] = $rating;
			}
		}

		$phone = $uid ? (string) get_user_meta( $uid, '_bmn_phone', true ) : bmn_normalize_mobile( (string) $req->get_param( 'phone' ) );
		if ( $phone ) {
			$meta['phone'] = $phone;
		}

		// Photo (optional for all, encouraged for 137).
		$files = $req->get_file_params();
		$photo = null;
		if ( ! empty( $files['photo'] ) && UPLOAD_ERR_NO_FILE !== (int) $files['photo']['error'] ) {
			$photo = self::store_photo( $files['photo'] );
			if ( is_wp_error( $photo ) ) {
				return $photo;
			}
		}

		$id = wp_insert_post(
			array(
				'post_type'    => self::PT,
				'post_status'  => 'private',
				'post_title'   => wp_strip_all_tags( $title ),
				'post_content' => $desc,
				'post_author'  => $uid,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return bmn_es_error( 'save', __( 'ثبت ناموفق بود.', 'boshrouyeh-eservices' ), 500 );
		}
		$meta['tracking'] = self::tracking_code();
		if ( $photo ) {
			$meta['photo'] = $photo;
		}
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, '_bmn_' . $k, $v );
		}
		self::add_timeline( $id, 'new', '' );

		if ( $phone ) {
			/* translators: 1: type label, 2: tracking code. */
			BMN_SMS::send( $phone, sprintf( __( 'شهرداری بشرویه: %1$s شما با کد پیگیری %2$s ثبت شد.', 'boshrouyeh-eservices' ), self::types()[ $type ], $meta['tracking'] ) );
		}
		self::notify_staff( $id );
		do_action( 'bmn_request_created', $id, $type );

		return new WP_REST_Response(
			array(
				'id'       => $id,
				'tracking' => $meta['tracking'],
				'status'   => 'new',
				'unit'     => class_exists( 'BMN_Staff' ) ? BMN_Staff::post_unit_name( $id ) : '',
				'message'  => __( 'با موفقیت ثبت شد.', 'boshrouyeh-eservices' ),
			),
			201
		);
	}

	/**
	 * Validate, re-encode (strips EXIF/GPS) and store an uploaded photo in the private dir.
	 *
	 * @param array $file $_FILES entry.
	 * @return string|WP_Error Relative path inside private dir.
	 */
	private static function store_photo( $file ) {
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ( ! is_uploaded_file( $file['tmp_name'] ) && ! apply_filters( 'bmn_allow_test_uploads', false ) ) ) {
			return bmn_es_error( 'upload', __( 'بارگذاری تصویر ناموفق بود.', 'boshrouyeh-eservices' ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return bmn_es_error( 'size', __( 'حجم تصویر حداکثر ۶ مگابایت است.', 'boshrouyeh-eservices' ) );
		}
		$check = wp_check_filetype_and_ext(
			$file['tmp_name'],
			sanitize_file_name( (string) $file['name'] ),
			array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
			)
		);
		$info  = @getimagesize( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( empty( $check['type'] ) || ! $info || ! in_array( $info['mime'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return bmn_es_error( 'mime', __( 'فقط تصویر JPG، PNG یا WebP مجاز است.', 'boshrouyeh-eservices' ) );
		}
		$dir = BMN_ES_Install::private_dir() . '/' . gmdate( 'Y/m' );
		wp_mkdir_p( $dir );
		$name   = bin2hex( random_bytes( 16 ) ) . '.jpg';
		$editor = wp_get_image_editor( $file['tmp_name'] );
		if ( is_wp_error( $editor ) ) {
			return bmn_es_error( 'image', __( 'پردازش تصویر ناموفق بود.', 'boshrouyeh-eservices' ) );
		}
		$editor->resize( 1600, 1600, false );
		$editor->set_quality( 82 );
		$saved = $editor->save( $dir . '/' . $name, 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			return bmn_es_error( 'image', __( 'ذخیرهٔ تصویر ناموفق بود.', 'boshrouyeh-eservices' ) );
		}
		return gmdate( 'Y/m' ) . '/' . $name;
	}

	/**
	 * Append to timeline.
	 *
	 * @param int    $id     Post.
	 * @param string $status Status.
	 * @param string $note   Note.
	 */
	public static function add_timeline( $id, $status, $note ) {
		$tl   = get_post_meta( $id, '_bmn_timeline', true );
		$tl   = is_array( $tl ) ? $tl : array();
		$tl[] = array(
			's'  => $status,
			'n'  => mb_substr( (string) $note, 0, 500 ),
			't'  => time(),
			'by' => get_current_user_id(),
		);
		update_post_meta( $id, '_bmn_timeline', $tl );
	}

	/**
	 * Email staff about a new request.
	 *
	 * @param int $id Post.
	 */
	private static function notify_staff( $id ) {
		$to = (string) bmn_es_option( 'staff_email', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}
		/* translators: %s: title. */
		wp_mail( $to, sprintf( __( '[شهرداری] درخواست جدید: %s', 'boshrouyeh-eservices' ), get_the_title( $id ) ), admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	}

	/**
	 * Public DTO (no phone, no internal notes author).
	 *
	 * @param WP_Post $p Post.
	 * @return array
	 */
	public static function dto( $p ) {
		$st     = self::statuses();
		$status = (string) get_post_meta( $p->ID, '_bmn_status', true );
		$status = isset( $st[ $status ] ) ? $status : 'new';
		$tl     = get_post_meta( $p->ID, '_bmn_timeline', true );
		$type   = (string) get_post_meta( $p->ID, '_bmn_type', true );
		return array(
			'id'           => $p->ID,
			'tracking'     => (string) get_post_meta( $p->ID, '_bmn_tracking', true ),
			'type'         => $type,
			'type_label'   => self::types()[ $type ] ?? '',
			'title'        => html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ),
			'status'       => $status,
			'status_label' => $st[ $status ][0],
			'progress'     => $st[ $status ][1],
			'created_fa'   => bmn_jdate( 'j F Y', (int) get_post_timestamp( $p ) ),
			'has_photo'    => (bool) get_post_meta( $p->ID, '_bmn_photo', true ),
			'unit'         => class_exists( 'BMN_Staff' ) ? BMN_Staff::post_unit_name( $p->ID ) : '',
			'timeline'     => array_map(
				fn( $e ) => array(
					'kind'         => empty( $e['u'] ) ? 'status' : 'referral',
					'status_label' => empty( $e['u'] ) ? ( $st[ $e['s'] ][0] ?? '' ) : sprintf( /* translators: %s: unit. */ __( 'ارجاع به %s', 'boshrouyeh-eservices' ), $e['u'] ),
					'unit'         => (string) ( $e['u'] ?? '' ),
					'note'         => (string) $e['n'],
					'date_fa'      => bmn_jdate( 'j F Y - H:i', (int) $e['t'] ),
				),
				is_array( $tl ) ? $tl : array()
			),
		);
	}

	/**
	 * GET /requests/track
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function track( $req ) {
		if ( ! bmn_rate_limit( 'track_' . bmn_client_ip(), 30, 10 * MINUTE_IN_SECONDS ) ) {
			return bmn_es_error( 'rate', __( 'درخواست‌ها زیاد است؛ کمی بعد تلاش کنید.', 'boshrouyeh-eservices' ), 429 );
		}
		$code = preg_replace( '/\D/', '', bmn_en_digits( (string) $req['code'] ) );
		$p    = strlen( $code ) === 10 ? self::find_by_code( $code ) : null;
		$deny = bmn_es_error( 'not_found', __( 'درخواستی با این مشخصات پیدا نشد.', 'boshrouyeh-eservices' ), 404 );
		if ( ! $p ) {
			return $deny;
		}
		$uid = get_current_user_id();
		if ( ! $uid || (int) $p->post_author !== $uid ) {
			$phone = (string) get_post_meta( $p->ID, '_bmn_phone', true );
			$four  = preg_replace( '/\D/', '', bmn_en_digits( (string) $req['phone4'] ) );
			if ( ! current_user_can( 'edit_bmn_requests' ) && ( strlen( $four ) !== 4 || ! $phone || ! hash_equals( substr( $phone, -4 ), $four ) ) ) {
				return $deny;
			}
		}
		$res = rest_ensure_response( self::dto( $p ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/**
	 * GET /me/requests
	 *
	 * @return WP_REST_Response
	 */
	public static function mine() {
		$posts = get_posts(
			array(
				'post_type'      => self::PT,
				'post_status'    => 'any',
				'author'         => get_current_user_id(),
				'posts_per_page' => 50,
				'no_found_rows'  => true,
			)
		);
		return rest_ensure_response( array_map( array( __CLASS__, 'dto' ), $posts ) );
	}

	/**
	 * Count user's requests by type.
	 *
	 * @param int   $uid   User.
	 * @param array $types Types.
	 * @return int
	 */
	public static function count_for_user( $uid, $types ) {
		$q = new WP_Query(
			array(
				'post_type'      => self::PT,
				'post_status'    => 'any',
				'author'         => $uid,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_bmn_type', 'value' => $types, 'compare' => 'IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Stream a private photo.
	 *
	 * @param int $id Post id.
	 */
	private static function stream_photo( $id ) {
		$rel  = (string) get_post_meta( $id, '_bmn_photo', true );
		$base = realpath( BMN_ES_Install::private_dir() );
		$path = $rel ? realpath( $base . '/' . $rel ) : false;
		if ( ! $path || ! $base || ! str_starts_with( $path, $base . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			status_header( 404 );
			exit;
		}
		nocache_headers();
		header( 'Content-Type: image/jpeg' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: inline; filename="report-' . (int) $id . '.jpg"' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * GET /requests/{id}/photo — owner or staff.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_Error|void
	 */
	public static function photo( $req ) {
		$p = get_post( (int) $req['id'] );
		if ( ! $p || self::PT !== $p->post_type || ( (int) $p->post_author !== get_current_user_id() && ! current_user_can( 'edit_post', $p->ID ) ) ) {
			return bmn_es_error( 'forbidden', __( 'دسترسی ندارید.', 'boshrouyeh-eservices' ), 403 );
		}
		self::stream_photo( $p->ID );
	}

	/**
	 * Admin photo endpoint (cookie + nonce).
	 */
	public static function admin_photo() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'bmn_photo_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( '', 403 );
		}
		self::stream_photo( $id );
	}

	/* ---------------- Admin UI ---------------- */

	/**
	 * Columns.
	 *
	 * @param array $cols Cols.
	 * @return array
	 */
	public static function columns( $cols ) {
		return array(
			'cb'           => $cols['cb'],
			'title'        => __( 'عنوان', 'boshrouyeh-eservices' ),
			'bmn_tracking' => __( 'کد پیگیری', 'boshrouyeh-eservices' ),
			'bmn_type'     => __( 'نوع', 'boshrouyeh-eservices' ),
			'bmn_status'   => __( 'وضعیت', 'boshrouyeh-eservices' ),
			'date'         => __( 'تاریخ', 'boshrouyeh-eservices' ),
		);
	}

	/**
	 * Column values.
	 *
	 * @param string $col Col.
	 * @param int    $id  Post.
	 */
	public static function column( $col, $id ) {
		$st = self::statuses();
		switch ( $col ) {
			case 'bmn_tracking':
				echo '<code>' . esc_html( (string) get_post_meta( $id, '_bmn_tracking', true ) ) . '</code>';
				break;
			case 'bmn_type':
				echo esc_html( self::types()[ (string) get_post_meta( $id, '_bmn_type', true ) ] ?? '' );
				break;
			case 'bmn_status':
				$s = (string) get_post_meta( $id, '_bmn_status', true );
				echo '<strong>' . esc_html( $st[ $s ][0] ?? '' ) . '</strong>';
				break;
		}
	}

	/**
	 * List filters.
	 *
	 * @param string $post_type Type.
	 */
	public static function filters( $post_type ) {
		if ( self::PT !== $post_type ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$t = isset( $_GET['bmn_type'] ) ? sanitize_key( $_GET['bmn_type'] ) : '';
		$s = isset( $_GET['bmn_status'] ) ? sanitize_key( $_GET['bmn_status'] ) : '';
		// phpcs:enable
		echo '<select name="bmn_type"><option value="">' . esc_html__( 'همهٔ انواع', 'boshrouyeh-eservices' ) . '</option>';
		foreach ( self::types() as $k => $v ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $t, $k, false ), esc_html( $v ) );
		}
		echo '</select><select name="bmn_status"><option value="">' . esc_html__( 'همهٔ وضعیت‌ها', 'boshrouyeh-eservices' ) . '</option>';
		foreach ( self::statuses() as $k => $v ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $s, $k, false ), esc_html( $v[0] ) );
		}
		echo '</select>';
	}

	/**
	 * Apply list filters + search by tracking code.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function admin_query( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || self::PT !== $q->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$mq = array();
		if ( ! empty( $_GET['bmn_type'] ) ) {
			$mq[] = array( 'key' => '_bmn_type', 'value' => sanitize_key( $_GET['bmn_type'] ) );
		}
		if ( ! empty( $_GET['bmn_status'] ) ) {
			$mq[] = array( 'key' => '_bmn_status', 'value' => sanitize_key( $_GET['bmn_status'] ) );
		}
		$s = $q->get( 's' );
		if ( $s && preg_match( '/^\d{10}$/', bmn_en_digits( $s ) ) ) {
			$q->set( 's', '' );
			$mq[] = array( 'key' => '_bmn_tracking', 'value' => bmn_en_digits( $s ) );
		}
		// phpcs:enable
		if ( $mq ) {
			$q->set( 'meta_query', $mq ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
	}

	/**
	 * Meta boxes.
	 */
	public static function boxes() {
		add_meta_box( 'bmn_req_info', __( 'جزئیات و رسیدگی', 'boshrouyeh-eservices' ), array( __CLASS__, 'box' ), self::PT, 'normal', 'high' );
	}

	/**
	 * Render handling box.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function box( $p ) {
		wp_nonce_field( 'bmn_req_save', 'bmn_req_nonce' );
		$d      = self::dto( $p );
		$phone  = (string) get_post_meta( $p->ID, '_bmn_phone', true );
		$lat    = get_post_meta( $p->ID, '_bmn_lat', true );
		$lng    = get_post_meta( $p->ID, '_bmn_lng', true );
		$cat    = (string) get_post_meta( $p->ID, '_bmn_category', true );
		$pref   = (int) get_post_meta( $p->ID, '_bmn_preferred', true );
		echo '<table class="form-table"><tbody>';
		printf( '<tr><th>%s</th><td><code style="font-size:16px">%s</code></td></tr>', esc_html__( 'کد پیگیری', 'boshrouyeh-eservices' ), esc_html( $d['tracking'] ) );
		printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'نوع', 'boshrouyeh-eservices' ), esc_html( $d['type_label'] . ( $cat ? ' — ' . ( self::categories()[ $cat ] ?? '' ) : '' ) ) );
		if ( $phone ) {
			printf( '<tr><th>%s</th><td dir="ltr" style="text-align:right"><a href="tel:%s">%s</a></td></tr>', esc_html__( 'همراه', 'boshrouyeh-eservices' ), esc_attr( $phone ), esc_html( $phone ) );
		}
		if ( $pref ) {
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'تاریخ پیشنهادی', 'boshrouyeh-eservices' ), esc_html( bmn_jdate( 'l j F Y', $pref ) ) );
		}
		if ( '' !== (string) $lat && '' !== (string) $lng ) {
			$osm = sprintf( 'https://www.openstreetmap.org/?mlat=%1$F&mlon=%2$F#map=18/%1$F/%2$F', (float) $lat, (float) $lng );
			printf( '<tr><th>%s</th><td><a href="%s" target="_blank" rel="noopener">%s</a> %s</td></tr>', esc_html__( 'موقعیت', 'boshrouyeh-eservices' ), esc_url( $osm ), esc_html( $lat . ', ' . $lng ), esc_html( (string) get_post_meta( $p->ID, '_bmn_address', true ) ) );
		}
		if ( $d['has_photo'] ) {
			$src = wp_nonce_url( admin_url( 'admin-post.php?action=bmn_request_photo&id=' . $p->ID ), 'bmn_photo_' . $p->ID );
			printf( '<tr><th>%1$s</th><td><a href="%2$s" target="_blank"><img src="%2$s" alt="" style="max-width:360px;border-radius:8px"></a></td></tr>', esc_html__( 'تصویر', 'boshrouyeh-eservices' ), esc_url( $src ) );
		}
		if ( class_exists( 'BMN_Staff' ) ) {
			$cur_unit = BMN_Staff::post_unit( $p->ID );
			echo '<tr><th>' . esc_html__( 'واحد مسئول', 'boshrouyeh-eservices' ) . '</th><td>';
			if ( current_user_can( 'bmn_refer' ) ) {
				echo '<select name="bmn_unit"><option value="0">—</option>';
				foreach ( BMN_Staff::units() as $uid_ => $uname ) {
					printf( '<option value="%d" %s>%s</option>', (int) $uid_, selected( $cur_unit, $uid_, false ), esc_html( $uname ) );
				}
				echo '</select> <span class="description">' . esc_html__( 'تغییر واحد = ارجاع؛ شهروند در پیگیری می‌بیند به کدام واحد ارجاع شده است.', 'boshrouyeh-eservices' ) . '</span>';
			} else {
				echo esc_html( $d['unit'] ? $d['unit'] : '—' );
			}
			echo '</td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'تغییر وضعیت', 'boshrouyeh-eservices' ) . '</th><td><select name="bmn_status">';
		foreach ( self::statuses() as $k => $v ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $d['status'], $k, false ), esc_html( $v[0] ) );
		}
		echo '</select> <label><input type="checkbox" name="bmn_sms" value="1" checked> ' . esc_html__( 'اطلاع با پیامک', 'boshrouyeh-eservices' ) . '</label></td></tr>';
		echo '<tr><th>' . esc_html__( 'یادداشت برای شهروند', 'boshrouyeh-eservices' ) . '</th><td><textarea name="bmn_note" rows="3" class="large-text" maxlength="500"></textarea></td></tr>';
		echo '</tbody></table><h4>' . esc_html__( 'سابقه', 'boshrouyeh-eservices' ) . '</h4><ol>';
		foreach ( $d['timeline'] as $e ) {
			echo '<li><strong>' . esc_html( $e['status_label'] ) . '</strong> — ' . esc_html( $e['date_fa'] ) . ( $e['note'] ? '<br>' . esc_html( $e['note'] ) : '' ) . '</li>';
		}
		echo '</ol>';
	}

	/**
	 * Save status change.
	 *
	 * @param int     $id   Post.
	 * @param WP_Post $post Post.
	 */
	public static function save_admin( $id, $post ) {
		if ( ! isset( $_POST['bmn_req_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmn_req_nonce'] ) ), 'bmn_req_save' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$new  = isset( $_POST['bmn_status'] ) ? sanitize_key( wp_unslash( $_POST['bmn_status'] ) ) : '';
		$note = isset( $_POST['bmn_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bmn_note'] ) ) : '';
		$old  = (string) get_post_meta( $id, '_bmn_status', true );
		$unit = isset( $_POST['bmn_unit'] ) ? absint( $_POST['bmn_unit'] ) : 0;
		if ( $unit && class_exists( 'BMN_Staff' ) && current_user_can( 'bmn_refer' ) && BMN_Staff::refer( $id, $unit, $new === $old ? $note : '' ) ) {
			$phone = (string) get_post_meta( $id, '_bmn_phone', true );
			if ( $phone && ! empty( $_POST['bmn_sms'] ) ) {
				/* translators: 1: tracking, 2: unit. */
				BMN_SMS::send( $phone, sprintf( __( 'شهرداری بشرویه: درخواست %1$s به «%2$s» ارجاع شد.', 'boshrouyeh-eservices' ), get_post_meta( $id, '_bmn_tracking', true ), BMN_Staff::post_unit_name( $id ) ) );
			}
			if ( $new === $old ) {
				$note = '';
			}
		}
		if ( ! array_key_exists( $new, self::statuses() ) || ( $new === $old && '' === $note ) ) {
			return;
		}
		update_post_meta( $id, '_bmn_status', $new );
		self::add_timeline( $id, $new, $note );
		if ( 'private' !== $post->post_status ) {
			remove_action( 'save_post_' . self::PT, array( __CLASS__, 'save_admin' ) );
			wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );
		}
		$phone = (string) get_post_meta( $id, '_bmn_phone', true );
		if ( $phone && ! empty( $_POST['bmn_sms'] ) ) {
			/* translators: 1: tracking, 2: status. */
			BMN_SMS::send( $phone, sprintf( __( 'شهرداری بشرویه: وضعیت درخواست %1$s: %2$s', 'boshrouyeh-eservices' ), get_post_meta( $id, '_bmn_tracking', true ), self::statuses()[ $new ][0] ) . ( $note ? "\n" . $note : '' ) );
		}
		do_action( 'bmn_request_status_changed', $id, $new, $old );
	}
}
