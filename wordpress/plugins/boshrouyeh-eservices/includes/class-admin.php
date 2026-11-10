<?php
/**
 * Admin: e-services settings, payments report, dashboard KPIs, session revocation.
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
final class BMN_ES_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
		add_action( 'admin_post_bmn_payments_csv', array( __CLASS__, 'csv' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'user_box' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'user_save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'sandbox_notice' ) );
	}

	/**
	 * Menus.
	 */
	public static function menu() {
		add_submenu_page( 'bmn-settings', __( 'خدمات الکترونیک', 'boshrouyeh-eservices' ), __( 'خدمات الکترونیک', 'boshrouyeh-eservices' ), 'manage_options', 'bmn-eservices', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'bmn-settings', __( 'پرداخت‌ها', 'boshrouyeh-eservices' ), __( 'پرداخت‌ها', 'boshrouyeh-eservices' ), 'bmn_view_payments', 'bmn-payments', array( __CLASS__, 'render_payments' ) );
	}

	/**
	 * Settings fields.
	 *
	 * @return array
	 */
	private static function fields() {
		return array(
			'sms_driver'         => array( __( 'سرویس پیامک', 'boshrouyeh-eservices' ), 'select', array( 'log' => 'Log (توسعه)', 'kavenegar' => 'کاوه‌نگار', 'smsir' => 'SMS.ir' ) ),
			'sms_sender'         => array( __( 'شمارهٔ خط ارسال', 'boshrouyeh-eservices' ), 'text' ),
			'kavenegar_key'      => array( __( 'API Key کاوه‌نگار', 'boshrouyeh-eservices' ), 'secret' ),
			'kavenegar_template' => array( __( 'نام الگوی OTP کاوه‌نگار', 'boshrouyeh-eservices' ), 'text' ),
			'smsir_key'          => array( __( 'API Key اس‌ام‌اس‌دات‌آی‌آر', 'boshrouyeh-eservices' ), 'secret' ),
			'smsir_template'     => array( __( 'شناسهٔ قالب OTP (پارامتر CODE)', 'boshrouyeh-eservices' ), 'text' ),
			'gateway'            => array( __( 'درگاه پرداخت', 'boshrouyeh-eservices' ), 'select', array( 'sandbox' => 'آزمایشی', 'zarinpal' => 'زرین‌پال', 'sep' => 'سامان (سپ)' ) ),
			'zarinpal_merchant'  => array( __( 'Merchant ID زرین‌پال', 'boshrouyeh-eservices' ), 'secret' ),
			'zarinpal_sandbox'   => array( __( 'زرین‌پال در حالت سندباکس', 'boshrouyeh-eservices' ), 'checkbox' ),
			'sep_terminal'       => array( __( 'شمارهٔ ترمینال سامان', 'boshrouyeh-eservices' ), 'text' ),
			'staff_email'        => array( __( 'ایمیل اطلاع‌رسانی درخواست‌های جدید', 'boshrouyeh-eservices' ), 'email' ),
		);
	}

	/**
	 * Register setting.
	 */
	public static function settings() {
		register_setting(
			'bmn_es_group',
			'bmn_es_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize (secrets left blank keep their previous value).
	 *
	 * @param mixed $in Input.
	 * @return array
	 */
	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$old = get_option( 'bmn_es_settings', array() );
		$out = array();
		foreach ( self::fields() as $k => $f ) {
			$v = $in[ $k ] ?? '';
			switch ( $f[1] ) {
				case 'select':
					$out[ $k ] = array_key_exists( (string) $v, $f[2] ) ? (string) $v : (string) array_key_first( $f[2] );
					break;
				case 'checkbox':
					$out[ $k ] = $v ? 1 : 0;
					break;
				case 'email':
					$out[ $k ] = sanitize_email( (string) $v );
					break;
				case 'secret':
					$v         = trim( sanitize_text_field( (string) $v ) );
					$out[ $k ] = '' === $v ? (string) ( $old[ $k ] ?? '' ) : $v;
					break;
				default:
					$out[ $k ] = sanitize_text_field( (string) $v );
			}
		}
		return $out;
	}

	/**
	 * Settings page.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'تنظیمات خدمات الکترونیک', 'boshrouyeh-eservices' ) . '</h1>';
		if ( defined( 'BMN_SMS_DRIVER' ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'سرویس پیامک با ثابت BMN_SMS_DRIVER در wp-config تعیین شده است.', 'boshrouyeh-eservices' ) . '</p></div>';
		}
		echo '<form method="post" action="options.php">';
		settings_fields( 'bmn_es_group' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( self::fields() as $k => $f ) {
			$val  = bmn_es_option( $k, '' );
			$name = 'bmn_es_settings[' . $k . ']';
			echo '<tr><th scope="row"><label for="bmn_es_' . esc_attr( $k ) . '">' . esc_html( $f[0] ) . '</label></th><td>';
			switch ( $f[1] ) {
				case 'select':
					echo '<select id="bmn_es_' . esc_attr( $k ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( $f[2] as $ok => $ol ) {
						echo '<option value="' . esc_attr( $ok ) . '" ' . selected( (string) $val, (string) $ok, false ) . '>' . esc_html( $ol ) . '</option>';
					}
					echo '</select>';
					break;
				case 'checkbox':
					echo '<input type="checkbox" id="bmn_es_' . esc_attr( $k ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $val, true, false ) . '>';
					break;
				case 'secret':
					echo '<input class="regular-text" type="password" autocomplete="new-password" id="bmn_es_' . esc_attr( $k ) . '" name="' . esc_attr( $name ) . '" value="" placeholder="' . esc_attr( $val ? '••••••••' . substr( (string) $val, -4 ) : '' ) . '"><p class="description">' . esc_html__( 'برای حفظ مقدار فعلی خالی بگذارید.', 'boshrouyeh-eservices' ) . '</p>';
					break;
				default:
					echo '<input class="regular-text" type="' . ( 'email' === $f[1] ? 'email' : 'text' ) . '" id="bmn_es_' . esc_attr( $k ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		submit_button();
		echo '</form><p class="description">' . esc_html__( 'برای امنیت بیشتر، کلید JWT را با ثابت BMN_JWT_SECRET (دست‌کم ۳۲ نویسه) در wp-config.php تعریف کنید.', 'boshrouyeh-eservices' ) . '</p></div>';
	}

	/**
	 * Warn when sandbox gateway is active on a live site.
	 */
	public static function sandbox_notice() {
		if ( current_user_can( 'manage_options' ) && 'sandbox' === BMN_Payments::gateway_key() && 'production' === wp_get_environment_type() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'درگاه پرداخت در حالت آزمایشی است؛ پیش از انتشار، درگاه بانکی را در «شهرداری بشرویه ← خدمات الکترونیک» فعال کنید.', 'boshrouyeh-eservices' ) . '</p></div>';
		}
	}

	/**
	 * Payments page.
	 */
	public static function render_payments() {
		global $wpdb;
		if ( ! current_user_can( 'bmn_view_payments' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$sql    = $status
			? $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY id DESC LIMIT 200', BMN_Payments::table(), $status )
			: $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 200', BMN_Payments::table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$rows = $wpdb->get_results( $sql );
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'پرداخت‌ها', 'boshrouyeh-eservices' ) . '</h1> ';
		if ( current_user_can( 'bmn_export_payments' ) || current_user_can( 'manage_options' ) ) {
			echo '<a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bmn_payments_csv' ), 'bmn_csv' ) ) . '">CSV</a>';
		}
		echo '<ul class="subsubsub">';
		foreach ( array( '' => 'همه', 'paid' => 'موفق', 'failed' => 'ناموفق', 'pending' => 'در انتظار' ) as $k => $l ) {
			echo '<li><a href="' . esc_url( add_query_arg( 'status', $k, admin_url( 'admin.php?page=bmn-payments' ) ) ) . '"' . ( $status === $k ? ' class="current"' : '' ) . '>' . esc_html( $l ) . '</a> | </li>';
		}
		echo '</ul><table class="widefat striped"><thead><tr><th>#</th><th>' . esc_html__( 'بابت', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'مبلغ (تومان)', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'وضعیت', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'درگاه', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'کد رهگیری', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'همراه', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'تاریخ', 'boshrouyeh-eservices' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $rows as $r ) {
			$d = BMN_Payments::dto( $r );
			echo '<tr><td>' . (int) $r->id . '</td><td><a href="' . esc_url( $d['receipt_url'] ) . '" target="_blank">' . esc_html( $r->title ) . '</a></td><td>' . esc_html( $d['amount_fa'] ) . '</td><td>' . esc_html( $d['status_label'] ) . '</td><td>' . esc_html( $r->gateway ) . '</td><td dir="ltr">' . esc_html( $r->bank_ref ) . '</td><td dir="ltr">' . esc_html( $r->phone ) . '</td><td>' . esc_html( $d['date_fa'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * CSV export (formula-injection safe).
	 */
	public static function csv() {
		global $wpdb;
		if ( ! current_user_can( 'bmn_export_payments' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'bmn_csv' );
		if ( class_exists( 'BMN_Staff' ) ) {
			BMN_Staff::log( 'payments.csv', 0, 'export' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, title, ref, amount, status, gateway, bank_ref, phone, created_at, paid_at FROM %i ORDER BY id DESC', BMN_Payments::table() ), ARRAY_A );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=payments-' . gmdate( 'Ymd' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'id', 'title', 'ref', 'amount_toman', 'status', 'gateway', 'bank_ref', 'phone', 'created_utc', 'paid_utc' ) );
		foreach ( (array) $rows as $r ) {
			fputcsv( $out, array_map( fn( $v ) => preg_match( '/^[=+\-@\t\r]/', (string) $v ) ? "'" . $v : $v, $r ) );
		}
		exit;
	}

	/**
	 * Dashboard KPIs (doc: success indicators).
	 */
	public static function widget() {
		if ( ! current_user_can( 'edit_bmn_requests' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'bmn_kpis',
			__( 'شاخص‌های خدمات الکترونیک (۳۰ روز)', 'boshrouyeh-eservices' ),
			static function () {
				global $wpdb;
				$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
				$q     = new WP_Query(
					array(
						'post_type'      => BMN_Requests::PT,
						'post_status'    => 'any',
						'posts_per_page' => 500,
						'date_query'     => array( array( 'after' => '30 days ago' ) ),
						'meta_query'     => array( array( 'key' => '_bmn_type', 'value' => 'report137' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'no_found_rows'  => true,
					)
				);
				$total = count( $q->posts );
				$fast  = 0;
				$open  = 0;
				foreach ( $q->posts as $p ) {
					$tl = get_post_meta( $p->ID, '_bmn_timeline', true );
					$st = (string) get_post_meta( $p->ID, '_bmn_status', true );
					if ( ! in_array( $st, array( 'done', 'rejected' ), true ) ) {
						++$open;
					}
					if ( is_array( $tl ) && count( $tl ) > 1 && ( $tl[1]['t'] - $tl[0]['t'] ) <= 72 * HOUR_IN_SECONDS ) {
						++$fast;
					}
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$pay = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) c, COALESCE(SUM(amount),0) s FROM %i WHERE status='paid' AND paid_at >= %s", BMN_Payments::table(), $since ) );
				$rows = array(
					__( 'گزارش‌های ۱۳۷', 'boshrouyeh-eservices' )                  => bmn_fa_digits( $total ),
					__( 'باز (در جریان)', 'boshrouyeh-eservices' )                => bmn_fa_digits( $open ),
					__( 'پاسخ زیر ۷۲ ساعت', 'boshrouyeh-eservices' )              => $total ? bmn_fa_digits( (int) round( $fast * 100 / $total ) ) . '٪' : '—',
					__( 'پرداخت‌های آنلاین موفق', 'boshrouyeh-eservices' )         => bmn_fa_digits( (int) ( $pay->c ?? 0 ) ),
					__( 'مجموع پرداخت (تومان)', 'boshrouyeh-eservices' )           => bmn_money( (int) ( $pay->s ?? 0 ) ),
				);
				echo '<table class="widefat striped"><tbody>';
				foreach ( $rows as $k => $v ) {
					echo '<tr><td>' . esc_html( $k ) . '</td><td><strong>' . esc_html( $v ) . '</strong></td></tr>';
				}
				echo '</tbody></table>';
			}
		);
	}

	/**
	 * User profile: revoke app sessions.
	 *
	 * @param WP_User $user User.
	 */
	public static function user_box( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$phone = (string) get_user_meta( $user->ID, '_bmn_phone', true );
		echo '<h2>' . esc_html__( 'شهروند', 'boshrouyeh-eservices' ) . '</h2><table class="form-table"><tr><th>' . esc_html__( 'همراه', 'boshrouyeh-eservices' ) . '</th><td dir="ltr" style="text-align:right">' . esc_html( $phone ) . '</td></tr><tr><th>' . esc_html__( 'نشست‌های اپ', 'boshrouyeh-eservices' ) . '</th><td><label><input type="checkbox" name="bmn_revoke" value="1"> ' . esc_html__( 'خروج از همهٔ دستگاه‌ها', 'boshrouyeh-eservices' ) . '</label></td></tr></table>';
	}

	/**
	 * Save profile action (nonce verified by WP core user-edit form).
	 *
	 * @param int $user_id User.
	 */
	public static function user_save( $user_id ) {
		if ( current_user_can( 'edit_user', $user_id ) && check_admin_referer( 'update-user_' . $user_id ) && ! empty( $_POST['bmn_revoke'] ) ) {
			BMN_Auth::revoke_all( $user_id );
		}
	}
}
