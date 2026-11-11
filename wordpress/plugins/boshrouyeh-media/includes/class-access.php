<?php
/**
 * Access manager (مدیریت دسترسی‌ها): users, roles, units, suspension, permission matrix and change log.
 * Only administrators (manage_options) can use it. Dangerous core caps are never exposed.
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Access.
 */
final class BMNM_Access {

	const OVR = 'bmnm_role_overrides';
	const LOG = 'bmnm_access_log';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'apply_overrides' ), 99 );
		add_action( 'admin_post_bmnm_user_save', array( __CLASS__, 'user_save' ) );
		add_action( 'admin_post_bmnm_user_add', array( __CLASS__, 'user_add' ) );
		add_action( 'admin_post_bmnm_role_save', array( __CLASS__, 'role_save' ) );
		add_action( 'admin_post_bmnm_role_add', array( __CLASS__, 'role_add' ) );
		add_action( 'admin_post_bmnm_role_reset', array( __CLASS__, 'role_reset' ) );
		add_filter( 'authenticate', array( __CLASS__, 'block_suspended' ), 99 );
		add_filter( 'determine_current_user', array( __CLASS__, 'drop_suspended' ), 99 );
	}

	/**
	 * Grantable capabilities, grouped (labels in Persian).
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function cap_groups() {
		return array(
			'پایه'                     => array(
				'read'          => 'ورود به پیشخوان',
				'bmn_dashboard' => 'میز کار کارکنان',
				'list_users'    => 'مشاهدهٔ فهرست کاربران',
			),
			'محتوا، اخبار و برگه‌ها'   => array(
				'edit_posts'             => 'نوشتن خبر',
				'edit_others_posts'      => 'ویرایش خبر دیگران',
				'edit_published_posts'   => 'ویرایش خبر منتشرشده',
				'publish_posts'          => 'انتشار خبر',
				'delete_posts'           => 'حذف خبر',
				'delete_published_posts' => 'حذف خبر منتشرشده',
				'edit_pages'             => 'ویرایش برگه‌ها',
				'edit_others_pages'      => 'ویرایش برگهٔ دیگران',
				'edit_published_pages'   => 'ویرایش برگهٔ منتشرشده',
				'publish_pages'          => 'انتشار برگه',
				'upload_files'           => 'بارگذاری رسانه',
				'moderate_comments'      => 'مدیریت دیدگاه‌ها',
				'manage_categories'      => 'مدیریت دسته‌ها',
			),
			'روابط عمومی و ارتباطات'   => array(
				'bmnm_approve'  => 'تأیید روابط عمومی',
				'bmnm_social'   => 'ارسال دوباره به کانال‌ها',
				'bmnm_send_sms' => 'ارسال پیامک گروهی',
			),
			'درخواست‌ها و سامانهٔ ۱۳۷' => array(
				'edit_bmn_requests'           => 'رسیدگی به درخواست',
				'edit_others_bmn_requests'    => 'درخواست‌های دیگران',
				'read_private_bmn_requests'   => 'خواندن درخواست خصوصی',
				'edit_published_bmn_requests' => 'ویرایش درخواست ثبت‌شده',
				'publish_bmn_requests'        => 'ثبت درخواست',
				'bmn_refer'                   => 'ارجاع به واحد',
				'bmn_all_units'               => 'دسترسی به همهٔ واحدها',
			),
			'ملاقات، بخشنامه، ناوگان'  => array(
				'edit_bmn_visits'           => 'نوبت ملاقات',
				'edit_others_bmn_visits'    => 'نوبت‌های دیگران',
				'publish_bmn_visits'        => 'تأیید نوبت',
				'edit_bmn_directives'       => 'بخشنامه‌ها',
				'bmn_manage_directives'     => 'صدور بخشنامه',
				'bmn_update_directives'     => 'گزارش اجرای بخشنامه',
				'edit_bmn_fleets'           => 'ناوگان',
				'bmn_manage_fleet'          => 'مدیریت ناوگان',
			),
			'مالی، گزارش و حراست'      => array(
				'bmn_view_payments'   => 'مشاهدهٔ پرداخت‌ها',
				'bmn_export_payments' => 'خروجی پرداخت‌ها',
				'bmn_view_reports'    => 'شاخص‌ها و گزارش‌ها',
				'bmn_view_audit'      => 'گزارش حراست',
			),
			'مشارکت و پروانه'          => array(
				'edit_bmn_polls'          => 'نظرسنجی‌ها',
				'edit_others_bmn_polls'   => 'نظرسنجی‌های دیگران',
				'publish_bmn_polls'       => 'انتشار نظرسنجی',
				'edit_published_bmn_polls' => 'ویرایش نظرسنجی فعال',
				'edit_bmn_permits'        => 'پرونده‌های پروانه',
				'edit_others_bmn_permits' => 'پرونده‌های دیگران',
				'publish_bmn_permits'     => 'ثبت پرونده',
				'edit_published_bmn_permits' => 'به‌روزرسانی وضعیت پرونده',
			),
		);
	}

	/**
	 * Flat whitelist.
	 *
	 * @return string[]
	 */
	public static function whitelist() {
		$out = array();
		foreach ( self::cap_groups() as $caps ) {
			$out = array_merge( $out, array_keys( $caps ) );
		}
		return $out;
	}

	/**
	 * Install module caps on admin and default roles.
	 */
	public static function install_caps() {
		$all  = array( 'bmnm_approve', 'bmnm_social', 'bmnm_send_sms' );
		$typ  = array();
		foreach ( array( 'bmn_poll', 'bmn_permit' ) as $t ) {
			foreach ( array( 'edit_%s', 'edit_others_%s', 'publish_%s', 'edit_published_%s', 'delete_%s', 'delete_others_%s', 'delete_published_%s', 'read_private_%s', 'edit_private_%s', 'delete_private_%s' ) as $f ) {
				$typ[] = sprintf( $f, $t . 's' );
			}
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_merge( $all, $typ ) as $c ) {
				$admin->add_cap( $c );
			}
		}
		$defaults = array(
			'bmn_pr'    => array_merge( $all, array( 'edit_bmn_polls', 'edit_others_bmn_polls', 'publish_bmn_polls', 'edit_published_bmn_polls', 'delete_bmn_polls' ) ),
			'bmn_mayor' => array( 'bmnm_approve', 'bmnm_send_sms', 'edit_bmn_polls', 'edit_others_bmn_polls', 'publish_bmn_polls', 'edit_published_bmn_polls' ),
			'editor'    => array( 'edit_bmn_polls', 'edit_others_bmn_polls', 'publish_bmn_polls', 'edit_published_bmn_polls' ),
			'bmn_unit_head' => array( 'edit_bmn_permits', 'edit_others_bmn_permits', 'publish_bmn_permits', 'edit_published_bmn_permits' ),
		);
		foreach ( $defaults as $role => $caps ) {
			$r = get_role( $role );
			if ( $r ) {
				foreach ( $caps as $c ) {
					$r->add_cap( $c );
				}
			}
		}
	}

	/**
	 * Re-apply admin's matrix edits (e-services re-creates its roles on upgrade).
	 */
	public static function apply_overrides() {
		$ovr = get_option( self::OVR, array() );
		if ( ! is_array( $ovr ) || ! $ovr ) {
			return;
		}
		foreach ( $ovr as $role => $caps ) {
			$r = get_role( $role );
			if ( ! $r || 'administrator' === $role ) {
				continue;
			}
			foreach ( (array) $caps as $cap => $on ) {
				if ( ! in_array( $cap, self::whitelist(), true ) ) {
					continue;
				}
				if ( $on && empty( $r->capabilities[ $cap ] ) ) {
					$r->add_cap( $cap );
				} elseif ( ! $on && ! empty( $r->capabilities[ $cap ] ) ) {
					$r->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Log a change.
	 *
	 * @param string $msg Message.
	 */
	private static function log( $msg ) {
		$log   = get_option( self::LOG, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array( 't' => time(), 'u' => get_current_user_id(), 'm' => $msg, 'ip' => bmnm_ip() );
		update_option( self::LOG, array_slice( $log, -300 ), false );
		if ( class_exists( 'BMN_Staff' ) && method_exists( 'BMN_Staff', 'log' ) ) {
			BMN_Staff::log( 'access', 0, $msg );
		}
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_menu_page( 'مدیریت دسترسی‌ها', 'دسترسی‌ها', 'manage_options', 'bmnm-access', array( __CLASS__, 'page' ), 'dashicons-shield-alt', 70 );
	}

	/**
	 * Staff roles (non-citizen).
	 *
	 * @return array<string,string>
	 */
	private static function roles() {
		$out = array();
		foreach ( wp_roles()->get_names() as $k => $n ) {
			if ( ! in_array( $k, array( 'bmn_citizen', 'subscriber' ), true ) ) {
				$out[ $k ] = translate_user_role( $n );
			}
		}
		return $out;
	}

	/**
	 * Units list.
	 *
	 * @return array<int,string>
	 */
	private static function units() {
		$terms = get_terms( array( 'taxonomy' => 'bmn_unit', 'hide_empty' => false ) );
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$out[ $t->term_id ] = $t->name;
		}
		return $out;
	}

	/**
	 * Page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'users';
		$msg = isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : '';
		// phpcs:enable
		$tabs = array( 'users' => 'کارکنان', 'citizens' => 'شهروندان', 'roles' => 'نقش‌ها و مجوزها', 'log' => 'گزارش تغییرات' );
		echo '<div class="wrap bmnm-wrap"><div class="bmnm-hero"><h1>مدیریت دسترسی‌ها</h1><p>تعیین نقش، واحد و مجوز هر کاربر؛ تعلیق حساب؛ ساخت نقش تازه. همهٔ تغییرات ثبت می‌شود.</p></div><hr class="wp-header-end">';
		if ( $msg ) {
			$pass = get_transient( 'bmnm_newpass_' . get_current_user_id() );
			echo '<div class="notice notice-' . ( 0 === strpos( $msg, '!' ) ? 'error' : 'success' ) . '"><p>' . esc_html( ltrim( $msg, '!' ) ) . '</p>' . ( $pass ? '<p>گذرواژهٔ موقت (فقط یک‌بار نمایش داده می‌شود): <code dir="ltr">' . esc_html( $pass ) . '</code></p>' : '' ) . '</div>';
			delete_transient( 'bmnm_newpass_' . get_current_user_id() );
		}
		echo '<nav class="nav-tab-wrapper bmnm-tabsnav">';
		foreach ( $tabs as $k => $l ) {
			echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=bmnm-access&tab=' . $k ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';
		if ( 'roles' === $tab ) {
			self::tab_roles();
		} elseif ( 'log' === $tab ) {
			self::tab_log();
		} else {
			self::tab_users( 'citizens' === $tab );
		}
		echo '</div>';
	}

	/**
	 * Users tab.
	 *
	 * @param bool $citizens Citizens instead of staff.
	 */
	private static function tab_users( $citizens ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$s     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$args  = array( 'number' => 100, 'orderby' => 'registered', 'order' => 'DESC' );
		if ( $citizens ) {
			$args['role'] = 'bmn_citizen';
		} else {
			$args['role__not_in'] = array( 'bmn_citizen' );
		}
		if ( $s ) {
			$phone = bmnm_mobile( $s );
			if ( $phone ) {
				$args['meta_key']   = '_bmn_phone'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['meta_value'] = $phone; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			} else {
				$args['search'] = '*' . $s . '*';
			}
		}
		$users = get_users( $args );
		$roles = self::roles();
		$units = self::units();
		echo '<div class="bmnm-cards">';
		echo '<form class="bmnm-search" method="get"><input type="hidden" name="page" value="bmnm-access"><input type="hidden" name="tab" value="' . ( $citizens ? 'citizens' : 'users' ) . '"><input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="نام، نام کاربری یا شمارهٔ همراه"><button class="button">جست‌وجو</button></form>';
		if ( ! $citizens ) {
			echo '<details class="bmnm-panel"><summary class="button button-primary">+ افزودن کارمند</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bmnm-grid-form"><input type="hidden" name="action" value="bmnm_user_add">';
			wp_nonce_field( 'bmnm_user_add' );
			echo '<label>نام و نام خانوادگی<input name="name" required></label><label>نام کاربری (لاتین)<input name="login" dir="ltr" required pattern="[A-Za-z0-9_.\-]{3,40}"></label><label>ایمیل<input type="email" name="email" dir="ltr"></label><label>شمارهٔ همراه<input name="phone" dir="ltr" placeholder="09…"></label>';
			echo '<label>نقش<select name="role">';
			foreach ( $roles as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( 'bmn_staff', $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select></label><label>واحد<select name="unit"><option value="0">—</option>';
			foreach ( $units as $id => $n ) {
				echo '<option value="' . (int) $id . '">' . esc_html( $n ) . '</option>';
			}
			echo '</select></label><p><button class="button button-primary">ساخت حساب</button> <small class="bmnm-muted">اگر ایمیل وارد شود پیوند تعیین گذرواژه ارسال می‌شود؛ وگرنه گذرواژهٔ موقت نمایش داده می‌شود.</small></p></form></details>';
		}
		echo '<div class="bmnm-panel"><table class="widefat striped bmnm-table bmnm-users"><thead><tr><th>کاربر</th><th>همراه</th><th>نقش</th>' . ( $citizens ? '<th>محله</th>' : '<th>واحد</th>' ) . '<th>وضعیت</th><th>عضویت</th><th></th></tr></thead><tbody>';
		foreach ( $users as $u ) {
			$susp  = (bool) get_user_meta( $u->ID, '_bmnm_suspended', true );
			$self  = get_current_user_id() === $u->ID;
			$role  = $u->roles ? reset( $u->roles ) : '';
			$form  = 'f' . $u->ID;
			echo '<tr class="' . ( $susp ? 'is-susp' : '' ) . '"><td><div class="bmnm-user">' . get_avatar( $u->ID, 36 ) . '<div><strong>' . esc_html( $u->display_name ) . '</strong><br><small dir="ltr">' . esc_html( $u->user_login ) . '</small></div></div></td>';
			echo '<td dir="ltr">' . esc_html( (string) get_user_meta( $u->ID, '_bmn_phone', true ) ) . '</td><td>';
			if ( $citizens ) {
				echo esc_html( $roles[ $role ] ?? 'شهروند' );
			} else {
				echo '<select name="role" form="' . esc_attr( $form ) . '"' . disabled( $self, true, false ) . '>';
				foreach ( $roles as $k => $l ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( $role, $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select>';
			}
			echo '</td><td>';
			if ( $citizens ) {
				echo esc_html( (string) get_user_meta( $u->ID, '_bmn_neighborhood', true ) );
			} else {
				$cur = (int) get_user_meta( $u->ID, 'bmn_unit', true );
				echo '<select name="unit" form="' . esc_attr( $form ) . '"><option value="0">—</option>';
				foreach ( $units as $id => $n ) {
					echo '<option value="' . (int) $id . '"' . selected( $cur, $id, false ) . '>' . esc_html( $n ) . '</option>';
				}
				echo '</select>';
			}
			echo '</td><td><label><input type="checkbox" name="suspended" value="1" form="' . esc_attr( $form ) . '"' . checked( $susp, true, false ) . disabled( $self, true, false ) . '> معلق</label></td>';
			echo '<td>' . esc_html( bmnm_jdate( 'j F Y', strtotime( $u->user_registered . ' UTC' ) ) ) . '</td><td>';
			echo '<form id="' . esc_attr( $form ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmnm_user_save"><input type="hidden" name="user_id" value="' . (int) $u->ID . '"><input type="hidden" name="tab" value="' . ( $citizens ? 'citizens' : 'users' ) . '">';
			wp_nonce_field( 'bmnm_user_' . $u->ID );
			echo '<button class="button button-small">ذخیره</button> <a class="button button-small" href="' . esc_url( get_edit_user_link( $u->ID ) ) . '">نمایه</a></form></td></tr>';
		}
		if ( ! $users ) {
			echo '<tr><td colspan="7" class="bmnm-muted">کاربری یافت نشد.</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * Save one user.
	 */
	public static function user_save() {
		$uid = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'bmnm_user_' . $uid );
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $uid ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		$u    = get_userdata( $uid );
		$tab  = isset( $_POST['tab'] ) && 'citizens' === $_POST['tab'] ? 'citizens' : 'users';
		$back = admin_url( 'admin.php?page=bmnm-access&tab=' . $tab );
		if ( ! $u ) {
			wp_safe_redirect( add_query_arg( 'm', '!کاربر یافت نشد.', $back ) );
			exit;
		}
		$self    = get_current_user_id() === $uid;
		$changes = array();
		if ( ! $self && isset( $_POST['role'] ) ) {
			$role = sanitize_key( $_POST['role'] );
			if ( isset( self::roles()[ $role ] ) && ! in_array( $role, $u->roles, true ) ) {
				$u->set_role( $role );
				$changes[] = 'نقش ← ' . self::roles()[ $role ];
			}
		}
		if ( isset( $_POST['unit'] ) ) {
			$unit = absint( $_POST['unit'] );
			if ( (int) get_user_meta( $uid, 'bmn_unit', true ) !== $unit && ( ! $unit || isset( self::units()[ $unit ] ) ) ) {
				update_user_meta( $uid, 'bmn_unit', $unit );
				$changes[] = 'واحد ← ' . ( $unit ? self::units()[ $unit ] : '—' );
			}
		}
		if ( ! $self ) {
			$susp = ! empty( $_POST['suspended'] );
			if ( $susp !== (bool) get_user_meta( $uid, '_bmnm_suspended', true ) ) {
				if ( $susp ) {
					update_user_meta( $uid, '_bmnm_suspended', 1 );
					WP_Session_Tokens::get_instance( $uid )->destroy_all();
					if ( class_exists( 'BMN_Auth' ) && method_exists( 'BMN_Auth', 'revoke_all' ) ) {
						BMN_Auth::revoke_all( $uid );
					}
					$changes[] = 'تعلیق حساب';
				} else {
					delete_user_meta( $uid, '_bmnm_suspended' );
					$changes[] = 'رفع تعلیق';
				}
			}
		}
		if ( $changes ) {
			self::log( $u->display_name . ' (' . $u->user_login . '): ' . implode( '، ', $changes ) );
		}
		wp_safe_redirect( add_query_arg( 'm', $changes ? 'ذخیره شد: ' . implode( '، ', $changes ) : 'تغییری نبود.', $back ) );
		exit;
	}

	/**
	 * Add staff user.
	 */
	public static function user_add() {
		check_admin_referer( 'bmnm_user_add' );
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'create_users' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		$back  = admin_url( 'admin.php?page=bmnm-access' );
		$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ), true ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$role  = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : 'bmn_staff';
		$phone = isset( $_POST['phone'] ) ? bmnm_mobile( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';
		if ( ! $login || username_exists( $login ) || ( $email && email_exists( $email ) ) || ! isset( self::roles()[ $role ] ) ) {
			wp_safe_redirect( add_query_arg( 'm', '!نام کاربری/ایمیل تکراری یا نقش نامعتبر است.', $back ) );
			exit;
		}
		if ( $phone && get_users( array( 'meta_key' => '_bmn_phone', 'meta_value' => $phone, 'number' => 1, 'fields' => 'ID' ) ) ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			wp_safe_redirect( add_query_arg( 'm', '!این شمارهٔ همراه برای حساب دیگری ثبت شده است.', $back ) );
			exit;
		}
		$pass = wp_generate_password( 16, true, false );
		$id   = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : $login,
				'role'         => $role,
			)
		);
		if ( is_wp_error( $id ) ) {
			wp_safe_redirect( add_query_arg( 'm', '!' . $id->get_error_message(), $back ) );
			exit;
		}
		if ( $phone ) {
			update_user_meta( $id, '_bmn_phone', $phone );
		}
		$unit = isset( $_POST['unit'] ) ? absint( $_POST['unit'] ) : 0;
		if ( $unit ) {
			update_user_meta( $id, 'bmn_unit', $unit );
		}
		if ( $email ) {
			wp_new_user_notification( $id, null, 'user' );
		} else {
			set_transient( 'bmnm_newpass_' . get_current_user_id(), $pass, 120 );
		}
		self::log( 'ساخت حساب ' . $login . ' با نقش ' . self::roles()[ $role ] );
		wp_safe_redirect( add_query_arg( 'm', 'حساب ' . $login . ' ساخته شد.', $back ) );
		exit;
	}

	/**
	 * Roles matrix tab.
	 */
	private static function tab_roles() {
		$roles = self::roles();
		unset( $roles['administrator'] );
		echo '<div class="bmnm-panel"><p class="bmnm-muted">مدیر کل همیشه همهٔ مجوزها را دارد و اینجا ویرایش نمی‌شود. مجوزهای حساس هسته (تنظیمات، افزونه‌ها، کاربران) قابل واگذاری نیستند.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmnm_role_save">';
		wp_nonce_field( 'bmnm_role_save' );
		echo '<div class="bmnm-matrix-wrap"><table class="widefat bmnm-matrix"><thead><tr><th>مجوز</th>';
		foreach ( $roles as $k => $l ) {
			echo '<th><span>' . esc_html( $l ) . '</span></th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( self::cap_groups() as $group => $caps ) {
			echo '<tr class="bmnm-group"><th colspan="' . ( count( $roles ) + 1 ) . '">' . esc_html( $group ) . '</th></tr>';
			foreach ( $caps as $cap => $label ) {
				echo '<tr><td>' . esc_html( $label ) . '<br><code>' . esc_html( $cap ) . '</code></td>';
				foreach ( array_keys( $roles ) as $r ) {
					$ro = get_role( $r );
					echo '<td><input type="checkbox" aria-label="' . esc_attr( $label . ' — ' . $roles[ $r ] ) . '" name="caps[' . esc_attr( $r ) . '][]" value="' . esc_attr( $cap ) . '"' . checked( $ro && ! empty( $ro->capabilities[ $cap ] ), true, false ) . '></td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table></div><p><button class="button button-primary button-large">ذخیرهٔ مجوزها</button></p></form></div>';

		echo '<div class="bmnm-cards"><form class="bmnm-panel" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmnm_role_add">';
		wp_nonce_field( 'bmnm_role_add' );
		echo '<h2>نقش تازه</h2><p><label>نام نقش<br><input name="label" required maxlength="60"></label></p><p><label>کپی مجوزها از<br><select name="from"><option value="">— خالی —</option>';
		foreach ( $roles as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></label></p><p><button class="button">ساخت نقش</button></p></form>';
		echo '<form class="bmnm-panel" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmnm_role_reset">';
		wp_nonce_field( 'bmnm_role_reset' );
		echo '<h2>بازگردانی / حذف</h2><p><select name="role">';
		foreach ( $roles as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></p><p><button class="button" name="op" value="reset">بازگردانی مجوزهای پیش‌فرض</button> <button class="button button-link-delete" name="op" value="delete" onclick="return confirm(\'نقش حذف شود؟ کاربرانش «کارشناس واحد» می‌شوند.\')">حذف نقش سفارشی</button></p></form></div>';
	}

	/**
	 * Save matrix.
	 */
	public static function role_save() {
		check_admin_referer( 'bmnm_role_save' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		$post = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- filtered by whitelist.
		$ovr  = get_option( self::OVR, array() );
		$ovr  = is_array( $ovr ) ? $ovr : array();
		$wl   = self::whitelist();
		$log  = array();
		foreach ( array_keys( self::roles() ) as $r ) {
			if ( 'administrator' === $r ) {
				continue;
			}
			$role = get_role( $r );
			if ( ! $role ) {
				continue;
			}
			$want = array_intersect( array_map( 'sanitize_key', (array) ( $post[ $r ] ?? array() ) ), $wl );
			foreach ( $wl as $cap ) {
				$has = ! empty( $role->capabilities[ $cap ] );
				$on  = in_array( $cap, $want, true );
				if ( $has !== $on ) {
					$on ? $role->add_cap( $cap ) : $role->remove_cap( $cap );
					$ovr[ $r ][ $cap ] = $on ? 1 : 0;
					$log[]             = translate_user_role( wp_roles()->get_names()[ $r ] ) . ( $on ? ' +' : ' −' ) . $cap;
				}
			}
		}
		update_option( self::OVR, $ovr, false );
		if ( $log ) {
			self::log( 'مجوزها: ' . implode( '، ', array_slice( $log, 0, 30 ) ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bmnm-access&tab=roles&m=' . rawurlencode( $log ? count( $log ) . ' تغییر ذخیره شد.' : 'تغییری نبود.' ) ) );
		exit;
	}

	/**
	 * Add custom role.
	 */
	public static function role_add() {
		check_admin_referer( 'bmnm_role_add' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$from  = isset( $_POST['from'] ) ? sanitize_key( $_POST['from'] ) : '';
		$slug  = 'bmnc_' . substr( md5( $label . microtime() ), 0, 8 );
		$caps  = array( 'read' => true );
		$src   = $from && 'administrator' !== $from ? get_role( $from ) : null;
		if ( $src ) {
			foreach ( $src->capabilities as $c => $on ) {
				if ( $on && ( in_array( $c, self::whitelist(), true ) || 0 === strpos( $c, 'edit_bmn_' ) || 0 === strpos( $c, 'bmn_' ) ) && ! in_array( $c, array( 'manage_options', 'edit_users', 'promote_users', 'create_users', 'delete_users', 'activate_plugins', 'unfiltered_html' ), true ) ) {
					$caps[ $c ] = true;
				}
			}
		}
		if ( $label ) {
			add_role( $slug, $label, $caps );
			self::log( 'ساخت نقش «' . $label . '»' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bmnm-access&tab=roles&m=' . rawurlencode( $label ? 'نقش ساخته شد.' : '!نام نقش لازم است.' ) ) );
		exit;
	}

	/**
	 * Reset / delete role.
	 */
	public static function role_reset() {
		check_admin_referer( 'bmnm_role_reset' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		$role = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';
		$op   = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
		$ovr  = get_option( self::OVR, array() );
		$m    = '!عملیات نامعتبر.';
		if ( 'administrator' !== $role && get_role( $role ) ) {
			if ( 'delete' === $op && 0 === strpos( $role, 'bmnc_' ) ) {
				foreach ( get_users( array( 'role' => $role, 'fields' => 'ID' ) ) as $uid ) {
					( new WP_User( $uid ) )->set_role( 'bmn_staff' );
				}
				remove_role( $role );
				unset( $ovr[ $role ] );
				$m = 'نقش حذف شد.';
			} elseif ( 'reset' === $op ) {
				unset( $ovr[ $role ] );
				if ( class_exists( 'BMN_Staff' ) && isset( BMN_Staff::roles_def()[ $role ] ) ) {
					$def = BMN_Staff::roles_def()[ $role ];
					remove_role( $role );
					add_role( $role, $def[0], $def[1] );
					update_option( self::OVR, $ovr, false );
					self::install_caps();
				}
				$m = 'مجوزهای پیش‌فرض بازگردانده شد.';
			}
			update_option( self::OVR, $ovr, false );
			self::log( ( 'delete' === $op ? 'حذف' : 'بازگردانی' ) . ' نقش ' . $role );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bmnm-access&tab=roles&m=' . rawurlencode( $m ) ) );
		exit;
	}

	/**
	 * Log tab.
	 */
	private static function tab_log() {
		$log = array_reverse( (array) get_option( self::LOG, array() ) );
		echo '<div class="bmnm-panel"><table class="widefat striped"><thead><tr><th>زمان</th><th>توسط</th><th>تغییر</th><th>IP</th></tr></thead><tbody>';
		foreach ( $log as $r ) {
			$u = get_userdata( (int) $r['u'] );
			echo '<tr><td>' . esc_html( bmnm_jdate( 'j F Y H:i', $r['t'] ) ) . '</td><td>' . esc_html( $u ? $u->display_name : '—' ) . '</td><td>' . esc_html( $r['m'] ) . '</td><td dir="ltr">' . esc_html( $r['ip'] ?? '' ) . '</td></tr>';
		}
		if ( ! $log ) {
			echo '<tr><td colspan="4" class="bmnm-muted">تغییری ثبت نشده است.</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Block suspended users at login.
	 *
	 * @param WP_User|WP_Error|null $user User.
	 * @return WP_User|WP_Error|null
	 */
	public static function block_suspended( $user ) {
		if ( $user instanceof WP_User && get_user_meta( $user->ID, '_bmnm_suspended', true ) ) {
			return new WP_Error( 'bmnm_suspended', 'این حساب معلق شده است.' );
		}
		return $user;
	}

	/**
	 * Drop suspended users on any request (cookie or app token).
	 *
	 * @param int|false $uid User.
	 * @return int|false
	 */
	public static function drop_suspended( $uid ) {
		return ( $uid && get_user_meta( (int) $uid, '_bmnm_suspended', true ) ) ? false : $uid;
	}
}
