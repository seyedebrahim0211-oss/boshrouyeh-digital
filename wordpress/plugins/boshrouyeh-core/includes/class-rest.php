<?php
/**
 * Public read-only REST API (namespace bmn/v1) for the site search and the mobile app.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST.
 */
final class BMN_Rest {

	const NS = 'bmn/v1';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		$public = '__return_true';
		$id_arg = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
		);
		$page_args = array(
			'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 500 ),
			'per_page' => array( 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 50 ),
		);

		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'search' ),
				'permission_callback' => $public,
				'args'                => array(
					'q' => array( 'type' => 'string', 'required' => true, 'minLength' => 2, 'maxLength' => 80, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
		register_rest_route( self::NS, '/home', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'home' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/config', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'config' ), 'permission_callback' => $public ) );
		register_rest_route(
			self::NS,
			'/services',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'services' ),
				'permission_callback' => $public,
				'args'                => array(
					'category' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'bmn_sanitize_slug' ),
				),
			)
		);
		register_rest_route( self::NS, '/services/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'service' ), 'permission_callback' => $public, 'args' => $id_arg ) );
		register_rest_route( self::NS, '/service-categories', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'categories' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/news', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'news' ), 'permission_callback' => $public, 'args' => $page_args ) );
		register_rest_route(
			self::NS,
			'/notices',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'notices' ),
				'permission_callback' => $public,
				'args'                => array_merge( $page_args, array( 'neighborhood' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'bmn_sanitize_slug' ) ) ),
			)
		);
		register_rest_route( self::NS, '/places', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'places' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/places/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'place' ), 'permission_callback' => $public, 'args' => $id_arg ) );
		register_rest_route( self::NS, '/events', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'events' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/tenders', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'tenders' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/content/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'content' ), 'permission_callback' => $public, 'args' => $id_arg ) );
		register_rest_route( self::NS, '/neighborhoods', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'neighborhoods' ), 'permission_callback' => $public ) );
		register_rest_route(
			self::NS,
			'/deceased',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'deceased' ),
				'permission_callback' => $public,
				'args'                => array(
					'q' => array( 'type' => 'string', 'required' => true, 'minLength' => 3, 'maxLength' => 60, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	/**
	 * Cache headers for public GET responses.
	 *
	 * @param mixed $data Data.
	 * @param int   $ttl  Seconds.
	 * @return WP_REST_Response
	 */
	private static function ok( $data, $ttl = 300 ) {
		$res = rest_ensure_response( $data );
		$res->header( 'Cache-Control', 'public, max-age=' . (int) $ttl );
		return $res;
	}

	/**
	 * Featured image URL.
	 *
	 * @param int    $id   Post id.
	 * @param string $size Size.
	 * @return string
	 */
	private static function image( $id, $size = 'khesht-card' ) {
		$url = get_the_post_thumbnail_url( $id, $size );
		if ( $url ) {
			return (string) $url;
		}
		return in_array( get_post_type( $id ), array( 'post', 'bmn_place', 'bmn_event', 'bmn_notice', 'bmn_tender' ), true ) ? bmn_illustration_url( $id ) : '';
	}

	/**
	 * Service DTO.
	 *
	 * @param WP_Post $p    Post.
	 * @param bool    $full Include details.
	 * @return array
	 */
	public static function service_dto( $p, $full = false ) {
		$cats = wp_get_post_terms( $p->ID, 'bmn_service_cat' );
		$cats = is_wp_error( $cats ) ? array() : $cats;
		$dto  = array(
			'id'          => $p->ID,
			'title'       => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'short_title' => (string) bmn_meta( $p->ID, 'short_title', html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ) ),
			'excerpt'     => wp_strip_all_tags( get_the_excerpt( $p ) ),
			'icon'        => (string) bmn_meta( $p->ID, 'icon', 'grid' ),
			'mode'        => (string) bmn_meta( $p->ID, 'mode', 'online' ),
			'duration'    => (string) bmn_meta( $p->ID, 'duration' ),
			'eservice'    => (string) bmn_meta( $p->ID, 'eservice' ),
			'featured'    => (bool) bmn_meta( $p->ID, 'featured', false ),
			'categories'  => array_map(
				fn( $t ) => array(
					'slug' => $t->slug,
					'name' => $t->name,
				),
				$cats
			),
			'url'         => get_permalink( $p ),
		);
		if ( $full ) {
			$faq = array();
			foreach ( bmn_lines( (string) bmn_meta( $p->ID, 'faq' ) ) as $line ) {
				$parts = array_map( 'trim', explode( '|', $line, 2 ) );
				if ( 2 === count( $parts ) ) {
					$faq[] = array( 'q' => $parts[0], 'a' => $parts[1] );
				}
			}
			$dto += array(
				'steps'        => bmn_lines( (string) bmn_meta( $p->ID, 'steps' ) ),
				'documents'    => bmn_lines( (string) bmn_meta( $p->ID, 'documents' ) ),
				'faq'          => $faq,
				'cost'         => (string) bmn_meta( $p->ID, 'cost' ),
				'unit'         => (string) bmn_meta( $p->ID, 'unit' ),
				'external_url' => (string) bmn_meta( $p->ID, 'external_url' ),
				'content'      => apply_filters( 'the_content', $p->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			);
		}
		return $dto;
	}

	/**
	 * Generic post DTO.
	 *
	 * @param WP_Post $p Post.
	 * @return array
	 */
	public static function post_dto( $p ) {
		return array(
			'id'      => $p->ID,
			'type'    => $p->post_type,
			'title'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'excerpt' => wp_strip_all_tags( get_the_excerpt( $p ) ),
			'date'    => get_post_time( 'c', true, $p ),
			'date_fa' => bmn_jdate( 'j F Y', (int) get_post_timestamp( $p ) ),
			'image'   => self::image( $p->ID ),
			'url'     => get_permalink( $p ),
		);
	}

	/**
	 * Unified search: services first, then notices/news/places/tenders.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function search( $req ) {
		if ( ! bmn_rate_limit( 'search_' . bmn_client_ip(), 90, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'bmn_rate_limited', __( 'درخواست‌ها زیاد است.', 'boshrouyeh-core' ), array( 'status' => 429 ) );
		}
		$q     = (string) $req['q'];
		$types = array(
			'bmn_service'      => __( 'خدمت', 'boshrouyeh-core' ),
			'bmn_notice'       => __( 'اطلاعیه', 'boshrouyeh-core' ),
			'post'             => __( 'خبر', 'boshrouyeh-core' ),
			'bmn_place'        => __( 'گردشگری', 'boshrouyeh-core' ),
			'bmn_tender'       => __( 'مناقصه', 'boshrouyeh-core' ),
			'page'             => __( 'برگه', 'boshrouyeh-core' ),
		);
		$out = array();
		foreach ( $types as $type => $label ) {
			$posts = get_posts(
				array(
					'post_type'      => $type,
					's'              => $q,
					'posts_per_page' => 'bmn_service' === $type ? 6 : 3,
					'no_found_rows'  => true,
				)
			);
			foreach ( $posts as $p ) {
				$out[] = array(
					'id'         => $p->ID,
					'title'      => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
					'url'        => get_permalink( $p ),
					'type'       => $type,
					'type_label' => $label,
				);
			}
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return self::ok( $out, 120 );
	}

	/**
	 * App home payload.
	 *
	 * @return WP_REST_Response
	 */
	public static function home() {
		$featured = get_posts(
			array(
				'post_type'      => 'bmn_service',
				'posts_per_page' => 8,
				'meta_key'       => '_bmn_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$notices  = get_posts(
			array(
				'post_type'      => 'bmn_notice',
				'posts_per_page' => 3,
				'meta_query'     => BMN_Post_Types::not_expired_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'no_found_rows'  => true,
			)
		);
		$news     = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 5, 'no_found_rows' => true ) );
		return self::ok(
			array(
				'today'    => bmn_jdate( 'l j F' ),
				'services' => array_map( array( __CLASS__, 'service_dto' ), $featured ),
				'notices'  => array_map(
					fn( $p ) => self::post_dto( $p ) + array( 'urgent' => 'urgent' === bmn_meta( $p->ID, 'urgency' ) ),
					$notices
				),
				'news'     => array_map( array( __CLASS__, 'post_dto' ), $news ),
			),
			120
		);
	}

	/**
	 * App configuration (contact, store links, feature flags).
	 *
	 * @return WP_REST_Response
	 */
	public static function config() {
		return self::ok(
			array(
				'name'       => get_bloginfo( 'name' ),
				'motto'      => 'شهرِ خشت، باد و قنات',
				'phone'      => (string) bmn_option( 'phone' ),
				'email'      => (string) bmn_option( 'email' ),
				'address'    => (string) bmn_option( 'address' ),
				'work_hours' => (string) bmn_option( 'work_hours' ),
				'location'   => array(
					'lat' => (float) bmn_option( 'lat', 33.8680 ),
					'lng' => (float) bmn_option( 'lng', 57.4290 ),
				),
				'emergency'  => array(
					array( 'title' => 'آتش‌نشانی', 'phone' => '125' ),
					array( 'title' => 'سامانه شهرداری', 'phone' => '137' ),
					array( 'title' => 'اورژانس', 'phone' => '115' ),
				),
				'features'   => apply_filters(
					'bmn_app_features',
					array(
						'payments' => defined( 'BMN_ES_VERSION' ),
						'report'   => defined( 'BMN_ES_VERSION' ),
						'tourism'  => true,
					)
				),
				'min_app_version' => (string) apply_filters( 'bmn_min_app_version', '1.0.0' ),
			),
			3600
		);
	}

	/**
	 * All services (optionally by category).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function services( $req ) {
		$args = array(
			'post_type'      => 'bmn_service',
			'posts_per_page' => 200,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'no_found_rows'  => true,
		);
		if ( $req['category'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'bmn_service_cat', 'field' => 'slug', 'terms' => $req['category'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		return self::ok( array_map( array( __CLASS__, 'service_dto' ), get_posts( $args ) ) );
	}

	/**
	 * Single service.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function service( $req ) {
		$p = get_post( (int) $req['id'] );
		if ( ! $p || 'bmn_service' !== $p->post_type || 'publish' !== $p->post_status ) {
			return new WP_Error( 'bmn_not_found', __( 'خدمت یافت نشد.', 'boshrouyeh-core' ), array( 'status' => 404 ) );
		}
		return self::ok( self::service_dto( $p, true ) );
	}

	/**
	 * Service categories.
	 *
	 * @return WP_REST_Response
	 */
	public static function categories() {
		$terms = get_terms( array( 'taxonomy' => 'bmn_service_cat', 'hide_empty' => false ) );
		$terms = is_wp_error( $terms ) ? array() : $terms;
		return self::ok(
			array_map(
				fn( $t ) => array(
					'slug'  => $t->slug,
					'name'  => $t->name,
					'count' => (int) $t->count,
					'icon'  => (string) get_term_meta( $t->term_id, '_bmn_icon', true ),
				),
				$terms
			),
			3600
		);
	}

	/**
	 * News.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function news( $req ) {
		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => (int) $req['per_page'],
				'paged'          => (int) $req['page'],
			)
		);
		$res = self::ok( array_map( array( __CLASS__, 'post_dto' ), $q->posts ) );
		$res->header( 'X-WP-TotalPages', (string) (int) $q->max_num_pages );
		return $res;
	}

	/**
	 * Notices (optionally for a neighborhood: its own + city-wide).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function notices( $req ) {
		$args = array(
			'post_type'      => 'bmn_notice',
			'posts_per_page' => (int) $req['per_page'],
			'paged'          => (int) $req['page'],
			'meta_query'     => BMN_Post_Types::not_expired_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);
		if ( $req['neighborhood'] ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'relation' => 'OR',
				array( 'taxonomy' => 'bmn_neighborhood', 'field' => 'slug', 'terms' => $req['neighborhood'] ),
				array( 'taxonomy' => 'bmn_neighborhood', 'operator' => 'NOT EXISTS' ),
			);
		}
		$q = new WP_Query( $args );
		return self::ok(
			array_map(
				fn( $p ) => self::post_dto( $p ) + array( 'urgent' => 'urgent' === bmn_meta( $p->ID, 'urgency' ) ),
				$q->posts
			),
			120
		);
	}

	/**
	 * Place DTO.
	 *
	 * @param WP_Post $p    Post.
	 * @param bool    $full Details.
	 * @return array
	 */
	private static function place_dto( $p, $full = false ) {
		$audio = (int) bmn_meta( $p->ID, 'audio', 0 );
		$en    = (int) bmn_meta( $p->ID, 'audio_en', 0 );
		$dto   = self::post_dto( $p ) + array(
			'era'      => (string) bmn_meta( $p->ID, 'era' ),
			'tags'     => array_values( array_filter( array_map( 'trim', explode( ',', str_replace( '،', ',', (string) bmn_meta( $p->ID, 'tags' ) ) ) ) ) ),
			'lat'      => (float) bmn_meta( $p->ID, 'lat', 0 ),
			'lng'      => (float) bmn_meta( $p->ID, 'lng', 0 ),
			'image_lg' => self::image( $p->ID, 'khesht-hero' ),
		);
		if ( $full ) {
			$dto += array(
				'hours'    => (string) bmn_meta( $p->ID, 'hours' ),
				'ticket'   => (string) bmn_meta( $p->ID, 'ticket' ),
				'address'  => (string) bmn_meta( $p->ID, 'address' ),
				'audio'    => $audio ? (string) wp_get_attachment_url( $audio ) : '',
				'audio_en' => $en ? (string) wp_get_attachment_url( $en ) : '',
				'content'  => apply_filters( 'the_content', $p->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			);
		}
		return $dto;
	}

	/**
	 * Places.
	 *
	 * @return WP_REST_Response
	 */
	public static function places() {
		$posts = get_posts( array( 'post_type' => 'bmn_place', 'posts_per_page' => 100, 'orderby' => 'menu_order', 'order' => 'ASC', 'no_found_rows' => true ) );
		return self::ok( array_map( fn( $p ) => self::place_dto( $p ), $posts ), 1800 );
	}

	/**
	 * Single place.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function place( $req ) {
		$p = get_post( (int) $req['id'] );
		if ( ! $p || 'bmn_place' !== $p->post_type || 'publish' !== $p->post_status ) {
			return new WP_Error( 'bmn_not_found', __( 'یافت نشد.', 'boshrouyeh-core' ), array( 'status' => 404 ) );
		}
		return self::ok( self::place_dto( $p, true ), 1800 );
	}

	/**
	 * Upcoming events.
	 *
	 * @return WP_REST_Response
	 */
	public static function events() {
		$posts = get_posts(
			array(
				'post_type'      => 'bmn_event',
				'posts_per_page' => 30,
				'meta_key'       => '_bmn_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => array( array( 'key' => '_bmn_start', 'value' => time() - DAY_IN_SECONDS, 'compare' => '>=', 'type' => 'NUMERIC' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'no_found_rows'  => true,
			)
		);
		return self::ok(
			array_map(
				function ( $p ) {
					$start = (int) bmn_meta( $p->ID, 'start', 0 );
					return self::post_dto( $p ) + array(
						'start'    => $start ? gmdate( 'c', $start ) : null,
						'start_fa' => $start ? bmn_jdate( 'l j F - H:i', $start ) : '',
						'venue'    => (string) bmn_meta( $p->ID, 'venue' ),
					);
				},
				$posts
			)
		);
	}

	/**
	 * Tenders.
	 *
	 * @return WP_REST_Response
	 */
	public static function tenders() {
		$posts = get_posts( array( 'post_type' => 'bmn_tender', 'posts_per_page' => 30, 'no_found_rows' => true ) );
		return self::ok(
			array_map(
				function ( $p ) {
					$d = (int) bmn_meta( $p->ID, 'deadline', 0 );
					return self::post_dto( $p ) + array(
						'kind'        => (string) bmn_meta( $p->ID, 'kind', 'tender' ),
						'number'      => (string) bmn_meta( $p->ID, 'number' ),
						'status'      => (string) bmn_meta( $p->ID, 'status', 'open' ),
						'deadline'    => $d ? gmdate( 'c', $d ) : null,
						'deadline_fa' => $d ? bmn_jdate( 'j F Y - H:i', $d ) : '',
					);
				},
				$posts
			)
		);
	}

	/**
	 * Rendered content for any public post (news/notice/event/tender detail in the app).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function content( $req ) {
		$p       = get_post( (int) $req['id'] );
		$allowed = array( 'post', 'bmn_notice', 'bmn_event', 'bmn_tender', 'bmn_transparency', 'page' );
		if ( ! $p || ! in_array( $p->post_type, $allowed, true ) || 'publish' !== $p->post_status || post_password_required( $p ) ) {
			return new WP_Error( 'bmn_not_found', __( 'یافت نشد.', 'boshrouyeh-core' ), array( 'status' => 404 ) );
		}
		return self::ok(
			self::post_dto( $p ) + array(
				'content' => apply_filters( 'the_content', $p->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			)
		);
	}

	/**
	 * Neighborhoods + waste schedule.
	 *
	 * @return WP_REST_Response
	 */
	public static function neighborhoods() {
		$terms = get_terms( array( 'taxonomy' => 'bmn_neighborhood', 'hide_empty' => false ) );
		$terms = is_wp_error( $terms ) ? array() : $terms;
		return self::ok(
			array_map(
				fn( $t ) => array(
					'slug'         => $t->slug,
					'name'         => $t->name,
					'waste_days'   => array_map( 'intval', array_filter( explode( ',', (string) get_term_meta( $t->term_id, '_bmn_waste_days', true ) ), 'strlen' ) ),
					'recycle_days' => array_map( 'intval', array_filter( explode( ',', (string) get_term_meta( $t->term_id, '_bmn_recycle_days', true ) ), 'strlen' ) ),
					'waste_time'   => (string) get_term_meta( $t->term_id, '_bmn_waste_time', true ),
				),
				$terms
			),
			3600
		);
	}

	/**
	 * Deceased search — rate limited, limited fields, max 20 rows.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function deceased( $req ) {
		if ( ! bmn_rate_limit( 'deceased_' . bmn_client_ip(), 20, 10 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'bmn_rate_limited', __( 'درخواست‌ها زیاد است؛ کمی بعد تلاش کنید.', 'boshrouyeh-core' ), array( 'status' => 429 ) );
		}
		$q = trim( preg_replace( '/\s+/u', ' ', (string) $req['q'] ) );
		if ( mb_strlen( $q ) < 3 ) {
			return new WP_Error( 'bmn_short', __( 'دست‌کم ۳ حرف وارد کنید.', 'boshrouyeh-core' ), array( 'status' => 400 ) );
		}
		$posts = get_posts(
			array(
				'post_type'      => 'bmn_deceased',
				'post_status'    => 'publish',
				's'              => $q,
				'posts_per_page' => 20,
				'no_found_rows'  => true,
			)
		);
		$res = rest_ensure_response(
			array_map(
				fn( $p ) => array(
					'name'     => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
					'father'   => (string) bmn_meta( $p->ID, 'father' ),
					'death'    => bmn_fa_digits( (string) bmn_meta( $p->ID, 'death' ) ),
					'cemetery' => (string) bmn_meta( $p->ID, 'cemetery' ),
					'plot'     => bmn_fa_digits( (string) bmn_meta( $p->ID, 'plot' ) ),
					'ceremony' => (string) bmn_meta( $p->ID, 'ceremony' ),
				),
				$posts
			)
		);
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}
}
