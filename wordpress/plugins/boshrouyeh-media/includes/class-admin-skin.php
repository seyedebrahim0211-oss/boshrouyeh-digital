<?php
/**
 * Traditional "Khesht" admin skin: fonts, palette, municipal dashboard, profile card, login page.
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin skin.
 */
final class BMNM_Admin_Skin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 100 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_assets' ) );
		add_filter( 'login_headerurl', static fn() => home_url( '/' ) );
		add_filter( 'login_headertext', static fn() => get_bloginfo( 'name' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard' ), 99 );
		add_filter( 'admin_footer_text', static fn() => '<span class="bmnm-foot">پیشخوان ' . esc_html( get_bloginfo( 'name' ) ) . ' — شهرِ خشت، باد و قنات</span>' );
		add_action( 'admin_notices', array( __CLASS__, 'profile_card' ), 1 );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'profile_save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'profile_save' ) );
		add_filter( 'admin_body_class', static fn( $c ) => $c . ' bmnm-skin' );
	}

	/**
	 * Font-face CSS using the theme's bundled fonts.
	 *
	 * @return string
	 */
	private static function fonts() {
		$base = get_theme_root_uri() . '/boshrouyeh-khesht/assets/fonts/';
		$css  = '';
		foreach ( array( 400, 500, 700, 800 ) as $w ) {
			$css .= "@font-face{font-family:Vazirmatn;font-weight:{$w};font-display:swap;src:url('" . esc_url( $base . "vazirmatn-arabic-{$w}-normal.woff2" ) . "') format('woff2');unicode-range:U+0600-06FF,U+200C-200E,U+FB50-FDFF,U+FE70-FEFF;}";
			$css .= "@font-face{font-family:Vazirmatn;font-weight:{$w};font-display:swap;src:url('" . esc_url( $base . "vazirmatn-latin-{$w}-normal.woff2" ) . "') format('woff2');unicode-range:U+0000-00FF;}";
		}
		$css .= "@font-face{font-family:Lalezar;font-display:swap;src:url('" . esc_url( $base . 'lalezar-arabic-400-normal.woff2' ) . "') format('woff2');}";
		return $css;
	}

	/**
	 * Admin assets.
	 */
	public static function assets() {
		wp_enqueue_style( 'bmnm-admin', BMNM_URL . 'assets/css/admin.css', array(), BMNM_VERSION );
		wp_add_inline_style( 'bmnm-admin', self::fonts() . ':root{--bmnm-girih:url(' . esc_url( BMNM_URL . 'assets/css/girih.svg' ) . ')}' );
		wp_enqueue_script( 'bmnm-admin-ui', BMNM_URL . 'assets/js/admin-ui.js', array(), BMNM_VERSION, true );
		wp_localize_script( 'bmnm-admin-ui', 'BMNMA', array( 'ajax' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	 * Login page.
	 */
	public static function login_assets() {
		wp_enqueue_style( 'bmnm-login', BMNM_URL . 'assets/css/login.css', array(), BMNM_VERSION );
		$logo = defined( 'BMN_CORE_URL' ) ? BMN_CORE_URL . 'assets/img/logo.svg' : '';
		wp_add_inline_style( 'bmnm-login', self::fonts() . ( $logo ? '.login h1 a{background-image:url(' . esc_url( $logo ) . ')!important}' : '' ) . 'body.login{--bmnm-girih:url(' . esc_url( BMNM_URL . 'assets/css/girih.svg' ) . ')}' );
	}

	/**
	 * Dashboard widgets.
	 */
	public static function dashboard() {
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
		if ( ! current_user_can( 'manage_options' ) ) {
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		}
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		wp_add_dashboard_widget( 'bmnm_city', 'پیشخوان شهرداری', array( __CLASS__, 'widget' ), null, null, 'normal', 'high' );
		global $wp_meta_boxes;
		$w = $wp_meta_boxes['dashboard']['normal']['high']['bmnm_city'] ?? null;
		if ( $w ) {
			unset( $wp_meta_boxes['dashboard']['normal']['high']['bmnm_city'] );
			$wp_meta_boxes['dashboard']['normal']['high'] = array( 'bmnm_city' => $w ) + (array) $wp_meta_boxes['dashboard']['normal']['high']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Stats (cached 5 min).
	 *
	 * @return array
	 */
	private static function stats() {
		$s = get_transient( 'bmnm_dash_stats' );
		if ( is_array( $s ) ) {
			return $s;
		}
		global $wpdb;
		$s = array(
			'open'     => 0,
			'week'     => 0,
			'paid'     => 0,
			'paid_sum' => 0,
			'polls'    => 0,
			'citizens' => 0,
		);
		if ( post_type_exists( 'bmn_request' ) ) {
			$open      = new WP_Query( array( 'post_type' => 'bmn_request', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_bmn_status', 'value' => array( 'done', 'rejected' ), 'compare' => 'NOT IN' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$week      = new WP_Query( array( 'post_type' => 'bmn_request', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'date_query' => array( array( 'after' => '7 days ago' ) ) ) );
			$s['open'] = (int) $open->found_posts;
			$s['week'] = (int) $week->found_posts;
		}
		if ( class_exists( 'BMN_Payments' ) ) {
			$start = gmdate( 'Y-m-d H:i:s', (int) strtotime( 'today', current_time( 'timestamp', true ) ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$row           = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) c, COALESCE(SUM(amount),0) s FROM %i WHERE status='paid' AND paid_at >= %s", BMN_Payments::table(), $start ) );
			$s['paid']     = $row ? (int) $row->c : 0;
			$s['paid_sum'] = $row ? (int) $row->s : 0;
		}
		$s['polls']    = (int) ( wp_count_posts( 'bmn_poll' )->publish ?? 0 );
		$counts        = count_users();
		$s['citizens'] = (int) ( $counts['avail_roles']['bmn_citizen'] ?? 0 );
		set_transient( 'bmnm_dash_stats', $s, 5 * MINUTE_IN_SECONDS );
		return $s;
	}

	/**
	 * Dashboard widget.
	 */
	public static function widget() {
		$u     = wp_get_current_user();
		$s     = self::stats();
		$tiles = array(
			array( 'درخواست‌های باز', $s['open'], 'qanat', current_user_can( 'edit_bmn_requests' ) ? admin_url( 'edit.php?post_type=bmn_request' ) : '' ),
			array( 'درخواست ۷ روز اخیر', $s['week'], 'khesht', '' ),
			array( 'پرداخت موفق امروز', $s['paid'], 'sun', current_user_can( 'bmn_view_payments' ) ? admin_url( 'admin.php?page=bmn-payments' ) : '' ),
			array( 'مبلغ امروز (تومان)', function_exists( 'bmn_money' ) ? bmn_money( $s['paid_sum'] ) : $s['paid_sum'], 'sun', '' ),
			array( 'منتظر تأیید روابط عمومی', BMNM_Content::pending_count(), 'brick', admin_url( 'admin.php?page=bmnm-hub' ) ),
			array( 'پیامک در صف', BMNM_SMS_Panel::pending(), 'night', current_user_can( 'bmnm_send_sms' ) ? admin_url( 'admin.php?page=bmnm-sms' ) : '' ),
			array( 'نظرسنجی فعال', $s['polls'], 'qanat', admin_url( 'edit.php?post_type=bmn_poll' ) ),
			array( 'شهروندان عضو', $s['citizens'], 'khesht', current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=bmnm-access&tab=citizens' ) : '' ),
		);
		$h = (int) current_time( 'G' );
		echo '<div class="bmnm-dash"><div class="bmnm-dash-hero"><div><h2>' . esc_html( ( $h < 12 ? 'صبح بخیر' : ( $h < 18 ? 'روز بخیر' : 'عصر بخیر' ) ) . '، ' . $u->display_name ) . '</h2><p>' . esc_html( bmnm_jdate( 'l j F Y', time() ) ) . '</p></div><div class="bmnm-dash-star" aria-hidden="true"></div></div><div class="bmnm-tiles">';
		foreach ( $tiles as $t ) {
			$inner = '<span class="v">' . esc_html( bmnm_fa( $t[1] ) ) . '</span><span class="l">' . esc_html( $t[0] ) . '</span>';
			echo $t[3] ? '<a class="bmnm-tile t-' . esc_attr( $t[2] ) . '" href="' . esc_url( $t[3] ) . '">' . $inner . '</a>' : '<div class="bmnm-tile t-' . esc_attr( $t[2] ) . '">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		}
		echo '</div><div class="bmnm-quick">';
		$q = array(
			array( 'edit_posts', 'post-new.php', 'خبر تازه' ),
			array( 'edit_posts', 'post-new.php?post_type=bmn_report', 'گزارش تصویری' ),
			array( 'edit_posts', 'admin.php?page=bmnm-forward', 'انتقال از پیام‌رسان' ),
			array( 'bmnm_send_sms', 'admin.php?page=bmnm-sms', 'ارسال پیامک' ),
			array( 'edit_bmn_requests', 'edit.php?post_type=bmn_request', 'میز درخواست‌ها' ),
			array( 'bmn_dashboard', 'admin.php?page=bmn-desk', 'میز کار' ),
			array( 'edit_bmn_polls', 'post-new.php?post_type=bmn_poll', 'نظرسنجی تازه' ),
			array( 'edit_bmn_permits', 'edit.php?post_type=bmn_permit', 'پرونده‌های پروانه' ),
			array( 'manage_options', 'admin.php?page=bmnm-access', 'مدیریت دسترسی‌ها' ),
		);
		foreach ( $q as $i ) {
			if ( current_user_can( $i[0] ) ) {
				echo '<a class="button" href="' . esc_url( admin_url( $i[1] ) ) . '">' . esc_html( $i[2] ) . '</a>';
			}
		}
		echo '</div></div>';
	}

	/**
	 * Profile hero card.
	 */
	public static function profile_card() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'profile', 'user-edit' ), true ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$uid = 'user-edit' === $screen->id && isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : get_current_user_id();
		$u   = get_userdata( $uid );
		if ( ! $u ) {
			return;
		}
		$role = $u->roles ? translate_user_role( wp_roles()->get_names()[ reset( $u->roles ) ] ?? '' ) : '';
		$unit = (int) get_user_meta( $uid, 'bmn_unit', true );
		$t    = $unit ? get_term( $unit, 'bmn_unit' ) : null;
		echo '<div class="bmnm-profile-card">' . get_avatar( $uid, 96 ) . '<div><h2>' . esc_html( $u->display_name ) . '</h2><p><span class="bmnm-badge ok">' . esc_html( $role ) . '</span>' . ( $t && ! is_wp_error( $t ) ? ' <span class="bmnm-badge">' . esc_html( $t->name ) . '</span>' : '' ) . ( get_user_meta( $uid, '_bmnm_suspended', true ) ? ' <span class="bmnm-badge err">معلق</span>' : '' ) . '</p><p class="bmnm-muted">عضو از ' . esc_html( bmnm_jdate( 'j F Y', strtotime( $u->user_registered . ' UTC' ) ) ) . ( get_user_meta( $uid, '_bmn_phone', true ) ? ' · <span dir="ltr">' . esc_html( (string) get_user_meta( $uid, '_bmn_phone', true ) ) . '</span>' : '' ) . '</p></div></div>';
	}

	/**
	 * Extra profile fields.
	 *
	 * @param WP_User $user User.
	 */
	public static function profile_fields( $user ) {
		wp_nonce_field( 'bmnm_profile', 'bmnm_profile_nonce' );
		$can_phone = current_user_can( 'manage_options' );
		echo '<h2>اطلاعات تماس شهرداری</h2><table class="form-table"><tr><th><label for="bmn_phone">شمارهٔ همراه</label></th><td><input type="text" dir="ltr" name="bmn_phone" id="bmn_phone" value="' . esc_attr( (string) get_user_meta( $user->ID, '_bmn_phone', true ) ) . '" class="regular-text"' . disabled( $can_phone, false, false ) . '>';
		echo '<p class="description">' . ( $can_phone ? 'برای پیامک‌های داخلی و ورود پیامکی استفاده می‌شود.' : 'فقط مدیر کل می‌تواند شماره را تغییر دهد.' ) . '</p></td></tr>';
		echo '<tr><th>پیامک‌های اطلاع‌رسانی</th><td><label><input type="checkbox" name="bmn_sms_optout" value="1"' . checked( '1' === get_user_meta( $user->ID, '_bmn_sms_optout', true ), true, false ) . '> پیامک گروهی دریافت نشود</label></td></tr></table>';
	}

	/**
	 * Save profile fields.
	 *
	 * @param int $uid User.
	 */
	public static function profile_save( $uid ) {
		if ( ! isset( $_POST['bmnm_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmnm_profile_nonce'] ) ), 'bmnm_profile' ) || ! current_user_can( 'edit_user', $uid ) ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) && isset( $_POST['bmn_phone'] ) ) {
			$p = bmnm_mobile( sanitize_text_field( wp_unslash( $_POST['bmn_phone'] ) ) );
			$taken = $p ? get_users( array( 'meta_key' => '_bmn_phone', 'meta_value' => $p, 'exclude' => array( $uid ), 'number' => 1, 'fields' => 'ID' ) ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( ! $taken ) {
				$p ? update_user_meta( $uid, '_bmn_phone', $p ) : delete_user_meta( $uid, '_bmn_phone' );
			}
		}
		if ( ! empty( $_POST['bmn_sms_optout'] ) ) {
			update_user_meta( $uid, '_bmn_sms_optout', '1' );
		} else {
			delete_user_meta( $uid, '_bmn_sms_optout' );
		}
	}
}
