<?php
/**
 * Mayor field visits (بازدید شهردار) and their follow-up dossiers (پروندهٔ پیگیری دستورها).
 *
 * Each directive: precise issue, specific order, responsible unit, deadline, funding source, current status.
 *
 * @package BoshrouyehEservices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Visits & directives.
 */
class BMN_Visits {

	const VISIT = 'bmn_visit';
	const DIR   = 'bmn_directive';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'add_meta_boxes_' . self::VISIT, array( __CLASS__, 'visit_boxes' ) );
		add_action( 'add_meta_boxes_' . self::DIR, array( __CLASS__, 'dir_boxes' ) );
		add_action( 'save_post_' . self::VISIT, array( __CLASS__, 'save_visit' ), 10, 2 );
		add_action( 'save_post_' . self::DIR, array( __CLASS__, 'save_dir' ), 10, 2 );
		add_filter( 'manage_' . self::DIR . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::DIR . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_filter' ) );
		add_filter( 'the_content', array( __CLASS__, 'visit_content' ), 15 );
		add_shortcode( 'bmn_directives', array( __CLASS__, 'shortcode' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'dir_title' ), 10, 2 );
	}

	/**
	 * Status map: key => [label, chip].
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'new'      => array( __( 'ابلاغ شد', 'boshrouyeh-eservices' ), 'kh-chip--review' ),
			'progress' => array( __( 'در حال اجرا', 'boshrouyeh-eservices' ), 'kh-chip--fast' ),
			'stopped'  => array( __( 'متوقف / نیازمند اعتبار', 'boshrouyeh-eservices' ), 'kh-chip--need' ),
			'done'     => array( __( 'انجام شد', 'boshrouyeh-eservices' ), 'kh-chip--done' ),
		);
	}

	/**
	 * Funding sources.
	 *
	 * @return array
	 */
	public static function funding() {
		return apply_filters(
			'bmn_funding_sources',
			array(
				'none'     => __( 'بدون نیاز به اعتبار (نیروی داخلی)', 'boshrouyeh-eservices' ),
				'current'  => __( 'بودجهٔ جاری شهرداری', 'boshrouyeh-eservices' ),
				'civil'    => __( 'اعتبارات عمرانی شهرداری', 'boshrouyeh-eservices' ),
				'province' => __( 'اعتبارات استانی (تملک دارایی)', 'boshrouyeh-eservices' ),
				'national' => __( 'اعتبارات ملی / ارزش افزوده', 'boshrouyeh-eservices' ),
				'people'   => __( 'مشارکت مردمی و خیرین', 'boshrouyeh-eservices' ),
				'pending'  => __( 'در دست تأمین', 'boshrouyeh-eservices' ),
			)
		);
	}

	/**
	 * Register CPTs.
	 */
	public static function register() {
		register_post_type(
			self::VISIT,
			array(
				'labels'          => array(
					'name'          => __( 'بازدیدهای شهردار', 'boshrouyeh-eservices' ),
					'singular_name' => __( 'بازدید', 'boshrouyeh-eservices' ),
					'add_new_item'  => __( 'ثبت بازدید جدید', 'boshrouyeh-eservices' ),
					'edit_item'     => __( 'ویرایش بازدید', 'boshrouyeh-eservices' ),
					'menu_name'     => __( 'بازدید شهردار', 'boshrouyeh-eservices' ),
				),
				'public'          => true,
				'has_archive'     => 'mayor-visits',
				'rewrite'         => array( 'slug' => 'mayor-visits', 'with_front' => false ),
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-location-alt',
				'menu_position'   => 5,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'capability_type' => array( 'bmn_visit', 'bmn_visits' ),
				'map_meta_cap'    => true,
			)
		);
		register_post_type(
			self::DIR,
			array(
				'labels'          => array(
					'name'          => __( 'دستورهای بازدید', 'boshrouyeh-eservices' ),
					'singular_name' => __( 'دستور', 'boshrouyeh-eservices' ),
					'add_new_item'  => __( 'ثبت دستور', 'boshrouyeh-eservices' ),
					'edit_item'     => __( 'پروندهٔ پیگیری دستور', 'boshrouyeh-eservices' ),
					'all_items'     => __( 'پرونده‌های پیگیری', 'boshrouyeh-eservices' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . self::VISIT,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => array( 'bmn_directive', 'bmn_directives' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/* ------------------------------------------------------------------ DTOs */

	/**
	 * Directive → array.
	 *
	 * @param WP_Post $p Post.
	 * @return array
	 */
	public static function dir_dto( $p ) {
		$st       = self::statuses();
		$status   = (string) get_post_meta( $p->ID, '_bmn_dstatus', true );
		$status   = isset( $st[ $status ] ) ? $status : 'new';
		$deadline = (int) get_post_meta( $p->ID, '_bmn_deadline', true );
		$overdue  = $deadline && 'done' !== $status && $deadline < time();
		$fund     = (string) get_post_meta( $p->ID, '_bmn_funding', true );
		$visit_id = (int) get_post_meta( $p->ID, '_bmn_visit', true );
		$visit    = $visit_id ? get_post( $visit_id ) : null;
		$log      = get_post_meta( $p->ID, '_bmn_dlog', true );
		$days     = $deadline ? (int) floor( ( $deadline - time() ) / DAY_IN_SECONDS ) : null;
		return array(
			'id'           => $p->ID,
			'code'         => (string) get_post_meta( $p->ID, '_bmn_dcode', true ),
			'issue'        => (string) get_post_meta( $p->ID, '_bmn_issue', true ),
			'order'        => (string) get_post_meta( $p->ID, '_bmn_order', true ),
			'unit'         => BMN_Staff::post_unit_name( $p->ID ),
			'deadline'     => $deadline ? gmdate( 'c', $deadline ) : '',
			'deadline_fa'  => $deadline ? bmn_jdate( 'j F Y', $deadline ) : '',
			'days_left'    => $days,
			'funding'      => self::funding()[ $fund ] ?? '',
			'funding_note' => (string) get_post_meta( $p->ID, '_bmn_funding_note', true ),
			'status'       => $overdue ? 'overdue' : $status,
			'status_label' => $overdue ? __( 'با تأخیر', 'boshrouyeh-eservices' ) . ' — ' . $st[ $status ][0] : $st[ $status ][0],
			'overdue'      => $overdue,
			'progress'     => 'done' === $status ? 100 : min( 100, max( 0, (int) get_post_meta( $p->ID, '_bmn_progress', true ) ) ),
			'updated_fa'   => bmn_jdate( 'j F Y', (int) get_post_modified_time( 'U', true, $p ) ),
			'visit'        => $visit && 'publish' === $visit->post_status ? array(
				'id'      => $visit->ID,
				'title'   => html_entity_decode( $visit->post_title, ENT_QUOTES, 'UTF-8' ),
				'date_fa' => self::visit_date_fa( $visit->ID ),
				'url'     => get_permalink( $visit ),
			) : null,
			'log'          => array_map(
				fn( $e ) => array(
					'status_label' => $st[ $e['s'] ][0] ?? '',
					'progress'     => (int) ( $e['p'] ?? 0 ),
					'note'         => (string) ( $e['n'] ?? '' ),
					'date_fa'      => bmn_jdate( 'j F Y', (int) $e['t'] ),
				),
				is_array( $log ) ? array_reverse( $log ) : array()
			),
		);
	}

	/**
	 * Visit date (fa).
	 *
	 * @param int $id Visit.
	 * @return string
	 */
	private static function visit_date_fa( $id ) {
		$ts = (int) get_post_meta( $id, '_bmn_vdate', true );
		return bmn_jdate( 'j F Y', $ts ? $ts : (int) get_post_timestamp( $id ) );
	}

	/**
	 * Directives of a visit (published only).
	 *
	 * @param int $visit_id Visit.
	 * @return WP_Post[]
	 */
	public static function directives_of( $visit_id ) {
		return get_posts(
			array(
				'post_type'      => self::DIR,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'meta_key'       => '_bmn_visit', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (int) $visit_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Visit → array.
	 *
	 * @param WP_Post $p    Post.
	 * @param bool    $full Include directives.
	 * @return array
	 */
	public static function visit_dto( $p, $full = false ) {
		$dirs   = array_map( array( __CLASS__, 'dir_dto' ), self::directives_of( $p->ID ) );
		$counts = array(
			'total'    => count( $dirs ),
			'done'     => count( array_filter( $dirs, fn( $d ) => 'done' === $d['status'] ) ),
			'overdue'  => count( array_filter( $dirs, fn( $d ) => $d['overdue'] ) ),
			'progress' => count( array_filter( $dirs, fn( $d ) => in_array( $d['status'], array( 'new', 'progress', 'stopped' ), true ) ) ),
		);
		$img = get_the_post_thumbnail_url( $p, 'large' );
		$out = array(
			'id'           => $p->ID,
			'title'        => html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ),
			'date_fa'      => self::visit_date_fa( $p->ID ),
			'place'        => (string) get_post_meta( $p->ID, '_bmn_place', true ),
			'neighborhood' => (string) get_post_meta( $p->ID, '_bmn_hood', true ),
			'excerpt'      => wp_strip_all_tags( get_the_excerpt( $p ) ),
			'image'        => $img ? $img : '',
			'url'          => get_permalink( $p ),
			'counts'       => $counts,
		);
		if ( $full ) {
			$out['report']     = wp_kses_post( wpautop( do_blocks( $p->post_content ) ) );
			$out['directives'] = $dirs;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ REST */

	/**
	 * Routes.
	 */
	public static function routes() {
		$pub = '__return_true';
		register_rest_route(
			'bmn/v1',
			'/visits',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_visits' ),
				'permission_callback' => $pub,
				'args'                => array( 'page' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 200 ) ),
			)
		);
		register_rest_route(
			'bmn/v1',
			'/visits/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_visit' ),
				'permission_callback' => $pub,
			)
		);
		register_rest_route(
			'bmn/v1',
			'/directives',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_directives' ),
				'permission_callback' => $pub,
				'args'                => array(
					'status' => array( 'type' => 'string', 'enum' => array( '', 'open', 'done', 'overdue' ), 'default' => '' ),
					'unit'   => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'bmn_sanitize_slug' ),
					'code'   => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
		register_rest_route(
			'bmn/v1',
			'/directives/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_stats' ),
				'permission_callback' => $pub,
			)
		);
	}

	/**
	 * GET /visits
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function rest_visits( $req ) {
		$q   = new WP_Query(
			array(
				'post_type'      => self::VISIT,
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'paged'          => (int) $req['page'],
			)
		);
		$res = rest_ensure_response( array_map( fn( $p ) => self::visit_dto( $p ), $q->posts ) );
		$res->header( 'X-WP-TotalPages', (string) $q->max_num_pages );
		$res->header( 'Cache-Control', 'public, max-age=120' );
		return $res;
	}

	/**
	 * GET /visits/{id}
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_visit( $req ) {
		$p = get_post( (int) $req['id'] );
		if ( ! $p || self::VISIT !== $p->post_type || 'publish' !== $p->post_status ) {
			return bmn_es_error( 'not_found', __( 'بازدید پیدا نشد.', 'boshrouyeh-eservices' ), 404 );
		}
		return rest_ensure_response( self::visit_dto( $p, true ) );
	}

	/**
	 * Query published directives.
	 *
	 * @param string $status '', open, done, overdue.
	 * @param string $unit   Unit slug.
	 * @param string $code   Tracking code.
	 * @return array
	 */
	public static function query( $status = '', $unit = '', $code = '' ) {
		$args = array(
			'post_type'      => self::DIR,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);
		if ( $code ) {
			$args['meta_query'][] = array( 'key' => '_bmn_dcode', 'value' => preg_replace( '/\D/', '', bmn_en_digits( $code ) ) );
		}
		if ( 'done' === $status ) {
			$args['meta_query'][] = array( 'key' => '_bmn_dstatus', 'value' => 'done' );
		} elseif ( 'open' === $status || 'overdue' === $status ) {
			$args['meta_query'][] = array( 'key' => '_bmn_dstatus', 'value' => 'done', 'compare' => '!=' );
			if ( 'overdue' === $status ) {
				$args['meta_query'][] = array( 'key' => '_bmn_deadline', 'value' => array( 1, time() ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' );
			}
		}
		if ( $unit ) {
			$args['tax_query'] = array( array( 'taxonomy' => BMN_Staff::TAX, 'field' => 'slug', 'terms' => $unit ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		return array_map( array( __CLASS__, 'dir_dto' ), get_posts( $args ) );
	}

	/**
	 * GET /directives
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function rest_directives( $req ) {
		$res = rest_ensure_response( self::query( (string) $req['status'], (string) $req['unit'], (string) $req['code'] ) );
		$res->header( 'Cache-Control', 'public, max-age=120' );
		return $res;
	}

	/**
	 * Aggregate stats (overall + per unit).
	 *
	 * @return array
	 */
	public static function stats() {
		$all   = self::query();
		$units = array();
		foreach ( $all as $d ) {
			$k = $d['unit'] ? $d['unit'] : '—';
			$units[ $k ] = $units[ $k ] ?? array( 'unit' => $k, 'total' => 0, 'done' => 0, 'overdue' => 0 );
			++$units[ $k ]['total'];
			$units[ $k ]['done']    += 'done' === $d['status'] ? 1 : 0;
			$units[ $k ]['overdue'] += $d['overdue'] ? 1 : 0;
		}
		$total = count( $all );
		$done  = count( array_filter( $all, fn( $d ) => 'done' === $d['status'] ) );
		return array(
			'total'   => $total,
			'done'    => $done,
			'overdue' => count( array_filter( $all, fn( $d ) => $d['overdue'] ) ),
			'open'    => $total - $done,
			'percent' => $total ? (int) round( 100 * $done / $total ) : 0,
			'units'   => array_values( $units ),
			'visits'  => (int) wp_count_posts( self::VISIT )->publish,
		);
	}

	/**
	 * GET /directives/stats
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_stats() {
		$res = rest_ensure_response( self::stats() );
		$res->header( 'Cache-Control', 'public, max-age=300' );
		return $res;
	}

	/* ------------------------------------------------------------------ Admin */

	/**
	 * Visit meta boxes.
	 */
	public static function visit_boxes() {
		add_meta_box( 'bmn_visit_info', __( 'مشخصات بازدید', 'boshrouyeh-eservices' ), array( __CLASS__, 'visit_box' ), self::VISIT, 'side', 'high' );
		add_meta_box( 'bmn_visit_dirs', __( 'دستورها و پرونده‌های پیگیری این بازدید', 'boshrouyeh-eservices' ), array( __CLASS__, 'visit_dirs_box' ), self::VISIT, 'normal', 'high' );
	}

	/**
	 * Visit info box.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function visit_box( $p ) {
		wp_nonce_field( 'bmn_visit_save', 'bmn_visit_nonce' );
		$ts = (int) get_post_meta( $p->ID, '_bmn_vdate', true );
		printf( '<p><label>%s<br><input type="text" name="bmn_vdate" value="%s" placeholder="1405/08/20" class="widefat" dir="ltr"></label></p>', esc_html__( 'تاریخ بازدید (شمسی)', 'boshrouyeh-eservices' ), esc_attr( $ts ? bmn_en_digits( bmn_jdate( 'Y/m/d', $ts ) ) : '' ) );
		printf( '<p><label>%s<br><input type="text" name="bmn_place" value="%s" class="widefat"></label></p>', esc_html__( 'محل بازدید', 'boshrouyeh-eservices' ), esc_attr( (string) get_post_meta( $p->ID, '_bmn_place', true ) ) );
		printf( '<p><label>%s<br><input type="text" name="bmn_hood" value="%s" class="widefat"></label></p>', esc_html__( 'محله', 'boshrouyeh-eservices' ), esc_attr( (string) get_post_meta( $p->ID, '_bmn_hood', true ) ) );
	}

	/**
	 * Directives list inside a visit.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function visit_dirs_box( $p ) {
		$dirs = get_posts(
			array(
				'post_type'      => self::DIR,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'meta_key'       => '_bmn_visit', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $p->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $dirs ) {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'دستور', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'واحد', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'مهلت', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'وضعیت', 'boshrouyeh-eservices' ) . '</th></tr></thead><tbody>';
			foreach ( $dirs as $d ) {
				$x = self::dir_dto( $d );
				printf( '<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_url( (string) get_edit_post_link( $d->ID ) ), esc_html( $d->post_title ), esc_html( $x['unit'] ), esc_html( $x['deadline_fa'] ), esc_html( $x['status_label'] . ' (' . bmn_fa_digits( $x['progress'] ) . '٪)' ) );
			}
			echo '</tbody></table>';
		}
		if ( 'auto-draft' !== $p->post_status && current_user_can( 'bmn_manage_directives' ) ) {
			printf( '<p><a class="button button-primary" href="%s">%s</a></p>', esc_url( admin_url( 'post-new.php?post_type=' . self::DIR . '&bmn_visit=' . $p->ID ) ), esc_html__( '+ افزودن دستور به این بازدید', 'boshrouyeh-eservices' ) );
		} elseif ( 'auto-draft' === $p->post_status ) {
			echo '<p>' . esc_html__( 'پس از ذخیرهٔ بازدید، دستورها را اضافه کنید.', 'boshrouyeh-eservices' ) . '</p>';
		}
	}

	/**
	 * Save visit meta.
	 *
	 * @param int     $id   Post.
	 * @param WP_Post $post Post.
	 */
	public static function save_visit( $id, $post ) {
		if ( ! isset( $_POST['bmn_visit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmn_visit_nonce'] ) ), 'bmn_visit_save' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$ts = BMN_Jalali::parse( isset( $_POST['bmn_vdate'] ) ? sanitize_text_field( wp_unslash( $_POST['bmn_vdate'] ) ) : '' );
		update_post_meta( $id, '_bmn_vdate', $ts );
		update_post_meta( $id, '_bmn_place', isset( $_POST['bmn_place'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['bmn_place'] ) ), 0, 150 ) : '' );
		update_post_meta( $id, '_bmn_hood', isset( $_POST['bmn_hood'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['bmn_hood'] ) ), 0, 80 ) : '' );
	}

	/**
	 * Directive meta box.
	 */
	public static function dir_boxes() {
		add_meta_box( 'bmn_dir_info', __( 'پروندهٔ پیگیری', 'boshrouyeh-eservices' ), array( __CLASS__, 'dir_box' ), self::DIR, 'normal', 'high' );
	}

	/**
	 * Directive editor. Unit staff may only update status/progress/report.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function dir_box( $p ) {
		wp_nonce_field( 'bmn_dir_save', 'bmn_dir_nonce' );
		$full  = current_user_can( 'bmn_manage_directives' );
		$ro    = $full ? '' : ' disabled';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- prefill only.
		$visit = (int) get_post_meta( $p->ID, '_bmn_visit', true ) ?: ( isset( $_GET['bmn_visit'] ) ? absint( $_GET['bmn_visit'] ) : 0 );
		$d     = self::dir_dto( $p );
		$st    = (string) get_post_meta( $p->ID, '_bmn_dstatus', true );
		$fund  = (string) get_post_meta( $p->ID, '_bmn_funding', true );
		$dl    = (int) get_post_meta( $p->ID, '_bmn_deadline', true );
		$unit  = BMN_Staff::post_unit( $p->ID );
		$visits = get_posts( array( 'post_type' => self::VISIT, 'post_status' => array( 'publish', 'draft', 'pending' ), 'posts_per_page' => 60 ) );

		echo '<table class="form-table"><tbody>';
		if ( $d['code'] ) {
			printf( '<tr><th>%s</th><td><code style="font-size:16px">%s</code></td></tr>', esc_html__( 'کد پیگیری عمومی', 'boshrouyeh-eservices' ), esc_html( $d['code'] ) );
		}
		echo '<tr><th>' . esc_html__( 'بازدید', 'boshrouyeh-eservices' ) . '</th><td><select name="bmn_visit"' . esc_attr( $ro ) . '><option value="0">—</option>';
		foreach ( $visits as $v ) {
			printf( '<option value="%d" %s>%s</option>', (int) $v->ID, selected( $visit, $v->ID, false ), esc_html( $v->post_title . ' — ' . self::visit_date_fa( $v->ID ) ) );
		}
		echo '</select></td></tr>';
		printf( '<tr><th>%s</th><td><textarea name="bmn_issue" rows="3" class="large-text" maxlength="1000"%s>%s</textarea><p class="description">%s</p></td></tr>', esc_html__( '۱. مسئلهٔ دقیق', 'boshrouyeh-eservices' ), esc_attr( $ro ), esc_textarea( $d['issue'] ), esc_html__( 'مشکل مشاهده‌شده را دقیق و قابل‌سنجش بنویسید (کجا، چه، چقدر).', 'boshrouyeh-eservices' ) );
		printf( '<tr><th>%s</th><td><textarea name="bmn_order" rows="3" class="large-text" maxlength="1000"%s>%s</textarea></td></tr>', esc_html__( '۲. دستور مشخص', 'boshrouyeh-eservices' ), esc_attr( $ro ), esc_textarea( $d['order'] ) );
		echo '<tr><th>' . esc_html__( '۳. واحد مسئول', 'boshrouyeh-eservices' ) . '</th><td><select name="bmn_unit"' . esc_attr( $ro ) . '><option value="0">—</option>';
		foreach ( BMN_Staff::units() as $id => $name ) {
			printf( '<option value="%d" %s>%s</option>', (int) $id, selected( $unit, $id, false ), esc_html( $name ) );
		}
		echo '</select></td></tr>';
		printf( '<tr><th>%s</th><td><input type="text" name="bmn_deadline" value="%s" placeholder="1405/09/30" dir="ltr"%s> %s</td></tr>', esc_html__( '۴. مهلت اجرا (شمسی)', 'boshrouyeh-eservices' ), esc_attr( $dl ? bmn_en_digits( bmn_jdate( 'Y/m/d', $dl ) ) : '' ), esc_attr( $ro ), $d['overdue'] ? '<strong style="color:#a8442a">' . esc_html__( 'مهلت گذشته است', 'boshrouyeh-eservices' ) . '</strong>' : '' );
		echo '<tr><th>' . esc_html__( '۵. منبع اعتبار', 'boshrouyeh-eservices' ) . '</th><td><select name="bmn_funding"' . esc_attr( $ro ) . '><option value="">—</option>';
		foreach ( self::funding() as $k => $v ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $fund, $k, false ), esc_html( $v ) );
		}
		printf( '</select> <input type="text" name="bmn_funding_note" value="%s" placeholder="%s" class="regular-text"%s></td></tr>', esc_attr( $d['funding_note'] ), esc_attr__( 'شرح / ردیف بودجه / مبلغ', 'boshrouyeh-eservices' ), esc_attr( $ro ) );
		echo '<tr><th>' . esc_html__( '۶. وضعیت فعلی', 'boshrouyeh-eservices' ) . '</th><td><select name="bmn_dstatus">';
		foreach ( self::statuses() as $k => $v ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $st ? $st : 'new', $k, false ), esc_html( $v[0] ) );
		}
		printf( '</select> <label>%s <input type="number" name="bmn_progress" min="0" max="100" step="5" value="%d" style="width:80px">٪</label></td></tr>', esc_html__( 'پیشرفت', 'boshrouyeh-eservices' ), (int) $d['progress'] );
		printf( '<tr><th>%s</th><td><textarea name="bmn_dnote" rows="2" class="large-text" maxlength="500" placeholder="%s"></textarea></td></tr>', esc_html__( 'گزارش اقدام (عمومی)', 'boshrouyeh-eservices' ), esc_attr__( 'مثلاً: پیمانکار مستقر شد؛ ۴۰٪ لکه‌گیری انجام شد.', 'boshrouyeh-eservices' ) );
		echo '</tbody></table>';
		if ( $d['log'] ) {
			echo '<h4>' . esc_html__( 'سابقهٔ اقدامات', 'boshrouyeh-eservices' ) . '</h4><ol>';
			foreach ( $d['log'] as $e ) {
				echo '<li><strong>' . esc_html( $e['status_label'] ) . '</strong> (' . esc_html( bmn_fa_digits( $e['progress'] ) ) . '٪) — ' . esc_html( $e['date_fa'] ) . ( $e['note'] ? '<br>' . esc_html( $e['note'] ) : '' ) . '</li>';
			}
			echo '</ol>';
		}
	}

	/**
	 * Auto title from the order text.
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Raw.
	 * @return array
	 */
	public static function dir_title( $data, $postarr ) {
		if ( self::DIR === $data['post_type'] && '' === trim( (string) $data['post_title'] ) && isset( $_POST['bmn_order'], $_POST['bmn_dir_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmn_dir_nonce'] ) ), 'bmn_dir_save' ) ) {
			$data['post_title'] = wp_trim_words( sanitize_textarea_field( wp_unslash( $_POST['bmn_order'] ) ), 12, '…' );
		}
		return $data;
	}

	/**
	 * Save directive.
	 *
	 * @param int     $id   Post.
	 * @param WP_Post $post Post.
	 */
	public static function save_dir( $id, $post ) {
		if ( ! isset( $_POST['bmn_dir_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmn_dir_nonce'] ) ), 'bmn_dir_save' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		if ( ! get_post_meta( $id, '_bmn_dcode', true ) ) {
			do {
				$code = (string) wp_rand( 100000, 999999 );
			} while ( get_posts( array( 'post_type' => self::DIR, 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => '_bmn_dcode', 'meta_value' => $code ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			update_post_meta( $id, '_bmn_dcode', $code );
		}
		if ( current_user_can( 'bmn_manage_directives' ) ) {
			update_post_meta( $id, '_bmn_visit', isset( $_POST['bmn_visit'] ) ? absint( $_POST['bmn_visit'] ) : 0 );
			update_post_meta( $id, '_bmn_issue', isset( $_POST['bmn_issue'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['bmn_issue'] ) ), 0, 1000 ) : '' );
			update_post_meta( $id, '_bmn_order', isset( $_POST['bmn_order'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['bmn_order'] ) ), 0, 1000 ) : '' );
			update_post_meta( $id, '_bmn_deadline', BMN_Jalali::parse( isset( $_POST['bmn_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['bmn_deadline'] ) ) : '' ) );
			$fund = isset( $_POST['bmn_funding'] ) ? sanitize_key( wp_unslash( $_POST['bmn_funding'] ) ) : '';
			update_post_meta( $id, '_bmn_funding', isset( self::funding()[ $fund ] ) ? $fund : '' );
			update_post_meta( $id, '_bmn_funding_note', isset( $_POST['bmn_funding_note'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['bmn_funding_note'] ) ), 0, 200 ) : '' );
			$unit = isset( $_POST['bmn_unit'] ) ? absint( $_POST['bmn_unit'] ) : 0;
			if ( $unit && isset( BMN_Staff::units()[ $unit ] ) ) {
				wp_set_object_terms( $id, array( $unit ), BMN_Staff::TAX, false );
			}
		}
		$status   = isset( $_POST['bmn_dstatus'] ) ? sanitize_key( wp_unslash( $_POST['bmn_dstatus'] ) ) : 'new';
		$status   = isset( self::statuses()[ $status ] ) ? $status : 'new';
		$progress = isset( $_POST['bmn_progress'] ) ? min( 100, max( 0, absint( $_POST['bmn_progress'] ) ) ) : 0;
		$progress = 'done' === $status ? 100 : $progress;
		$note     = isset( $_POST['bmn_dnote'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['bmn_dnote'] ) ), 0, 500 ) : '';
		$old_s    = (string) get_post_meta( $id, '_bmn_dstatus', true );
		$old_p    = (int) get_post_meta( $id, '_bmn_progress', true );
		if ( $status !== $old_s || $progress !== $old_p || '' !== $note ) {
			update_post_meta( $id, '_bmn_dstatus', $status );
			update_post_meta( $id, '_bmn_progress', $progress );
			$log   = get_post_meta( $id, '_bmn_dlog', true );
			$log   = is_array( $log ) ? $log : array();
			$log[] = array(
				's'  => $status,
				'p'  => $progress,
				'n'  => $note,
				't'  => time(),
				'by' => get_current_user_id(),
			);
			update_post_meta( $id, '_bmn_dlog', $log );
			BMN_Staff::log( 'directive', $id, $old_s . ' → ' . $status . ' ' . $progress . '%' );
		}
	}

	/**
	 * Admin columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['bmn_deadline'] = __( 'مهلت', 'boshrouyeh-eservices' );
				$new['bmn_dstatus']  = __( 'وضعیت', 'boshrouyeh-eservices' );
				$new['bmn_code']     = __( 'کد پیگیری', 'boshrouyeh-eservices' );
			}
		}
		return $new;
	}

	/**
	 * Column values.
	 *
	 * @param string $col Column.
	 * @param int    $id  Post.
	 */
	public static function column( $col, $id ) {
		$d = self::dir_dto( get_post( $id ) );
		if ( 'bmn_deadline' === $col ) {
			echo esc_html( $d['deadline_fa'] );
		} elseif ( 'bmn_dstatus' === $col ) {
			echo '<span style="color:' . ( $d['overdue'] ? '#a8442a' : 'inherit' ) . '">' . esc_html( $d['status_label'] . ' — ' . bmn_fa_digits( $d['progress'] ) . '٪' ) . '</span>';
		} elseif ( 'bmn_code' === $col ) {
			echo esc_html( $d['code'] );
		}
	}

	/**
	 * Admin filter ?bmn_overdue=1.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function admin_filter( $q ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		if ( ! is_admin() || ! $q->is_main_query() || self::DIR !== $q->get( 'post_type' ) || empty( $_GET['bmn_overdue'] ) ) {
			return;
		}
		$mq   = (array) $q->get( 'meta_query' );
		$mq[] = array( 'key' => '_bmn_dstatus', 'value' => 'done', 'compare' => '!=' );
		$mq[] = array( 'key' => '_bmn_deadline', 'value' => array( 1, time() ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' );
		$q->set( 'meta_query', $mq ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	/* ------------------------------------------------------------------ Front */

	/**
	 * Render directive cards.
	 *
	 * @param array $dirs Directive DTOs.
	 * @return string
	 */
	public static function cards( $dirs ) {
		if ( ! $dirs ) {
			return '<p class="bmn-empty">' . esc_html__( 'هنوز دستوری ثبت نشده است.', 'boshrouyeh-eservices' ) . '</p>';
		}
		$chips = array(
			'new'      => 'kh-chip--review',
			'progress' => 'kh-chip--fast',
			'stopped'  => 'kh-chip--need',
			'done'     => 'kh-chip--done',
			'overdue'  => 'kh-chip--closed',
		);
		$h = '<div class="bmn-ords">';
		foreach ( $dirs as $d ) {
			$h .= '<article class="bmn-ord' . ( $d['overdue'] ? ' is-overdue' : '' ) . '" id="dir-' . esc_attr( $d['code'] ) . '">';
			$h .= '<header><span class="kh-chip ' . esc_attr( $chips[ $d['status'] ] ?? '' ) . '">' . esc_html( $d['status_label'] ) . '</span><span class="bmn-ord__code">' . esc_html__( 'کد پیگیری', 'boshrouyeh-eservices' ) . ' ' . esc_html( bmn_fa_digits( $d['code'] ) ) . '</span></header>';
			$h .= '<div class="bmn-ord__rows">';
			$rows = array(
				__( 'مسئلهٔ دقیق', 'boshrouyeh-eservices' )  => $d['issue'],
				__( 'دستور مشخص', 'boshrouyeh-eservices' )  => $d['order'],
				__( 'واحد مسئول', 'boshrouyeh-eservices' )  => $d['unit'],
				__( 'مهلت اجرا', 'boshrouyeh-eservices' )   => $d['deadline_fa'] . ( null !== $d['days_left'] && 'done' !== $d['status'] ? ( $d['days_left'] >= 0 ? ' (' . bmn_fa_digits( $d['days_left'] ) . ' روز مانده)' : ' (' . bmn_fa_digits( abs( $d['days_left'] ) ) . ' روز تأخیر)' ) : '' ),
				__( 'منبع اعتبار', 'boshrouyeh-eservices' ) => trim( $d['funding'] . ( $d['funding_note'] ? ' — ' . $d['funding_note'] : '' ) ),
				__( 'وضعیت فعلی', 'boshrouyeh-eservices' )  => $d['status_label'] . ' — ' . bmn_fa_digits( $d['progress'] ) . '٪',
			);
			foreach ( $rows as $k => $v ) {
				$h .= '<div class="bmn-ord__row"><span class="bmn-ord__k">' . esc_html( $k ) . '</span><span class="bmn-ord__v">' . esc_html( $v ? $v : '—' ) . '</span></div>';
			}
			$h .= '</div><div class="bmn-ord__bar" role="progressbar" aria-valuenow="' . esc_attr( (string) $d['progress'] ) . '" aria-valuemin="0" aria-valuemax="100"><span style="width:' . esc_attr( (string) $d['progress'] ) . '%"></span></div>';
			$h .= '<p class="bmn-ord__meta">' . esc_html__( 'آخرین بروزرسانی:', 'boshrouyeh-eservices' ) . ' ' . esc_html( $d['updated_fa'] ) . '</p>';
			if ( $d['log'] ) {
				$h .= '<details><summary>' . esc_html__( 'سابقهٔ اقدامات', 'boshrouyeh-eservices' ) . '</summary><ol>';
				foreach ( $d['log'] as $e ) {
					$h .= '<li><b>' . esc_html( $e['date_fa'] ) . '</b> — ' . esc_html( $e['status_label'] . ' (' . bmn_fa_digits( $e['progress'] ) . '٪)' ) . ( $e['note'] ? '<br>' . esc_html( $e['note'] ) : '' ) . '</li>';
				}
				$h .= '</ol></details>';
			}
			if ( ! empty( $d['visit'] ) && ! is_singular( self::VISIT ) ) {
				$h .= '<p class="bmn-ord__visit"><a href="' . esc_url( $d['visit']['url'] ) . '">' . esc_html( $d['visit']['title'] . ' — ' . $d['visit']['date_fa'] ) . '</a></p>';
			}
			$h .= '</article>';
		}
		return $h . '</div>';
	}

	/**
	 * Append the dossier to single visit pages.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function visit_content( $content ) {
		if ( ! is_singular( self::VISIT ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! wp_style_is( 'bmn-es', 'registered' ) ) {
			BMN_ES_Blocks::register_assets();
		}
		wp_enqueue_style( 'bmn-es' );
		$v    = self::visit_dto( get_post(), true );
		$meta = '<ul class="bmn-visit-meta"><li>' . esc_html__( 'تاریخ:', 'boshrouyeh-eservices' ) . ' ' . esc_html( $v['date_fa'] ) . '</li>' . ( $v['place'] ? '<li>' . esc_html__( 'محل:', 'boshrouyeh-eservices' ) . ' ' . esc_html( $v['place'] ) . '</li>' : '' ) . ( $v['neighborhood'] ? '<li>' . esc_html__( 'محله:', 'boshrouyeh-eservices' ) . ' ' . esc_html( $v['neighborhood'] ) . '</li>' : '' ) . '<li>' . esc_html( sprintf( /* translators: 1: done, 2: total. */ __( '%1$s از %2$s دستور انجام شده', 'boshrouyeh-eservices' ), bmn_fa_digits( $v['counts']['done'] ), bmn_fa_digits( $v['counts']['total'] ) ) ) . '</li></ul>';
		return $meta . $content . '<h2 class="bmn-ords-title">' . esc_html__( 'پروندهٔ پیگیری دستورهای این بازدید', 'boshrouyeh-eservices' ) . '</h2>' . self::cards( $v['directives'] );
	}

	/**
	 * [bmn_directives] — transparency board with stats, filters and code lookup.
	 *
	 * @return string
	 */
	public static function shortcode() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public read-only filters.
		$status = isset( $_GET['vs'] ) ? sanitize_key( wp_unslash( $_GET['vs'] ) ) : '';
		$unit   = isset( $_GET['vu'] ) ? sanitize_title( wp_unslash( $_GET['vu'] ) ) : '';
		$code   = isset( $_GET['vc'] ) ? sanitize_text_field( wp_unslash( $_GET['vc'] ) ) : '';
		// phpcs:enable
		$status = in_array( $status, array( 'open', 'done', 'overdue' ), true ) ? $status : '';
		if ( ! wp_style_is( 'bmn-es', 'registered' ) ) {
			BMN_ES_Blocks::register_assets();
		}
		wp_enqueue_style( 'bmn-es' );

		$s      = self::stats();
		$h      = '<div class="bmn-ord-board">';
		$h     .= '<div class="bmn-ord-stats">';
		foreach ( array(
			array( $s['visits'], __( 'بازدید', 'boshrouyeh-eservices' ) ),
			array( $s['total'], __( 'دستور', 'boshrouyeh-eservices' ) ),
			array( $s['percent'] . '٪', __( 'انجام شده', 'boshrouyeh-eservices' ) ),
			array( $s['overdue'], __( 'با تأخیر', 'boshrouyeh-eservices' ) ),
		) as $c ) {
			$h .= '<div><strong>' . esc_html( bmn_fa_digits( $c[0] ) ) . '</strong><span>' . esc_html( $c[1] ) . '</span></div>';
		}
		$h .= '</div><form class="bmn-ord-filter" method="get"><input type="search" name="vc" value="' . esc_attr( $code ) . '" placeholder="' . esc_attr__( 'کد پیگیری دستور', 'boshrouyeh-eservices' ) . '" inputmode="numeric"><select name="vs"><option value="">' . esc_html__( 'همهٔ وضعیت‌ها', 'boshrouyeh-eservices' ) . '</option>';
		foreach ( array( 'open' => __( 'در جریان', 'boshrouyeh-eservices' ), 'done' => __( 'انجام شده', 'boshrouyeh-eservices' ), 'overdue' => __( 'با تأخیر', 'boshrouyeh-eservices' ) ) as $k => $v ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		$h .= '</select><select name="vu"><option value="">' . esc_html__( 'همهٔ واحدها', 'boshrouyeh-eservices' ) . '</option>';
		foreach ( get_terms( array( 'taxonomy' => BMN_Staff::TAX, 'hide_empty' => true ) ) as $t ) {
			$h .= '<option value="' . esc_attr( $t->slug ) . '"' . selected( $unit, $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
		}
		$h .= '</select><button class="kh-btn kh-btn--action" type="submit">' . esc_html__( 'نمایش', 'boshrouyeh-eservices' ) . '</button></form>';
		$h .= self::cards( self::query( $status, $unit, $code ) );
		return $h . '</div>';
	}
}
