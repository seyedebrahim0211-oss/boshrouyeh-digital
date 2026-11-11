<?php
/**
 * Appointments with municipal units (نوبت‌دهی واحدها). Schedule is configured per unit (bmn_unit term).
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Appointments.
 */
final class BMNM_Appointments {

	const DAYS_AHEAD = 14;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'bmn_unit_edit_form_fields', array( __CLASS__, 'term_fields' ) );
		add_action( 'edited_bmn_unit', array( __CLASS__, 'term_save' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'bmn_unit_appointment', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_bmnm_appt_status', array( __CLASS__, 'set_status' ) );
	}

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bmnm_appts';
	}

	/**
	 * Install.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			unit_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			phone varchar(15) NOT NULL DEFAULT '',
			name varchar(80) NOT NULL DEFAULT '',
			day date NOT NULL,
			slot char(5) NOT NULL,
			subject varchar(255) NOT NULL DEFAULT '',
			code char(6) NOT NULL,
			status varchar(12) NOT NULL DEFAULT 'booked',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY unit_day (unit_id, day, slot),
			KEY user_id (user_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
	}

	/**
	 * Status labels.
	 *
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array( 'booked' => 'رزروشده', 'done' => 'انجام شد', 'noshow' => 'حاضر نشد', 'canceled' => 'لغو شد' );
	}

	/**
	 * Unit config.
	 *
	 * @param int $tid Term.
	 * @return array{on:bool,days:int[],slots:string[],cap:int}
	 */
	public static function cfg( $tid ) {
		$days  = array_map( 'intval', array_filter( explode( ',', (string) get_term_meta( $tid, '_bmnm_appt_days', true ) ), 'strlen' ) );
		$slots = array_values( array_filter( array_map( 'trim', explode( ',', (string) get_term_meta( $tid, '_bmnm_appt_slots', true ) ) ), static fn( $s ) => (bool) preg_match( '/^\d{2}:\d{2}$/', $s ) ) );
		return array(
			'on'    => (bool) get_term_meta( $tid, '_bmnm_appt_on', true ),
			'days'  => $days ? $days : array( 0, 1, 2, 3, 4 ),
			'slots' => $slots ? $slots : array( '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00' ),
			'cap'   => max( 1, (int) get_term_meta( $tid, '_bmnm_appt_cap', true ) ),
		);
	}

	/**
	 * Term edit fields.
	 *
	 * @param WP_Term $t Term.
	 */
	public static function term_fields( $t ) {
		$c    = self::cfg( $t->term_id );
		$days = function_exists( 'bmn_weekdays' ) ? bmn_weekdays() : array( 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه' );
		wp_nonce_field( 'bmnm_appt_term', 'bmnm_appt_nonce' );
		echo '<tr class="form-field"><th>نوبت‌دهی اینترنتی</th><td><label><input type="checkbox" name="appt_on" value="1"' . checked( $c['on'], true, false ) . '> فعال</label></td></tr>';
		echo '<tr class="form-field"><th>روزهای پذیرش</th><td>';
		foreach ( $days as $i => $d ) {
			echo '<label style="margin-inline-end:10px"><input type="checkbox" name="appt_days[]" value="' . (int) $i . '"' . checked( in_array( $i, $c['days'], true ), true, false ) . '> ' . esc_html( $d ) . '</label>';
		}
		echo '</td></tr><tr class="form-field"><th>ساعت‌های نوبت</th><td><input name="appt_slots" dir="ltr" value="' . esc_attr( implode( ',', $c['slots'] ) ) . '"><p class="description">با ویرگول، مثل 08:00,08:30,09:00</p></td></tr>';
		echo '<tr class="form-field"><th>ظرفیت هر نوبت</th><td><input type="number" min="1" max="20" name="appt_cap" value="' . (int) $c['cap'] . '"></td></tr>';
	}

	/**
	 * Save term fields.
	 *
	 * @param int $tid Term.
	 */
	public static function term_save( $tid ) {
		if ( ! isset( $_POST['bmnm_appt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmnm_appt_nonce'] ) ), 'bmnm_appt_term' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_term_meta( $tid, '_bmnm_appt_on', ! empty( $_POST['appt_on'] ) ? 1 : 0 );
		update_term_meta( $tid, '_bmnm_appt_days', implode( ',', array_map( 'absint', (array) ( $_POST['appt_days'] ?? array() ) ) ) );
		update_term_meta( $tid, '_bmnm_appt_slots', isset( $_POST['appt_slots'] ) ? preg_replace( '/[^0-9:,]/', '', sanitize_text_field( wp_unslash( $_POST['appt_slots'] ) ) ) : '' );
		update_term_meta( $tid, '_bmnm_appt_cap', isset( $_POST['appt_cap'] ) ? min( 20, max( 1, absint( $_POST['appt_cap'] ) ) ) : 1 );
	}

	/**
	 * Saturday-first weekday index of a date.
	 *
	 * @param DateTimeImmutable $d Date.
	 * @return int
	 */
	private static function sat_index( $d ) {
		return ( (int) $d->format( 'w' ) + 1 ) % 7;
	}

	/**
	 * Free slots for the next days.
	 *
	 * @param int $tid Unit.
	 * @return array
	 */
	public static function availability( $tid ) {
		global $wpdb;
		$c     = self::cfg( $tid );
		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'today', $tz );
		$end   = $today->modify( '+' . self::DAYS_AHEAD . ' days' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT day, slot, COUNT(*) n FROM %i WHERE unit_id = %d AND status = 'booked' AND day BETWEEN %s AND %s GROUP BY day, slot", self::table(), $tid, $today->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$taken = array();
		foreach ( (array) $rows as $r ) {
			$taken[ $r->day . ' ' . $r->slot ] = (int) $r->n;
		}
		$now = new DateTimeImmutable( 'now', $tz );
		$out = array();
		for ( $i = 0; $i <= self::DAYS_AHEAD; $i++ ) {
			$d = $today->modify( "+{$i} days" );
			if ( ! in_array( self::sat_index( $d ), $c['days'], true ) ) {
				continue;
			}
			$slots = array();
			foreach ( $c['slots'] as $s ) {
				$at = new DateTimeImmutable( $d->format( 'Y-m-d' ) . ' ' . $s, $tz );
				if ( $at <= $now->modify( '+1 hour' ) ) {
					continue;
				}
				$left = $c['cap'] - ( $taken[ $d->format( 'Y-m-d' ) . ' ' . $s ] ?? 0 );
				$slots[] = array( 'time' => $s, 'time_fa' => bmnm_fa( $s ), 'left' => max( 0, $left ) );
			}
			if ( $slots ) {
				$out[] = array( 'date' => $d->format( 'Y-m-d' ), 'date_fa' => bmnm_jdate( 'l j F', $d->getTimestamp() ), 'slots' => $slots );
			}
		}
		return $out;
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		$auth = static fn() => is_user_logged_in() ? true : bmnm_error( 'auth', 'ابتدا وارد شوید.', 401 );
		register_rest_route( 'bmn/v1', '/appointments/units', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_units' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'bmn/v1', '/appointments/slots', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_slots' ), 'permission_callback' => '__return_true', 'args' => array( 'unit' => array( 'type' => 'integer', 'required' => true ) ) ) );
		register_rest_route(
			'bmn/v1',
			'/appointments',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_mine' ), 'permission_callback' => $auth ),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'r_book' ),
					'permission_callback' => $auth,
					'args'                => array(
						'unit'    => array( 'type' => 'integer', 'required' => true ),
						'date'    => array( 'type' => 'string', 'required' => true, 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
						'slot'    => array( 'type' => 'string', 'required' => true, 'pattern' => '^\d{2}:\d{2}$' ),
						'subject' => array( 'type' => 'string', 'default' => '', 'maxLength' => 250, 'sanitize_callback' => 'sanitize_text_field' ),
					),
				),
			)
		);
		register_rest_route( 'bmn/v1', '/appointments/(?P<id>\d+)/cancel', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_cancel' ), 'permission_callback' => $auth ) );
	}

	/**
	 * Units with booking on.
	 *
	 * @return array
	 */
	public static function units() {
		$terms = get_terms( array( 'taxonomy' => 'bmn_unit', 'hide_empty' => false ) );
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			if ( self::cfg( $t->term_id )['on'] ) {
				$out[] = array( 'id' => $t->term_id, 'name' => $t->name, 'desc' => wp_strip_all_tags( $t->description ) );
			}
		}
		return $out;
	}

	/**
	 * GET units.
	 *
	 * @return WP_REST_Response
	 */
	public static function r_units() {
		return rest_ensure_response( self::units() );
	}

	/**
	 * GET slots.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_slots( $req ) {
		$tid = (int) $req['unit'];
		if ( ! term_exists( $tid, 'bmn_unit' ) || ! self::cfg( $tid )['on'] ) {
			return bmnm_error( 'unit', 'این واحد نوبت اینترنتی ندارد.', 404 );
		}
		$res = rest_ensure_response( self::availability( $tid ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/**
	 * Row DTO.
	 *
	 * @param object $r Row.
	 * @return array
	 */
	private static function dto( $r ) {
		$t  = get_term( (int) $r->unit_id, 'bmn_unit' );
		$ts = ( new DateTimeImmutable( $r->day . ' ' . $r->slot, wp_timezone() ) )->getTimestamp();
		return array(
			'id'           => (int) $r->id,
			'unit'         => $t && ! is_wp_error( $t ) ? $t->name : '',
			'date_fa'      => bmnm_jdate( 'l j F Y', $ts ),
			'time_fa'      => bmnm_fa( $r->slot ),
			'subject'      => $r->subject,
			'code'         => bmnm_fa( $r->code ),
			'status'       => $r->status,
			'status_label' => self::statuses()[ $r->status ] ?? $r->status,
			'can_cancel'   => 'booked' === $r->status && $ts > time() + HOUR_IN_SECONDS,
		);
	}

	/**
	 * GET mine.
	 *
	 * @return WP_REST_Response
	 */
	public static function r_mine() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY day DESC, slot DESC LIMIT 30', self::table(), get_current_user_id() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return rest_ensure_response( array_map( array( __CLASS__, 'dto' ), (array) $rows ) );
	}

	/**
	 * POST book.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_book( $req ) {
		global $wpdb;
		$uid = get_current_user_id();
		$tid = (int) $req['unit'];
		if ( ! bmnm_rate_limit( 'appt_' . $uid, 6, DAY_IN_SECONDS ) ) {
			return bmnm_error( 'rate', 'تعداد نوبت‌های امروز شما زیاد است.', 429 );
		}
		$avail = self::availability( $tid );
		$ok    = false;
		foreach ( $avail as $d ) {
			if ( $d['date'] === $req['date'] ) {
				foreach ( $d['slots'] as $s ) {
					$ok = $ok || ( $s['time'] === $req['slot'] && $s['left'] > 0 );
				}
			}
		}
		if ( ! $ok || ! self::cfg( $tid )['on'] ) {
			return bmnm_error( 'slot', 'این نوبت دیگر خالی نیست؛ زمان دیگری انتخاب کنید.', 409 );
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$dup = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND unit_id = %d AND status = 'booked' AND day >= %s", self::table(), $uid, $tid, wp_date( 'Y-m-d' ) ) );
		if ( $dup ) {
			return bmnm_error( 'dup', 'شما برای این واحد نوبت فعال دارید.', 409 );
		}
		$u     = wp_get_current_user();
		$phone = (string) get_user_meta( $uid, '_bmn_phone', true );
		$code  = (string) random_int( 100000, 999999 );
		$wpdb->insert(
			self::table(),
			array(
				'unit_id'    => $tid,
				'user_id'    => $uid,
				'phone'      => $phone,
				'name'       => mb_substr( $u->display_name, 0, 80 ),
				'day'        => $req['date'],
				'slot'       => $req['slot'],
				'subject'    => mb_substr( (string) $req['subject'], 0, 250 ),
				'code'       => $code,
				'status'     => 'booked',
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), (int) $wpdb->insert_id ) );
		// phpcs:enable
		$dto = self::dto( $row );
		if ( $phone && class_exists( 'BMN_SMS' ) ) {
			BMN_SMS::send( $phone, 'شهرداری بشرویه: نوبت شما در «' . $dto['unit'] . '» ' . $dto['date_fa'] . ' ساعت ' . $dto['time_fa'] . ' ثبت شد. کد نوبت: ' . $dto['code'] );
		}
		return rest_ensure_response( $dto );
	}

	/**
	 * POST cancel.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_cancel( $req ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND user_id = %d', self::table(), (int) $req['id'], get_current_user_id() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $row || ! self::dto( $row )['can_cancel'] ) {
			return bmnm_error( 'cancel', 'این نوبت قابل لغو نیست.', 403 );
		}
		$wpdb->update( self::table(), array( 'status' => 'canceled' ), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row->status = 'canceled';
		return rest_ensure_response( self::dto( $row ) );
	}

	/**
	 * [bmn_unit_appointment]
	 *
	 * @return string
	 */
	public static function shortcode() {
		return '<div class="bmnm-appt kh-card" data-units="' . esc_attr( wp_json_encode( self::units() ) ) . '" data-logged="' . ( is_user_logged_in() ? '1' : '0' ) . '"><h3>نوبت‌دهی اینترنتی واحدهای شهرداری</h3><noscript>برای رزرو، جاوااسکریپت را فعال کنید.</noscript></div>';
	}

	/**
	 * Staff page.
	 */
	public static function menu() {
		if ( isset( $GLOBALS['admin_page_hooks']['bmn-desk'] ) ) {
			add_submenu_page( 'bmn-desk', 'نوبت‌های واحد', 'نوبت‌های واحد', 'bmn_dashboard', 'bmnm-appts', array( __CLASS__, 'page' ) );
		} else {
			add_submenu_page( 'bmnm-hub', 'نوبت‌های واحد', 'نوبت‌های واحد', 'manage_options', 'bmnm-appts', array( __CLASS__, 'page' ) );
		}
	}

	/**
	 * Staff list.
	 */
	public static function page() {
		global $wpdb;
		$all  = current_user_can( 'bmn_all_units' ) || current_user_can( 'manage_options' );
		$unit = (int) get_user_meta( get_current_user_id(), 'bmn_unit', true );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$day  = isset( $_GET['day'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['day'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['day'] ) ) : wp_date( 'Y-m-d' );
		echo '<div class="wrap bmnm-wrap"><div class="bmnm-hero"><h1>نوبت‌های واحد</h1><p>' . esc_html( bmnm_jdate( 'l j F Y', ( new DateTimeImmutable( $day, wp_timezone() ) )->getTimestamp() ) ) . '</p></div><hr class="wp-header-end">';
		if ( ! $all && ! $unit ) {
			echo '<div class="notice notice-warning"><p>واحد شما مشخص نشده است؛ از مدیر کل بخواهید واحد را در «دسترسی‌ها» تعیین کند.</p></div></div>';
			return;
		}
		echo '<form class="bmnm-search"><input type="hidden" name="page" value="bmnm-appts"><input type="date" name="day" value="' . esc_attr( $day ) . '"><button class="button">نمایش</button></form>';
		$sql  = $all
			? $wpdb->prepare( 'SELECT * FROM %i WHERE day = %s ORDER BY slot', self::table(), $day )
			: $wpdb->prepare( 'SELECT * FROM %i WHERE day = %s AND unit_id = %d ORDER BY slot', self::table(), $day, $unit );
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		echo '<div class="bmnm-panel"><table class="widefat striped"><thead><tr><th>ساعت</th><th>واحد</th><th>شهروند</th><th>موضوع</th><th>کد</th><th>وضعیت</th><th></th></tr></thead><tbody>';
		foreach ( (array) $rows as $r ) {
			$d = self::dto( $r );
			echo '<tr><td><b>' . esc_html( $d['time_fa'] ) . '</b></td><td>' . esc_html( $d['unit'] ) . '</td><td>' . esc_html( $r->name ) . '<br><small dir="ltr">' . esc_html( $r->phone ) . '</small></td><td>' . esc_html( $r->subject ) . '</td><td><code>' . esc_html( $r->code ) . '</code></td><td><span class="bmnm-badge">' . esc_html( $d['status_label'] ) . '</span></td><td>';
			if ( 'booked' === $r->status ) {
				foreach ( array( 'done' => 'انجام شد', 'noshow' => 'حاضر نشد' ) as $k => $l ) {
					echo '<a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bmnm_appt_status&id=' . (int) $r->id . '&s=' . $k ), 'bmnm_appt_' . $r->id ) ) . '">' . esc_html( $l ) . '</a> ';
				}
			}
			echo '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="7" class="bmnm-muted">نوبتی برای این روز ثبت نشده است.</td></tr>';
		}
		echo '</tbody></table><p class="description">تنظیم روزها و ساعت‌های هر واحد: «درخواست‌ها ← واحدها» و ویرایش واحد.</p></div></div>';
	}

	/**
	 * Mark status.
	 */
	public static function set_status() {
		global $wpdb;
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'bmnm_appt_' . $id );
		$s   = isset( $_GET['s'] ) && in_array( $_GET['s'], array( 'done', 'noshow' ), true ) ? sanitize_key( $_GET['s'] ) : '';
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$own = $row && ( current_user_can( 'bmn_all_units' ) || current_user_can( 'manage_options' ) || (int) get_user_meta( get_current_user_id(), 'bmn_unit', true ) === (int) $row->unit_id );
		if ( ! $s || ! $own || ! current_user_can( 'bmn_dashboard' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		$wpdb->update( self::table(), array( 'status' => $s ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=bmnm-appts' ) );
		exit;
	}
}
