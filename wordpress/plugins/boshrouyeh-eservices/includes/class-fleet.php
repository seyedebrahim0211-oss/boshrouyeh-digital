<?php
/**
 * Live minibus tracking (ناوگان مینی‌بوس) from GPS trackers.
 *
 * Ingest:
 *  - OsmAnd protocol (Traccar Client app on a phone, or a Traccar server forward):
 *      GET|POST /wp-json/bmn/v1/fleet/osmand?id=<DEVICE_TOKEN>&lat=..&lon=..&speed=..&bearing=..&timestamp=..
 *  - JSON: POST /wp-json/bmn/v1/fleet/ping  {"token":"..","lat":..,"lng":..,"speed":..,"heading":..}
 * Public:
 *  - GET /wp-json/bmn/v1/fleet/live   routes + stops + vehicles (position, last/next stop, ETA)
 *
 * @package BoshrouyehEservices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fleet.
 */
class BMN_Fleet {

	const ROUTE   = 'bmn_route';
	const VEHICLE = 'bmn_vehicle';
	const STALE   = 180;   // Seconds without a ping → offline.
	const AT_STOP = 70;    // Metres.

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'add_meta_boxes_' . self::ROUTE, array( __CLASS__, 'route_boxes' ) );
		add_action( 'add_meta_boxes_' . self::VEHICLE, array( __CLASS__, 'vehicle_boxes' ) );
		add_action( 'save_post_' . self::ROUTE, array( __CLASS__, 'save_route' ) );
		add_action( 'save_post_' . self::VEHICLE, array( __CLASS__, 'save_vehicle' ) );
		add_shortcode( 'bmn_fleet_map', array( __CLASS__, 'shortcode' ) );
		add_action( 'bmn_fleet_purge', array( __CLASS__, 'purge' ) );
		if ( ! wp_next_scheduled( 'bmn_fleet_purge' ) ) {
			wp_schedule_event( time() + 900, 'daily', 'bmn_fleet_purge' );
		}
	}

	/**
	 * CPTs.
	 */
	public static function register() {
		$caps = array( 'bmn_fleet', 'bmn_fleets' );
		register_post_type(
			self::ROUTE,
			array(
				'labels'          => array(
					'name'          => __( 'خطوط مینی‌بوس', 'boshrouyeh-eservices' ),
					'singular_name' => __( 'خط', 'boshrouyeh-eservices' ),
					'add_new_item'  => __( 'افزودن خط', 'boshrouyeh-eservices' ),
					'menu_name'     => __( 'ناوگان مینی‌بوس', 'boshrouyeh-eservices' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'menu_icon'       => 'dashicons-car',
				'menu_position'   => 6,
				'supports'        => array( 'title', 'excerpt' ),
				'capability_type' => $caps,
				'map_meta_cap'    => true,
			)
		);
		register_post_type(
			self::VEHICLE,
			array(
				'labels'          => array(
					'name'          => __( 'خودروها و ردیاب‌ها', 'boshrouyeh-eservices' ),
					'singular_name' => __( 'خودرو', 'boshrouyeh-eservices' ),
					'add_new_item'  => __( 'افزودن خودرو', 'boshrouyeh-eservices' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . self::ROUTE,
				'supports'        => array( 'title' ),
				'capability_type' => $caps,
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Positions table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bmn_fleet_pos';
	}

	/**
	 * Create table.
	 */
	public static function install_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta(
			"CREATE TABLE {$t} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				vehicle_id bigint(20) unsigned NOT NULL,
				lat decimal(9,6) NOT NULL,
				lng decimal(9,6) NOT NULL,
				speed smallint(5) unsigned NOT NULL DEFAULT 0,
				heading smallint(5) unsigned NOT NULL DEFAULT 0,
				recorded_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY vehicle_time (vehicle_id, recorded_at)
			) {$wpdb->get_charset_collate()};"
		);
	}

	/* ------------------------------------------------------------------ Geometry */

	/**
	 * Haversine distance (m).
	 *
	 * @param float $a1 Lat 1.
	 * @param float $o1 Lng 1.
	 * @param float $a2 Lat 2.
	 * @param float $o2 Lng 2.
	 * @return float
	 */
	public static function dist( $a1, $o1, $a2, $o2 ) {
		$r  = 6371000;
		$p1 = deg2rad( $a1 );
		$p2 = deg2rad( $a2 );
		$dp = deg2rad( $a2 - $a1 );
		$dl = deg2rad( $o2 - $o1 );
		$h  = sin( $dp / 2 ) ** 2 + cos( $p1 ) * cos( $p2 ) * sin( $dl / 2 ) ** 2;
		return 2 * $r * asin( min( 1, sqrt( $h ) ) );
	}

	/**
	 * Parse stops: one per line "name|lat|lng".
	 *
	 * @param string $raw Raw.
	 * @return array<int,array{name:string,lat:float,lng:float}>
	 */
	public static function parse_stops( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$p = array_map( 'trim', explode( '|', bmn_en_digits( $line ) ) );
			if ( count( $p ) >= 3 && is_numeric( $p[1] ) && is_numeric( $p[2] ) ) {
				$out[] = array(
					'name' => mb_substr( sanitize_text_field( $p[0] ), 0, 80 ),
					'lat'  => round( (float) $p[1], 6 ),
					'lng'  => round( (float) $p[2], 6 ),
				);
			}
		}
		return $out;
	}

	/**
	 * Stops of a route.
	 *
	 * @param int $route Route id.
	 * @return array
	 */
	public static function stops( $route ) {
		$s = get_post_meta( $route, '_bmn_stops_parsed', true );
		return is_array( $s ) ? $s : array();
	}

	/* ------------------------------------------------------------------ Ingest */

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/fleet/osmand',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'osmand' ),
				'permission_callback' => '__return_true', // Device token checked in callback.
			)
		);
		register_rest_route(
			'bmn/v1',
			'/fleet/ping',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ping' ),
				'permission_callback' => '__return_true', // Device token checked in callback.
			)
		);
		register_rest_route(
			'bmn/v1',
			'/fleet/live',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'live' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Find vehicle by device token (constant-time compare on the hash).
	 *
	 * @param string $token Token.
	 * @return int Vehicle id or 0.
	 */
	private static function vehicle_by_token( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $token );
		if ( strlen( $token ) < 20 ) {
			return 0;
		}
		$hash = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
		$ids  = get_posts(
			array(
				'post_type'      => self::VEHICLE,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => '_bmn_token_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( ! $ids || ! hash_equals( (string) get_post_meta( $ids[0], '_bmn_token_hash', true ), $hash ) ) {
			return 0;
		}
		return (int) $ids[0];
	}

	/**
	 * OsmAnd protocol endpoint.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function osmand( $req ) {
		$ts = $req['timestamp'];
		if ( is_numeric( $ts ) && $ts > 1e12 ) {
			$ts = (int) ( $ts / 1000 );
		} elseif ( is_string( $ts ) && ! is_numeric( $ts ) ) {
			$ts = strtotime( $ts );
		}
		// OsmAnd speed is knots.
		$speed = is_numeric( $req['speed'] ) ? (float) $req['speed'] * 1.852 : 0;
		return self::record( (string) $req['id'], $req['lat'], $req['lon'], $speed, $req['bearing'], (int) $ts );
	}

	/**
	 * JSON ping endpoint (km/h).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function ping( $req ) {
		$token = (string) $req->get_header( 'x-device-key' );
		$token = $token ? $token : (string) $req['token'];
		return self::record( $token, $req['lat'], $req['lng'], (float) $req['speed'], $req['heading'], (int) $req['ts'] );
	}

	/**
	 * Validate & store a fix, update the vehicle's stop progress.
	 *
	 * @param string $token   Device token.
	 * @param mixed  $lat     Lat.
	 * @param mixed  $lng     Lng.
	 * @param float  $speed   km/h.
	 * @param mixed  $heading Degrees.
	 * @param int    $ts      Unix time (0 = now).
	 * @return WP_REST_Response|WP_Error
	 */
	private static function record( $token, $lat, $lng, $speed, $heading, $ts ) {
		if ( ! bmn_rate_limit( 'fleet_' . bmn_client_ip(), 3000, 10 * MINUTE_IN_SECONDS ) ) {
			return bmn_es_error( 'rate', 'rate limited', 429 );
		}
		$vid = self::vehicle_by_token( $token );
		if ( ! $vid ) {
			return bmn_es_error( 'device', 'unknown device', 403 );
		}
		$lat = (float) $lat;
		$lng = (float) $lng;
		if ( $lat < 24 || $lat > 40.5 || $lng < 44 || $lng > 63.5 ) {
			return bmn_es_error( 'location', 'out of range', 400 );
		}
		$now = time();
		$ts  = ( $ts > $now - DAY_IN_SECONDS && $ts <= $now + 120 ) ? $ts : $now;
		$last = get_post_meta( $vid, '_bmn_last', true );
		if ( is_array( $last ) && $ts - (int) $last['t'] < 3 ) {
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => true ), 200 );
		}
		$speed = (int) min( 200, max( 0, round( $speed ) ) );
		$head  = (int) ( ( (int) $heading % 360 + 360 ) % 360 );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- telemetry table.
		$wpdb->insert(
			self::table(),
			array(
				'vehicle_id'  => $vid,
				'lat'         => $lat,
				'lng'         => $lng,
				'speed'       => $speed,
				'heading'     => $head,
				'recorded_at' => gmdate( 'Y-m-d H:i:s', $ts ),
			),
			array( '%d', '%f', '%f', '%d', '%d', '%s' )
		);

		// Stop progress.
		$route = (int) get_post_meta( $vid, '_bmn_route', true );
		$stops = $route ? self::stops( $route ) : array();
		$prev  = is_array( $last ) ? $last : array();
		$state = array(
			'lat'  => $lat,
			'lng'  => $lng,
			's'    => $speed,
			'h'    => $head,
			't'    => $ts,
			'stop' => $prev['stop'] ?? -1,
			'dir'  => $prev['dir'] ?? 1,
			'at'   => -1,
		);
		if ( $stops ) {
			$best = -1;
			$bd   = PHP_FLOAT_MAX;
			foreach ( $stops as $i => $st ) {
				$d = self::dist( $lat, $lng, $st['lat'], $st['lng'] );
				if ( $d < $bd ) {
					$bd   = $d;
					$best = $i;
				}
			}
			if ( $bd <= self::AT_STOP ) {
				$state['at'] = $best;
				if ( $best !== (int) $state['stop'] ) {
					if ( (int) $state['stop'] >= 0 ) {
						$state['dir'] = $best > (int) $state['stop'] ? 1 : -1;
					}
					$state['stop'] = $best;
				}
				$n = count( $stops );
				if ( 0 === $best ) {
					$state['dir'] = 1;
				} elseif ( $n - 1 === $best ) {
					$state['dir'] = -1;
				}
			}
		}
		update_post_meta( $vid, '_bmn_last', $state );
		delete_transient( 'bmn_fleet_live' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* ------------------------------------------------------------------ Public */

	/**
	 * Live snapshot (cached 5 s).
	 *
	 * @return array
	 */
	public static function snapshot() {
		$hit = get_transient( 'bmn_fleet_live' );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$routes = array();
		foreach ( get_posts( array( 'post_type' => self::ROUTE, 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $r ) {
			$routes[ $r->ID ] = array(
				'id'       => $r->ID,
				'name'     => html_entity_decode( $r->post_title, ENT_QUOTES, 'UTF-8' ),
				'color'    => (string) get_post_meta( $r->ID, '_bmn_color', true ) ?: '#1f6f78',
				'hours'    => (string) get_post_meta( $r->ID, '_bmn_hours', true ),
				'headway'  => (string) get_post_meta( $r->ID, '_bmn_headway', true ),
				'fare'     => (string) get_post_meta( $r->ID, '_bmn_fare', true ),
				'note'     => wp_strip_all_tags( $r->post_excerpt ),
				'stops'    => self::stops( $r->ID ),
				'vehicles' => array(),
			);
		}
		$now = time();
		foreach ( get_posts( array( 'post_type' => self::VEHICLE, 'post_status' => 'publish', 'posts_per_page' => 200 ) ) as $v ) {
			$rid  = (int) get_post_meta( $v->ID, '_bmn_route', true );
			$last = get_post_meta( $v->ID, '_bmn_last', true );
			if ( ! isset( $routes[ $rid ] ) ) {
				continue;
			}
			$stops = $routes[ $rid ]['stops'];
			$item  = array(
				'id'      => $v->ID,
				'name'    => html_entity_decode( $v->post_title, ENT_QUOTES, 'UTF-8' ),
				'plate'   => (string) get_post_meta( $v->ID, '_bmn_plate', true ),
				'online'  => false,
				'lat'     => null,
				'lng'     => null,
				'speed'   => 0,
				'heading' => 0,
				'ago'     => null,
				'at_stop' => '',
				'last'    => '',
				'next'    => '',
				'eta_min' => null,
			);
			if ( is_array( $last ) && ! empty( $last['t'] ) ) {
				$ago             = max( 0, $now - (int) $last['t'] );
				$item['online']  = $ago <= self::STALE;
				$item['lat']     = (float) $last['lat'];
				$item['lng']     = (float) $last['lng'];
				$item['speed']   = (int) $last['s'];
				$item['heading'] = (int) $last['h'];
				$item['ago']     = $ago;
				$si              = (int) ( $last['stop'] ?? -1 );
				if ( $stops && $si >= 0 && isset( $stops[ $si ] ) ) {
					$item['last'] = $stops[ $si ]['name'];
					$ni           = $si + (int) ( $last['dir'] ?? 1 );
					$ni           = max( 0, min( count( $stops ) - 1, $ni ) );
					if ( (int) ( $last['at'] ?? -1 ) >= 0 ) {
						$item['at_stop'] = $stops[ (int) $last['at'] ]['name'];
					}
					if ( $ni !== $si ) {
						$item['next'] = $stops[ $ni ]['name'];
						$d            = self::dist( $item['lat'], $item['lng'], $stops[ $ni ]['lat'], $stops[ $ni ]['lng'] );
						$kmh          = max( 15, $item['speed'] );
						$item['eta_min'] = $item['online'] ? (int) max( 1, round( $d / ( $kmh * 1000 / 60 ) ) ) : null;
					}
				}
			}
			$routes[ $rid ]['vehicles'][] = $item;
		}
		$out = array(
			'updated' => gmdate( 'c', $now ),
			'routes'  => array_values( $routes ),
		);
		set_transient( 'bmn_fleet_live', $out, 5 );
		return $out;
	}

	/**
	 * GET /fleet/live
	 *
	 * @return WP_REST_Response
	 */
	public static function live() {
		$res = rest_ensure_response( self::snapshot() );
		$res->header( 'Cache-Control', 'public, max-age=5' );
		return $res;
	}

	/**
	 * Shortcode: live map.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( ! wp_style_is( 'bmn-es', 'registered' ) ) {
			BMN_ES_Blocks::register_assets();
		}
		wp_enqueue_style( 'bmn-es' );
		wp_enqueue_style( 'bmn-leaflet', BMN_ES_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_enqueue_script( 'bmn-leaflet', BMN_ES_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
		wp_enqueue_script( 'bmn-fleet', BMN_ES_URL . 'assets/js/fleet.js', array( 'bmn-leaflet' ), BMN_ES_VERSION, true );
		wp_localize_script(
			'bmn-fleet',
			'BMN_FLEET',
			array(
				'api'    => esc_url_raw( rest_url( 'bmn/v1/fleet/live' ) ),
				'tiles'  => apply_filters( 'bmn_tile_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' ),
				'center' => array( (float) bmn_option( 'lat', 33.868 ), (float) bmn_option( 'lng', 57.429 ) ),
			)
		);
		return '<div class="bmn-fleet" data-bmn-fleet><div class="bmn-fleet__map" role="region" aria-label="' . esc_attr__( 'نقشهٔ زندهٔ مینی‌بوس‌ها', 'boshrouyeh-eservices' ) . '"></div><div class="bmn-fleet__list" aria-live="polite"><p>' . esc_html__( 'در حال دریافت موقعیت ناوگان…', 'boshrouyeh-eservices' ) . '</p></div></div>';
	}

	/* ------------------------------------------------------------------ Admin */

	/**
	 * Route boxes.
	 */
	public static function route_boxes() {
		add_meta_box( 'bmn_route', __( 'ایستگاه‌ها و مشخصات خط', 'boshrouyeh-eservices' ), array( __CLASS__, 'route_box' ), self::ROUTE, 'normal', 'high' );
	}

	/**
	 * Route box.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function route_box( $p ) {
		wp_nonce_field( 'bmn_route_save', 'bmn_route_nonce' );
		printf( '<p><label><strong>%s</strong><br><textarea name="bmn_stops" rows="10" class="large-text code" dir="rtl" placeholder="میدان امام|33.8681|57.4302">%s</textarea></label><br><span class="description">%s</span></p>', esc_html__( 'ایستگاه‌ها به ترتیب مسیر (هر خط: نام|عرض|طول)', 'boshrouyeh-eservices' ), esc_textarea( (string) get_post_meta( $p->ID, '_bmn_stops', true ) ), esc_html__( 'مختصات را از نقشه (کلیک راست در OpenStreetMap / Google Maps) بردارید.', 'boshrouyeh-eservices' ) );
		foreach ( array(
			'color'   => array( __( 'رنگ خط', 'boshrouyeh-eservices' ), '#1f6f78' ),
			'hours'   => array( __( 'ساعات کار', 'boshrouyeh-eservices' ), '۶:۳۰ تا ۲۱' ),
			'headway' => array( __( 'فاصلهٔ حرکت', 'boshrouyeh-eservices' ), 'هر ۲۰ دقیقه' ),
			'fare'    => array( __( 'کرایه', 'boshrouyeh-eservices' ), '۵۰٬۰۰۰ ریال' ),
		) as $k => $f ) {
			printf( '<p><label>%s <input type="text" name="bmn_%s" value="%s" placeholder="%s"></label></p>', esc_html( $f[0] ), esc_attr( $k ), esc_attr( (string) get_post_meta( $p->ID, '_bmn_' . $k, true ) ), esc_attr( $f[1] ) );
		}
	}

	/**
	 * Save route.
	 *
	 * @param int $id Post.
	 */
	public static function save_route( $id ) {
		if ( ! isset( $_POST['bmn_route_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmn_route_nonce'] ) ), 'bmn_route_save' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$raw = isset( $_POST['bmn_stops'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bmn_stops'] ) ) : '';
		update_post_meta( $id, '_bmn_stops', $raw );
		update_post_meta( $id, '_bmn_stops_parsed', self::parse_stops( $raw ) );
		$color = isset( $_POST['bmn_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['bmn_color'] ) ) : '';
		update_post_meta( $id, '_bmn_color', $color ? $color : '#1f6f78' );
		foreach ( array( 'hours', 'headway', 'fare' ) as $k ) {
			update_post_meta( $id, '_bmn_' . $k, isset( $_POST[ 'bmn_' . $k ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST[ 'bmn_' . $k ] ) ), 0, 60 ) : '' );
		}
		delete_transient( 'bmn_fleet_live' );
	}

	/**
	 * Vehicle boxes.
	 */
	public static function vehicle_boxes() {
		add_meta_box( 'bmn_vehicle', __( 'خط، پلاک و ردیاب GPS', 'boshrouyeh-eservices' ), array( __CLASS__, 'vehicle_box' ), self::VEHICLE, 'normal', 'high' );
	}

	/**
	 * Vehicle box (token is shown once right after generation).
	 *
	 * @param WP_Post $p Post.
	 */
	public static function vehicle_box( $p ) {
		wp_nonce_field( 'bmn_vehicle_save', 'bmn_vehicle_nonce' );
		$route = (int) get_post_meta( $p->ID, '_bmn_route', true );
		echo '<p><label>' . esc_html__( 'خط', 'boshrouyeh-eservices' ) . ' <select name="bmn_route"><option value="0">—</option>';
		foreach ( get_posts( array( 'post_type' => self::ROUTE, 'post_status' => 'publish', 'posts_per_page' => 50 ) ) as $r ) {
			printf( '<option value="%d" %s>%s</option>', (int) $r->ID, selected( $route, $r->ID, false ), esc_html( $r->post_title ) );
		}
		echo '</select></label></p>';
		printf( '<p><label>%s <input type="text" name="bmn_plate" value="%s"></label></p>', esc_html__( 'پلاک', 'boshrouyeh-eservices' ), esc_attr( (string) get_post_meta( $p->ID, '_bmn_plate', true ) ) );
		$fresh = get_transient( 'bmn_fleet_token_' . $p->ID . '_' . get_current_user_id() );
		$has   = (bool) get_post_meta( $p->ID, '_bmn_token_hash', true );
		echo '<hr><h4>' . esc_html__( 'اتصال ردیاب', 'boshrouyeh-eservices' ) . '</h4>';
		if ( $fresh ) {
			delete_transient( 'bmn_fleet_token_' . $p->ID . '_' . get_current_user_id() );
			echo '<p><strong>' . esc_html__( 'شناسهٔ دستگاه (فقط همین یک‌بار نمایش داده می‌شود):', 'boshrouyeh-eservices' ) . '</strong><br><code style="font-size:15px;user-select:all">' . esc_html( $fresh ) . '</code></p>';
		} elseif ( $has ) {
			echo '<p>' . esc_html__( 'ردیاب متصل است. برای دستگاه جدید، شناسهٔ تازه بسازید (شناسهٔ قبلی باطل می‌شود).', 'boshrouyeh-eservices' ) . '</p>';
		}
		echo '<p><label><input type="checkbox" name="bmn_new_token" value="1"' . ( $has ? '' : ' checked' ) . '> ' . esc_html__( 'ساخت شناسهٔ جدید دستگاه هنگام ذخیره', 'boshrouyeh-eservices' ) . '</label></p>';
		echo '<details><summary>' . esc_html__( 'راهنمای اتصال', 'boshrouyeh-eservices' ) . '</summary><ol>';
		echo '<li>' . esc_html__( 'گوشی اندرویدی در خودرو: اپ «Traccar Client» را نصب کنید؛ Device identifier = شناسهٔ بالا؛ Server URL:', 'boshrouyeh-eservices' ) . ' <code dir="ltr">' . esc_html( rest_url( 'bmn/v1/fleet/osmand' ) ) . '</code> ' . esc_html__( '؛ فاصلهٔ ارسال ۱۰ ثانیه.', 'boshrouyeh-eservices' ) . '</li>';
		echo '<li>' . esc_html__( 'ردیاب سخت‌افزاری (GT06، Teltonika و…): دستگاه را به سرور Traccar وصل کنید و در traccar.xml ارسال به نشانی زیر را فعال کنید:', 'boshrouyeh-eservices' ) . ' <code dir="ltr">' . esc_html( rest_url( 'bmn/v1/fleet/osmand' ) ) . '?id=&lt;شناسه&gt;&amp;lat={latitude}&amp;lon={longitude}&amp;speed={speed}&amp;bearing={course}&amp;timestamp={fixTime}</code></li>';
		echo '<li>' . esc_html__( 'سامانهٔ اختصاصی: POST JSON به', 'boshrouyeh-eservices' ) . ' <code dir="ltr">' . esc_html( rest_url( 'bmn/v1/fleet/ping' ) ) . '</code> ' . esc_html__( 'با هدر X-Device-Key و بدنهٔ {"lat":..,"lng":..,"speed":..,"heading":..}', 'boshrouyeh-eservices' ) . '</li>';
		echo '</ol></details>';
		$last = get_post_meta( $p->ID, '_bmn_last', true );
		if ( is_array( $last ) && ! empty( $last['t'] ) ) {
			echo '<p>' . esc_html__( 'آخرین موقعیت:', 'boshrouyeh-eservices' ) . ' ' . esc_html( bmn_jdate( 'Y/m/d H:i:s', (int) $last['t'] ) . ' — ' . $last['lat'] . ', ' . $last['lng'] . ' — ' . $last['s'] . ' km/h' ) . '</p>';
		}
	}

	/**
	 * Save vehicle.
	 *
	 * @param int $id Post.
	 */
	public static function save_vehicle( $id ) {
		if ( ! isset( $_POST['bmn_vehicle_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmn_vehicle_nonce'] ) ), 'bmn_vehicle_save' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		update_post_meta( $id, '_bmn_route', isset( $_POST['bmn_route'] ) ? absint( $_POST['bmn_route'] ) : 0 );
		update_post_meta( $id, '_bmn_plate', isset( $_POST['bmn_plate'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['bmn_plate'] ) ), 0, 30 ) : '' );
		if ( ! empty( $_POST['bmn_new_token'] ) ) {
			$token = 'bsh' . bin2hex( random_bytes( 14 ) );
			update_post_meta( $id, '_bmn_token_hash', hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ) );
			set_transient( 'bmn_fleet_token_' . $id . '_' . get_current_user_id(), $token, 10 * MINUTE_IN_SECONDS );
			BMN_Staff::log( 'fleet_key', $id, 'new device token' );
		}
		delete_transient( 'bmn_fleet_live' );
	}

	/**
	 * Keep 3 days of raw positions.
	 */
	public static function purge() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE recorded_at < %s', self::table(), gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ) ) );
	}
}
