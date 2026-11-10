<?php
/**
 * Custom post types and taxonomies.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post types.
 */
final class BMN_Post_Types {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'pre_get_posts', array( __CLASS__, 'archive_queries' ) );
		add_filter( 'manage_bmn_service_posts_columns', array( __CLASS__, 'service_columns' ) );
		add_action( 'manage_bmn_service_posts_custom_column', array( __CLASS__, 'service_column' ), 10, 2 );
		add_filter( 'manage_bmn_tender_posts_columns', array( __CLASS__, 'tender_columns' ) );
		add_action( 'manage_bmn_tender_posts_custom_column', array( __CLASS__, 'tender_column' ), 10, 2 );
	}

	/**
	 * Labels helper.
	 *
	 * @param string $single Singular.
	 * @param string $plural Plural.
	 * @return array
	 */
	private static function labels( $single, $plural ) {
		return array(
			'name'               => $plural,
			'singular_name'      => $single,
			/* translators: %s: item name. */
			'add_new_item'       => sprintf( __( 'افزودن %s', 'boshrouyeh-core' ), $single ),
			'add_new'            => __( 'افزودن', 'boshrouyeh-core' ),
			/* translators: %s: item name. */
			'edit_item'          => sprintf( __( 'ویرایش %s', 'boshrouyeh-core' ), $single ),
			/* translators: %s: item name. */
			'view_item'          => sprintf( __( 'مشاهده %s', 'boshrouyeh-core' ), $single ),
			'all_items'          => $plural,
			/* translators: %s: items name. */
			'search_items'       => sprintf( __( 'جست‌وجوی %s', 'boshrouyeh-core' ), $plural ),
			'not_found'          => __( 'موردی یافت نشد.', 'boshrouyeh-core' ),
			'not_found_in_trash' => __( 'در زباله‌دان موردی نیست.', 'boshrouyeh-core' ),
			'menu_name'          => $plural,
		);
	}

	/**
	 * Register CPTs & taxonomies.
	 */
	public static function register() {
		$common = array(
			'public'       => true,
			'show_in_rest' => true,
			'menu_position' => 21,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
		);

		// Taxonomies first so their rewrite rules win over CPT attachment rules (services/category/...).
		register_taxonomy(
			'bmn_service_cat',
			array( 'bmn_service' ),
			array(
				'labels'            => self::labels( __( 'دستهٔ خدمت', 'boshrouyeh-core' ), __( 'دسته‌های خدمات', 'boshrouyeh-core' ) ),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'services/category', 'with_front' => false ),
			)
		);
		register_taxonomy(
			'bmn_neighborhood',
			array( 'bmn_notice', 'bmn_event' ),
			array(
				'labels'            => self::labels( __( 'محله', 'boshrouyeh-core' ), __( 'محله‌ها', 'boshrouyeh-core' ) ),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'neighborhood', 'with_front' => false ),
			)
		);
		register_taxonomy(
			'bmn_doc_type',
			array( 'bmn_transparency' ),
			array(
				'labels'            => self::labels( __( 'نوع سند', 'boshrouyeh-core' ), __( 'انواع سند', 'boshrouyeh-core' ) ),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'transparency/type', 'with_front' => false ),
			)
		);

		register_post_type(
			'bmn_service',
			array_merge(
				$common,
				array(
					'labels'      => self::labels( __( 'خدمت', 'boshrouyeh-core' ), __( 'خدمات الکترونیک', 'boshrouyeh-core' ) ),
					'menu_icon'   => 'dashicons-grid-view',
					'has_archive' => 'services',
					'rewrite'     => array( 'slug' => 'services', 'with_front' => false ),
					'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
				)
			)
		);
		register_post_type(
			'bmn_notice',
			array_merge(
				$common,
				array(
					'labels'      => self::labels( __( 'اطلاعیه', 'boshrouyeh-core' ), __( 'اطلاعیه‌ها', 'boshrouyeh-core' ) ),
					'menu_icon'   => 'dashicons-megaphone',
					'has_archive' => 'notices',
					'rewrite'     => array( 'slug' => 'notices', 'with_front' => false ),
				)
			)
		);
		register_post_type(
			'bmn_tender',
			array_merge(
				$common,
				array(
					'labels'      => self::labels( __( 'مناقصه/مزایده', 'boshrouyeh-core' ), __( 'مناقصه و مزایده', 'boshrouyeh-core' ) ),
					'menu_icon'   => 'dashicons-clipboard',
					'has_archive' => 'tenders',
					'rewrite'     => array( 'slug' => 'tenders', 'with_front' => false ),
				)
			)
		);
		register_post_type(
			'bmn_place',
			array_merge(
				$common,
				array(
					'labels'      => self::labels( __( 'جاذبه', 'boshrouyeh-core' ), __( 'گردشگری', 'boshrouyeh-core' ) ),
					'menu_icon'   => 'dashicons-location-alt',
					'has_archive' => 'places',
					'rewrite'     => array( 'slug' => 'places', 'with_front' => false ),
					'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
				)
			)
		);
		register_post_type(
			'bmn_event',
			array_merge(
				$common,
				array(
					'labels'      => self::labels( __( 'رویداد', 'boshrouyeh-core' ), __( 'رویدادها', 'boshrouyeh-core' ) ),
					'menu_icon'   => 'dashicons-calendar-alt',
					'has_archive' => 'events',
					'rewrite'     => array( 'slug' => 'events', 'with_front' => false ),
				)
			)
		);
		register_post_type(
			'bmn_transparency',
			array_merge(
				$common,
				array(
					'labels'      => self::labels( __( 'سند شفافیت', 'boshrouyeh-core' ), __( 'شفافیت', 'boshrouyeh-core' ) ),
					'menu_icon'   => 'dashicons-chart-bar',
					'has_archive' => 'transparency',
					'rewrite'     => array( 'slug' => 'transparency', 'with_front' => false ),
				)
			)
		);
		// Deceased records: never publicly queryable; searchable only through the rate-limited endpoint.
		register_post_type(
			'bmn_deceased',
			array(
				'labels'              => self::labels( __( 'متوفی', 'boshrouyeh-core' ), __( 'متوفیان', 'boshrouyeh-core' ) ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'menu_icon'           => 'dashicons-heart',
				'menu_position'       => 22,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
			)
		);
	}

	/**
	 * Sensible archive ordering + hide expired notices.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function archive_queries( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_post_type_archive( 'bmn_service' ) || $q->is_tax( 'bmn_service_cat' ) ) {
			$q->set( 'posts_per_page', 100 );
			$q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
		}
		if ( $q->is_post_type_archive( 'bmn_place' ) ) {
			$q->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
		}
		if ( $q->is_post_type_archive( 'bmn_notice' ) ) {
			$q->set( 'meta_query', self::not_expired_meta_query() );
		}
	}

	/**
	 * Meta query: notice not expired.
	 *
	 * @return array
	 */
	public static function not_expired_meta_query() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => '_bmn_expires',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_bmn_expires',
				'value'   => time(),
				'compare' => '>',
				'type'    => 'NUMERIC',
			),
		);
	}

	/**
	 * Service list columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function service_columns( $cols ) {
		$cols['bmn_mode']     = __( 'نحوهٔ ارائه', 'boshrouyeh-core' );
		$cols['bmn_featured'] = __( 'میانبر', 'boshrouyeh-core' );
		return $cols;
	}

	/**
	 * Service column values.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post ID.
	 */
	public static function service_column( $col, $post_id ) {
		if ( 'bmn_mode' === $col ) {
			$modes = bmn_service_modes();
			echo esc_html( $modes[ bmn_meta( $post_id, 'mode', 'online' ) ] ?? '' );
		} elseif ( 'bmn_featured' === $col ) {
			echo bmn_meta( $post_id, 'featured' ) ? '✓' : '—';
		}
	}

	/**
	 * Tender columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function tender_columns( $cols ) {
		$cols['bmn_deadline'] = __( 'مهلت', 'boshrouyeh-core' );
		$cols['bmn_status']   = __( 'وضعیت', 'boshrouyeh-core' );
		return $cols;
	}

	/**
	 * Tender column values.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post ID.
	 */
	public static function tender_column( $col, $post_id ) {
		if ( 'bmn_deadline' === $col ) {
			$d = (int) bmn_meta( $post_id, 'deadline', 0 );
			echo $d ? esc_html( bmn_jdate( 'Y/m/d H:i', $d ) ) : '—';
		} elseif ( 'bmn_status' === $col ) {
			echo esc_html( (string) bmn_meta( $post_id, 'status', 'open' ) );
		}
	}
}
