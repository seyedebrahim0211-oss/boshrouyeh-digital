<?php
/**
 * Bulk SMS panel (uses the e-services SMS driver): groups, personalisation, scheduling, batched cron sending, opt-out.
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * SMS panel.
 */
final class BMNM_SMS_Panel {

	const DBV   = '1';
	const BATCH = 40;
	const MAX   = 5000;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'bmnm_sms_run', array( __CLASS__, 'run' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 12 );
		add_action( 'admin_post_bmnm_sms_create', array( __CLASS__, 'create' ) );
		add_action( 'admin_post_bmnm_sms_cancel', array( __CLASS__, 'cancel' ) );
		add_action( 'admin_post_bmnm_sms_test', array( __CLASS__, 'test' ) );
		add_action( 'wp_ajax_bmnm_sms_count', array( __CLASS__, 'ajax_count' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Tables.
	 *
	 * @return array{0:string,1:string}
	 */
	public static function tables() {
		global $wpdb;
		return array( $wpdb->prefix . 'bmnm_sms_campaigns', $wpdb->prefix . 'bmnm_sms_queue' );
	}

	/**
	 * Install / upgrade tables.
	 */
	public static function maybe_install() {
		if ( self::DBV === get_option( 'bmnm_sms_db' ) ) {
			return;
		}
		self::install();
	}

	/**
	 * dbDelta.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		list( $c, $q ) = self::tables();
		$cs            = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$c} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL DEFAULT '',
			message text NOT NULL,
			audience varchar(255) NOT NULL DEFAULT '',
			total int(11) NOT NULL DEFAULT 0,
			sent int(11) NOT NULL DEFAULT 0,
			failed int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'queued',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			send_at datetime NOT NULL,
			done_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status, send_at)
			) {$cs};"
		);
		dbDelta(
			"CREATE TABLE {$q} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			phone varchar(15) NOT NULL,
			name varchar(80) NOT NULL DEFAULT '',
			status varchar(10) NOT NULL DEFAULT 'wait',
			error varchar(190) NOT NULL DEFAULT '',
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			KEY camp (campaign_id, status)
			) {$cs};"
		);
		update_option( 'bmnm_sms_db', self::DBV, false );
	}

	/**
	 * Every-minute schedule.
	 *
	 * @param array $s Schedules.
	 * @return array
	 */
	public static function schedules( $s ) {
		$s['bmnm_minute'] = array( 'interval' => 60, 'display' => 'هر دقیقه' );
		return $s;
	}

	/**
	 * Ensure the runner is scheduled.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'bmnm_sms_run' ) ) {
			wp_schedule_event( time() + 60, 'bmnm_minute', 'bmnm_sms_run' );
		}
	}

	/**
	 * Driver ready?
	 *
	 * @return bool
	 */
	public static function ready() {
		return class_exists( 'BMN_SMS' ) && 'log' !== BMN_SMS::driver();
	}

	/**
	 * Audience groups.
	 *
	 * @return array<string,string>
	 */
	public static function groups() {
		return array(
			'citizens' => 'همهٔ شهروندان عضو',
			'hoods'    => 'شهروندان محله‌های انتخابی',
			'staff'    => 'کارکنان (نقش‌های انتخابی)',
			'manual'   => 'شماره‌های دستی',
		);
	}

	/**
	 * Resolve recipients.
	 *
	 * @param array $a Audience { group, hoods[], roles[], numbers }.
	 * @return array<string,string> phone => name
	 */
	public static function recipients( $a ) {
		$out   = array();
		$group = (string) ( $a['group'] ?? '' );
		if ( 'manual' === $group ) {
			foreach ( preg_split( '/[\s,،;]+/u', (string) ( $a['numbers'] ?? '' ) ) as $n ) {
				$p = bmnm_mobile( $n );
				if ( $p ) {
					$out[ $p ] = '';
				}
			}
			return array_slice( $out, 0, self::MAX, true );
		}
		$args = array(
			'number'     => self::MAX,
			'fields'     => array( 'ID', 'display_name' ),
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array( 'key' => '_bmn_phone', 'compare' => 'EXISTS' ),
				array(
					'relation' => 'OR',
					array( 'key' => '_bmn_sms_optout', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_bmn_sms_optout', 'value' => '1', 'compare' => '!=' ),
				),
			),
		);
		if ( 'citizens' === $group || 'hoods' === $group ) {
			$args['role'] = 'bmn_citizen';
		}
		if ( 'hoods' === $group ) {
			$hoods = array_filter( array_map( 'sanitize_title', (array) ( $a['hoods'] ?? array() ) ) );
			if ( ! $hoods ) {
				return array();
			}
			$args['meta_query'][] = array( 'key' => '_bmn_neighborhood', 'value' => $hoods, 'compare' => 'IN' );
		}
		if ( 'staff' === $group ) {
			$roles = array_filter( array_map( 'sanitize_key', (array) ( $a['roles'] ?? array() ) ) );
			if ( ! $roles ) {
				return array();
			}
			$args['role__in'] = $roles;
		}
		foreach ( get_users( $args ) as $u ) {
			$p = bmnm_mobile( (string) get_user_meta( $u->ID, '_bmn_phone', true ) );
			if ( $p ) {
				$out[ $p ] = 'شهروند' === $u->display_name ? '' : $u->display_name;
			}
		}
		return $out;
	}

	/**
	 * Audience from POST.
	 *
	 * @return array
	 */
	private static function audience_from_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- callers verify.
		return array(
			'group'   => isset( $_POST['group'] ) ? sanitize_key( $_POST['group'] ) : '',
			'hoods'   => isset( $_POST['hoods'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_POST['hoods'] ) ) : array(),
			'roles'   => isset( $_POST['roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ) ) : array(),
			'numbers' => isset( $_POST['numbers'] ) ? sanitize_textarea_field( wp_unslash( $_POST['numbers'] ) ) : '',
		);
		// phpcs:enable
	}

	/**
	 * AJAX recipient count.
	 */
	public static function ajax_count() {
		check_ajax_referer( 'bmnm_sms', 'nonce' );
		if ( ! current_user_can( 'bmnm_send_sms' ) ) {
			wp_send_json_error( null, 403 );
		}
		wp_send_json_success( array( 'count' => count( self::recipients( self::audience_from_post() ) ) ) );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_submenu_page( 'bmnm-hub', 'سامانهٔ پیامک', 'سامانهٔ پیامک', 'bmnm_send_sms', 'bmnm-sms', array( __CLASS__, 'page' ) );
	}

	/**
	 * Page.
	 */
	public static function page() {
		global $wpdb;
		if ( ! current_user_can( 'bmnm_send_sms' ) ) {
			return;
		}
		list( $c ) = self::tables();
		echo '<div class="wrap bmnm-wrap"><div class="bmnm-hero"><h1>سامانهٔ پیامک شهرداری</h1><p>ارسال گروهی اطلاعیه به شهروندان، محله‌ها و کارکنان؛ زمان‌بندی، شخصی‌سازی و گزارش تحویل.</p></div><hr class="wp-header-end">';
		if ( ! self::ready() ) {
			echo '<div class="notice notice-warning"><p>سرویس پیامک واقعی فعال نیست (درایور Log). پیام‌ها فقط ثبت می‌شوند. از «شهرداری بشرویه ← خدمات الکترونیک» سرویس را تنظیم کنید.</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['m'] ) ? sanitize_key( $_GET['m'] ) : '';
		$map = array( 'created' => array( 'success', 'کمپین ثبت شد و در صف ارسال است.' ), 'empty' => array( 'error', 'گیرنده‌ای یافت نشد.' ), 'rate' => array( 'error', 'کمپین‌های پشت‌سرهم زیاد است؛ کمی صبر کنید.' ), 'canceled' => array( 'success', 'کمپین لغو شد.' ), 'test_ok' => array( 'success', 'پیامک آزمایشی ارسال شد.' ), 'test_fail' => array( 'error', 'ارسال آزمایشی ناموفق بود یا شماره‌ای برای حساب شما ثبت نشده.' ), 'short' => array( 'error', 'متن پیام خالی است.' ) );
		if ( isset( $map[ $msg ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $msg ][0] ) . '"><p>' . esc_html( $map[ $msg ][1] ) . '</p></div>';
		}
		$hoods = get_terms( array( 'taxonomy' => 'bmn_neighborhood', 'hide_empty' => false ) );
		$roles = wp_roles()->get_names();
		echo '<div class="bmnm-cards"><form class="bmnm-panel bmnm-sms-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'bmnm_sms' ) ) . '"><input type="hidden" name="action" value="bmnm_sms_create">';
		wp_nonce_field( 'bmnm_sms_create' );
		echo '<h2>پیامک تازه</h2><p><label>عنوان داخلی<br><input name="title" class="widefat" required maxlength="120" placeholder="مثلاً: قطعی آب محلهٔ ..."></label></p>';
		echo '<p><strong>گیرندگان</strong><br>';
		foreach ( self::groups() as $k => $l ) {
			echo '<label class="bmnm-pill"><input type="radio" name="group" value="' . esc_attr( $k ) . '" ' . checked( 'citizens', $k, false ) . '> ' . esc_html( $l ) . '</label>';
		}
		echo '</p><div class="bmnm-aud aud-hoods"><p>';
		foreach ( is_array( $hoods ) ? $hoods : array() as $h ) {
			echo '<label class="bmnm-pill"><input type="checkbox" name="hoods[]" value="' . esc_attr( $h->slug ) . '"> ' . esc_html( $h->name ) . '</label>';
		}
		echo '</p></div><div class="bmnm-aud aud-staff"><p>';
		foreach ( $roles as $slug => $name ) {
			if ( 'bmn_citizen' === $slug || 'subscriber' === $slug ) {
				continue;
			}
			echo '<label class="bmnm-pill"><input type="checkbox" name="roles[]" value="' . esc_attr( $slug ) . '"> ' . esc_html( translate_user_role( $name ) ) . '</label>';
		}
		echo '</p></div><div class="bmnm-aud aud-manual"><p><textarea name="numbers" rows="4" class="widefat" dir="ltr" placeholder="09121234567, 09351234567 …"></textarea></p></div>';
		echo '<p class="bmnm-count-line">تعداد گیرندگان: <b class="bmnm-rcount">—</b> <button type="button" class="button button-small bmnm-recount">محاسبه</button></p>';
		echo '<p><label>متن پیام <small>(جای‌نگهدار: <code>{name}</code>)</small><br><textarea name="message" rows="5" class="widefat bmnm-smstext" required maxlength="700"></textarea></label><span class="bmnm-smsinfo"></span></p>';
		echo '<p><label><input type="checkbox" name="optout" value="1" checked> افزودن «لغو۱۱» به انتهای پیام (الزامی برای خطوط تبلیغاتی)</label></p>';
		echo '<p><label>زمان ارسال (خالی = همین حالا)<br><input type="datetime-local" name="send_at"></label></p>';
		echo '<p><button class="button button-primary button-large" onclick="return confirm(\'پیامک برای همهٔ گیرندگان ارسال شود؟\')">ثبت و ارسال</button></p></form>';

		echo '<div class="bmnm-panel"><h2>ارسال آزمایشی به شمارهٔ خودم</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmnm_sms_test">';
		wp_nonce_field( 'bmnm_sms_test' );
		$me = (string) get_user_meta( get_current_user_id(), '_bmn_phone', true );
		echo '<p>شمارهٔ ثبت‌شده: <b dir="ltr">' . esc_html( $me ? $me : '—' ) . '</b> <small>(از «نمایه» قابل ویرایش است)</small></p><p><textarea name="message" rows="2" class="widefat">پیامک آزمایشی شهرداری بشرویه</textarea></p><p><button class="button">ارسال آزمایشی</button></p></form></div></div>';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 50', $c ) );
		echo '<div class="bmnm-panel"><h2>کمپین‌ها</h2><table class="widefat striped bmnm-table"><thead><tr><th>عنوان</th><th>گیرندگان</th><th>پیشرفت</th><th>وضعیت</th><th>زمان</th><th>ثبت‌کننده</th><th></th></tr></thead><tbody>';
		$st = array( 'queued' => 'در صف', 'running' => 'در حال ارسال', 'done' => 'پایان‌یافته', 'canceled' => 'لغوشده' );
		foreach ( (array) $rows as $r ) {
			$pct = $r->total ? (int) round( ( $r->sent + $r->failed ) * 100 / $r->total ) : 0;
			$u   = get_userdata( (int) $r->created_by );
			echo '<tr><td><strong>' . esc_html( $r->title ) . '</strong><br><small class="bmnm-muted">' . esc_html( wp_trim_words( $r->message, 14, '…' ) ) . '</small></td>';
			echo '<td>' . esc_html( bmnm_fa( $r->total ) ) . '<br><small>' . esc_html( $r->audience ) . '</small></td>';
			echo '<td><div class="bmnm-bar"><i style="width:' . (int) $pct . '%"></i></div><small>✔ ' . esc_html( bmnm_fa( $r->sent ) ) . ' · ✖ ' . esc_html( bmnm_fa( $r->failed ) ) . '</small></td>';
			echo '<td><span class="bmnm-badge s-' . esc_attr( $r->status ) . '">' . esc_html( $st[ $r->status ] ?? $r->status ) . '</span></td>';
			echo '<td>' . esc_html( bmnm_jdate( 'j F H:i', strtotime( $r->send_at . ' UTC' ) ) ) . '</td><td>' . esc_html( $u ? $u->display_name : '—' ) . '</td><td>';
			if ( in_array( $r->status, array( 'queued', 'running' ), true ) ) {
				echo '<a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bmnm_sms_cancel&id=' . (int) $r->id ), 'bmnm_sms_cancel' ) ) . '" onclick="return confirm(\'لغو شود؟\')">لغو</a>';
			}
			echo '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="7" class="bmnm-muted">هنوز کمپینی ثبت نشده است.</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * Create campaign.
	 */
	public static function create() {
		global $wpdb;
		check_admin_referer( 'bmnm_sms_create' );
		if ( ! current_user_can( 'bmnm_send_sms' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		$back = admin_url( 'admin.php?page=bmnm-sms' );
		$msg  = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) ) : '';
		if ( '' === $msg ) {
			wp_safe_redirect( add_query_arg( 'm', 'short', $back ) );
			exit;
		}
		if ( ! current_user_can( 'manage_options' ) && ! bmnm_rate_limit( 'smscamp_' . get_current_user_id(), 3, HOUR_IN_SECONDS ) ) {
			wp_safe_redirect( add_query_arg( 'm', 'rate', $back ) );
			exit;
		}
		if ( ! empty( $_POST['optout'] ) && false === mb_strpos( $msg, 'لغو' ) ) {
			$msg .= "\nلغو۱۱";
		}
		$aud  = self::audience_from_post();
		$list = self::recipients( $aud );
		if ( ! $list ) {
			wp_safe_redirect( add_query_arg( 'm', 'empty', $back ) );
			exit;
		}
		$when = isset( $_POST['send_at'] ) ? sanitize_text_field( wp_unslash( $_POST['send_at'] ) ) : '';
		$ts   = $when ? ( new DateTimeImmutable( str_replace( 'T', ' ', $when ), wp_timezone() ) )->getTimestamp() : time();
		$desc = self::groups()[ $aud['group'] ] ?? '';
		list( $c, $q ) = self::tables();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$c,
			array(
				'title'      => mb_substr( isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'بی‌عنوان', 0, 190 ),
				'message'    => $msg,
				'audience'   => mb_substr( $desc, 0, 250 ),
				'total'      => count( $list ),
				'status'     => 'queued',
				'created_by' => get_current_user_id(),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'send_at'    => gmdate( 'Y-m-d H:i:s', max( time(), $ts ) ),
			)
		);
		$cid = (int) $wpdb->insert_id;
		foreach ( array_chunk( $list, 200, true ) as $chunk ) {
			$vals = array();
			foreach ( $chunk as $phone => $name ) {
				$vals[] = $wpdb->prepare( '(%d,%s,%s)', $cid, $phone, mb_substr( $name, 0, 80 ) );
			}
			$wpdb->query( "INSERT INTO {$q} (campaign_id, phone, name) VALUES " . implode( ',', $vals ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- values prepared above.
		}
		// phpcs:enable
		bmnm_log( 'sms', 'کمپین «' . $desc . '» برای ' . count( $list ) . ' گیرنده ثبت شد.' );
		spawn_cron();
		wp_safe_redirect( add_query_arg( 'm', 'created', $back ) );
		exit;
	}

	/**
	 * Cancel campaign.
	 */
	public static function cancel() {
		global $wpdb;
		check_admin_referer( 'bmnm_sms_cancel' );
		if ( ! current_user_can( 'bmnm_send_sms' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		list( $c ) = self::tables();
		$id        = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$wpdb->update( $c, array( 'status' => 'canceled', 'done_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_safe_redirect( admin_url( 'admin.php?page=bmnm-sms&m=canceled' ) );
		exit;
	}

	/**
	 * Test to self.
	 */
	public static function test() {
		check_admin_referer( 'bmnm_sms_test' );
		if ( ! current_user_can( 'bmnm_send_sms' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		$phone = bmnm_mobile( (string) get_user_meta( get_current_user_id(), '_bmn_phone', true ) );
		$text  = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$ok    = $phone && $text && class_exists( 'BMN_SMS' ) && bmnm_rate_limit( 'smstest_' . get_current_user_id(), 5, HOUR_IN_SECONDS ) && true === BMN_SMS::send( $phone, $text );
		wp_safe_redirect( admin_url( 'admin.php?page=bmnm-sms&m=' . ( $ok ? 'test_ok' : 'test_fail' ) ) );
		exit;
	}

	/**
	 * Cron runner: one batch per minute.
	 */
	public static function run() {
		global $wpdb;
		if ( get_transient( 'bmnm_sms_lock' ) || ! class_exists( 'BMN_SMS' ) ) {
			return;
		}
		set_transient( 'bmnm_sms_lock', 1, 55 );
		list( $c, $q ) = self::tables();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$camp = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE status IN ('queued','running') AND send_at <= %s ORDER BY id ASC LIMIT 1", $c, gmdate( 'Y-m-d H:i:s' ) ) );
		if ( $camp ) {
			if ( 'queued' === $camp->status ) {
				$wpdb->update( $c, array( 'status' => 'running' ), array( 'id' => $camp->id ) );
			}
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE campaign_id = %d AND status = 'wait' ORDER BY id ASC LIMIT %d", $q, $camp->id, self::BATCH ) );
			$sent  = 0;
			$fail  = 0;
			foreach ( (array) $items as $it ) {
				$text = trim( str_replace( '{name}', $it->name, (string) $camp->message ) );
				$text = preg_replace( '/^\s*[،,]\s*/u', '', $text );
				$r    = BMN_SMS::send( $it->phone, $text );
				if ( true === $r ) {
					++$sent;
					$wpdb->update( $q, array( 'status' => 'sent', 'sent_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $it->id ) );
				} else {
					++$fail;
					$wpdb->update( $q, array( 'status' => 'fail', 'error' => mb_substr( is_wp_error( $r ) ? $r->get_error_message() : 'error', 0, 190 ) ), array( 'id' => $it->id ) );
				}
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET sent = sent + %d, failed = failed + %d WHERE id = %d', $c, $sent, $fail, $camp->id ) );
			$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE campaign_id = %d AND status = 'wait'", $q, $camp->id ) );
			if ( ! $left ) {
				$wpdb->update( $c, array( 'status' => 'done', 'done_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $camp->id ) );
			}
		}
		// phpcs:enable
		delete_transient( 'bmnm_sms_lock' );
	}

	/**
	 * Queue size (dashboard).
	 *
	 * @return int
	 */
	public static function pending() {
		global $wpdb;
		list( , $q ) = self::tables();
		if ( self::DBV !== get_option( 'bmnm_sms_db' ) ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'wait'", $q ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * REST: citizen SMS opt-out.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/me/sms',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'me_sms' ),
				'permission_callback' => static fn() => is_user_logged_in(),
				'args'                => array( 'receive' => array( 'type' => 'boolean' ) ),
			)
		);
	}

	/**
	 * GET/POST /me/sms {receive:bool}
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function me_sms( $req ) {
		$uid = get_current_user_id();
		if ( 'POST' === $req->get_method() && null !== $req['receive'] ) {
			if ( $req['receive'] ) {
				delete_user_meta( $uid, '_bmn_sms_optout' );
			} else {
				update_user_meta( $uid, '_bmn_sms_optout', '1' );
			}
		}
		return rest_ensure_response( array( 'receive' => '1' !== get_user_meta( $uid, '_bmn_sms_optout', true ) ) );
	}
}
